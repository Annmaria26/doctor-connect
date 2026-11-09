/**
 * ==============================================================================
 * DOCTOR CONNECT - LIVE QUEUE CLIENT (JAVASCRIPT)
 * Polling <= 3 seconds for real-time queue synchronization
 * ==============================================================================
 */

class LiveQueueManager {
    constructor(options = {}) {
        this.doctorId = options.doctorId || null;
        this.appointmentId = options.appointmentId || null;
        this.role = options.role || 'patient';
        this.pollInterval = options.pollInterval || 3000;
        this.apiUrl = options.apiUrl || '../api/live_queue.php';
        this.timer = null;
        this.lastServingToken = null;
        this.lastStatus = null;
        this.audioEnabled = true;

        this.init();
    }

    init() {
        if (!this.doctorId && !this.appointmentId) {
            console.warn('LiveQueueManager: No doctorId or appointmentId provided.');
            return;
        }

        // Fetch immediately, then every pollInterval ms
        this.fetchStatus();
        this.timer = setInterval(() => this.fetchStatus(), this.pollInterval);

        // Resume audio context on first user click if restricted by browser policy
        document.addEventListener('click', () => {
            this.ensureAudioContext();
        }, { once: true });
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    ensureAudioContext() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext && !this.audioCtx) {
                this.audioCtx = new AudioContext();
            }
            if (this.audioCtx && this.audioCtx.state === 'suspended') {
                this.audioCtx.resume();
            }
        } catch (e) {
            console.log('Web Audio API not supported or restricted');
        }
    }

    playChime() {
        if (!this.audioEnabled) return;
        try {
            this.ensureAudioContext();
            if (!this.audioCtx) return;

            const now = this.audioCtx.currentTime;
            
            // First bell note (C5 - 523.25 Hz)
            const osc1 = this.audioCtx.createOscillator();
            const gain1 = this.audioCtx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(523.25, now);
            gain1.gain.setValueAtTime(0.15, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.5);
            osc1.connect(gain1);
            gain1.connect(this.audioCtx.destination);
            osc1.start(now);
            osc1.stop(now + 0.5);

            // Second bell note (E5 - 659.25 Hz)
            const osc2 = this.audioCtx.createOscillator();
            const gain2 = this.audioCtx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(659.25, now + 0.15);
            gain2.gain.setValueAtTime(0.2, now + 0.15);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.7);
            osc2.connect(gain2);
            gain2.connect(this.audioCtx.destination);
            osc2.start(now + 0.15);
            osc2.stop(now + 0.7);
        } catch (e) {
            console.log('Error playing chime:', e);
        }
    }

    async fetchStatus() {
        try {
            const params = new URLSearchParams();
            if (this.doctorId) params.append('doctor_id', this.doctorId);
            if (this.appointmentId) params.append('appointment_id', this.appointmentId);

            const res = await fetch(`${this.apiUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
            const data = await res.json();

            if (data.success) {
                this.updateUI(data);
            }
        } catch (err) {
            console.warn('LiveQueue fetch failed:', err.message);
        }
    }

    updateUI(data) {
        // 1. Update Current Serving Token
        const servingElem = document.getElementById('lq-current-serving');
        if (servingElem) {
            const tokenText = data.current_serving_token ? `#${data.current_serving_token}` : 'None';
            servingElem.textContent = tokenText;
        }

        const servingPatientElem = document.getElementById('lq-current-patient');
        if (servingPatientElem && data.current_serving) {
            servingPatientElem.textContent = data.current_serving.patient_name || 'Serving Patient';
        }

        // 2. Update Next Waiting Token
        const nextElem = document.getElementById('lq-next-token');
        if (nextElem) {
            nextElem.textContent = data.next_waiting_token ? `#${data.next_waiting_token}` : 'None';
        }

        // 3. Update Waiting and Completed counts
        const waitingCountElem = document.getElementById('lq-waiting-count');
        if (waitingCountElem) waitingCountElem.textContent = data.waiting_count;

        const completedCountElem = document.getElementById('lq-completed-count');
        if (completedCountElem) completedCountElem.textContent = data.completed_count;

        // 4. Patient-Specific Queue Updates (if viewing an appointment)
        if (data.my_appointment) {
            const myAppt = data.my_appointment;

            // Check if status changed to serving or called
            if (this.lastStatus && this.lastStatus !== myAppt.status) {
                if (myAppt.status === 'serving' || (myAppt.status === 'waiting' && myAppt.is_next)) {
                    this.playChime();
                    this.showStatusToast(`Alert: Your turn is ${myAppt.status === 'serving' ? 'NOW SERVING' : 'NEXT'}!`);
                }
            }
            this.lastStatus = myAppt.status;

            // Update my status badge
            const statusBadgeElem = document.getElementById('lq-my-status');
            if (statusBadgeElem) {
                statusBadgeElem.className = `badge badge-${myAppt.status}`;
                statusBadgeElem.innerHTML = `${myAppt.status.toUpperCase()} ${myAppt.status === 'serving' ? '<span class="pulse-dot"></span>' : ''}`;
            }

            // Update tokens ahead
            const aheadElem = document.getElementById('lq-tokens-ahead');
            if (aheadElem) {
                aheadElem.textContent = myAppt.tokens_ahead !== null ? myAppt.tokens_ahead : '0';
            }

            // Update meeting link visibility / unlock state
            const meetingBox = document.getElementById('lq-meeting-container');
            if (meetingBox) {
                if (myAppt.can_join_meeting && myAppt.meeting_link) {
                    meetingBox.className = 'meeting-banner meeting-unlocked';
                    meetingBox.innerHTML = `
                        <div style="font-size:2rem; margin-bottom:0.5rem;">🎉</div>
                        <h3 style="color:#065f46; margin-bottom:0.4rem; font-size:1.3rem;">It's Your Turn! Video Consultation Ready</h3>
                        <p style="color:#047857; margin-bottom:1rem; max-width:500px;">
                            The doctor is ready or preparing for your consultation. Please join the video room now.
                        </p>
                        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; justify-content:center;">
                            <a href="${myAppt.meeting_room_url}" class="btn btn-teal btn-lg" style="box-shadow:0 4px 12px rgba(13,148,136,0.3);">
                                📹 Enter Video Consultation Room
                            </a>
                            <a href="${myAppt.meeting_link}" target="_blank" rel="noopener" class="btn btn-outline btn-lg">
                                Open in New Tab ↗
                            </a>
                        </div>
                    `;
                } else if (myAppt.status === 'completed') {
                    meetingBox.className = 'meeting-banner';
                    meetingBox.style.background = '#f1f5f9';
                    meetingBox.style.border = '1px solid #cbd5e1';
                    meetingBox.innerHTML = `
                        <h4 style="color:#334155; margin-bottom:0.5rem;">Consultation Completed</h4>
                        <p style="color:#64748b; margin-bottom:1rem;">Your consultation with the doctor has ended.</p>
                        <a href="../patient/prescription.php?id=${myAppt.id}" class="btn btn-primary">
                            📄 View / Print Prescription
                        </a>
                    `;
                }
            }
        }

        // 5. Update live tokens table (in doctor / reception / monitor views)
        const tableBody = document.getElementById('lq-table-body');
        if (tableBody && data.tokens) {
            tableBody.innerHTML = data.tokens.map(t => {
                let badgeClass = 'badge-waiting';
                if (t.status === 'serving') badgeClass = 'badge-serving';
                if (t.status === 'completed') badgeClass = 'badge-completed';
                if (t.status === 'no_show') badgeClass = 'badge-no_show';
                if (t.status === 'cancelled') badgeClass = 'badge-cancelled';

                let actionButtons = '';
                if (this.role === 'doctor' || this.role === 'admin') {
                    if (t.status === 'waiting') {
                        actionButtons = `
                            <button class="btn btn-sm btn-primary" onclick="handleQueueAction('mark_serving', ${t.id})">Call</button>
                            <button class="btn btn-sm btn-outline" onclick="handleQueueAction('mark_no_show', ${t.id})">No-Show</button>
                        `;
                    } else if (t.status === 'serving') {
                        actionButtons = `
                            <a href="consultation.php?id=${t.id}" class="btn btn-sm btn-teal">In Call 📹</a>
                            <button class="btn btn-sm btn-outline" onclick="handleQueueAction('mark_served', ${t.id})">Complete</button>
                        `;
                    } else if (t.status === 'completed' && t.prescription) {
                        actionButtons = `<a href="../patient/prescription.php?id=${t.id}" target="_blank" class="btn btn-sm btn-outline">Rx</a>`;
                    }
                }

                return `
                    <tr class="${t.status === 'serving' ? 'table-row-serving' : ''}" style="${t.status === 'serving' ? 'background:#ecfdf5;' : ''}">
                        <td><strong style="font-size:1.05rem;">#${t.token_number}</strong></td>
                        <td>${escapeHtml(t.patient_name || 'Walk-in Patient')}</td>
                        <td>${formatTime(t.estimated_time)}</td>
                        <td><span class="badge ${badgeClass}">${t.status.toUpperCase()} ${t.status === 'serving' ? '<span class="pulse-dot"></span>' : ''}</span></td>
                        ${this.role === 'doctor' || this.role === 'admin' ? `<td>${actionButtons}</td>` : ''}
                    </tr>
                `;
            }).join('');
        }
    }

    showStatusToast(msg) {
        let toast = document.getElementById('lq-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'lq-toast';
            toast.style.position = 'fixed';
            toast.style.bottom = '24px';
            toast.style.right = '24px';
            toast.style.background = '#0f172a';
            toast.style.color = '#ffffff';
            toast.style.padding = '14px 22px';
            toast.style.borderRadius = '8px';
            toast.style.boxShadow = '0 10px 25px rgba(0,0,0,0.3)';
            toast.style.zIndex = '99999';
            toast.style.fontWeight = '600';
            toast.style.fontSize = '0.95rem';
            document.body.appendChild(toast);
        }
        toast.textContent = msg;
        toast.style.display = 'block';
        setTimeout(() => {
            if (toast) toast.style.display = 'none';
        }, 5000);
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function formatTime(dateTimeStr) {
    if (!dateTimeStr) return '-';
    try {
        const d = new Date(dateTimeStr.replace(' ', 'T'));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch(e) {
        return dateTimeStr;
    }
}

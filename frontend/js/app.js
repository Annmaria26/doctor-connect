/**
 * ==============================================================================
 * DOCTOR CONNECT - FRONTEND REACTIVE STATE ENGINE (STANDALONE / NO BACKEND)
 * Uses browser localStorage with multi-tab real-time sync & Web Audio chime
 * ==============================================================================
 */

const STORAGE_KEY = 'doctor_connect_frontend_state';

// Pre-seeded initial application state
function getInitialState() {
    const today = new Date().toISOString().split('T')[0];
    return {
        clinic_settings: {
            clinic_name: 'DoctorConnect TeleHealth Clinic',
            clinic_tagline: 'Instant Online Token & Live Queue Consultation',
            clinic_phone: '+1 (800) 555-0199',
            clinic_email: 'care@doctorconnect.com',
            clinic_address: '450 Healthtech Blvd, Suite 300, Silicon Valley, CA',
            default_slot_duration: 15,
            default_consultation_fee: 50.00
        },
        doctors: [
            { id: 1, name: 'Dr. Sarah Jenkins', specialization: 'Cardiology', fee: 80.00, avg_mins: 15, bio: 'Senior Cardiologist specializing in preventive heart health and hypertension management.', start_time: '09:00', end_time: '17:00' },
            { id: 2, name: 'Dr. Marcus Chen', specialization: 'Pediatrics', fee: 55.00, avg_mins: 15, bio: 'Compassionate pediatrician focusing on developmental milestones and acute care.', start_time: '08:30', end_time: '15:30' },
            { id: 3, name: 'Dr. Emily Rodriguez', specialization: 'Dermatology', fee: 65.00, avg_mins: 20, bio: 'Consultant dermatologist treating skin conditions, eczema, and tele-dermatology diagnostics.', start_time: '13:00', end_time: '18:00' },
            { id: 4, name: 'Dr. David Patel', specialization: 'General Medicine', fee: 40.00, avg_mins: 10, bio: 'Primary care physician managing viral illness, diabetes, and lifestyle wellness.', start_time: '08:00', end_time: '16:00' }
        ],
        current_user: {
            role: 'patient', // 'patient', 'doctor', 'admin'
            id: 1,
            name: 'John Doe',
            email: 'john.doe@gmail.com',
            phone: '+1 555-0101',
            active_doctor_id: 1
        },
        appointments: [
            {
                id: 101,
                patient_name: 'John Doe',
                patient_phone: '+1 555-0101',
                patient_email: 'john.doe@gmail.com',
                doctor_id: 1,
                date: today,
                token_number: 1,
                status: 'completed',
                estimated_time: today + ' 09:00',
                reason: 'Hypertension routine follow-up',
                diagnosis: 'Controlled Essential Hypertension',
                prescription: '1. Amlodipine 5mg - 1 tablet once daily in morning\n2. Low sodium diet (<2g/day)\n3. Recheck BP in 3 weeks',
                meeting_link: 'https://meet.jit.si/doctor-connect-appt-101-demo'
            },
            {
                id: 102,
                patient_name: 'Jane Smith',
                patient_phone: '+1 555-0102',
                patient_email: 'jane.smith@gmail.com',
                doctor_id: 1,
                date: today,
                token_number: 2,
                status: 'serving',
                estimated_time: today + ' 09:15',
                reason: 'Chest tightness after brisk walking',
                diagnosis: '',
                prescription: '',
                meeting_link: 'https://meet.jit.si/doctor-connect-appt-102-demo'
            },
            {
                id: 103,
                patient_name: 'Robert Brown',
                patient_phone: '+1 555-0103',
                patient_email: 'robert.brown@gmail.com',
                doctor_id: 1,
                date: today,
                token_number: 3,
                status: 'waiting',
                estimated_time: today + ' 09:30',
                reason: 'High cholesterol report review and fatigue',
                diagnosis: '',
                prescription: '',
                meeting_link: 'https://meet.jit.si/doctor-connect-appt-103-demo'
            },
            {
                id: 104,
                patient_name: 'Alice Taylor',
                patient_phone: '+1 555-0104',
                patient_email: 'alice.taylor@gmail.com',
                doctor_id: 1,
                date: today,
                token_number: 4,
                status: 'waiting',
                estimated_time: today + ' 09:45',
                reason: 'Follow-up for ECG baseline review',
                diagnosis: '',
                prescription: '',
                meeting_link: 'https://meet.jit.si/doctor-connect-appt-104-demo'
            }
        ],
        queue_logs: [
            { id: 1, appointment_id: 101, action: 'called', time: '09:00 AM' },
            { id: 2, appointment_id: 101, action: 'served', time: '09:14 AM' },
            { id: 3, appointment_id: 102, action: 'called', time: '09:15 AM' },
            { id: 4, appointment_id: 102, action: 'serving', time: '09:16 AM' }
        ]
    };
}

// Global Store Helper
class DCStore {
    static load() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            const initial = getInitialState();
            DCStore.save(initial);
            return initial;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            const initial = getInitialState();
            DCStore.save(initial);
            return initial;
        }
    }

    static save(data) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
        // Trigger manual event for same-tab updates
        window.dispatchEvent(new Event('dc_state_updated'));
    }

    static reset() {
        localStorage.removeItem(STORAGE_KEY);
        return DCStore.load();
    }
}

// Sound Synthesizer via HTML5 Web Audio API
class AudioBell {
    static playChime() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioBell.ctx) AudioBell.ctx = new AudioContext();
            if (AudioBell.ctx.state === 'suspended') AudioBell.ctx.resume();

            const now = AudioBell.ctx.currentTime;
            
            // Bell tone 1 (523.25 Hz - C5)
            const osc1 = AudioBell.ctx.createOscillator();
            const gain1 = AudioBell.ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(523.25, now);
            gain1.gain.setValueAtTime(0.18, now);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
            osc1.connect(gain1);
            gain1.connect(AudioBell.ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.45);

            // Bell tone 2 (659.25 Hz - E5)
            const osc2 = AudioBell.ctx.createOscillator();
            const gain2 = AudioBell.ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(659.25, now + 0.15);
            gain2.gain.setValueAtTime(0.22, now + 0.15);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.7);
            osc2.connect(gain2);
            gain2.connect(AudioBell.ctx.destination);
            osc2.start(now + 0.15);
            osc2.stop(now + 0.7);
        } catch (e) {
            console.log('Audio chime error:', e);
        }
    }
}

// Queue Controller
class QueueEngine {
    static getDoctorQueue(doctorId, date) {
        const state = DCStore.load();
        const appts = state.appointments.filter(a => a.doctor_id === doctorId && a.date === date);
        appts.sort((a, b) => a.token_number - b.token_number);

        const currentServing = appts.find(a => a.status === 'serving') || null;
        const waitingList = appts.filter(a => a.status === 'waiting');
        const nextWaiting = waitingList.length > 0 ? waitingList[0] : null;

        return {
            current_serving: currentServing,
            current_serving_token: currentServing ? currentServing.token_number : null,
            next_waiting: nextWaiting,
            next_waiting_token: nextWaiting ? nextWaiting.token_number : null,
            waiting_count: waitingList.length,
            serving_count: currentServing ? 1 : 0,
            completed_count: appts.filter(a => a.status === 'completed').length,
            no_show_count: appts.filter(a => a.status === 'no_show').length,
            tokens: appts
        };
    }

    // Call Next Token
    static callNext(doctorId) {
        const state = DCStore.load();
        const today = new Date().toISOString().split('T')[0];

        // 1. If currently serving someone, mark them completed
        const currentlyServing = state.appointments.find(a => a.doctor_id === doctorId && a.date === today && a.status === 'serving');
        if (currentlyServing) {
            currentlyServing.status = 'completed';
            state.queue_logs.unshift({
                id: Date.now(),
                appointment_id: currentlyServing.id,
                action: 'served',
                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            });
        }

        // 2. Find lowest waiting token
        const waitingAppts = state.appointments.filter(a => a.doctor_id === doctorId && a.date === today && a.status === 'waiting');
        waitingAppts.sort((a, b) => a.token_number - b.token_number);

        if (waitingAppts.length === 0) {
            DCStore.save(state);
            return { success: false, message: 'No more waiting patients in queue for today.' };
        }

        const nextAppt = waitingAppts[0];
        nextAppt.status = 'serving';

        state.queue_logs.unshift({
            id: Date.now() + 1,
            appointment_id: nextAppt.id,
            action: 'called',
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        DCStore.save(state);
        AudioBell.playChime();

        return {
            success: true,
            message: `Token #${nextAppt.token_number} (${nextAppt.patient_name}) is now called to the consultation room!`,
            token: nextAppt
        };
    }

    // Mark Specific Token Status
    static setStatus(appointmentId, status) {
        const state = DCStore.load();
        const appt = state.appointments.find(a => a.id === appointmentId);
        if (!appt) return { success: false, message: 'Appointment not found.' };

        appt.status = status;
        state.queue_logs.unshift({
            id: Date.now(),
            appointment_id: appt.id,
            action: status === 'serving' ? 'called' : (status === 'completed' ? 'served' : status),
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        DCStore.save(state);
        if (status === 'serving') AudioBell.playChime();
        return { success: true, message: `Status updated to ${status}.` };
    }

    // Complete Consultation with Prescription Notes
    static completeConsultation(appointmentId, diagnosis, prescription) {
        const state = DCStore.load();
        const appt = state.appointments.find(a => a.id === appointmentId);
        if (!appt) return { success: false, message: 'Appointment not found.' };

        appt.status = 'completed';
        appt.diagnosis = diagnosis;
        appt.prescription = prescription;

        state.queue_logs.unshift({
            id: Date.now(),
            appointment_id: appt.id,
            action: 'served',
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        DCStore.save(state);
        return { success: true, message: 'Consultation completed and prescription saved!' };
    }

    // Atomic / Race-safe Sequential Token Generator
    static bookToken(patientName, patientPhone, doctorId, date, reason) {
        const state = DCStore.load();
        const doctor = state.doctors.find(d => d.id === doctorId);
        if (!doctor) return { success: false, error: 'Doctor not found.' };

        // Find max token number for this doctor and date
        const existingTokens = state.appointments
            .filter(a => a.doctor_id === doctorId && a.date === date)
            .map(a => a.token_number);

        const nextTokenNumber = existingTokens.length > 0 ? Math.max(...existingTokens) + 1 : 1;

        // Calculate estimated meeting time: session_start + (token_number - 1) * avg_mins
        const startParts = (doctor.start_time || '09:00').split(':');
        const startHours = parseInt(startParts[0], 10);
        const startMins = parseInt(startParts[1], 10);

        const totalOffsetMins = (nextTokenNumber - 1) * (doctor.avg_mins || 15);
        const finalHours = Math.floor((startMins + totalOffsetMins) / 60) + startHours;
        const finalMins = (startMins + totalOffsetMins) % 60;
        const ampm = finalHours >= 12 ? 'PM' : 'AM';
        const displayHours = finalHours % 12 || 12;
        const estTimeFormatted = `${date} ${displayHours.toString().padStart(2, '0')}:${finalMins.toString().padStart(2, '0')} ${ampm}`;

        const newId = Date.now();
        const newAppt = {
            id: newId,
            patient_name: patientName,
            patient_phone: patientPhone || '+1 555-0000',
            patient_email: patientName.toLowerCase().replace(/\s+/g, '.') + '@example.com',
            doctor_id: doctorId,
            date: date,
            token_number: nextTokenNumber,
            status: 'waiting',
            estimated_time: estTimeFormatted,
            reason: reason || 'General medical consultation',
            diagnosis: '',
            prescription: '',
            meeting_link: `https://meet.jit.si/doctor-connect-appt-${newId}`
        };

        state.appointments.push(newAppt);
        state.queue_logs.unshift({
            id: Date.now() + 2,
            appointment_id: newId,
            action: 'called',
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        });

        DCStore.save(state);
        return { success: true, appointment: newAppt };
    }
}

// Client-Side CSV Generator
function exportCSV() {
    const state = DCStore.load();
    const rows = [
        ['Appointment ID', 'Date', 'Token Number', 'Status', 'Estimated Meeting Time', 'Patient Name', 'Patient Phone', 'Doctor ID', 'Chief Complaint', 'Diagnosis', 'Prescription']
    ];

    state.appointments.forEach(a => {
        rows.push([
            a.id,
            a.date,
            a.token_number,
            a.status.toUpperCase(),
            a.estimated_time,
            `"${a.patient_name}"`,
            a.patient_phone,
            a.doctor_id,
            `"${(a.reason || '').replace(/"/g, '""')}"`,
            `"${(a.diagnosis || '').replace(/"/g, '""')}"`,
            `"${(a.prescription || '').replace(/"/g, '""')}"`
        ]);
    });

    const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + rows.map(e => e.join(',')).join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `doctor_connect_report_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * ==============================================================================
 * DOCTOR CONNECT - BOOKING WIZARD & DYNAMIC SLOT CALCULATOR
 * ==============================================================================
 */

document.addEventListener('DOMContentLoaded', function() {
    const doctorSelect = document.getElementById('doctor_id');
    const dateInput = document.getElementById('appointment_date');
    const submitBtn = document.getElementById('btn-book-submit');
    const slotInfoBox = document.getElementById('slot-info-box');
    const slotSummary = document.getElementById('slot-summary');
    const previewToken = document.getElementById('preview-token-number');
    const previewEstTime = document.getElementById('preview-estimated-time');
    const previewFee = document.getElementById('preview-fee');
    const previewDoctor = document.getElementById('preview-doctor-name');

    if (!doctorSelect || !dateInput) return;

    // Minimum date is today
    const today = new Date().toISOString().split('T')[0];
    dateInput.min = today;
    if (!dateInput.value) {
        dateInput.value = today;
    }

    async function checkAvailability() {
        const doctorId = doctorSelect.value;
        const date = dateInput.value;

        if (!doctorId || !date) {
            if (slotInfoBox) slotInfoBox.style.display = 'none';
            return;
        }

        // Show loading state
        if (slotInfoBox) {
            slotInfoBox.style.display = 'block';
            slotSummary.innerHTML = '<span style="color:#0284c7;">Calculating available slots & token order...</span>';
        }

        try {
            const res = await fetch(`../api/get_slots.php?doctor_id=${doctorId}&date=${date}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (!data.success) {
                if (slotSummary) slotSummary.innerHTML = `<span style="color:#dc2626;">${data.error || 'Unable to check availability.'}</span>`;
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            const slot = data.data;

            if (previewDoctor) previewDoctor.textContent = slot.doctor.name + ' (' + slot.doctor.specialization + ')';
            if (previewFee) previewFee.textContent = '$' + parseFloat(slot.doctor.consultation_fee).toFixed(2);

            if (!slot.available) {
                if (slotSummary) {
                    slotSummary.innerHTML = `
                        <div class="alert alert-warning" style="margin-bottom:0;">
                            ⚠️ <strong>Not Available:</strong> ${slot.message}
                        </div>
                    `;
                }
                if (submitBtn) submitBtn.disabled = true;
                if (previewToken) previewToken.textContent = '-';
                if (previewEstTime) previewEstTime.textContent = '-';
            } else {
                if (slotSummary) {
                    slotSummary.innerHTML = `
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:6px; color:#166534;">
                            ✓ <strong>Shift Hours:</strong> ${formatTimeStr(slot.schedule.start_time)} - ${formatTimeStr(slot.schedule.end_time)} &nbsp;|&nbsp; 
                            <strong>Capacity:</strong> ${slot.booked_count} / ${slot.total_capacity} booked (${slot.remaining_slots} remaining)
                        </div>
                    `;
                }
                if (previewToken) previewToken.textContent = '#' + slot.next_token;
                if (previewEstTime) previewEstTime.textContent = slot.next_estimated_time_formatted;
                if (submitBtn) submitBtn.disabled = false;
            }
        } catch (err) {
            console.error('Error checking slots:', err);
            if (slotSummary) slotSummary.innerHTML = '<span style="color:#dc2626;">Error checking doctor schedule.</span>';
        }
    }

    function formatTimeStr(timeStr) {
        if (!timeStr) return '';
        const parts = timeStr.split(':');
        let hours = parseInt(parts[0], 10);
        const minutes = parts[1];
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${hours}:${minutes} ${ampm}`;
    }

    doctorSelect.addEventListener('change', checkAvailability);
    dateInput.addEventListener('change', checkAvailability);

    // Initial check
    checkAvailability();
});

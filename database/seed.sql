-- ==============================================================================
-- DOCTOR CONNECT - SEED DATA
-- Default password for all users: password123
-- ==============================================================================

-- 1. CLINIC SETTINGS
INSERT INTO clinic_settings (setting_key, setting_value) VALUES
('clinic_name', 'DoctorConnect TeleHealth Clinic'),
('clinic_tagline', 'Instant Online Token & Live Queue Consultation'),
('clinic_phone', '+1 (800) 555-0199'),
('clinic_email', 'care@doctorconnect.com'),
('clinic_address', '450 Healthtech Blvd, Suite 300, Silicon Valley, CA'),
('default_slot_duration', '15'),
('default_consultation_fee', '50.00'),
('jitsi_domain', 'meet.jit.si'),
('allow_cancel_hours', '2')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- 2. USERS (Admin, Doctors, Patients)
-- Hash for 'password123': $2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e
INSERT INTO users (id, name, email, phone, password_hash, role) VALUES
(1, 'Admin Receptionist', 'admin@doctorconnect.com', '+15550000001', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'admin'),
(2, 'Dr. Sarah Jenkins', 'sarah.jenkins@doctorconnect.com', '+15550000002', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'doctor'),
(3, 'Dr. Marcus Chen', 'marcus.chen@doctorconnect.com', '+15550000003', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'doctor'),
(4, 'Dr. Emily Rodriguez', 'emily.rodriguez@doctorconnect.com', '+15550000004', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'doctor'),
(5, 'Dr. David Patel', 'david.patel@doctorconnect.com', '+15550000005', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'doctor'),
(6, 'John Doe', 'john.doe@gmail.com', '+15550000006', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'patient'),
(7, 'Jane Smith', 'jane.smith@gmail.com', '+15550000007', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'patient'),
(8, 'Robert Brown', 'robert.brown@gmail.com', '+15550000008', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'patient'),
(9, 'Alice Taylor', 'alice.taylor@gmail.com', '+15550000009', '$2y$10$peNHoR.Z411S9mGwatXp8OtsprU3lEAYYztKJClzmBkc24nKUOf5e', 'patient')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- 3. DOCTORS PROFILE
INSERT INTO doctors (id, user_id, specialization, consultation_fee, avg_consultation_mins, bio) VALUES
(1, 2, 'Cardiology', 80.00, 15, 'Senior Board Certified Cardiologist with 14+ years experience managing hypertension, heart rhythm, and preventive cardiovascular care.'),
(2, 3, 'Pediatrics', 55.00, 15, 'Compassionate pediatrician specializing in newborn care, childhood immunizations, developmental milestones, and pediatric acute care.'),
(3, 4, 'Dermatology', 65.00, 20, 'Consultant dermatologist focused on skin conditions, eczema, acne management, and telehealth tele-dermatology diagnostics.'),
(4, 5, 'General Medicine', 40.00, 10, 'Experienced primary care physician treating seasonal viral illnesses, diabetes management, chronic lifestyle conditions, and general wellness.')
ON DUPLICATE KEY UPDATE specialization=VALUES(specialization);

-- 4. DOCTOR SCHEDULES (Weekly Availability: 0=Sun to 6=Sat)
-- Dr. Sarah Jenkins (Cardiology): Mon to Fri 09:00 - 13:00, Sat 10:00 - 14:00
INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active) VALUES
(1, 1, '09:00:00', '13:00:00', 15, 1),
(1, 2, '09:00:00', '13:00:00', 15, 1),
(1, 3, '09:00:00', '13:00:00', 15, 1),
(1, 4, '09:00:00', '13:00:00', 15, 1),
(1, 5, '09:00:00', '13:00:00', 15, 1),
(1, 6, '10:00:00', '14:00:00', 15, 1),
(1, 0, '10:00:00', '13:00:00', 15, 1);

-- Dr. Marcus Chen (Pediatrics): All days 08:30 - 14:30
INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active) VALUES
(2, 1, '08:30:00', '14:30:00', 15, 1),
(2, 2, '08:30:00', '14:30:00', 15, 1),
(2, 3, '08:30:00', '14:30:00', 15, 1),
(2, 4, '08:30:00', '14:30:00', 15, 1),
(2, 5, '08:30:00', '14:30:00', 15, 1),
(2, 6, '09:00:00', '13:00:00', 15, 1),
(2, 0, '09:00:00', '12:00:00', 15, 1);

-- Dr. Emily Rodriguez (Dermatology): Mon to Sat 13:00 - 18:00
INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active) VALUES
(3, 1, '13:00:00', '18:00:00', 20, 1),
(3, 2, '13:00:00', '18:00:00', 20, 1),
(3, 3, '13:00:00', '18:00:00', 20, 1),
(3, 4, '13:00:00', '18:00:00', 20, 1),
(3, 5, '13:00:00', '18:00:00', 20, 1),
(3, 6, '13:00:00', '17:00:00', 20, 1),
(3, 0, '14:00:00', '17:00:00', 20, 1);

-- Dr. David Patel (General Medicine): All 7 days 08:00 - 15:00
INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_duration, is_active) VALUES
(4, 0, '08:00:00', '15:00:00', 10, 1),
(4, 1, '08:00:00', '15:00:00', 10, 1),
(4, 2, '08:00:00', '15:00:00', 10, 1),
(4, 3, '08:00:00', '15:00:00', 10, 1),
(4, 4, '08:00:00', '15:00:00', 10, 1),
(4, 5, '08:00:00', '15:00:00', 10, 1),
(4, 6, '08:00:00', '15:00:00', 10, 1);

-- 5. TODAY'S INITIAL APPOINTMENTS (FOR LIVE QUEUE DEMONSTRATION)
-- Doctor 1 (Dr. Sarah Jenkins):
-- Token 1: Completed with prescription
-- Token 2: Serving right now!
-- Token 3: Waiting (Next in line)
-- Token 4: Waiting
INSERT INTO appointments (id, patient_id, doctor_id, appointment_date, token_number, status, meeting_link, estimated_time, reason, diagnosis, prescription) VALUES
(1, 6, 1, CURRENT_DATE(), 1, 'completed', 'https://meet.jit.si/doctor-connect-appt-1-x9a2f', CONCAT(CURRENT_DATE(), ' 09:00:00'), 'Routine hypertension checkup and palpitations', 'Mild essential hypertension (controlled)', '1. Amlodipine 5mg - 1 tablet once daily morning\n2. Low sodium diet (<2g/day)\n3. Recheck BP in 3 weeks'),
(2, 7, 1, CURRENT_DATE(), 2, 'serving', 'https://meet.jit.si/doctor-connect-appt-2-b4k8m', CONCAT(CURRENT_DATE(), ' 09:15:00'), 'Chest tightness on fast exertion', NULL, NULL),
(3, 8, 1, CURRENT_DATE(), 3, 'waiting', 'https://meet.jit.si/doctor-connect-appt-3-k2p9q', CONCAT(CURRENT_DATE(), ' 09:30:00'), 'High cholesterol report review and fatigue', NULL, NULL),
(4, 9, 1, CURRENT_DATE(), 4, 'waiting', 'https://meet.jit.si/doctor-connect-appt-4-m7z1r', CONCAT(CURRENT_DATE(), ' 09:45:00'), 'Follow-up for ECG baseline review', NULL, NULL)
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- 6. QUEUE AUDIT LOGS
INSERT INTO queue_logs (appointment_id, action, action_time) VALUES
(1, 'called', NOW() - INTERVAL 45 MINUTE),
(1, 'serving', NOW() - INTERVAL 44 MINUTE),
(1, 'served', NOW() - INTERVAL 25 MINUTE),
(2, 'called', NOW() - INTERVAL 10 MINUTE),
(2, 'serving', NOW() - INTERVAL 9 MINUTE);

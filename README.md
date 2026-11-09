# DOCTOR CONNECT ⚕
### Token-Based Online Doctor Consultation Platform

A full-stack, race-safe telehealth platform built with **PHP 8.1+ (PDO)**, **MySQL / MariaDB**, **HTML5 / CSS3**, and **Vanilla JavaScript**.

---

## 🚀 Key Features & Architectural Highlights

1. **Atomic & Race-Safe Token Allocation**:
   - Enforced by strict database constraint:
     ```sql
     UNIQUE KEY unique_token_per_doctor_day (doctor_id, appointment_date, token_number)
     ```
   - Transactional locking (`FOR UPDATE`) with automatic retry loop & exponential jitter to guarantee zero duplicate token collisions under concurrent load.
   - First booking on any calendar date receives Token #1, followed sequentially by #2, #3, etc.

2. **Dynamically Computed Consultation Slots**:
   - Slots are computed dynamically on-demand from `doctor_schedules` rather than stored statically.
   - Real-time computation of operating shifts, capacity, booked tokens, remaining slots, and next assigned token.

3. **Sub-3s Live Queue Synchronization**:
   - Vanilla JavaScript polling engine (`assets/js/live_queue.js`) pinging `api/live_queue.php` every 3000ms.
   - Synthesizer chime alerts via the **HTML5 Web Audio API** when a patient's token is called.

4. **Guaranteed Meeting Link Privacy & Unlocking**:
   - Video consultation rooms are **strictly locked** while patients wait further back in the queue.
   - The meeting link automatically unlocks when a patient's token becomes **NEXT** or **SERVING**.
   - Built-in **Jitsi Meet WebRTC** iframe room (`https://meet.jit.si/doctor-connect-appt-{id}-{hash}`) with support for custom Zoom or Google Meet URLs.

5. **Multi-Role User Portals**:
   - **Patient Portal**: Search doctors, dynamic booking wizard, live queue waiting room, WebRTC video consultation room, and printable clinical prescriptions.
   - **Doctor Console**: Daily queue controller, "Call Next" button, in-consultation split workstation, interactive clinical prescription builder, and weekly availability schedule editor.
   - **Admin & Receptionist Console**: Multi-doctor queue manager, walk-in patient token issuance, live clinic display monitor (for waiting room TVs), audit trail telemetry, date-wise reports, and 1-click CSV export.

---

## 🛠️ Technology Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.1+ with PDO (Prepared Statements & Transactions) |
| **Database** | MySQL 5.7+ / MariaDB 10.3+ (Automatic local SQLite capability included) |
| **Frontend** | HTML5, Modern CSS3 (Inter font, responsive flex/grid), Vanilla JavaScript ES6 |
| **Video Telehealth** | Jitsi Meet WebRTC (Embedded iframe + External window) |
| **Security** | Bcrypt password hashing (`PASSWORD_BCRYPT`), CSRF Tokens, Session Inactivity Timeout, Strict Input Validation |

---

## 📋 Pre-Seeded Demo Accounts

All pre-seeded demo accounts use password: `password123`

| Role | Name | Email | Default Dashboard |
|---|---|---|---|
| **Admin / Reception** | Reception Desk | `admin@doctorconnect.com` | `admin/dashboard.php` |
| **Doctor (Cardiology)** | Dr. Sarah Jenkins | `sarah.jenkins@doctorconnect.com` | `doctor/dashboard.php` |
| **Doctor (Pediatrics)** | Dr. Marcus Chen | `marcus.chen@doctorconnect.com` | `doctor/dashboard.php` |
| **Doctor (Dermatology)** | Dr. Emily Rodriguez | `emily.rodriguez@doctorconnect.com` | `doctor/dashboard.php` |
| **Doctor (General Med)** | Dr. David Patel | `david.patel@doctorconnect.com` | `doctor/dashboard.php` |
| **Patient** | John Doe | `john.doe@gmail.com` | `patient/dashboard.php` |
| **Patient** | Jane Smith | `jane.smith@gmail.com` | `patient/dashboard.php` |

---

## 🏁 Quickstart Instructions

### Option A: Running with PHP Built-in Server (Instant)

1. Open PowerShell or Terminal in the project directory:
   ```powershell
   cd C:\Users\annjo\OneDrive\Desktop\Optima
   ```
2. Run database setup:
   ```powershell
   php setup.php
   ```
3. Start the PHP development server:
   ```powershell
   php -S localhost:8000
   ```
4. Open your browser and navigate to:
   ```
   http://localhost:8000
   ```

### Option B: Running with XAMPP (Apache + MySQL)

1. Ensure Apache and MySQL are running in the **XAMPP Control Panel**.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or MySQL CLI.
3. Import `database/schema.sql` and `database/seed.sql` into a database named `doctor_connect`.
   *(Or navigate directly to `http://localhost/Optima/setup.php` to run the automated installer with 1 click).*
4. Access the application at:
   ```
   http://localhost/Optima/
   ```

---

## 📁 Project Directory Structure

```
Optima/
├── config/
│   ├── config.php              # App constants, session security, URL helpers
│   └── database.php            # PDO database wrapper (MySQL / MariaDB / SQLite)
├── database/
│   ├── schema.sql              # MySQL DDL with unique constraints & indices
│   ├── seed.sql                # Seed data for doctors, schedules, patients, tokens
│   └── doctor_connect.sqlite   # Local test database file
├── includes/
│   ├── auth.php                # Authentication, bcrypt, role middleware
│   ├── csrf.php                # CSRF token generator & validator
│   ├── functions.php           # Race-safe token generation, dynamic slot calculation
│   ├── header.php              # Global navigation bar & flash message alerts
│   └── footer.php              # Standard footer & scripts
├── api/
│   ├── live_queue.php          # Real-time queue polling endpoint (sub-3s)
│   ├── get_slots.php           # Dynamic doctor slot calculation endpoint
│   ├── queue_action.php        # Call next, mark serving, mark served, no-show, walk-in
│   └── cancel_token.php        # Patient appointment cancellation handler
├── patient/
│   ├── dashboard.php           # Patient overview: active tokens & consultation history
│   ├── book.php                # Token booking wizard with dynamic capacity preview
│   ├── queue.php               # Live waiting room board with audio chime & video link
│   ├── consultation.php        # Embedded WebRTC video consultation room
│   ├── history.php             # Full appointment history with status filtering
│   └── prescription.php        # Clean medical letterhead & printable prescription
├── doctor/
│   ├── dashboard.php           # Doctor console: current patient & today's metrics
│   ├── queue.php               # Live queue controller & walk-in issuance
│   ├── consultation.php        # Split video room & interactive prescription builder
│   ├── schedule.php            # Weekly operating hours & slot duration manager
│   └── prescriptions.php       # Archive of completed consultations and Rx
├── admin/
│   ├── dashboard.php           # Clinic KPI overview, revenue, & queue telemetry
│   ├── reception.php           # Multi-doctor reception desk & walk-in booking
│   ├── queue_monitor.php       # Full-screen clinic TV display for waiting rooms
│   ├── doctors.php             # Doctor roster management
│   ├── doctor_create.php       # Register new medical specialist account
│   ├── doctor_edit.php         # Edit doctor profile & consultation rates
│   ├── reports.php             # Date-wise & doctor-wise reports with filters
│   ├── export_csv.php          # 1-click CSV telemetry and appointment download
│   └── settings.php            # Clinic branding, fees, and telehealth settings
├── assets/
│   ├── css/
│   │   └── style.css           # Clean, accessible medical-grade UI styling
│   └── js/
│       ├── live_queue.js       # Live queue polling manager & Web Audio chime
│       └── booking.js          # Dynamic slot calculation & capacity preview
├── index.php                   # Public landing page with doctor directory
├── login.php                   # Authentication page with 1-click demo logins
├── register.php                # Patient self-registration
├── logout.php                  # Secure session clearance
├── setup.php                   # Web and CLI database setup & seeder wizard
├── test_suite.php              # Automated test suite (31 automated unit tests)
└── README.md                   # Complete documentation
```

---

## 🧪 Verification & Automated Testing

A test suite verifying all 31 system assertions is included. Run it at any time via CLI:
```powershell
php test_suite.php
```

All 31 verification tests cover:
- Database schema and table existence
- Bcrypt password validation for all user roles
- Dynamic slot calculation from doctor schedules
- Atomic race-safe token generation & UNIQUE key collision resistance
- Estimated meeting time formula (`session_start + (token - 1) * avg_mins`)
- Meeting link visibility rules (locked when waiting; unlocked when NEXT or SERVING)
- Queue advancement & audit trail logging
- Cancellation rules (preventing cancellation within 2 hours or when serving)

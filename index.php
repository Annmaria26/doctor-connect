<?php
// ==============================================================================
// DOCTOR CONNECT - HOMEPAGE & DOCTOR DIRECTORY
// ==============================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = "DoctorConnect - Token-Based Online Doctor Consultation";

$doctors = [];
try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT d.*, u.name as doctor_name, u.email as doctor_email
        FROM doctors d
        JOIN users u ON u.id = d.user_id
        ORDER BY d.id ASC
    ");
    $doctors = $stmt->fetchAll();
} catch (Exception $e) {
    // Handled in setup
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION -->
<div style="background: linear-gradient(135deg, #0f172a 0%, #0369a1 100%); color:white; border-radius:var(--radius-lg); padding:3.5rem 2rem; margin-bottom:2.5rem; text-align:center; position:relative; overflow:hidden;">
    <div style="max-width:800px; margin:0 auto; position:relative; z-index:2;">
        <span style="background:rgba(255,255,255,0.15); backdrop-filter:blur(4px); padding:0.35rem 0.9rem; border-radius:var(--radius-full); font-size:0.85rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; display:inline-block; margin-bottom:1.25rem;">
            ✦ Real-Time Telehealth & Queue Platform
        </span>
        <h1 style="font-size:2.8rem; font-weight:800; line-height:1.2; margin-bottom:1.25rem;">
            Skip the Waiting Room.<br><span style="color:#38bdf8;">Consult Your Doctor on Time.</span>
        </h1>
        <p style="font-size:1.15rem; color:#e0f2fe; margin-bottom:2rem; line-height:1.6;">
            Book an instant, race-safe consultation token. Track live queue position with sub-3s updates, and join your video consultation when your turn is called.
        </p>
        <div style="display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
            <a href="<?= isLoggedIn() ? url('patient/book.php') : url('register.php') ?>" class="btn btn-teal btn-lg" style="box-shadow:0 10px 20px rgba(13,148,136,0.35);">
                📅 Book an Online Token Now
            </a>
            <a href="#how-it-works" class="btn btn-outline btn-lg" style="background:rgba(255,255,255,0.1); border-color:rgba(255,255,255,0.3); color:white;">
                How It Works &darr;
            </a>
        </div>
    </div>
</div>

<!-- DEMO SHORTCUT BANNER FOR EASY TESTING -->
<div class="card" style="background:#f8fafc; border:1px solid #cbd5e1; margin-bottom:2.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <h4 style="color:#0f172a; margin-bottom:0.25rem;">🧪 Quick Testing & Demo Accounts</h4>
            <p style="color:#64748b; font-size:0.875rem; margin:0;">
                All pre-seeded demo accounts use password: <code style="background:#e2e8f0; padding:2px 6px; border-radius:4px; font-weight:700;">password123</code>
            </p>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= url('login.php?quick=patient') ?>" class="btn btn-sm btn-outline">Patient Demo</a>
            <a href="<?= url('login.php?quick=doctor') ?>" class="btn btn-sm btn-outline">Doctor Demo</a>
            <a href="<?= url('login.php?quick=admin') ?>" class="btn btn-sm btn-outline">Admin / Receptionist Demo</a>
            <a href="<?= url('setup.php') ?>" class="btn btn-sm btn-primary">Database Setup Tool</a>
        </div>
    </div>
</div>

<!-- HOW IT WORKS -->
<div id="how-it-works" style="margin-bottom:3.5rem;">
    <div style="text-align:center; max-width:650px; margin:0 auto 2.5rem;">
        <h2 style="font-size:2rem; font-weight:800; color:#0f172a; margin-bottom:0.5rem;">The 4-Step Patient Journey</h2>
        <p style="color:#64748b;">Effortless consultation management with race-safe token allocation</p>
    </div>

    <div class="grid-2" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));">
        <div class="card" style="text-align:center; padding:2rem 1.5rem;">
            <div style="width:54px; height:54px; background:#e0f2fe; color:#0284c7; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; margin:0 auto 1.25rem;">1</div>
            <h3 style="font-size:1.15rem; margin-bottom:0.5rem; color:#0f172a;">Select Doctor & Date</h3>
            <p style="color:#64748b; font-size:0.9rem;">Choose a specialist. Available slots are dynamically calculated based on doctor schedules.</p>
        </div>
        <div class="card" style="text-align:center; padding:2rem 1.5rem;">
            <div style="width:54px; height:54px; background:#ccfbf1; color:#0f766e; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; margin:0 auto 1.25rem;">2</div>
            <h3 style="font-size:1.15rem; margin-bottom:0.5rem; color:#0f172a;">Race-Safe Token</h3>
            <p style="color:#64748b; font-size:0.9rem;">Receive an atomic, unique daily token number with an estimated consultation meeting time.</p>
        </div>
        <div class="card" style="text-align:center; padding:2rem 1.5rem;">
            <div style="width:54px; height:54px; background:#fef3c7; color:#b45309; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; margin:0 auto 1.25rem;">3</div>
            <h3 style="font-size:1.15rem; margin-bottom:0.5rem; color:#0f172a;">Live Queue Updates</h3>
            <p style="color:#64748b; font-size:0.9rem;">Monitor real-time waiting room progress. Web Audio chimes notify you when your turn approaches.</p>
        </div>
        <div class="card" style="text-align:center; padding:2rem 1.5rem;">
            <div style="width:54px; height:54px; background:#dcfce7; color:#15803d; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; font-weight:800; margin:0 auto 1.25rem;">4</div>
            <h3 style="font-size:1.15rem; margin-bottom:0.5rem; color:#0f172a;">Video Consultation</h3>
            <p style="color:#64748b; font-size:0.9rem;">Secure meeting link activates automatically when your token is next or currently serving.</p>
        </div>
    </div>
</div>

<!-- FEATURED DOCTORS DIRECTORY -->
<div id="doctors" style="margin-bottom:3rem;">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
        <div>
            <h2 style="font-size:1.85rem; font-weight:800; color:#0f172a;">Available Specialists</h2>
            <p style="color:#64748b;">Browse board-certified doctors available for online consultation</p>
        </div>
        <a href="<?= url('patient/book.php') ?>" class="btn btn-primary">Book Consultation</a>
    </div>

    <div class="grid-2">
        <?php foreach ($doctors as $doc): ?>
            <div class="card" style="display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                        <div>
                            <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:700; margin-bottom:0.35rem;">
                                <?= e($doc['specialization']) ?>
                            </span>
                            <h3 style="font-size:1.25rem; font-weight:700; color:#0f172a; margin-top:0.25rem;">
                                <?= e($doc['doctor_name']) ?>
                            </h3>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:1.35rem; font-weight:800; color:#0f766e;">
                                $<?= number_format((float)$doc['consultation_fee'], 2) ?>
                            </div>
                            <div style="font-size:0.75rem; color:#64748b;">per consultation</div>
                        </div>
                    </div>

                    <p style="color:#475569; font-size:0.9rem; line-height:1.6; margin-bottom:1.25rem;">
                        <?= e($doc['bio'] ?: 'Experienced physician providing online consultations and personalized treatment plans.') ?>
                    </p>

                    <div style="display:flex; gap:1rem; font-size:0.825rem; color:#64748b; margin-bottom:1.25rem; background:#f8fafc; padding:0.6rem 0.9rem; border-radius:var(--radius-sm);">
                        <div>⏱ <strong>Avg Duration:</strong> <?= (int)$doc['avg_consultation_mins'] ?> mins</div>
                        <div>✓ <strong>Video Mode:</strong> Jitsi WebRTC</div>
                    </div>
                </div>

                <div style="border-top:1px solid #f1f5f9; padding-top:1rem; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.85rem; color:#10b981; font-weight:600;">● Accepting Tokens Today</span>
                    <a href="<?= url('patient/book.php?doctor_id=' . $doc['id']) ?>" class="btn btn-primary btn-sm">
                        Book Token &rarr;
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
// ==============================================================================
// DOCTOR CONNECT - USER LOGIN
// ==============================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

// If already logged in, redirect to respective dashboard
if (isLoggedIn()) {
    if (isAdmin()) header('Location: ' . url('admin/dashboard.php'));
    elseif (isDoctor()) header('Location: ' . url('doctor/dashboard.php'));
    else header('Location: ' . url('patient/dashboard.php'));
    exit;
}

$error = '';
$prefillEmail = '';

// Handle quick demo parameters
if (isset($_GET['quick'])) {
    switch ($_GET['quick']) {
        case 'patient':
            $prefillEmail = 'john.doe@gmail.com';
            break;
        case 'doctor':
            $prefillEmail = 'sarah.jenkins@doctorconnect.com';
            break;
        case 'admin':
            $prefillEmail = 'admin@doctorconnect.com';
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && verifyPassword($password, $user['password_hash'])) {
                loginUser($user);
                setFlash('success', "Welcome back, {$user['name']}!");

                $redirect = $_GET['redirect'] ?? '';
                if (!empty($redirect) && str_starts_with($redirect, '/')) {
                    header("Location: {$redirect}");
                    exit;
                }

                if ($user['role'] === ROLE_ADMIN) {
                    header('Location: ' . url('admin/dashboard.php'));
                } elseif ($user['role'] === ROLE_DOCTOR) {
                    header('Location: ' . url('doctor/dashboard.php'));
                } else {
                    header('Location: ' . url('patient/dashboard.php'));
                }
                exit;
            } else {
                $error = 'Invalid email address or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Sign In - " . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:440px; margin:2.5rem auto;">
    <div class="card" style="box-shadow:var(--shadow-md);">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="brand-icon" style="margin:0 auto 0.75rem; width:44px; height:44px; font-size:1.4rem;">⚕</div>
            <h2 style="font-size:1.5rem; font-weight:800; color:#0f172a;">Sign In to DoctorConnect</h2>
            <p style="color:#64748b; font-size:0.875rem;">Access your consultations, live queue, and medical records</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="font-size:0.875rem; margin-bottom:1.25rem;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($prefillEmail ?: ($_POST['email'] ?? '')) ?>" required autofocus placeholder="name@example.com">
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                    <label class="form-label" for="password" style="margin:0;">Password</label>
                    <span style="font-size:0.75rem; color:#64748b;">(Demo default: password123)</span>
                </div>
                <input type="password" id="password" name="password" class="form-control" required placeholder="••••••••" value="<?= $prefillEmail ? 'password123' : '' ?>">
            </div>

            <div style="margin-top:1.5rem; margin-bottom:1rem;">
                <button type="submit" class="btn btn-primary" style="width:100%;">
                    Sign In
                </button>
            </div>
        </form>

        <div style="border-top:1px solid #f1f5f9; padding-top:1.25rem; margin-top:1rem; text-align:center;">
            <p style="font-size:0.875rem; color:#64748b; margin-bottom:0.75rem;">
                New patient? <a href="<?= url('register.php') ?>" style="font-weight:600;">Create an account</a>
            </p>

            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:10px; font-size:0.8rem; text-align:left;">
                <div style="font-weight:700; color:#334155; margin-bottom:6px;">⚡ 1-Click Demo Quick-Fill:</div>
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-sm btn-outline" style="font-size:0.75rem;" onclick="fillLogin('john.doe@gmail.com')">Patient</button>
                    <button type="button" class="btn btn-sm btn-outline" style="font-size:0.75rem;" onclick="fillLogin('sarah.jenkins@doctorconnect.com')">Doctor</button>
                    <button type="button" class="btn btn-sm btn-outline" style="font-size:0.75rem;" onclick="fillLogin('admin@doctorconnect.com')">Admin / Reception</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(email) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = 'password123';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

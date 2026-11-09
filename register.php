<?php
// ==============================================================================
// DOCTOR CONNECT - PATIENT REGISTRATION
// ==============================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

if (isLoggedIn()) {
    header('Location: ' . url('patient/dashboard.php'));
    exit;
}

$errors = [];
$name = '';
$email = '';
$phone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Please provide your full legal name.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (empty($phone) || strlen($phone) < 8) {
        $errors[] = 'Please provide a valid phone number (min 8 digits).';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        try {
            $db = Database::getConnection();

            // Check uniqueness of email and phone
            $stmtCheck = $db->prepare("SELECT email, phone FROM users WHERE email = ? OR phone = ? LIMIT 1");
            $stmtCheck->execute([$email, $phone]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                if ($existing['email'] === $email) {
                    $errors[] = 'An account with this email address already exists.';
                }
                if ($existing['phone'] === $phone) {
                    $errors[] = 'An account with this phone number already exists.';
                }
            } else {
                $hash = hashPassword($password);
                $stmtInsert = $db->prepare("
                    INSERT INTO users (name, email, phone, password_hash, role)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([$name, $email, $phone, $hash, ROLE_PATIENT]);
                $newUserId = (int)$db->lastInsertId();

                // Fetch new user and log in
                $stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmtUser->execute([$newUserId]);
                $newUser = $stmtUser->fetch();

                loginUser($newUser);
                setFlash('success', 'Registration successful! Welcome to DoctorConnect.');
                header('Location: ' . url('patient/dashboard.php'));
                exit;
            }
        } catch (Exception $e) {
            $errors[] = 'Registration error: ' . $e->getMessage();
        }
    }
}

$pageTitle = "Patient Registration - " . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width:480px; margin:2.5rem auto;">
    <div class="card" style="box-shadow:var(--shadow-md);">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <div class="brand-icon" style="margin:0 auto 0.75rem; width:44px; height:44px; font-size:1.4rem;">⚕</div>
            <h2 style="font-size:1.5rem; font-weight:800; color:#0f172a;">Create Patient Account</h2>
            <p style="color:#64748b; font-size:0.875rem;">Register to book consultation tokens and track queues</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" style="font-size:0.875rem; margin-bottom:1.25rem;">
                <ul style="margin:0; padding-left:1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= e($name) ?>" required placeholder="e.g. John Doe">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?= e($email) ?>" required placeholder="name@example.com">
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone Number (with Country/Area code)</label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= e($phone) ?>" required placeholder="+1 555 123 4567">
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Min 6 chars">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required placeholder="Repeat password">
                </div>
            </div>

            <div style="margin-top:1.5rem; margin-bottom:1rem;">
                <button type="submit" class="btn btn-teal" style="width:100%;">
                    Create Account &rarr;
                </button>
            </div>
        </form>

        <div style="border-top:1px solid #f1f5f9; padding-top:1rem; text-align:center;">
            <p style="font-size:0.875rem; color:#64748b; margin:0;">
                Already have an account? <a href="<?= url('login.php') ?>" style="font-weight:600;">Sign in here</a>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

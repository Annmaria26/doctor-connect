<?php
// ==============================================================================
// DOCTOR CONNECT - AUTHENTICATION & ACCESS CONTROL
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return $_SESSION['user'] ?? null;
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

function isDoctor(): bool {
    return currentUserRole() === ROLE_DOCTOR;
}

function isAdmin(): bool {
    return currentUserRole() === ROLE_ADMIN;
}

function isPatient(): bool {
    return currentUserRole() === ROLE_PATIENT;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('warning', 'Please sign in to access this page.');
        header('Location: ' . url('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
        exit;
    }
}

function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array)$roles;
    if (!in_array(currentUserRole(), $roles, true)) {
        setFlash('danger', 'Access denied. You do not have permission to view that resource.');
        // Redirect to appropriate role dashboard
        switch (currentUserRole()) {
            case ROLE_ADMIN:
                header('Location: ' . url('admin/dashboard.php'));
                break;
            case ROLE_DOCTOR:
                header('Location: ' . url('doctor/dashboard.php'));
                break;
            default:
                header('Location: ' . url('patient/dashboard.php'));
                break;
        }
        exit;
    }
}

function loginUser(array $user): void {
    // Regenerate session id to prevent fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'role' => $user['role']
    ];

    // If doctor, load doctor profile ID
    if ($user['role'] === ROLE_DOCTOR) {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, specialization, consultation_fee, avg_consultation_mins FROM doctors WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $doctor = $stmt->fetch();
        if ($doctor) {
            $_SESSION['doctor_id'] = (int)$doctor['id'];
            $_SESSION['doctor_specialization'] = $doctor['specialization'];
            $_SESSION['doctor_fee'] = $doctor['consultation_fee'];
            $_SESSION['doctor_avg_mins'] = $doctor['avg_consultation_mins'];
        }
    }
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

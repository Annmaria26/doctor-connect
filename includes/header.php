<?php
// ==============================================================================
// DOCTOR CONNECT - PAGE HEADER & NAVIGATION
// ==============================================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? APP_NAME . ' - ' . APP_TAGLINE;
$user = currentUser();
$userRole = currentUserRole();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <!-- Modern Sans Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- App Stylesheet -->
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <meta name="csrf-token" content="<?= e(getCsrfToken()) ?>">
</head>
<body>

<!-- GLOBAL NAVIGATION BAR -->
<header class="navbar">
    <div class="nav-container">
        <a href="<?= url() ?>" class="brand">
            <div class="brand-icon">⚕</div>
            <div>Doctor<span>Connect</span></div>
        </a>

        <nav>
            <ul class="nav-links">
                <?php if (!$user): ?>
                    <li><a href="<?= url('index.php#doctors') ?>">Find Doctors</a></li>
                    <li><a href="<?= url('index.php#how-it-works') ?>">How It Works</a></li>
                    <li><a href="<?= url('login.php') ?>" class="btn btn-outline btn-sm">Sign In</a></li>
                    <li><a href="<?= url('register.php') ?>" class="btn btn-primary btn-sm">Register</a></li>
                <?php elseif ($userRole === ROLE_PATIENT): ?>
                    <li><a href="<?= url('patient/dashboard.php') ?>">Dashboard</a></li>
                    <li><a href="<?= url('patient/book.php') ?>" class="btn btn-primary btn-sm">+ Book Token</a></li>
                    <li><a href="<?= url('patient/history.php') ?>">My Consultations</a></li>
                <?php elseif ($userRole === ROLE_DOCTOR): ?>
                    <li><a href="<?= url('doctor/dashboard.php') ?>">Doctor Console</a></li>
                    <li><a href="<?= url('doctor/queue.php') ?>">Live Queue</a></li>
                    <li><a href="<?= url('doctor/schedule.php') ?>">Weekly Schedule</a></li>
                    <li><a href="<?= url('doctor/prescriptions.php') ?>">History</a></li>
                <?php elseif ($userRole === ROLE_ADMIN): ?>
                    <li><a href="<?= url('admin/dashboard.php') ?>">Admin Home</a></li>
                    <li><a href="<?= url('admin/reception.php') ?>">Reception Console</a></li>
                    <li><a href="<?= url('admin/queue_monitor.php') ?>" target="_blank">Live TV Board ↗</a></li>
                    <li><a href="<?= url('admin/doctors.php') ?>">Doctors</a></li>
                    <li><a href="<?= url('admin/reports.php') ?>">Reports</a></li>
                    <li><a href="<?= url('admin/settings.php') ?>">Settings</a></li>
                <?php endif; ?>

                <?php if ($user): ?>
                    <li class="user-menu">
                        <span class="role-badge role-<?= e($userRole) ?>"><?= e($userRole) ?></span>
                        <span style="font-weight:600; font-size:0.9rem; color:var(--slate-800);"><?= e($user['name']) ?></span>
                        <a href="<?= url('logout.php') ?>" class="btn btn-outline btn-sm" title="Sign Out">Sign Out</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<!-- FLASH MESSAGES -->
<?php $flashes = getFlashes(); if (!empty($flashes)): ?>
<div class="flash-container">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
            <div><?= e($flash['message']) ?></div>
            <button class="alert-close" onclick="this.parentElement.remove();">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<main class="main-content">

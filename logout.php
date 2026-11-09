<?php
// ==============================================================================
// DOCTOR CONNECT - LOGOUT HANDLER
// ==============================================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();

session_start();
setFlash('info', 'You have been successfully signed out.');
header('Location: ' . url('login.php'));
exit;

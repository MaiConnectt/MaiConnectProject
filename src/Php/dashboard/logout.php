<?php
/**
 * Logout Handler
 * Destroys session and redirects to login
 */

session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login page
require_once __DIR__ . '/../config/conexion.php';
header('Location: ' . BASE_URL . '/src/Php/login/login.php?logout=1');
exit;
?>
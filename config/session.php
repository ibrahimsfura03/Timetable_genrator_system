<?php
session_start();

define('SESSION_TIMEOUT', 3600);

if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
} else {
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        session_destroy();
        header("Location: " . $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'], 3) . "/login.php?expired=1");
        exit();
    }
    $_SESSION['login_time'] = time();
}

function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

function requireAdmin() {
    if (!isAdminLoggedIn()) {
        $base = dirname($_SERVER['PHP_SELF'], 2);
        header("Location: $base/login.php");
        exit();
    }
}

function getCurrentUser() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['user'] ?? 'Unknown';
}

function logout() {
    session_destroy();
    $base = dirname($_SERVER['PHP_SELF'], 2);
    header("Location: $base/login.php");
    exit();
}
?>

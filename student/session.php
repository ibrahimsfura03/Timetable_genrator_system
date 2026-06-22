<?php
session_start();

define('SESSION_TIMEOUT', 3600);

if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = time();
} else {
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        session_destroy();
        $base = dirname(dirname($_SERVER['PHP_SELF']));
        header("Location: $base/student/login.php?expired=1");
        exit();
    }
    $_SESSION['login_time'] = time();
}

function isStudentLoggedIn() {
    return isset($_SESSION['student_id']);
}

function requireStudentLogin() {
    if (!isStudentLoggedIn()) {
        $base = dirname(dirname($_SERVER['PHP_SELF']));
        header("Location: $base/student/login.php");
        exit();
    }
}

function getStudentId() {
    return $_SESSION['student_id'] ?? null;
}

function getStudentName() {
    return $_SESSION['student_name'] ?? 'Student';
}

function getDepartmentId() {
    return $_SESSION['department_id'] ?? null;
}

function getLevelId() {
    return $_SESSION['level_id'] ?? null;
}

function studentLogout() {
    session_destroy();
    $base = dirname(dirname($_SERVER['PHP_SELF']));
    header("Location: $base/student/login.php");
    exit();
}
?>

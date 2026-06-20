<?php
session_start();
include 'db.php';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    logAudit($conn, $user_id, 'LOGOUT', 'USER', $user_id);
}

session_destroy();
header("Location: ../login.php");
exit();
?>

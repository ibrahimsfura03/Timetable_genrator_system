<?php
session_start();
session_destroy();
header("Location: /Timetable_genrator_system/student/login.php");
exit();
?>

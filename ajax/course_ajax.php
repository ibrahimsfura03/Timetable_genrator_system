<?php
include '../config/db.php';

header('Content-Type: application/json');

$department_id = intval($_GET['dept'] ?? 0);

if ($department_id <= 0) {
    echo json_encode(array());
    exit;
}

$courses = array();
$stmt = $conn->prepare("SELECT id, course_code, course_title FROM courses WHERE department_id = ? AND status = 'active'");
if ($stmt) {
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode($courses);
?>

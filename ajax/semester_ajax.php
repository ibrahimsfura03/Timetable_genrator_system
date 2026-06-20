<?php
include '../config/db.php';

header('Content-Type: application/json');

$semesters = array(
    array('id' => 1, 'name' => 'First Semester'),
    array('id' => 2, 'name' => 'Second Semester')
);

echo json_encode($semesters);
?>

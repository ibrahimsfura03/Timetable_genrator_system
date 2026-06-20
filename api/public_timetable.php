<?php
header('Content-Type: application/json');
include '../config/db.php';

$entries = array();
$stmt = $conn->prepare("
    SELECT 
        te.id, te.day_of_week, te.start_time, te.end_time,
        c.course_code, c.course_title,
        d.department_name,
        l.level_name,
        h.hall_name,
        gt.is_published
    FROM timetable_entries te
    JOIN courses c ON te.course_id = c.id
    JOIN departments d ON c.department_id = d.id
    JOIN levels l ON c.level_id = l.id
    JOIN halls h ON te.hall_id = h.id
    JOIN generated_timetables gt ON te.timetable_id = gt.id
    WHERE gt.is_published = 1 AND gt.status = 'active'
    ORDER BY 
        CASE WHEN te.day_of_week = 'Monday' THEN 1
             WHEN te.day_of_week = 'Tuesday' THEN 2
             WHEN te.day_of_week = 'Wednesday' THEN 3
             WHEN te.day_of_week = 'Thursday' THEN 4
             WHEN te.day_of_week = 'Friday' THEN 5
        END,
        te.start_time ASC
    LIMIT 1000
");

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $entries = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode($entries);
?>

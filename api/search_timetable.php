<?php
header('Content-Type: application/json');
include '../config/db.php';

$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';
$entries = array();

if (empty($query)) {
    echo json_encode(array());
    exit;
}

$search_term = '%' . $query . '%';

if ($type === 'department') {
    $stmt = $conn->prepare("
        SELECT 
            te.id, te.day_of_week, te.start_time, te.end_time,
            c.course_code, c.course_title,
            d.department_name,
            l.level_name,
            h.hall_name
        FROM timetable_entries te
        JOIN courses c ON te.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN levels l ON c.level_id = l.id
        JOIN halls h ON te.hall_id = h.id
        JOIN generated_timetables gt ON te.timetable_id = gt.id
        WHERE gt.is_published = 1 AND d.department_name LIKE ?
        ORDER BY te.day_of_week, te.start_time ASC
        LIMIT 500
    ");
} elseif ($type === 'level') {
    $stmt = $conn->prepare("
        SELECT 
            te.id, te.day_of_week, te.start_time, te.end_time,
            c.course_code, c.course_title,
            d.department_name,
            l.level_name,
            h.hall_name
        FROM timetable_entries te
        JOIN courses c ON te.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN levels l ON c.level_id = l.id
        JOIN halls h ON te.hall_id = h.id
        JOIN generated_timetables gt ON te.timetable_id = gt.id
        WHERE gt.is_published = 1 AND l.level_name LIKE ?
        ORDER BY te.day_of_week, te.start_time ASC
        LIMIT 500
    ");
} elseif ($type === 'course') {
    $stmt = $conn->prepare("
        SELECT 
            te.id, te.day_of_week, te.start_time, te.end_time,
            c.course_code, c.course_title,
            d.department_name,
            l.level_name,
            h.hall_name
        FROM timetable_entries te
        JOIN courses c ON te.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN levels l ON c.level_id = l.id
        JOIN halls h ON te.hall_id = h.id
        JOIN generated_timetables gt ON te.timetable_id = gt.id
        WHERE gt.is_published = 1 AND (c.course_code LIKE ? OR c.course_title LIKE ?)
        ORDER BY te.day_of_week, te.start_time ASC
        LIMIT 500
    ");
} elseif ($type === 'hall') {
    $stmt = $conn->prepare("
        SELECT 
            te.id, te.day_of_week, te.start_time, te.end_time,
            c.course_code, c.course_title,
            d.department_name,
            l.level_name,
            h.hall_name
        FROM timetable_entries te
        JOIN courses c ON te.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN levels l ON c.level_id = l.id
        JOIN halls h ON te.hall_id = h.id
        JOIN generated_timetables gt ON te.timetable_id = gt.id
        WHERE gt.is_published = 1 AND h.hall_name LIKE ?
        ORDER BY te.day_of_week, te.start_time ASC
        LIMIT 500
    ");
} else {
    $stmt = $conn->prepare("
        SELECT 
            te.id, te.day_of_week, te.start_time, te.end_time,
            c.course_code, c.course_title,
            d.department_name,
            l.level_name,
            h.hall_name
        FROM timetable_entries te
        JOIN courses c ON te.course_id = c.id
        JOIN departments d ON c.department_id = d.id
        JOIN levels l ON c.level_id = l.id
        JOIN halls h ON te.hall_id = h.id
        JOIN generated_timetables gt ON te.timetable_id = gt.id
        WHERE gt.is_published = 1 AND (c.course_code LIKE ? OR c.course_title LIKE ? OR 
              d.department_name LIKE ? OR h.hall_name LIKE ?)
        ORDER BY te.day_of_week, te.start_time ASC
        LIMIT 500
    ");
}

if ($stmt) {
    if ($type === 'course') {
        $stmt->bind_param("ss", $search_term, $search_term);
    } elseif ($type === 'hall') {
        $stmt->bind_param("s", $search_term);
    } elseif ($type === '') {
        $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
    } else {
        $stmt->bind_param("s", $search_term);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $entries = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

echo json_encode($entries);
?>

<?php
include '../config/session.php';
include '../config/db.php';

requireAdmin();
$user_id = getCurrentUser();

$message = '';
$message_type = '';
$generation_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token invalid.';
        $message_type = 'error';
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'generate_all') {
                // Auto-generate for all departments and levels
                $departments_data = [];
                $stmt = $conn->prepare("SELECT id FROM departments WHERE status = 'active'");
                if ($stmt) {
                    $stmt->execute();
                    $departments_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
                
                $levels_data = [];
                $stmt = $conn->prepare("SELECT id FROM levels WHERE status = 'active'");
                if ($stmt) {
                    $stmt->execute();
                    $levels_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
                
                $total_generated = 0;
                
                foreach ($departments_data as $dept) {
                    foreach ($levels_data as $level) {
                        $dept_id = $dept['id'];
                        $level_id = $level['id'];
                        
                        // Check if department has courses for this level
                        $has_courses = 0;
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE department_id = ? AND level_id = ? AND status = 'active'");
                        if ($stmt) {
                            $stmt->bind_param("ii", $dept_id, $level_id);
                            $stmt->execute();
                            $has_courses = $stmt->get_result()->fetch_assoc()['count'];
                            $stmt->close();
                        }
                        
                        // Skip if no courses
                        if ($has_courses == 0) {
                            continue;
                        }
                        
                        $dept_info = [];
                        $stmt = $conn->prepare("SELECT department_name FROM departments WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $dept_id);
                            $stmt->execute();
                            $dept_info = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                        }
                        
                        $level_info = [];
                        $stmt = $conn->prepare("SELECT level_name FROM levels WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param("i", $level_id);
                            $stmt->execute();
                            $level_info = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                        }
                        
                        $timetable_name = $dept_info['department_name'] . ' - ' . $level_info['level_name'] . ' ' . date('Y/Y');
                        $semester = 1;
                        
                        $result = generateTimetable($conn, $user_id, $timetable_name, $dept_id, $level_id, $semester);
                        
                        if ($result['success']) {
                            $total_generated++;
                        }
                    }
                }
                
                if ($total_generated > 0) {
                    $message = "Successfully generated $total_generated timetable(s).";
                    $message_type = 'success';
                } else {
                    $message = "No timetables were generated. Check that courses exist for departments/levels.";
                    $message_type = 'error';
                }
            } elseif ($_POST['action'] === 'generate') {
                $timetable_name = sanitizeInput($_POST['timetable_name'] ?? '');
                $department_id = intval($_POST['department_id'] ?? 0);
                $level_id = intval($_POST['level_id'] ?? 0);
                $semester = intval($_POST['semester'] ?? 1);
                
                if (empty($timetable_name) || $department_id <= 0 || $level_id <= 0) {
                    $message = 'All required fields must be filled.';
                    $message_type = 'error';
                } else {
                    $generation_result = generateTimetable($conn, $user_id, $timetable_name, $department_id, $level_id, $semester);
                    
                    if ($generation_result['success']) {
                        $message = $generation_result['message'];
                        $message_type = 'success';
                    } else {
                        $message = $generation_result['message'];
                        $message_type = 'error';
                    }
                }
            }
        }
    }
}

function generateTimetable(&$conn, $user_id, $timetable_name, $dept_id, $level_id, $semester) {
    
    $courses = getCoursesByDeptLevel($conn, $dept_id, $level_id);
    if (empty($courses)) {
        return array('success' => false, 'message' => 'No courses found for the selected department and level.');
    }
    
    $halls = getAvailableHalls($conn);
    if (empty($halls)) {
        return array('success' => false, 'message' => 'No halls available.');
    }
    
    $time_slots = getTimeSlots($conn);
    if (empty($time_slots)) {
        return array('success' => false, 'message' => 'No time slots configured. Create time slots first.');
    }
    
    $days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');
    
    $timetable_id = null;
    $stmt = $conn->prepare("INSERT INTO generated_timetables (timetable_name, department_id, level_id, semester, status, is_published) VALUES (?, ?, ?, ?, 'active', 0)");
    if ($stmt) {
        $stmt->bind_param("siii", $timetable_name, $dept_id, $level_id, $semester);
        if ($stmt->execute()) {
            $timetable_id = $stmt->insert_id;
        } else {
            return array('success' => false, 'message' => 'Error creating timetable record.');
        }
        $stmt->close();
    }
    
    if (!$timetable_id) {
        return array('success' => false, 'message' => 'Failed to create timetable.');
    }
    
    $allocated_slots = array();
    $allocation_errors = array();
    
    foreach ($courses as $course) {
        $allocated = false;
        
        foreach ($days as $day) {
            if ($allocated) break;
            
            foreach ($time_slots as $slot) {
                
                if (isSlotAvailable($conn, $timetable_id, $slot['id'], $day, $halls)) {
                    
                    foreach ($halls as $hall) {
                        if (isHallAvailable($conn, $timetable_id, $hall['id'], $day, $slot['start_time'], $slot['end_time'])) {
                            
                            if (!isCourseDayConflict($conn, $timetable_id, $course['id'], $day)) {
                                
                                $stmt = $conn->prepare("
                                    INSERT INTO timetable_entries 
                                    (timetable_id, course_id, hall_id, time_slot_id, day_of_week, start_time, end_time, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                                ");
                                
                                if ($stmt) {
                                    $stmt->bind_param("iiissss", $timetable_id, $course['id'], $hall['id'], $slot['id'], $day, $slot['start_time'], $slot['end_time']);
                                    if ($stmt->execute()) {
                                        $entry_id = $stmt->insert_id;
                                        logAudit($conn, $user_id, 'CREATE', 'TIMETABLE_ENTRY', $entry_id);
                                        $allocated = true;
                                        $allocated_slots[] = array(
                                            'course_code' => $course['course_code'],
                                            'hall' => $hall['hall_name'],
                                            'day' => $day,
                                            'time' => $slot['start_time'] . ' - ' . $slot['end_time']
                                        );
                                        $stmt->close();
                                        break 3;
                                    }
                                    $stmt->close();
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if (!$allocated) {
            $allocation_errors[] = $course['course_code'];
        }
    }
    
    if (count($allocation_errors) > 0) {
        $conn->query("DELETE FROM timetable_entries WHERE timetable_id = $timetable_id");
        $conn->query("DELETE FROM generated_timetables WHERE id = $timetable_id");
        return array(
            'success' => false, 
            'message' => 'Could not allocate all courses. Errors: ' . implode(', ', $allocation_errors) . '. Please adjust courses, halls, or time slots and try again.'
        );
    }
    
    logAudit($conn, $user_id, 'CREATE', 'TIMETABLE', $timetable_id, null, array('name' => $timetable_name, 'courses' => count($courses)));
    
    return array(
        'success' => true, 
        'message' => "Timetable generated successfully with " . count($courses) . " courses allocated.",
        'timetable_id' => $timetable_id,
        'allocated' => count($allocated_slots),
        'total' => count($courses)
    );
}

function getCoursesByDeptLevel(&$conn, $dept_id, $level_id) {
    $courses = array();
    $stmt = $conn->prepare("SELECT id, course_code, course_title, duration FROM courses WHERE department_id = ? AND level_id = ? AND status = 'active' ORDER BY course_code ASC");
    if ($stmt) {
        $stmt->bind_param("ii", $dept_id, $level_id);
        $stmt->execute();
        $courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return $courses;
}

function getAvailableHalls(&$conn) {
    $halls = array();
    $stmt = $conn->prepare("SELECT id, hall_name, capacity FROM halls WHERE status = 'active' ORDER BY hall_name ASC");
    if ($stmt) {
        $stmt->execute();
        $halls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return $halls;
}

function getTimeSlots(&$conn) {
    $slots = array();
    $stmt = $conn->prepare("SELECT id, start_time, end_time, duration FROM time_slots WHERE status = 'active' ORDER BY start_time ASC");
    if ($stmt) {
        $stmt->execute();
        $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    return $slots;
}

function isSlotAvailable(&$conn, $timetable_id, $slot_id, $day, $halls) {
    $hall_count = 0;
    foreach ($halls as $hall) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count FROM timetable_entries 
            WHERE timetable_id = ? AND time_slot_id = ? AND day_of_week = ? AND hall_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("iisi", $timetable_id, $slot_id, $day, $hall['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->fetch_assoc()['count'] > 0) {
                $hall_count++;
            }
            $stmt->close();
        }
    }
    return $hall_count < count($halls);
}

function isHallAvailable(&$conn, $timetable_id, $hall_id, $day, $start_time, $end_time) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM timetable_entries 
        WHERE timetable_id = ? AND hall_id = ? AND day_of_week = ? 
        AND NOT (end_time <= ? OR start_time >= ?)
    ");
    if ($stmt) {
        $stmt->bind_param("iisss", $timetable_id, $hall_id, $day, $start_time, $end_time);
        $stmt->execute();
        $result = $stmt->get_result();
        $conflict = $result->fetch_assoc()['count'] > 0;
        $stmt->close();
        return !$conflict;
    }
    return true;
}

function isCourseDayConflict(&$conn, $timetable_id, $course_id, $day) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM timetable_entries 
        WHERE timetable_id = ? AND course_id = ? AND day_of_week = ?
    ");
    if ($stmt) {
        $stmt->bind_param("iis", $timetable_id, $course_id, $day);
        $stmt->execute();
        $result = $stmt->get_result();
        $conflict = $result->fetch_assoc()['count'] > 0;
        $stmt->close();
        return $conflict;
    }
    return false;
}

$departments = array();
$stmt = $conn->prepare("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC");
if ($stmt) {
    $stmt->execute();
    $departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$levels = array();
$stmt = $conn->prepare("SELECT id, level_name FROM levels WHERE status = 'active' ORDER BY level_code ASC");
if ($stmt) {
    $stmt->execute();
    $levels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Timetable - ATGS Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="main-wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>ATGS</h3>
                <p>Admin Panel</p>
            </div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="departments.php">Departments</a></li>
                <li><a href="levels.php">Levels</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="courses.php">Courses</a></li>
                <li><a href="halls.php">Halls</a></li>
                <li><a href="timeslots.php">Time Slots</a></li>
                <li><a href="timetable.php">Timetables</a></li>
                <li><a href="generate.php" class="active">Generate Timetable</a></li>
                <li><a href="audit_logs.php">Audit Logs</a></li>
                <li><a href="settings.php">Settings</a></li>
            </ul>
            <div class="sidebar-logout">
                <a href="../config/logout.php">Logout</a>
            </div>
        </div>
        <div style="margin-left: 240px;">
            <div class="topbar">
                <div class="topbar-info">
                    <span>Welcome, <strong><?php echo htmlspecialchars(getUserName()); ?></strong></span>
                </div>
                <div class="topbar-user">
                    <span><?php echo date('d M Y, H:i'); ?></span>
                </div>
            </div>
            <div class="content">
                <div class="page-header">
                    <h1>Generate Timetable</h1>
                    <p>Automatically generate conflict-free timetables</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container" style="max-width: 500px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                        <div>
                            <h3 style="margin-bottom: 16px;">Generate for Specific Department & Level</h3>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="generate">
                                
                                <div class="form-group">
                                    <label for="timetableName">Timetable Name *</label>
                                    <input type="text" id="timetableName" name="timetable_name" placeholder="e.g., CS 100 Level Timetable 2024/2025" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="departmentId">Department *</label>
                                    <select id="departmentId" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="levelId">Level *</label>
                                    <select id="levelId" name="level_id" required>
                                        <option value="">Select Level</option>
                                        <?php foreach ($levels as $level): ?>
                                            <option value="<?php echo $level['id']; ?>"><?php echo htmlspecialchars($level['level_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="semester">Semester</label>
                                    <select id="semester" name="semester">
                                        <option value="1">First Semester</option>
                                        <option value="2">Second Semester</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">Generate Timetable</button>
                            </form>
                        </div>
                        
                        <div>
                            <h3 style="margin-bottom: 16px;">Auto-Generate All</h3>
                            <p style="color: #666; font-size: 14px; margin-bottom: 16px;">Generate timetables for all departments and levels at once. The system will automatically allocate courses to available rooms and time slots.</p>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="generate_all">
                                <button type="submit" class="btn btn-success btn-block" onclick="return confirm('Generate timetables for ALL departments and levels? This may take a moment.')">Generate All Timetables</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php if ($generation_result && $generation_result['success']): ?>
                    <div style="margin-top: 30px; padding: 20px; background: #e8f5e9; border-radius: 4px;">
                        <h3 style="color: #2e7d32; margin-bottom: 16px;">Generation Summary</h3>
                        <p><strong>Total Courses:</strong> <?php echo $generation_result['total']; ?></p>
                        <p><strong>Successfully Allocated:</strong> <?php echo $generation_result['allocated']; ?></p>
                        <p style="margin-top: 16px;">
                            <a href="timetable.php" class="btn btn-success">View Timetables</a>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

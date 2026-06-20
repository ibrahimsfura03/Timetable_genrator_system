<?php
include 'session.php';
include '../config/db.php';

requireStudentLogin();

$student_id = getStudentId();
$dept_id = getDepartmentId();
$level_id = getLevelId();

$student_info = null;
$stmt = $conn->prepare("SELECT full_name, registration_number, department_id, level_id FROM students WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$dept_name = '';
$level_name = '';
$stmt = $conn->prepare("SELECT department_name FROM departments WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $dept_name = $result->fetch_assoc()['department_name'];
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT level_name FROM levels WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $level_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $level_name = $result->fetch_assoc()['level_name'];
    }
    $stmt->close();
}

$timetable_count = 0;
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT te.id) as count
    FROM timetable_entries te
    JOIN courses c ON te.course_id = c.id
    JOIN generated_timetables gt ON te.timetable_id = gt.id
    WHERE c.department_id = ? AND c.level_id = ? AND gt.is_published = 1 AND gt.status = 'active'
");
if ($stmt) {
    $stmt->bind_param("ii", $dept_id, $level_id);
    $stmt->execute();
    $timetable_count = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - ATGS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>ATGS - Student Portal</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: white; font-size: 14px;">Welcome, <?php echo htmlspecialchars(getStudentName()); ?></span>
            <a href="logout.php" style="color: white; text-decoration: none; padding: 8px 12px; background: #c62828; border-radius: 4px; font-size: 14px;">Logout</a>
        </div>
    </div>
    
    <div class="main-wrapper">
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>Student</h3>
                <p>Portal</p>
            </div>
            
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="timetable.php">My Timetable</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            
            <div class="sidebar-logout">
                <a href="logout.php">Logout</a>
            </div>
        </div>
        
        <div style="margin-left: 240px; display: flex; flex-direction: column; min-height: calc(100vh - 60px);">
            <div class="topbar">
                <div class="topbar-info">
                    <span>Student Information</span>
                </div>
                <div class="topbar-user">
                    <span><?php echo date('d M Y, H:i'); ?></span>
                </div>
            </div>
            
            <div class="content" style="flex: 1; display: flex; flex-direction: column;">
                <div class="page-header" style="width: 100%;">
                    <h1>Dashboard</h1>
                    <p>Your academic information and timetable</p>
                </div>
                
                <div class="cards-container" style="width: 100%; max-width: none;">
                    <div class="card">
                        <div class="card-value"><?php echo htmlspecialchars($dept_name); ?></div>
                        <div class="card-label">Department</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo htmlspecialchars($level_name); ?></div>
                        <div class="card-label">Level</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo $timetable_count; ?></div>
                        <div class="card-label">Classes Scheduled</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo htmlspecialchars($student_info['registration_number']); ?></div>
                        <div class="card-label">Registration Number</div>
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <h2 style="font-size: 18px; margin-bottom: 16px;">Quick Actions</h2>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="timetable.php" class="btn btn-primary">View My Timetable</a>
                        <a href="profile.php" class="btn btn-secondary">Update Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

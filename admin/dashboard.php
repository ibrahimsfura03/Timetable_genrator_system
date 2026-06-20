<?php
include '../config/session.php';
include '../config/db.php';

requireAdmin();

$user_id = getCurrentUser();

$stats = [
    'departments' => 0,
    'courses' => 0,
    'levels' => 0,
    'halls' => 0,
    'timetables' => 0
];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM departments WHERE status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['departments'] = $result->fetch_assoc()['count'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['courses'] = $result->fetch_assoc()['count'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM levels WHERE status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['levels'] = $result->fetch_assoc()['count'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM halls WHERE status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['halls'] = $result->fetch_assoc()['count'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM generated_timetables WHERE status = 'active'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['timetables'] = $result->fetch_assoc()['count'];
    $stmt->close();
}

$recent_activities = [];
$stmt = $conn->prepare("
    SELECT action, entity_type, created_at 
    FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ATGS Admin</title>
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
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="departments.php">Departments</a></li>
                <li><a href="levels.php">Levels</a></li>
                <li><a href="students.php">Students</a></li>
                <li><a href="courses.php">Courses</a></li>
                <li><a href="halls.php">Halls</a></li>
                <li><a href="timeslots.php">Time Slots</a></li>
                <li><a href="timetable.php">Timetables</a></li>
                <li><a href="generate.php">Generate Timetable</a></li>
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
                    <h1>Dashboard</h1>
                    <p>System overview and quick statistics</p>
                </div>
                
                <div class="cards-container">
                    <div class="card">
                        <div class="card-value"><?php echo $stats['departments']; ?></div>
                        <div class="card-label">Departments</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo $stats['levels']; ?></div>
                        <div class="card-label">Levels</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo $stats['courses']; ?></div>
                        <div class="card-label">Courses</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo $stats['halls']; ?></div>
                        <div class="card-label">Halls</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-value"><?php echo $stats['timetables']; ?></div>
                        <div class="card-label">Generated Timetables</div>
                    </div>
                </div>
                
                <div style="margin-top: 40px;">
                    <h2 style="font-size: 18px; margin-bottom: 16px;">Recent Activities</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Entity Type</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_activities) > 0): ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['entity_type']); ?></td>
                                            <td><?php echo date('d M Y, H:i', strtotime($activity['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #999;">No recent activities</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
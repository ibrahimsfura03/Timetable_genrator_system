<?php
include 'session.php';
include '../config/db.php';

requireStudentLogin();

$student_id = getStudentId();
$dept_id = getDepartmentId();
$level_id = getLevelId();

$view_type = $_GET['view'] ?? 'table';

$timetable_entries = [];
$stmt = $conn->prepare("
    SELECT te.id, te.day_of_week, te.start_time, te.end_time,
           c.course_code, c.course_title, c.credit_units,
           h.hall_name,
           gt.timetable_name
    FROM timetable_entries te
    JOIN courses c ON te.course_id = c.id
    JOIN halls h ON te.hall_id = h.id
    JOIN generated_timetables gt ON te.timetable_id = gt.id
    WHERE c.department_id = ? AND c.level_id = ? AND gt.is_published = 1 AND gt.status = 'active'
    ORDER BY 
        CASE WHEN te.day_of_week = 'Monday' THEN 1
             WHEN te.day_of_week = 'Tuesday' THEN 2
             WHEN te.day_of_week = 'Wednesday' THEN 3
             WHEN te.day_of_week = 'Thursday' THEN 4
             WHEN te.day_of_week = 'Friday' THEN 5
        END,
        te.start_time ASC
");
if ($stmt) {
    $stmt->bind_param("ii", $dept_id, $level_id);
    $stmt->execute();
    $timetable_entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$dept_name = '';
$level_name = '';
$stmt = $conn->prepare("SELECT department_name FROM departments WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $dept_id);
    $stmt->execute();
    $dept_name = $stmt->get_result()->fetch_assoc()['department_name'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT level_name FROM levels WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $level_id);
    $stmt->execute();
    $level_name = $stmt->get_result()->fetch_assoc()['level_name'];
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Timetable - ATGS</title>
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
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="timetable.php" class="active">My Timetable</a></li>
                <li><a href="profile.php">My Profile</a></li>
            </ul>
            
            <div class="sidebar-logout">
                <a href="logout.php">Logout</a>
            </div>
        </div>
        
        <div style="margin-left: 240px;">
            <div class="topbar">
                <div class="topbar-info">
                    <span><?php echo htmlspecialchars($dept_name); ?> - <?php echo htmlspecialchars($level_name); ?></span>
                </div>
                <div class="topbar-user">
                    <span><?php echo date('d M Y, H:i'); ?></span>
                </div>
            </div>
            
            <div class="content">
                <div class="page-header">
                    <h1>My Timetable</h1>
                    <p>Your class schedule for <?php echo htmlspecialchars($dept_name); ?> - <?php echo htmlspecialchars($level_name); ?></p>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <button onclick="window.print()" class="btn btn-secondary">Print Timetable</button>
                </div>
                
                <?php if (count($timetable_entries) > 0): ?>
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button onclick="filterByDay('all')" class="btn btn-primary" id="btn-all">All Days</button>
                            <button onclick="filterByDay('Monday')" class="btn btn-secondary" id="btn-Monday">Monday</button>
                            <button onclick="filterByDay('Tuesday')" class="btn btn-secondary" id="btn-Tuesday">Tuesday</button>
                            <button onclick="filterByDay('Wednesday')" class="btn btn-secondary" id="btn-Wednesday">Wednesday</button>
                            <button onclick="filterByDay('Thursday')" class="btn btn-secondary" id="btn-Thursday">Thursday</button>
                            <button onclick="filterByDay('Friday')" class="btn btn-secondary" id="btn-Friday">Friday</button>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="table-container">
                    <table id="timetableTable">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Title</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Hall</th>
                                <th>Credits</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($timetable_entries) > 0): ?>
                                <?php foreach ($timetable_entries as $entry): ?>
                                    <tr class="day-row" data-day="<?php echo htmlspecialchars($entry['day_of_week']); ?>">
                                        <td><?php echo htmlspecialchars($entry['course_code']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['course_title']); ?></td>
                                        <td><?php echo htmlspecialchars($entry['day_of_week']); ?></td>
                                        <td><?php echo date('H:i', strtotime($entry['start_time'])) . ' - ' . date('H:i', strtotime($entry['end_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($entry['hall_name']); ?></td>
                                        <td><?php echo $entry['credit_units']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #999;">No classes scheduled yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function filterByDay(day) {
            const rows = document.querySelectorAll('.day-row');
            
            rows.forEach(row => {
                if (day === 'all') {
                    row.style.display = '';
                } else {
                    const rowDay = row.getAttribute('data-day');
                    row.style.display = (rowDay === day) ? '' : 'none';
                }
            });
            
            // Update button styles
            document.querySelectorAll('[id^="btn-"]').forEach(btn => {
                btn.className = btn.id === 'btn-' + day ? 'btn btn-primary' : 'btn btn-secondary';
            });
        }
    </script>
</body>
</html>

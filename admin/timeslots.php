<?php
include '../config/session.php';
include '../config/db.php';

requireAdmin();
$user_id = getCurrentUser();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security token invalid.';
        $message_type = 'error';
    } else {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'generate') {
                $start_time = $_POST['start_time'] ?? '';
                $end_time = $_POST['end_time'] ?? '';
                $duration_val = sanitizeInput($_POST['duration'] ?? '');
                
                if (empty($start_time) || empty($end_time) || empty($duration_val)) {
                    $message = 'All fields are required.';
                    $message_type = 'error';
                } else {
                    $slots_created = 0;
                    
                    $start = strtotime($start_time);
                    $end = strtotime($end_time);
                    
                    $duration_minutes = 60;
                    if (strpos($duration_val, '2') === 0) $duration_minutes = 120;
                    elseif (strpos($duration_val, '3') === 0) $duration_minutes = 180;
                    
                    $current = $start;
                    while ($current + ($duration_minutes * 60) <= $end) {
                        $slot_start = date('H:i:s', $current);
                        $slot_end = date('H:i:s', $current + ($duration_minutes * 60));
                        
                        $stmt = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, status) VALUES (?, ?, ?, 'active') ON DUPLICATE KEY UPDATE status='active'");
                        if ($stmt) {
                            $stmt->bind_param("sss", $slot_start, $slot_end, $duration_val);
                            if ($stmt->execute()) {
                                $slots_created++;
                            }
                            $stmt->close();
                        }
                        
                        $current += ($duration_minutes * 60);
                    }
                    
                    if ($slots_created > 0) {
                        $message = "Generated $slots_created time slots successfully.";
                        $message_type = 'success';
                    }
                }
            }
            else if ($_POST['action'] === 'add') {
                $start_time = $_POST['slot_start'] ?? '';
                $end_time = $_POST['slot_end'] ?? '';
                $duration = sanitizeInput($_POST['slot_duration'] ?? '');
                $day = sanitizeInput($_POST['slot_day'] ?? '');
                
                if (empty($start_time) || empty($end_time) || empty($duration)) {
                    $message = 'Start time, end time, and duration are required.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("INSERT INTO time_slots (start_time, end_time, duration, day_of_week, status) VALUES (?, ?, ?, ?, 'active')");
                    if ($stmt) {
                        $stmt->bind_param("ssss", $start_time, $end_time, $duration, $day);
                        if ($stmt->execute()) {
                            $slot_id = $stmt->insert_id;
                            logAudit($conn, $user_id, 'CREATE', 'TIME_SLOT', $slot_id);
                            $message = 'Time slot added successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error adding time slot.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
            else if ($_POST['action'] === 'delete') {
                $slot_id = intval($_POST['slot_id'] ?? 0);
                if ($slot_id <= 0) {
                    $message = 'Invalid slot.';
                    $message_type = 'error';
                } else {
                    $stmt = $conn->prepare("UPDATE time_slots SET status='inactive' WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("i", $slot_id);
                        if ($stmt->execute()) {
                            logAudit($conn, $user_id, 'DELETE', 'TIME_SLOT', $slot_id);
                            $message = 'Time slot deleted successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error deleting time slot.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

$slots = [];
$stmt = $conn->prepare("SELECT id, start_time, end_time, duration, day_of_week, status, created_at FROM time_slots WHERE status = 'active' ORDER BY start_time ASC");
if ($stmt) {
    $stmt->execute();
    $slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$csrf_token = generateCSRFToken();
$days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Slots - ATGS Admin</title>
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
                <li><a href="timeslots.php" class="active">Time Slots</a></li>
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
                    <h1>Time Slot Management</h1>
                    <p>Create and manage class time slots</p>
                </div>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    
                    <div class="form-container">
                        <h3 style="margin-bottom: 16px;">Automatic Generation</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="generate">
                            <div class="form-group">
                                <label for="startTime">Start Time *</label>
                                <input type="time" id="startTime" name="start_time" value="08:00" required>
                            </div>
                            <div class="form-group">
                                <label for="endTime">End Time *</label>
                                <input type="time" id="endTime" name="end_time" value="16:00" required>
                            </div>
                            <div class="form-group">
                                <label for="duration">Duration *</label>
                                <select id="duration" name="duration" required>
                                    <option value="1 Hour">1 Hour</option>
                                    <option value="2 Hours">2 Hours</option>
                                    <option value="3 Hours">3 Hours</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success btn-block">Generate Slots</button>
                        </form>
                    </div>
                    
                    <div class="form-container">
                        <h3 style="margin-bottom: 16px;">Manual Addition</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label for="slotStart">Start Time *</label>
                                <input type="time" id="slotStart" name="slot_start" required>
                            </div>
                            <div class="form-group">
                                <label for="slotEnd">End Time *</label>
                                <input type="time" id="slotEnd" name="slot_end" required>
                            </div>
                            <div class="form-group">
                                <label for="slotDuration">Duration *</label>
                                <input type="text" id="slotDuration" name="slot_duration" placeholder="e.g., 1 Hour" required>
                            </div>
                            <div class="form-group">
                                <label for="slotDay">Day (Optional)</label>
                                <select id="slotDay" name="slot_day">
                                    <option value="">All Days</option>
                                    <?php foreach ($days as $day): ?>
                                        <option value="<?php echo $day; ?>"><?php echo $day; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success btn-block">Add Slot</button>
                        </form>
                    </div>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Duration</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($slots) > 0): ?>
                                <?php foreach ($slots as $slot): ?>
                                    <tr>
                                        <td><?php echo date('H:i', strtotime($slot['start_time'])); ?></td>
                                        <td><?php echo date('H:i', strtotime($slot['end_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($slot['duration']); ?></td>
                                        <td><?php echo htmlspecialchars($slot['day_of_week'] ?? 'All Days'); ?></td>
                                        <td><span style="padding: 4px 8px; border-radius: 4px; background: #e8f5e9; color: #2e7d32;">Active</span></td>
                                        <td><?php echo date('d M Y', strtotime($slot['created_at'])); ?></td>
                                        <td>
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="slot_id" value="<?php echo $slot['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: #999;">No time slots found. Create some to get started.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

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
        $institution_name = sanitizeInput($_POST['institution_name'] ?? '');
        $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
        $semester_current = intval($_POST['semester_current'] ?? 1);
        $timetable_days = implode(',', $_POST['timetable_days'] ?? array());
        $allow_weekend = isset($_POST['allow_weekend']) ? '1' : '0';
        
        if (empty($institution_name) || empty($academic_year)) {
            $message = 'Institution name and academic year are required.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='institution_name'");
            if ($stmt) {
                $stmt->bind_param("s", $institution_name);
                $stmt->execute();
                $stmt->close();
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='academic_year'");
            if ($stmt) {
                $stmt->bind_param("s", $academic_year);
                $stmt->execute();
                $stmt->close();
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='semester_current'");
            if ($stmt) {
                $stmt->bind_param("i", $semester_current);
                $stmt->execute();
                $stmt->close();
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='timetable_days'");
            if ($stmt) {
                $stmt->bind_param("s", $timetable_days);
                $stmt->execute();
                $stmt->close();
            }
            
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key='allow_weekend'");
            if ($stmt) {
                $stmt->bind_param("s", $allow_weekend);
                $stmt->execute();
                $stmt->close();
            }
            
            logAudit($conn, $user_id, 'UPDATE', 'SETTINGS', 0);
            $message = 'Settings updated successfully.';
            $message_type = 'success';
        }
    }
}

$settings = array();
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $stmt->close();
}

$csrf_token = generateCSRFToken();
$days_array = explode(',', $settings['timetable_days'] ?? 'Monday,Tuesday,Wednesday,Thursday,Friday');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ATGS Admin</title>
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
                <li><a href="generate.php">Generate Timetable</a></li>
                <li><a href="audit_logs.php">Audit Logs</a></li>
                <li><a href="settings.php" class="active">Settings</a></li>
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
                    <h1>System Settings</h1>
                    <p>Configure system-wide settings and preferences</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-container" style="max-width: 600px;">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="form-group">
                            <label for="institutionName">Institution Name *</label>
                            <input type="text" id="institutionName" name="institution_name" value="<?php echo htmlspecialchars($settings['institution_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="academicYear">Academic Year *</label>
                            <input type="text" id="academicYear" name="academic_year" placeholder="e.g., 2024/2025" value="<?php echo htmlspecialchars($settings['academic_year'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="semesterCurrent">Current Semester</label>
                            <select id="semesterCurrent" name="semester_current">
                                <option value="1" <?php echo ($settings['semester_current'] ?? '') === '1' ? 'selected' : ''; ?>>First Semester</option>
                                <option value="2" <?php echo ($settings['semester_current'] ?? '') === '2' ? 'selected' : ''; ?>>Second Semester</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Timetable Days</label>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-top: 10px;">
                                <?php foreach (array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') as $day): ?>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox" id="day_<?php echo $day; ?>" name="timetable_days[]" value="<?php echo $day; ?>" 
                                            <?php echo in_array($day, $days_array) ? 'checked' : ''; ?>>
                                        <label for="day_<?php echo $day; ?>" style="margin: 0; cursor: pointer;"><?php echo $day; ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="allowWeekend" name="allow_weekend" value="1" <?php echo ($settings['allow_weekend'] ?? '') === '1' ? 'checked' : ''; ?>>
                                <label for="allowWeekend" style="margin: 0; cursor: pointer;">Allow Weekend Scheduling</label>
                            </div>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-success">Save Settings</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
                
                <div style="margin-top: 40px; padding: 20px; background: #f5f5f5; border-radius: 4px;">
                    <h3 style="margin-top: 0;">System Information</h3>
                    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                    <p><strong>Database:</strong> <?php echo $conn->server_info; ?></p>
                    <p><strong>Server Time:</strong> <?php echo date('d M Y H:i:s'); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

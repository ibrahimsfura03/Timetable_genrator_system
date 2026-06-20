<?php
include 'session.php';
include '../config/db.php';

requireStudentLogin();

$student_id = getStudentId();
$message = '';
$message_type = '';

$student = null;
$stmt = $conn->prepare("SELECT id, registration_number, full_name, department_id, level_id FROM students WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All password fields are required.';
        $message_type = 'error';
    } else if (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters.';
        $message_type = 'error';
    } else if ($new_password !== $confirm_password) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT password FROM students WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stored = $result->fetch_assoc();
                if ($current_password === $stored['password']) {
                    $stmt = $conn->prepare("UPDATE students SET password = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $new_password, $student_id);
                        if ($stmt->execute()) {
                            $message = 'Password updated successfully.';
                            $message_type = 'success';
                        } else {
                            $message = 'Error updating password.';
                            $message_type = 'error';
                        }
                        $stmt->close();
                    }
                } else {
                    $message = 'Current password is incorrect.';
                    $message_type = 'error';
                }
            }
            $stmt->close();
        }
    }
}

$dept_name = '';
$level_name = '';
$stmt = $conn->prepare("SELECT department_name FROM departments WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student['department_id']);
    $stmt->execute();
    $dept_name = $stmt->get_result()->fetch_assoc()['department_name'];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT level_name FROM levels WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $student['level_id']);
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
    <title>My Profile - ATGS</title>
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
                <li><a href="timetable.php">My Timetable</a></li>
                <li><a href="profile.php" class="active">My Profile</a></li>
            </ul>
            
            <div class="sidebar-logout">
                <a href="logout.php">Logout</a>
            </div>
        </div>
        
        <div style="margin-left: 240px;">
            <div class="topbar">
                <div class="topbar-info">
                    <span>Profile Information</span>
                </div>
                <div class="topbar-user">
                    <span><?php echo date('d M Y, H:i'); ?></span>
                </div>
            </div>
            
            <div class="content">
                <div class="page-header">
                    <h1>My Profile</h1>
                    <p>View and manage your account information</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <div class="form-container">
                        <h3 style="margin-bottom: 20px;">Registration Information</h3>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($student['full_name']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Registration Number</label>
                            <input type="text" value="<?php echo htmlspecialchars($student['registration_number']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" value="<?php echo htmlspecialchars($dept_name); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Level</label>
                            <input type="text" value="<?php echo htmlspecialchars($level_name); ?>" disabled>
                        </div>
                        
                        <p style="font-size: 12px; color: #999; margin-top: 16px;">Academic information can only be changed by administrators.</p>
                    </div>
                    
                    <div class="form-container">
                        <h3 style="margin-bottom: 20px;">Change Password</h3>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="currentPassword">Current Password *</label>
                                <div style="position: relative;">
                                    <input type="password" id="currentPassword" name="current_password" required style="padding-right: 40px;">
                                    <button type="button" onclick="togglePassword('currentPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="newPassword">New Password *</label>
                                <div style="position: relative;">
                                    <input type="password" id="newPassword" name="new_password" required style="padding-right: 40px;">
                                    <button type="button" onclick="togglePassword('newPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmPassword">Confirm Password *</label>
                                <div style="position: relative;">
                                    <input type="password" id="confirmPassword" name="confirm_password" required style="padding-right: 40px;">
                                    <button type="button" onclick="togglePassword('confirmPassword')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            
                            <button type="submit" name="update_password" class="btn btn-primary btn-block">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>

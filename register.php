<?php
include 'config/db.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $reg_number = trim($_POST['registration_number'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $dept_id = intval($_POST['department_id'] ?? 0);
    $level_id = intval($_POST['level_id'] ?? 0);
    
    if (empty($reg_number) || empty($full_name) || empty($password) || $dept_id <= 0 || $level_id <= 0) {
        $message = 'All fields are required.';
        $message_type = 'error';
    } else if (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $message_type = 'error';
    } else if ($password !== $password_confirm) {
        $message = 'Passwords do not match.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("SELECT id FROM students WHERE registration_number = ?");
        if ($stmt) {
            $stmt->bind_param("s", $reg_number);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $message = 'Registration number already exists.';
                $message_type = 'error';
                $stmt->close();
            } else {
                $stmt->close();
                $stmt = $conn->prepare("INSERT INTO students (registration_number, full_name, password, department_id, level_id, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                if ($stmt) {
                    $stmt->bind_param("sssii", $reg_number, $full_name, $password, $dept_id, $level_id);
                    if ($stmt->execute()) {
                        $message = 'Registration successful! Your account is pending approval. Administrators will review your registration.';
                        $message_type = 'success';
                    } else {
                        $message = 'Error during registration. Please try again.';
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$departments = [];
$stmt = $conn->prepare("SELECT id, department_name FROM departments WHERE status = 'active' ORDER BY department_name ASC");
if ($stmt) {
    $stmt->execute();
    $departments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$levels = [];
$stmt = $conn->prepare("SELECT id, level_name FROM levels WHERE status = 'active' ORDER BY level_code ASC");
if ($stmt) {
    $stmt->execute();
    $levels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - ATGS</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <h2>ATGS - Al-Qalam University Katsina</h2>
        <div>
            <a href="index.php">Home</a>
            <a href="student/login.php">Student Login</a>
            <a href="login.php" style="margin-left: 10px;">Admin</a>
        </div>
    </div>
    
    <div class="content" style="max-width: 600px; margin: 40px auto;">
        <div class="page-header">
            <h1>Student Registration</h1>
            <p>Create a new student account</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="regNumber">Registration Number *</label>
                    <input type="text" id="regNumber" name="registration_number" required>
                </div>
                
                <div class="form-group">
                    <label for="fullName">Full Name *</label>
                    <input type="text" id="fullName" name="full_name" required>
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
                    <label for="password">Password *</label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" required style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="passwordConfirm">Confirm Password *</label>
                    <div style="position: relative;">
                        <input type="password" id="passwordConfirm" name="password_confirm" required style="padding-right: 40px;">
                        <button type="button" onclick="togglePassword('passwordConfirm')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #666; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="submit" name="register" class="btn btn-primary btn-block">Register</button>
                </div>
                
                <div style="margin-top: 16px; text-align: center; font-size: 14px; color: #666;">
                    Already have an account? <a href="student/login.php">Login here</a>
                </div>
            </form>
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

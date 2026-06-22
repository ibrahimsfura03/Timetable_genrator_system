<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
include dirname(__FILE__) . '/config/db.php';

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $message = "Email and password are required.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, password, role FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                if ($password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user'] = $user['fullname'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    header("Location: admin/dashboard.php");
                    exit();
                } else {
                    $message = "Invalid email or password.";
                    $message_type = "error";
                }
            } else {
                $message = "Invalid email or password.";
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Database error. Please try again later.";
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ATGS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .navbar {
            background: rgba(26, 35, 126, 0.95);
            padding: 12px 20px;
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h2 {
            font-size: 20px;
            font-weight: 600;
        }
        
        .navbar div {
            display: flex;
            gap: 15px;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 6px 12px;
            border-radius: 4px;
            transition: background 0.2s;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 420px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            font-size: 24px;
            color: #1a237e;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 6px;
            color: #333;
            font-size: 14px;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #1a237e;
            box-shadow: 0 0 0 2px rgba(26, 35, 126, 0.1);
        }
        
        .message {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .message.error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        
        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .form-button {
            width: 100%;
            padding: 10px;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .form-button:hover {
            background: #0d1852;
        }
        
        .form-button:active {
            transform: translateY(1px);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>ATGS - Al-Qalam University Katsina</h2>
        <div>
            <a href="index.php">Home</a>
            <a href="register.php">Register</a>
            <a href="student/login.php">Student Login</a>
        </div>
    </div>
    
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <h1>Admin Login</h1>
                <p>Automatic Timetable Generator System</p>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
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
                
                <button type="submit" name="login" class="form-button">Sign In</button>
            </form>
            
            <div class="login-footer">
                <p>Al-Qalam University Katsina &copy; 2024</p>
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

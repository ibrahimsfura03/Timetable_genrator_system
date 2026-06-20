<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include dirname(__FILE__) . '/config/db.php';

$message = "";

if(isset($_POST['login'])){

$email = mysqli_real_escape_string($conn, $_POST['email']);
$password = mysqli_real_escape_string($conn, $_POST['password']);
$role = mysqli_real_escape_string($conn, $_POST['role']);

$query = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' AND role='$role'");

if(mysqli_num_rows($query) > 0){

$user = mysqli_fetch_assoc($query);

if($password == $user['password']){

$_SESSION['user'] = $user['fullname'];
$_SESSION['role'] = $user['role'];

header("Location: admin/dashboard.php");
exit();

}else{
$message = "Wrong Password";
}

}else{
$message = "User Not Found";
}
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-box">

<h2>System Login</h2>

<form method="POST">

<input type="email" name="email" placeholder="Email" required>
<input type="password" name="password" placeholder="Password" required>

<select name="role">
<option>Admin</option>
<option>Student</option>
<option>Lecturer</option>
</select>

<button type="submit" name="login">Login</button>

</form>

<p><?php echo $message; ?></p>

</div>

</body>
</html>
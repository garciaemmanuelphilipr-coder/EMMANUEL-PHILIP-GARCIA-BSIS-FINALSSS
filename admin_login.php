<?php
require "db.php";
$error = "";

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $result = run("SELECT * FROM users WHERE username = ? AND role = 'admin'", "s", [$_POST['username']]);
    $user = mysqli_fetch_assoc($result);

    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Wrong username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Login - eventSched&Attendance</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login">
  <h2>eventSched&amp;Attendance</h2>
  <p><b>Admin Login</b></p>
  <p class="hint">Hint: Username: Robin, Password: Robin123</p>
  <form method="post">
    <label>Username <input type="text" name="username" required></label>
    <label>Password <input type="password" name="password" required></label>
    <p class="bad"><?php echo e($error); ?></p>
    <button type="submit">Log in as admin</button>
  </form>
  <p><a href="index.php">Staff / Organizer login</a></p>
</div>
</body>
</html>
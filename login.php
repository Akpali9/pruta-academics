<?php
require_once "../config/secure.php";
securePage();
require_once 'config/database.php';
require_once "config/rate_limit.php";
require_once "config/device.php";
require_once "config/session_secure.php";

// check attempts first
checkLoginAttempts($pdo, $email);

$stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
$stmt->execute([$email]);
$user = $stmt->fetch();
secureSessionStart();

if (!$user || !password_verify($password, $user['password'])) {

    addLoginAttempt($pdo, $email);
    die("Invalid login");
}

// reset attempts
resetAttempts($pdo, $email);

// set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

$_SESSION['ip'] = getUserIP();
$_SESSION['device'] = getDeviceHash();
session_start();

$message = "";

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare(
        "SELECT * FROM users WHERE email=?"
    );

    $stmt->execute([$email]);

    $user = $stmt->fetch();

    if(
        $user &&
        password_verify(
            $password,
            $user['password']
        )
    )
    {
        $_SESSION['user_id']
            = $user['id'];

        $_SESSION['role']
            = $user['role'];

        $_SESSION['fullname']
            = $user['fullname'];

        if($user['role'] === 'admin')
        {
            header(
                "Location: admin/dashboard.php"
            );
        }
        else
        {
            header(
                "Location: student/dashboard.php"
            );
        }

        exit;
    }

    $message = "Invalid Login";
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="card">

<h2>Login</h2>

<p><?= $message ?></p>

<form method="POST">

<input
type="email"
name="email"
placeholder="Email"
required>

<input
type="password"
name="password"
placeholder="Password"
required>

<button type="submit">
Login
</button>

</form>

<a href="register.php">
Create Account
</a><p> or </p> <a href="forgot_password.php">
forgot password
</a>

</div>

</body>
</html>

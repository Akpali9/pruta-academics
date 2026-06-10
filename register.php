<?php

require_once 'config/database.php';

$message = "";

if($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    $hash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $check = $pdo->prepare(
        "SELECT id FROM users WHERE email=?"
    );

    $check->execute([$email]);

    if($check->rowCount() > 0)
    {
        $message = "Email already exists";
    }
    else
    {
        $stmt = $pdo->prepare(
            "INSERT INTO users
            (fullname,email,phone,password)
            VALUES(?,?,?,?)"
        );

        $stmt->execute([
            $fullname,
            $email,
            $phone,
            $hash
        ]);

        $message = "Registration successful";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
<title>Register</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="card">

<h2>Student Registration</h2>

<p><?= $message ?></p>

<form method="POST">

<input
type="text"
name="fullname"
placeholder="Full Name"
required>

<input
type="email"
name="email"
placeholder="Email"
required>

<input
type="text"
name="phone"
placeholder="Phone"
required>

<input
type="password"
name="password"
placeholder="Password"
required>

<button type="submit">
Register
</button>

</form>

<a href="login.php">
Already have an account?
</a>

</div>

</body>
</html>

<?php
require_once "config/database.php";
require_once "../config/secure.php";
securePage();
$token = $_GET['token'];

$stmt = $pdo->prepare("
SELECT * FROM users
WHERE reset_token=? AND reset_expiry > NOW()
");

$stmt->execute([$token]);
$user = $stmt->fetch();

if(!$user) {
    die("Invalid or expired reset link");
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password=?, reset_token=NULL, reset_expiry=NULL
        WHERE id=?
    ");

    $stmt->execute([$password, $user['id']]);

    echo "Password reset successful. <a href='login.php'>Login</a>";
    exit;
}
?>

<h2>Reset Password</h2>

<form method="POST">
<input type="password" name="password" placeholder="New Password" required>
<button>Reset</button>
</form>

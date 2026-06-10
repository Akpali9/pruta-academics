<?php
require_once "config/database.php";

$message = "";

if($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = $_POST['email'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if($user) {

        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $stmt = $pdo->prepare("
            UPDATE users
            SET reset_token=?, reset_expiry=?
            WHERE email=?
        ");

        $stmt->execute([$token, $expiry, $email]);

        $link = "http://localhost/lms/reset_password.php?token=$token";

        // simple mail (upgrade later to SMTP)
        mail($email, "Password Reset", "Reset link: $link");

        $message = "Reset link sent to email";
    } else {
        $message = "Email not found";
    }
}
?>

<h2>Forgot Password</h2>

<p><?= $message ?></p>

<form method="POST">
<input type="email" name="email" placeholder="Enter email" required>
<button>Send Reset Link</button>
</form>

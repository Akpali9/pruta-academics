<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM courses WHERE id=?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if(!$course){
    die("Course not found");
}
?>

<h2><?= $course['title'] ?></h2>
<p><?= $course['description'] ?></p>

<h3>Step 1: Pay Manually</h3>
<p>Send payment to admin account and upload receipt.</p>

<form action="upload_receipt.php" method="POST" enctype="multipart/form-data">

<input type="hidden" name="course_id" value="<?= $course['id'] ?>">

<input type="file" name="receipt" required>

<button type="submit">Upload Receipt</button>

</form>

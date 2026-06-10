<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/access_check.php";

requireLogin();

$user_id = $_SESSION['user_id'];
$module_id = $_GET['id'];

// get module
$stmt = $pdo->prepare("SELECT * FROM modules WHERE id=?");
$stmt->execute([$module_id]);
$module = $stmt->fetch();

// get enrollment
$stmt = $pdo->prepare("
SELECT * FROM enrollments
WHERE user_id=? AND course_id=? AND status='active'
");

$stmt->execute([$user_id, $module['course_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    die("No active enrollment");
}

// check expiry (YOUR CODE INTEGRATION)
if (checkExpiry($enrollment)) {
    die("Access expired. Please renew course.");
}
?>

<h2><?= $module['title'] ?></h2>

<video width="700" controls>
    <source src="../stream.php?file=<?= $module['video'] ?>">
</video>

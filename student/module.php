<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireLogin();

$user_id = $_SESSION['user_id'];
$module_id = $_GET['id'];

// get module
$stmt = $pdo->prepare("
SELECT * FROM modules WHERE id=?
");
$stmt->execute([$module_id]);
$module = $stmt->fetch();

// check enrollment
$check = $pdo->prepare("
SELECT e.*
FROM enrollments e
JOIN courses c ON e.course_id = c.id
WHERE e.user_id=? AND e.course_id=?
AND e.payment_status='approved'
");

$check->execute([$user_id, $module['course_id']]);
if(!$check->fetch()){
    die("No access");
}
?>

<h2><?= $module['title'] ?></h2>

<!-- SECURE VIDEO STREAM -->
<video width="700" controls controlsList="nodownload">
    <source src="../stream.php?file=<?= $module['video'] ?>" type="video/mp4">
</video>

<br><br>

<a href="assignment.php?module_id=<?= $module['id'] ?>">
    View Assignment
</a>

<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";

requireLogin();

$user_id = $_SESSION['user_id'];
$course_id = $_GET['id'];

// check access
$check = $pdo->prepare("
SELECT * FROM enrollments
WHERE user_id=? AND course_id=? AND payment_status='approved'
");
$check->execute([$user_id, $course_id]);
$enrollment = $check->fetch();

if(!$enrollment){
    die("Access denied");
}

// fetch modules
$stmt = $pdo->prepare("
SELECT * FROM modules
WHERE course_id=?
ORDER BY module_order ASC
");
$stmt->execute([$course_id]);

$modules = $stmt->fetchAll();
?>

<h2>Course Modules</h2>

<?php foreach($modules as $m): ?>

<div style="border:1px solid #ccc; margin:10px; padding:10px;">
    <h3><?= $m['title'] ?></h3>

    <a href="module.php?id=<?= $m['id'] ?>">
        Open Module
    </a>
</div>

<?php endforeach; ?>

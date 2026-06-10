<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();

requireLogin();

$user_id = $_SESSION['user_id'];
$module_id = $_GET['id'];

// get module
$stmt = $pdo->prepare("SELECT * FROM modules WHERE id=?");
$stmt->execute([$module_id]);
$module = $stmt->fetch();

// check previous module completion
$check = $pdo->prepare("
SELECT s.status
FROM submissions s
JOIN assignments a ON s.assignment_id = a.id
JOIN modules m ON a.module_id = m.id
WHERE s.user_id=?
AND m.course_id=?
AND m.module_order < ?
AND s.status != 'passed'
");

$check->execute([
    $user_id,
    $module['course_id'],
    $module['module_order']
]);

if($check->fetch()){
    die("Complete previous modules first");
}
?>

<h2><?= $module['title'] ?></h2>

<video width="700" controls>
    <source src="../stream.php?file=<?= $module['video'] ?>">
</video>

<a href="assignment.php?module_id=<?= $module['id'] ?>">
    Go to Assignment
</a>

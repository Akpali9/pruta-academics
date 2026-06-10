<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireLogin();

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
SELECT 
m.title,
m.module_order,
COALESCE(s.status,'not submitted') AS status
FROM modules m
LEFT JOIN assignments a ON m.id = a.module_id
LEFT JOIN submissions s 
ON s.assignment_id = a.id AND s.user_id=?
WHERE m.course_id IN (
SELECT course_id FROM enrollments WHERE user_id=?
)
ORDER BY m.module_order
");

$stmt->execute([$user_id, $user_id]);

$data = $stmt->fetchAll();
?>

<h2>My Progress</h2>

<?php foreach($data as $d): ?>

<div style="padding:10px; border:1px solid #ccc;">
    <b><?= $d['title'] ?></b> - <?= $d['status'] ?>
</div>

<?php endforeach; ?>

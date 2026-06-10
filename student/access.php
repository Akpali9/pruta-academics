<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireLogin();

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT e.*, c.title
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id=?
");

$stmt->execute([$user_id]);
$enrollments = $stmt->fetchAll();
?>

<h2>My Course Access</h2>

<?php foreach($enrollments as $e): ?>

<div style="border:1px solid #ccc; margin:10px; padding:10px;">

<h3><?= $e['title'] ?></h3>

<p>Status: <?= $e['payment_status'] ?></p>

<?php if($e['payment_status'] == "approved"): ?>
    <p>Access Code: <?= $e['access_code'] ?></p>
    <p>Expires: <?= $e['expires_at'] ?></p>
<?php else: ?>
    <p>Waiting for admin approval...</p>
<?php endif; ?>

</div>

<?php endforeach; ?>

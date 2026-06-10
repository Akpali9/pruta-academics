<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireAdmin();

if(isset($_GET['pass'])){

$id = $_GET['pass'];

$pdo->prepare("
UPDATE submissions
SET status='passed'
WHERE id=?
")->execute([$id]);

echo "Marked Passed";
}

$subs = $pdo->query("
SELECT s.*, u.fullname
FROM submissions s
JOIN users u ON s.user_id=u.id
")->fetchAll();
?>

<h2>Submissions</h2>

<?php foreach($subs as $s): ?>

<div style="border:1px solid #ccc; margin:10px; padding:10px;">

<p><?= $s['fullname'] ?></p>
<p><?= $s['answer'] ?></p>

<a href="?pass=<?= $s['id'] ?>">Mark Pass</a>

</div>

<?php endforeach; ?>

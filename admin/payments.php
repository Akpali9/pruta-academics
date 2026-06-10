<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireAdmin();

if(isset($_GET['approve'])){

    $id = $_GET['approve'];

    $code = strtoupper(bin2hex(random_bytes(4)));

    $expiry = date('Y-m-d H:i:s', strtotime('+3 months'));

    $stmt = $pdo->prepare("
        UPDATE enrollments
        SET payment_status='approved',
        status='active',
        access_code=?,
        expires_at=?
        WHERE id=?
    ");

    $stmt->execute([$code, $expiry, $id]);

    echo "Approved";
}

if(isset($_GET['decline'])){

    $id = $_GET['decline'];

    $stmt = $pdo->prepare("
        UPDATE enrollments
        SET payment_status='declined'
        WHERE id=?
    ");

    $stmt->execute([$id]);

    echo "Declined";
}

$pending = $pdo->query("
    SELECT e.*, u.fullname, c.title
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.payment_status='pending'
")->fetchAll();
?>

<h2>Payment Approvals</h2>

<?php foreach($pending as $p): ?>

<div style="border:1px solid #ccc; margin:10px; padding:10px;">

<p><b>Student:</b> <?= $p['fullname'] ?></p>
<p><b>Course:</b> <?= $p['title'] ?></p>

<a href="?approve=<?= $p['id'] ?>">Approve</a> |
<a href="?decline=<?= $p['id'] ?>">Decline</a>

</div>

<?php endforeach; ?>

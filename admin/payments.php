<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/mailer.php";

requireAdmin();

/**
 * APPROVE PAYMENT
 */
if (isset($_GET['approve'])) {

    $id = (int) $_GET['approve'];

    // prevent re-approval
    $stmt = $pdo->prepare("
        SELECT * FROM enrollments
        WHERE id=? AND payment_status='pending'
    ");
    $stmt->execute([$id]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        die("Invalid or already processed request.");
    }

    // generate access code
    $code = strtoupper(bin2hex(random_bytes(4)));

    // set expiry (3 months)
    $expiry = date('Y-m-d H:i:s', strtotime('+3 months'));

    // update enrollment
    $stmt = $pdo->prepare("
        UPDATE enrollments
        SET payment_status='approved',
            status='active',
            access_code=?,
            expires_at=?
        WHERE id=?
    ");

    $stmt->execute([$code, $expiry, $id]);

    /**
     * SEND EMAIL NOTIFICATION
     */
    $stmt = $pdo->prepare("
        SELECT u.email, c.title
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN courses c ON e.course_id = c.id
        WHERE e.id=?
    ");

    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if ($user) {

        $message = "
Hello,

Your course payment has been approved.

Course: {$user['title']}
Access Code: {$code}

Your access is valid for 3 months.

Login to start learning.
        ";

        sendMail(
            $user['email'],
            "Course Approved -  Access Granted",
            $message
        );
    }

    echo "<script>alert('Approved successfully');window.location.href='payments.php';</script>";
    exit;
}

/**
 * DECLINE PAYMENT
 */
if (isset($_GET['decline'])) {

    $id = (int) $_GET['decline'];

    $stmt = $pdo->prepare("
        SELECT * FROM enrollments
        WHERE id=? AND payment_status='pending'
    ");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        die("Invalid or already processed request.");
    }

    $stmt = $pdo->prepare("
        UPDATE enrollments
        SET payment_status='declined',
            status='inactive'
        WHERE id=?
    ");

    $stmt->execute([$id]);

    echo "<script>alert('Declined successfully');window.location.href='payments.php';</script>";
    exit;
}

/**
 * FETCH PENDING PAYMENTS
 */
$stmt = $pdo->query("
    SELECT e.*, u.fullname, c.title
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN courses c ON e.course_id = c.id
    WHERE e.payment_status='pending'
    ORDER BY e.id DESC
");

$pending = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Approvals</title>
    <style>
        body { font-family: Arial; background:#f4f4f4; }
        .card { background:#fff; padding:15px; margin:10px; border-radius:8px; }
        a { text-decoration:none; margin-right:10px; }
        .approve { color:green; }
        .decline { color:red; }
    </style>
</head>
<body>

<h2>Payment Approvals</h2>

<?php if (count($pending) === 0): ?>
    <p>No pending payments.</p>
<?php endif; ?>

<?php foreach ($pending as $p): ?>

<div class="card">

    <p><b>Student:</b> <?= htmlspecialchars($p['fullname']) ?></p>
    <p><b>Course:</b> <?= htmlspecialchars($p['title']) ?></p>

    <p><b>Receipt:</b></p>
    <a href="../<?= htmlspecialchars($p['receipt']) ?>" target="_blank">
        View Receipt
    </a>

    <br><br>

    <a class="approve" href="?approve=<?= $p['id'] ?>"
       onclick="return confirm('Approve this payment?')">
        Approve
    </a>

    <a class="decline" href="?decline=<?= $p['id'] ?>"
       onclick="return confirm('Decline this payment?')">
        Decline
    </a>

</div>

<?php endforeach; ?>

</body>
</html>

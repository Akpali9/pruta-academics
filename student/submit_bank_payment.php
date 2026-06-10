<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/secure.php";
enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();

requireLogin();

$user_id = $_SESSION['user_id'];

$course_id = $_POST['course_id'];
$ref = $_POST['bank_reference'];

// upload receipt
$file = time() . "_" . $_FILES['receipt']['name'];
$tmp = $_FILES['receipt']['tmp_name'];

$path = "../uploads/receipts/" . $file;

move_uploaded_file($tmp, $path);

// save enrollment (pending approval)
$stmt = $pdo->prepare("
INSERT INTO enrollments
(user_id, course_id, payment_status, receipt, bank_reference)
VALUES (?,?,?,?,?)
");

$stmt->execute([
    $user_id,
    $course_id,
    'pending',
    $path,
    $ref
]);

echo "Payment submitted. Await admin confirmation.";

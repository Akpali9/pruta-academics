<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();

requireLogin();

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $user_id = $_SESSION['user_id'];
    $course_id = $_POST['course_id'];

    $file = $_FILES['receipt']['name'];
    $tmp = $_FILES['receipt']['tmp_name'];

    $path = "../uploads/receipts/" . time() . "_" . $file;

    move_uploaded_file($tmp, $path);

    $stmt = $pdo->prepare("
        INSERT INTO enrollments
        (user_id, course_id, receipt, payment_status)
        VALUES (?,?,?, 'pending')
    ");

    $stmt->execute([
        $user_id,
        $course_id,
        $path
    ]);

    echo "Receipt uploaded successfully. Await admin approval.";
}

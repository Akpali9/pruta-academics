<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: courses.php");
    exit();
}

$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$transaction_id = isset($_POST['transaction_id']) ? sanitizeInput($_POST['transaction_id']) : '';

// Validate course
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    die("Course not found");
}

// Check if already enrolled
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$_SESSION['user_id'], $course_id]);
if ($stmt->rowCount() > 0) {
    header("Location: course.php?id=" . $course_id . "&msg=already_enrolled");
    exit();
}

// Handle file upload
if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    header("Location: course.php?id=" . $course_id . "&error=upload_failed");
    exit();
}

$file = $_FILES['receipt'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
$maxSize = 5 * 1024 * 1024; // 5MB

if (!in_array($file['type'], $allowedTypes)) {
    header("Location: course.php?id=" . $course_id . "&error=invalid_type");
    exit();
}

if ($file['size'] > $maxSize) {
    header("Location: course.php?id=" . $course_id . "&error=file_too_large");
    exit();
}

// Create uploads directory if not exists
$uploadDir = "../uploads/receipts/";
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = "receipt_" . $_SESSION['user_id'] . "_" . $course_id . "_" . time() . "." . $extension;
$filepath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO payment_receipts (user_id, course_id, receipt_path, transaction_id, status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $course_id, $filepath, $transaction_id]);
    
    header("Location: course.php?id=" . $course_id . "&msg=receipt_uploaded");
} else {
    header("Location: course.php?id=" . $course_id . "&error=upload_failed");
}
exit();
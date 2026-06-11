<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
$progress = isset($_POST['progress']) ? (int)$_POST['progress'] : 0;

// Update progress in enrollments table
$stmt = $pdo->prepare("UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?");
$stmt->execute([$progress, $user_id, $course_id]);

echo json_encode(['success' => true]);
?>
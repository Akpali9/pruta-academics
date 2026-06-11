<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;

// Mark course as completed
$stmt = $pdo->prepare("
    UPDATE enrollments 
    SET status = 'completed', 
        completed_at = NOW(),
        progress = 100 
    WHERE user_id = ? AND course_id = ? AND status = 'active'
");
$stmt->execute([$user_id, $course_id]);

// Check if this is the first time completion (send notification only once)
$stmt = $pdo->prepare("
    SELECT completion_notified FROM enrollments 
    WHERE user_id = ? AND course_id = ?
");
$stmt->execute([$user_id, $course_id]);
$enrollment = $stmt->fetch();

if (!$enrollment['completion_notified']) {
    // Get user and course details
    $stmt = $pdo->prepare("
        SELECT u.fullname, u.email, c.title, c.instructor_id, c.id as course_id
        FROM users u, courses c
        WHERE u.id = ? AND c.id = ?
    ");
    $stmt->execute([$user_id, $course_id]);
    $details = $stmt->fetch();
    
    // Mark as notified to prevent multiple notifications
    $stmt = $pdo->prepare("UPDATE enrollments SET completion_notified = 1 WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    
    // Get admin emails
    $admins = $pdo->query("SELECT email, fullname FROM users WHERE role = 'admin'")->fetchAll();
    
    // Create notification message
    $notification = "
        <h2>Course Completion Notification</h2>
        <p><strong>Student:</strong> {$details['fullname']}</p>
        <p><strong>Email:</strong> {$details['email']}</p>
        <p><strong>Course:</strong> {$details['title']}</p>
        <p><strong>Completed on:</strong> " . date('F j, Y g:i A') . "</p>
        <hr>
        <p><strong>Action Required:</strong> Please review the student's completion and send certificate if applicable.</p>
        <p><a href='http://localhost/pruta/admin/completion-requests.php'>View Completion Requests</a></p>
    ";
    
    // Send notification to all admins
    foreach ($admins as $admin) {
        // Log email (since actual email sending might not be configured)
        $logFile = __DIR__ . '/../logs/completion_notifications.log';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] To: {$admin['email']} | Student: {$details['fullname']} | Course: {$details['title']}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

echo json_encode(['success' => true]);
?>
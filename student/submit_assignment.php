<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assignment_id = (int)$_POST['assignment_id'];
    $module_id = (int)$_POST['module_id'];
    $answer = trim($_POST['answer']);
    
    // Handle file attachment
    $attachment = '';
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'image/jpeg', 'image/png'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = mime_content_type($_FILES['attachment']['tmp_name']);
        
        if (in_array($fileType, $allowed) && $_FILES['attachment']['size'] <= $maxSize) {
            $upload_dir = "../uploads/submissions/";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $filename = 'submission_' . $user_id . '_' . time() . '.' . $ext;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $filename)) {
                $attachment = "uploads/submissions/" . $filename;
            }
        }
    }
    
    // Check if already submitted
    $check = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id = ? AND user_id = ?");
    $check->execute([$assignment_id, $user_id]);
    
    if ($check->rowCount() > 0) {
        // Update existing submission
        $stmt = $pdo->prepare("
            UPDATE submissions SET answer = ?, attachments = ?, submitted_at = NOW() 
            WHERE assignment_id = ? AND user_id = ?
        ");
        $stmt->execute([$answer, $attachment, $assignment_id, $user_id]);
        $message = "Assignment updated successfully!";
    } else {
        // Insert new submission
        $stmt = $pdo->prepare("
            INSERT INTO submissions (assignment_id, user_id, answer, attachments, status, submitted_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$assignment_id, $user_id, $answer, $attachment]);
        $message = "Assignment submitted successfully!";
    }
    
    // Redirect back to module
    header("Location: learn.php?id=" . $module_id . "&submitted=1");
    exit;
}
?>
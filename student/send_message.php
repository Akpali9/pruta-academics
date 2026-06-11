<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$user_id = $_SESSION['user_id'];
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int)$_POST['course_id'];
    $instructor_id = isset($_POST['instructor_id']) ? (int)$_POST['instructor_id'] : 0;
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $module_id = (int)($_POST['module_id'] ?? 1);
    
    // Validate inputs
    if (empty($subject)) {
        $error = "Subject is required.";
    } elseif (empty($message)) {
        $error = "Message content is required.";
    } else {
        // If instructor_id is not provided or invalid, get a default admin
        if ($instructor_id <= 0) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            $defaultAdmin = $stmt->fetch();
            if ($defaultAdmin) {
                $instructor_id = $defaultAdmin['id'];
            } else {
                $error = "No instructor found. Please contact support.";
            }
        }
        
        // Verify instructor exists
        if (!$error) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin'");
            $stmt->execute([$instructor_id]);
            if (!$stmt->fetch()) {
                // Get a default admin as instructor
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $stmt->execute();
                $defaultAdmin = $stmt->fetch();
                if ($defaultAdmin) {
                    $instructor_id = $defaultAdmin['id'];
                } else {
                    $error = "No instructor available. Please contact support.";
                }
            }
        }
    }
    
    if (!$error) {
        // Handle attachment
        $attachment = '';
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = "../uploads/messages/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            $fileType = mime_content_type($_FILES['attachment']['tmp_name']);
            if (in_array($fileType, $allowed) && $_FILES['attachment']['size'] <= $maxSize) {
                $filename = 'msg_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['attachment']['name']);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $filename)) {
                    $attachment = "uploads/messages/" . $filename;
                }
            }
        }
        
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (course_id, user_id, instructor_id, subject, message, attachment, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$course_id, $user_id, $instructor_id, $subject, $message, $attachment]);
        
        $success = true;
        header("Location: learn.php?id=" . $module_id . "&msg=sent");
        exit;
    }
}

// If error, go back with error message
if ($error) {
    header("Location: learn.php?id=" . ($_POST['module_id'] ?? 1) . "&error=" . urlencode($error));
    exit;
}
?>
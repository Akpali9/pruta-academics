<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$module_id = isset($_POST['module_id']) ? (int)$_POST['module_id'] : 0;
$progress = isset($_POST['progress']) ? (int)$_POST['progress'] : 0;

if ($module_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid module ID']);
    exit;
}

// Ensure progress is between 0 and 100
$progress = min(100, max(0, $progress));

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_id INT NOT NULL,
        progress INT DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed TINYINT DEFAULT 0,
        completed_at DATETIME NULL,
        UNIQUE KEY unique_user_module (user_id, module_id)
    )");
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM user_progress WHERE user_id = ? AND module_id = ?");
    $stmt->execute([$user_id, $module_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing
        $stmt = $pdo->prepare("UPDATE user_progress SET progress = ?, last_updated = NOW() WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$progress, $user_id, $module_id]);
    } else {
        // Insert new
        $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, module_id, progress, last_updated) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $module_id, $progress]);
    }
    
    // If progress is 100%, mark as completed
    if ($progress >= 100) {
        $stmt = $pdo->prepare("UPDATE user_progress SET completed = 1, completed_at = NOW() WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$user_id, $module_id]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
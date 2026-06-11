<?php
session_start();
require_once "../config/database.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['progress' => 0, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$module_id = isset($_GET['module_id']) ? (int)$_GET['module_id'] : 0;

if ($module_id <= 0) {
    echo json_encode(['progress' => 0, 'error' => 'Invalid module ID']);
    exit;
}

// Check if table exists, if not create it
try {
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
} catch (PDOException $e) {
    echo json_encode(['progress' => 0, 'error' => 'Table creation failed']);
    exit;
}

$stmt = $pdo->prepare("SELECT progress FROM user_progress WHERE user_id = ? AND module_id = ?");
$stmt->execute([$user_id, $module_id]);
$progress = $stmt->fetch();

echo json_encode(['progress' => $progress['progress'] ?? 0]);
?>
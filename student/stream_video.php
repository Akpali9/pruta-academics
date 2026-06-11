<?php
session_start();
require_once "../config/database.php";

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$module_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Verify user has access to this module
$stmt = $pdo->prepare("
    SELECT m.video, m.course_id, e.payment_status, e.status, e.expires_at
    FROM modules m
    JOIN courses c ON m.course_id = c.id
    JOIN enrollments e ON c.id = e.course_id
    WHERE m.id = ? AND e.user_id = ? AND e.payment_status = 'approved' AND e.status = 'active'
");
$stmt->execute([$module_id, $user_id]);
$module = $stmt->fetch();

if (!$module) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// Check expiry
if (!empty($module['expires_at']) && strtotime($module['expires_at']) < time()) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

$video_path = "../uploads/videos/" . $module['video'];

if (!file_exists($video_path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Generate a unique token for this session
$token = md5($user_id . $module_id . session_id() . date('Y-m-d H'));
$_SESSION['video_token'] = $token;

// Enhanced security headers to prevent caching and downloading
header("Cache-Control: no-cache, no-store, must-revalidate, private");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: video/mp4");
header("Content-Disposition: inline; filename=\"stream.mp4\"");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Log video access
$stmt = $pdo->prepare("
    INSERT INTO video_access_logs (user_id, module_id, ip_address, user_agent, accessed_at) 
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([$user_id, $module_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

// Stream the video with partial content support but limited
$file_size = filesize($video_path);
$fp = fopen($video_path, 'rb');

// Handle range requests (for seeking)
$start = 0;
$end = $file_size - 1;

if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $range_parts = explode('-', $range);
    $start = (int)$range_parts[0];
    if (isset($range_parts[1]) && !empty($range_parts[1])) {
        $end = (int)$range_parts[1];
    }
    header('HTTP/1.1 206 Partial Content');
} else {
    header('HTTP/1.1 200 OK');
}

header("Content-Range: bytes $start-$end/$file_size");
header("Content-Length: " . ($end - $start + 1));

// Stream the video
fseek($fp, $start);
$buffer_size = 8192;
while (!feof($fp) && ($p = ftell($fp)) <= $end) {
    if ($p + $buffer_size > $end) {
        $buffer_size = $end - $p + 1;
    }
    echo fread($fp, $buffer_size);
    flush();
}
fclose($fp);
exit;
?>
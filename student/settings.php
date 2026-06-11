<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/utilities.php";

securePage();
requireLogin();

$userId = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile Settings
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $fullname = sanitizeInput($_POST['fullname']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $bio = sanitizeInput($_POST['bio'] ?? '');
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Invalid email format";
            $messageType = "error";
        } else {
            // Check if email exists for other users
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $userId]);
            if ($check->rowCount() > 0) {
                $message = "Email already exists for another user";
                $messageType = "error";
            } else {
                $update = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, bio = ? WHERE id = ?");
                if ($update->execute([$fullname, $email, $phone, $bio, $userId])) {
                    $_SESSION['fullname'] = $fullname;
                    $_SESSION['email'] = $email;
                    $message = "Profile updated successfully";
                    $messageType = "success";
                    
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                } else {
                    $message = "Failed to update profile";
                    $messageType = "error";
                }
            }
        }
    }
    
    // Change Password
    elseif (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password'])) {
            $message = "Current password is incorrect";
            $messageType = "error";
        } elseif (strlen($newPassword) < 8) {
            $message = "New password must be at least 8 characters";
            $messageType = "error";
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $message = "Password must contain uppercase, lowercase, and number";
            $messageType = "error";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "New passwords do not match";
            $messageType = "error";
        } else {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($update->execute([$hashedPassword, $userId])) {
                $message = "Password changed successfully";
                $messageType = "success";
            } else {
                $message = "Failed to change password";
                $messageType = "error";
            }
        }
    }
    
    // Update Notification Settings
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_notifications') {
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        $courseUpdates = isset($_POST['course_updates']) ? 1 : 0;
        $promotionalEmails = isset($_POST['promotional_emails']) ? 1 : 0;
        
        // Check if notification settings table exists, create if not
        try {
            $createTable = "
                CREATE TABLE IF NOT EXISTS user_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL UNIQUE,
                    email_notifications BOOLEAN DEFAULT 1,
                    course_updates BOOLEAN DEFAULT 1,
                    promotional_emails BOOLEAN DEFAULT 0,
                    theme_preference VARCHAR(20) DEFAULT 'light',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ";
            $pdo->exec($createTable);
            
            // Insert or update settings
            $update = $pdo->prepare("
                INSERT INTO user_settings (user_id, email_notifications, course_updates, promotional_emails, updated_at) 
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                email_notifications = VALUES(email_notifications),
                course_updates = VALUES(course_updates),
                promotional_emails = VALUES(promotional_emails),
                updated_at = NOW()
            ");
            $update->execute([$userId, $emailNotifications, $courseUpdates, $promotionalEmails]);
            
            $message = "Notification settings updated successfully";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Failed to update notification settings";
            $messageType = "error";
            error_log("Notification settings error: " . $e->getMessage());
        }
    }
    
    // Update Privacy Settings
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_privacy') {
        $profileVisibility = $_POST['profile_visibility'] ?? 'public';
        $showEmail = isset($_POST['show_email']) ? 1 : 0;
        $showProgress = isset($_POST['show_progress']) ? 1 : 0;
        
        // Update privacy settings in database
        $update = $pdo->prepare("
            UPDATE users SET 
            profile_visibility = ?,
            show_email = ?,
            show_progress = ?
            WHERE id = ?
        ");
        
        // Add columns if they don't exist
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_visibility VARCHAR(20) DEFAULT 'public'");
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS show_email BOOLEAN DEFAULT 1");
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS show_progress BOOLEAN DEFAULT 1");
        } catch (PDOException $e) {
            // Columns might already exist
        }
        
        if ($update->execute([$profileVisibility, $showEmail, $showProgress, $userId])) {
            $message = "Privacy settings updated successfully";
            $messageType = "success";
        } else {
            $message = "Failed to update privacy settings";
            $messageType = "error";
        }
    }
    
    // Handle Account Deletion Request
    elseif (isset($_POST['action']) && $_POST['action'] === 'request_deletion') {
        $reason = sanitizeInput($_POST['deletion_reason'] ?? 'Not specified');
        
        // Create account deletion request
        try {
            $createTable = "
                CREATE TABLE IF NOT EXISTS account_deletion_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    reason TEXT,
                    status VARCHAR(20) DEFAULT 'pending',
                    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    processed_at DATETIME,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ";
            $pdo->exec($createTable);
            
            $insert = $pdo->prepare("
                INSERT INTO account_deletion_requests (user_id, reason, requested_at) 
                VALUES (?, ?, NOW())
            ");
            $insert->execute([$userId, $reason]);
            
            $message = "Deletion request submitted. Our team will contact you within 48 hours.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Failed to submit deletion request";
            $messageType = "error";
            error_log("Deletion request error: " . $e->getMessage());
        }
    }
}

// Get user settings
$userSettings = [
    'email_notifications' => 1,
    'course_updates' => 1,
    'promotional_emails' => 0
];

try {
    $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    if ($settings) {
        $userSettings = $settings;
    }
} catch (PDOException $e) {
    // Table might not exist yet
}

// Get user privacy settings
$privacySettings = [
    'profile_visibility' => $user['profile_visibility'] ?? 'public',
    'show_email' => $user['show_email'] ?? 1,
    'show_progress' => $user['show_progress'] ?? 1
];

// Get active sessions
$activeSessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? AND is_active = 1 
        ORDER BY last_activity DESC
    ");
    $stmt->execute([$userId]);
    $activeSessions = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | LearnHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            transition: all 0.3s ease;
        }

        body.dark {
            background: #0f172a;
        }

        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        body.dark .navbar {
            background: #1e293b;
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            position: relative;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo i {
            background: none;
            color: #667eea;
        }

        /* Desktop Navigation */
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        body.dark .nav-links a {
            color: #cbd5e1;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #667eea;
            transform: translateY(-2px);
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: #475569;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        body.dark .mobile-menu-btn {
            color: #cbd5e1;
        }

        .mobile-menu-btn:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* Mobile Navigation Dropdown */
        .mobile-nav {
            display: none;
            position: absolute;
            top: 70px;
            left: 0;
            right: 0;
            background: white;
            flex-direction: column;
            padding: 20px;
            gap: 15px;
            border-top: 1px solid #e2e8f0;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            z-index: 999;
        }

        body.dark .mobile-nav {
            background: #1e293b;
            border-top-color: #334155;
        }

        .mobile-nav a {
            text-decoration: none;
            color: #475569;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        body.dark .mobile-nav a {
            color: #cbd5e1;
        }

        .mobile-nav a:hover {
            background: #f1f5f9;
            color: #667eea;
        }

        body.dark .mobile-nav a:hover {
            background: #0f172a;
        }

        .mobile-nav .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white !important;
            justify-content: center;
            margin-top: 10px;
        }

        .mobile-nav .logout-btn:hover {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s ease;
            color: #475569;
        }

        body.dark .theme-toggle {
            color: #cbd5e1;
        }

        .theme-toggle:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: rotate(15deg);
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white !important;
            padding: 8px 20px;
            border-radius: 8px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .page-header h1 {
            font-size: 32px;
            color: #1e293b;
            margin-bottom: 10px;
        }

        body.dark .page-header h1 {
            color: #f1f5f9;
        }

        .page-header p {
            color: #64748b;
            font-size: 16px;
        }

        body.dark .page-header p {
            color: #94a3b8;
        }

        /* Settings Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Sidebar */
        .settings-sidebar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        body.dark .settings-sidebar {
            background: #1e293b;
        }

        .settings-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .settings-nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: #475569;
        }

        body.dark .settings-nav-item {
            color: #cbd5e1;
        }

        .settings-nav-item i {
            width: 20px;
        }

        .settings-nav-item:hover {
            background: #f1f5f9;
            color: #667eea;
        }

        body.dark .settings-nav-item:hover {
            background: #0f172a;
        }

        .settings-nav-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Settings Content */
        .settings-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            animation: fadeIn 0.3s ease;
        }

        body.dark .settings-content {
            background: #1e293b;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .settings-section {
            display: none;
        }

        .settings-section.active {
            display: block;
        }

        .settings-section h2 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #1e293b;
        }

        body.dark .settings-section h2 {
            color: #f1f5f9;
        }

        .settings-section .section-desc {
            color: #64748b;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        body.dark .settings-section .section-desc {
            color: #94a3b8;
            border-bottom-color: #334155;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
        }

        body.dark .form-group label {
            color: #cbd5e1;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        body.dark .form-group input,
        body.dark .form-group select,
        body.dark .form-group textarea {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* Checkbox Toggle */
        .toggle-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        body.dark .toggle-switch {
            border-bottom-color: #334155;
        }

        .toggle-label {
            flex: 1;
        }

        .toggle-label h4 {
            margin-bottom: 5px;
            color: #1e293b;
        }

        body.dark .toggle-label h4 {
            color: #f1f5f9;
        }

        .toggle-label p {
            font-size: 12px;
            color: #64748b;
        }

        .toggle-checkbox {
            position: relative;
            width: 50px;
            height: 24px;
        }

        .toggle-checkbox input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.3s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #667eea;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* Button Styles */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        body.dark .btn-secondary {
            background: #334155;
            color: #cbd5e1;
        }

        .form-actions {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        body.dark .form-actions {
            border-top-color: #334155;
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        body.dark .alert-success {
            background: rgba(22, 163, 74, 0.2);
            color: #86efac;
        }

        body.dark .alert-error {
            background: rgba(220, 38, 38, 0.2);
            color: #fca5a5;
        }

        /* Session Items */
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        body.dark .session-item {
            background: #0f172a;
        }

        .session-info .device {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .session-info .details {
            font-size: 12px;
            color: #64748b;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* Danger Zone */
        .danger-zone {
            margin-top: 30px;
            padding: 20px;
            background: #fef2f2;
            border-radius: 10px;
            border: 1px solid #fecaca;
        }

        body.dark .danger-zone {
            background: rgba(220, 38, 38, 0.1);
            border-color: rgba(220, 38, 38, 0.3);
        }

        .danger-zone h3 {
            color: #dc2626;
            margin-bottom: 10px;
        }

        .danger-zone p {
            color: #64748b;
            margin-bottom: 15px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .settings-layout {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .session-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
                <span>LearnHub</span>
            </div>
            
            <!-- Desktop Navigation -->
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="courses.php"><i class="fas fa-book"></i> All Courses</a>
                <a href="my-courses.php"><i class="fas fa-play-circle"></i> My Courses</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
                <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation Dropdown -->
        <div class="mobile-nav" id="mobileNav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="courses.php"><i class="fas fa-book"></i> All Courses</a>
            <a href="my-courses.php"><i class="fas fa-play-circle"></i> My Courses</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 12px; gap: 15px;">
                <button class="theme-toggle" id="mobileThemeToggle" style="background: none; border: none; font-size: 20px; cursor: pointer;">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="../logout.php" class="logout-btn" style="flex: 1; text-align: center;">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your account preferences and security settings</p>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <div class="settings-layout">
            <!-- Sidebar Navigation -->
            <div class="settings-sidebar">
                <div class="settings-nav">
                    <div class="settings-nav-item active" data-section="profile">
                        <i class="fas fa-user-circle"></i>
                        <span>Profile Settings</span>
                    </div>
                    <div class="settings-nav-item" data-section="security">
                        <i class="fas fa-lock"></i>
                        <span>Security</span>
                    </div>
                    <div class="settings-nav-item" data-section="notifications">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </div>
                    <div class="settings-nav-item" data-section="privacy">
                        <i class="fas fa-shield-alt"></i>
                        <span>Privacy</span>
                    </div>
                    <div class="settings-nav-item" data-section="sessions">
                        <i class="fas fa-desktop"></i>
                        <span>Active Sessions</span>
                    </div>
                    <div class="settings-nav-item" data-section="danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Danger Zone</span>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- Profile Settings -->
                <div class="settings-section active" id="profile-section">
                    <h2>Profile Settings</h2>
                    <p class="section-desc">Update your personal information and public profile</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Bio (Optional)</label>
                            <textarea name="bio" rows="4" placeholder="Tell us a little about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Settings -->
                <div class="settings-section" id="security-section">
                    <h2>Security Settings</h2>
                    <p class="section-desc">Change your password and enhance account security</p>
                    
                    <form method="POST" action="" id="passwordForm">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" name="new_password" id="new_password" required>
                                <small style="color: #94a3b8;">Minimum 8 characters with uppercase, lowercase, and number</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" required>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Notification Settings -->
                <div class="settings-section" id="notifications-section">
                    <h2>Notification Preferences</h2>
                    <p class="section-desc">Choose what email notifications you want to receive</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <h4>Email Notifications</h4>
                                <p>Receive important updates about your account</p>
                            </div>
                            <label class="toggle-checkbox">
                                <input type="checkbox" name="email_notifications" <?= $userSettings['email_notifications'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <h4>Course Updates</h4>
                                <p>Get notified about new lessons and course materials</p>
                            </div>
                            <label class="toggle-checkbox">
                                <input type="checkbox" name="course_updates" <?= $userSettings['course_updates'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <h4>Promotional Emails</h4>
                                <p>Receive offers, news, and updates about new courses</p>
                            </div>
                            <label class="toggle-checkbox">
                                <input type="checkbox" name="promotional_emails" <?= $userSettings['promotional_emails'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Privacy Settings -->
                <div class="settings-section" id="privacy-section">
                    <h2>Privacy Settings</h2>
                    <p class="section-desc">Control who can see your profile information</p>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_privacy">
                        
                        <div class="form-group">
                            <label>Profile Visibility</label>
                            <select name="profile_visibility">
                                <option value="public" <?= $privacySettings['profile_visibility'] === 'public' ? 'selected' : '' ?>>Public - Anyone can see my profile</option>
                                <option value="private" <?= $privacySettings['profile_visibility'] === 'private' ? 'selected' : '' ?>>Private - Only me</option>
                                <option value="enrolled" <?= $privacySettings['profile_visibility'] === 'enrolled' ? 'selected' : '' ?>>Enrolled Students Only</option>
                            </select>
                        </div>
                        
                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <h4>Show Email Address</h4>
                                <p>Display your email on your public profile</p>
                            </div>
                            <label class="toggle-checkbox">
                                <input type="checkbox" name="show_email" <?= $privacySettings['show_email'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <h4>Show Course Progress</h4>
                                <p>Allow others to see your learning progress</p>
                            </div>
                            <label class="toggle-checkbox">
                                <input type="checkbox" name="show_progress" <?= $privacySettings['show_progress'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Privacy Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Active Sessions -->
                <div class="settings-section" id="sessions-section">
                    <h2>Active Sessions</h2>
                    <p class="section-desc">Manage where you're logged in</p>
                    
                    <?php if(empty($activeSessions)): ?>
                        <p style="color: #64748b; text-align: center; padding: 40px;">
                            No active sessions found
                        </p>
                    <?php else: ?>
                        <?php foreach($activeSessions as $session): ?>
                        <div class="session-item">
                            <div class="session-info">
                                <div class="device">
                                    <i class="fas fa-<?= strpos($session['user_agent'], 'Mobile') !== false ? 'mobile-alt' : 'desktop' ?>"></i>
                                    <?= $session['user_agent'] ? 'Current Device' : 'Unknown Device' ?>
                                </div>
                                <div class="details">
                                    IP: <?= htmlspecialchars($session['ip_address'] ?? 'Unknown') ?> • 
                                    Last active: <?= date('M d, Y h:i A', strtotime($session['last_activity'])) ?>
                                </div>
                            </div>
                            <button class="btn btn-danger btn-sm" onclick="terminateSession(<?= $session['id'] ?>)">
                                <i class="fas fa-sign-out-alt"></i> Terminate
                            </button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Danger Zone -->
                <div class="settings-section" id="danger-section">
                    <h2>Danger Zone</h2>
                    <p class="section-desc">Irreversible account actions</p>
                    
                    <div class="danger-zone">
                        <h3><i class="fas fa-exclamation-triangle"></i> Delete Account</h3>
                        <p>Once you delete your account, all your data including courses, progress, and certificates will be permanently removed. This action cannot be undone.</p>
                        
                        <form method="POST" action="" onsubmit="return confirmDeletion()">
                            <input type="hidden" name="action" value="request_deletion">
                            <div class="form-group">
                                <label>Reason for deletion (optional)</label>
                                <textarea name="deletion_reason" rows="3" placeholder="Please tell us why you're leaving..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Request Account Deletion
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle for Desktop
        const themeToggle = document.getElementById('themeToggle');
        const mobileThemeToggle = document.getElementById('mobileThemeToggle');
        const body = document.body;
        
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else {
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        }
        
        function toggleTheme() {
            body.classList.toggle('dark');
            if (body.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        }
        
        themeToggle.addEventListener('click', toggleTheme);
        if (mobileThemeToggle) {
            mobileThemeToggle.addEventListener('click', toggleTheme);
        }

        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileNav = document.getElementById('mobileNav');
        
        mobileMenuBtn.addEventListener('click', function() {
            if (mobileNav.style.display === 'flex') {
                mobileNav.style.display = 'none';
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            } else {
                mobileNav.style.display = 'flex';
                mobileMenuBtn.innerHTML = '<i class="fas fa-times"></i>';
            }
        });
        
        // Close mobile menu when clicking a link
        const mobileLinks = mobileNav.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                mobileNav.style.display = 'none';
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            });
        });
        
        if (mobileThemeToggle) {
            mobileThemeToggle.addEventListener('click', function() {
                mobileNav.style.display = 'none';
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            });
        }

        // Settings Navigation
        const navItems = document.querySelectorAll('.settings-nav-item');
        const sections = document.querySelectorAll('.settings-section');
        
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                const sectionId = item.dataset.section;
                
                navItems.forEach(nav => nav.classList.remove('active'));
                item.classList.add('active');
                
                sections.forEach(section => section.classList.remove('active'));
                document.getElementById(`${sectionId}-section`).classList.add('active');
            });
        });

        // Password Form Validation
        const passwordForm = document.getElementById('passwordForm');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    return false;
                }
                
                const hasUpper = /[A-Z]/.test(newPassword);
                const hasLower = /[a-z]/.test(newPassword);
                const hasNumber = /[0-9]/.test(newPassword);
                
                if (!hasUpper || !hasLower || !hasNumber) {
                    e.preventDefault();
                    alert('Password must contain at least one uppercase letter, one lowercase letter, and one number!');
                    return false;
                }
            });
        }

        // Confirm Account Deletion
        function confirmDeletion() {
            return confirm('WARNING: This will permanently delete your account and all associated data. This action cannot be undone. Are you absolutely sure?');
        }

        function terminateSession(sessionId) {
            if (confirm('Are you sure you want to terminate this session?')) {
                fetch('terminate_session.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'session_id=' + sessionId
                }).then(response => {
                    if (response.ok) location.reload();
                });
            }
        }
    </script>
</body>
</html>
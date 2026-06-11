<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/utilities.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$userId = $_SESSION['user_id'];
$message = "";
$messageType = "";

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update profile information
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $fullname = sanitizeInput($_POST['fullname']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $bio = sanitizeInput($_POST['bio'] ?? '');
        $location = sanitizeInput($_POST['location'] ?? '');
        $website = sanitizeInput($_POST['website'] ?? '');
        $occupation = sanitizeInput($_POST['occupation'] ?? '');
        
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
                $update = $pdo->prepare("
                    UPDATE users SET 
                        fullname = ?, 
                        email = ?, 
                        phone = ?, 
                        bio = ?, 
                        location = ?, 
                        website = ?, 
                        occupation = ? 
                    WHERE id = ?
                ");
                if ($update->execute([$fullname, $email, $phone, $bio, $location, $website, $occupation, $userId])) {
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
    
    // Handle avatar upload
    elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        $fileType = mime_content_type($_FILES['avatar']['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $message = "Invalid file type. Please upload JPG, PNG, or GIF";
            $messageType = "error";
        } elseif ($_FILES['avatar']['size'] > $maxSize) {
            $message = "File too large. Max 2MB";
            $messageType = "error";
        } else {
            // Create uploads directory if not exists
            $uploadDir = __DIR__ . "/../uploads/avatars/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Delete old avatar if exists
            if (!empty($user['avatar']) && file_exists($uploadDir . basename($user['avatar']))) {
                unlink($uploadDir . basename($user['avatar']));
            }
            
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = "avatar_" . $userId . "_" . time() . "." . $extension;
            $filepath = "uploads/avatars/" . $filename;
            $fullPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $fullPath)) {
                $update = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $update->execute([$filepath, $userId]);
                $message = "Avatar updated successfully";
                $messageType = "success";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } else {
                $message = "Failed to upload avatar";
                $messageType = "error";
            }
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT e.id) as total_enrolled,
        SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_courses,
        ROUND(AVG(e.progress)) as avg_progress
    FROM users u
    LEFT JOIN enrollments e ON u.id = e.user_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

// Get avatar URL
$avatarUrl = !empty($user['avatar']) ? "../" . $user['avatar'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | LearnHub</title>
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

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
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

        .profile-avatar {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
        }

        .avatar-container {
            position: relative;
        }

        .avatar {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            overflow: hidden;
            position: relative;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: white;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #667eea;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .avatar-upload:hover {
            transform: scale(1.1);
            background: #667eea;
            color: white;
        }

        .profile-info h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .profile-info p {
            opacity: 0.9;
            margin: 5px 0;
        }

        .member-since {
            margin-top: 15px;
            font-size: 14px;
            opacity: 0.8;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        body.dark .stat-card {
            background: #1e293b;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #64748b;
            margin-top: 5px;
        }

        body.dark .stat-label {
            color: #94a3b8;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            animation: fadeInUp 0.5s ease;
        }

        body.dark .card {
            background: #1e293b;
        }

        .card h2 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark .card h2 {
            color: #f1f5f9;
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
        body.dark .form-group textarea {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Progress Bar */
        .progress-bar {
            background: #e2e8f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            width: 0%;
            transition: width 0.5s ease;
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
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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

        /* Social Links */
        .social-links {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        body.dark .social-links {
            border-top-color: #334155;
        }

        .social-links p {
            margin: 10px 0;
            color: #64748b;
        }

        body.dark .social-links p {
            color: #94a3b8;
        }

        .social-links strong {
            color: #1e293b;
        }

        body.dark .social-links strong {
            color: #f1f5f9;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .profile-avatar {
                flex-direction: column;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
                <a href="my-learning.php"><i class="fas fa-play-circle"></i> My Learning</a>
                <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
                <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
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
            <a href="my-learning.php"><i class="fas fa-play-circle"></i> My Learning</a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 12px; gap: 15px;">
                <button class="theme-toggle" id="mobileThemeToggle" style="background: none; border: none; font-size: 20px; cursor: pointer;">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="../logout.php" class="logout-btn" style="flex: 1; text-align: center;">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <div class="avatar-container">
                    <div class="avatar">
                        <?php if($avatarUrl && file_exists(__DIR__ . "/../" . $user['avatar'])): ?>
                            <img src="<?= $avatarUrl ?>?t=<?= time() ?>" alt="Avatar">
                        <?php else: ?>
                            <i class="fas fa-user-graduate"></i>
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="" enctype="multipart/form-data" id="avatarForm">
                        <label class="avatar-upload" for="avatarInput">
                            <i class="fas fa-camera"></i>
                        </label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
                    </form>
                </div>
                <div class="profile-info">
                    <h1><?= htmlspecialchars($user['fullname']) ?></h1>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?></p>
                    <?php if(!empty($user['phone'])): ?>
                        <p><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></p>
                    <?php endif; ?>
                    <?php if(!empty($user['location'])): ?>
                        <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($user['location']) ?></p>
                    <?php endif; ?>
                    <?php if(!empty($user['occupation'])): ?>
                        <p><i class="fas fa-briefcase"></i> <?= htmlspecialchars($user['occupation']) ?></p>
                    <?php endif; ?>
                    <div class="member-since">
                        <i class="fas fa-calendar-alt"></i> Member since <?= date('F j, Y', strtotime($user['created_at'] ?? 'now')) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_enrolled'] ?? 0 ?></div>
                <div class="stat-label">Courses Enrolled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['completed_courses'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= round($stats['avg_progress'] ?? 0) ?>%</div>
                <div class="stat-label">Average Progress</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Edit Profile Form -->
            <div class="card">
                <h2>
                    <i class="fas fa-edit"></i>
                    Edit Profile
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="City, Country">
                        </div>
                        
                        <div class="form-group">
                            <label>Occupation</label>
                            <input type="text" name="occupation" value="<?= htmlspecialchars($user['occupation'] ?? '') ?>" placeholder="e.g., Student, Developer">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" value="<?= htmlspecialchars($user['website'] ?? '') ?>" placeholder="https://yourwebsite.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Bio</label>
                        <textarea name="bio" rows="4" placeholder="Tell us about yourself..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <!-- Profile Info & Stats -->
            <div class="card">
                <h2>
                    <i class="fas fa-chart-line"></i>
                    Learning Statistics
                </h2>
                
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span>Overall Progress</span>
                        <span><?= round($stats['avg_progress'] ?? 0) ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= round($stats['avg_progress'] ?? 0) ?>%"></div>
                    </div>
                </div>
                
                <div class="social-links">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Account Information</h3>
                    <p><strong>User ID:</strong> #<?= $userId ?></p>
                    <p><strong>Role:</strong> <?= ucfirst($_SESSION['role'] ?? 'Student') ?></p>
                    <p><strong>Account Created:</strong> <?= date('F j, Y', strtotime($user['created_at'] ?? 'now')) ?></p>
                    <?php if(!empty($user['last_login'])): ?>
                        <p><strong>Last Login:</strong> <?= date('F j, Y g:i A', strtotime($user['last_login'])) ?></p>
                    <?php endif; ?>
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

        // Avatar Upload
        const avatarInput = document.getElementById('avatarInput');
        if (avatarInput) {
            avatarInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const avatarDiv = document.querySelector('.avatar');
                        avatarDiv.innerHTML = `<img src="${e.target.result}" alt="Avatar">`;
                    };
                    reader.readAsDataURL(this.files[0]);
                    
                    // Submit the form
                    this.form.submit();
                }
            });
        }

        // Animate progress bar
        document.addEventListener('DOMContentLoaded', () => {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const width = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = width;
                }, 100);
            }
        });
    </script>
</body>
</html>
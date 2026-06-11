<?php
// Include required files in correct order
require_once "../config/database.php";
require_once "../config/utilities.php";
require_once "../config/session_secure.php";
require_once "../config/auth.php";
require_once "../config/secure.php";
require_once "../config/session_lock.php";
require_once "../config/rate_limit.php";

// Start session securely
secureSessionStart();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Enforce single session
if (function_exists('enforceSingleSession')) {
    enforceSingleSession($pdo, $_SESSION['user_id']);
}

// Secure the page
if (function_exists('securePage')) {
    securePage();
}

$user_id = $_SESSION['user_id'];

// Get user statistics
$stats = [
    'total_courses' => 0,
    'enrolled_courses' => 0,
    'completed_courses' => 0
];

try {
    // Get total available courses
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status = 'active'");
    $result = $stmt->fetch();
    $stats['total_courses'] = $result['total'] ?? 0;
    
    // Get enrolled courses count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['enrolled_courses'] = $stmt->fetchColumn();
    
    // Get completed courses count (where progress is 100 or status is completed)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND (progress >= 100 OR status = 'completed')");
    $stmt->execute([$user_id]);
    $stats['completed_courses'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Statistics query failed: " . $e->getMessage());
}

// Get enrolled courses with progress
$enrolledCourses = [];
try {
    // Direct query to get enrolled courses with progress
    $stmt = $pdo->prepare("
        SELECT 
            e.id as enrollment_id,
            e.progress,
            e.status as enrollment_status,
            e.enrolled_at,
            c.id, 
            c.title, 
            c.description, 
            c.instructor, 
            c.duration, 
            c.level, 
            c.thumbnail,
            c.price
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $enrolledCourses = $stmt->fetchAll();
    
    // Calculate module progress for each course if progress is 0
    foreach ($enrolledCourses as &$course) {
        if (empty($course['progress']) || $course['progress'] == 0) {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT m.id) as total_modules,
                    COUNT(DISTINCT CASE WHEN up.completed = 1 THEN up.id END) as completed_modules
                FROM modules m
                LEFT JOIN user_progress up ON m.id = up.module_id AND up.user_id = ?
                WHERE m.course_id = ?
            ");
            $stmt->execute([$user_id, $course['id']]);
            $moduleStats = $stmt->fetch();
            
            if ($moduleStats && $moduleStats['total_modules'] > 0) {
                $calculatedProgress = round(($moduleStats['completed_modules'] / $moduleStats['total_modules']) * 100);
                $course['progress'] = $calculatedProgress;
                
                $updateStmt = $pdo->prepare("UPDATE enrollments SET progress = ? WHERE user_id = ? AND course_id = ?");
                $updateStmt->execute([$calculatedProgress, $user_id, $course['id']]);
            }
        }
        $course['progress'] = intval($course['progress'] ?? 0);
    }
    unset($course);
    
} catch (PDOException $e) {
    error_log("Enrolled courses query failed: " . $e->getMessage());
    $enrolledCourses = [];
}

// Get recent activities
$activities = [];
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'user_activities'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM user_activities 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $activities = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Activities query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | LearnHub</title>
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
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
            -webkit-background-clip: unset;
            background-clip: unset;
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

        .nav-links a:hover {
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
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Welcome Section */
        .welcome-section {
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

        .welcome-section h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-section h1 i {
            margin-right: 10px;
        }

        .welcome-section p {
            font-size: 16px;
            opacity: 0.9;
        }

        .welcome-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 15px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            animation: fadeInUp 0.5s ease;
        }

        body.dark .stat-card {
            background: #1e293b;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: bold;
            color: #1e293b;
        }

        body.dark .stat-info h3 {
            color: #f1f5f9;
        }

        .stat-info p {
            color: #64748b;
            margin-top: 5px;
        }

        body.dark .stat-info p {
            color: #94a3b8;
        }

        /* Section Title */
        .section-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark .section-title {
            color: #f1f5f9;
        }

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        body.dark .course-card {
            background: #1e293b;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .course-image {
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            position: relative;
        }

        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .course-content {
            padding: 20px;
        }

        .course-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #1e293b;
        }

        body.dark .course-title {
            color: #f1f5f9;
        }

        .course-description {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        body.dark .course-description {
            color: #94a3b8;
        }

        .course-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 12px;
            color: #94a3b8;
        }

        .course-progress {
            margin: 15px 0;
        }

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
            border-radius: 4px;
        }

        .progress-text {
            font-size: 12px;
            margin-top: 5px;
            color: #64748b;
        }

        .course-btn {
            display: inline-block;
            padding: 8px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .course-btn:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 25px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        body.dark .action-btn {
            background: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }

        .action-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
        }

        /* Activities Table */
        .activities-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            animation: fadeInUp 0.7s ease;
        }

        body.dark .activities-table {
            background: #1e293b;
        }

        .activities-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .activities-table th,
        .activities-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        body.dark .activities-table th,
        body.dark .activities-table td {
            border-bottom-color: #334155;
        }

        .activities-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 600;
        }

        body.dark .activities-table th {
            background: #0f172a;
            color: #cbd5e1;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        body.dark .empty-state i {
            color: #475569;
        }

        .empty-state p {
            color: #64748b;
            font-size: 16px;
            margin-bottom: 20px;
        }

        body.dark .empty-state p {
            color: #94a3b8;
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

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .welcome-section {
                padding: 30px 20px;
            }
            .welcome-section h1 {
                font-size: 24px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .courses-grid {
                grid-template-columns: 1fr;
            }
            .activities-table {
                overflow-x: auto;
            }
            .activities-table th,
            .activities-table td {
                padding: 10px 15px;
                font-size: 14px;
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
                <a href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="messages.php">
                    <i class="fas fa-message"></i> Messages
                </a>
                <a href="courses.php">
                    <i class="fas fa-book"></i> My Courses
                </a>
                <a href="my-learning.php">
                    <i class="fas fa-play-circle"></i> Learning
                </a>
                <a href="profile.php">
                    <i class="fas fa-user"></i> Profile
                </a>
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="../logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
            
            <!-- Mobile Menu Button -->
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <!-- Mobile Navigation Dropdown -->
        <div class="mobile-nav" id="mobileNav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="messages.php"><i class="fas fa-message"></i> Messages</a>
            <a href="courses.php"><i class="fas fa-book"></i> My Courses</a>
            <a href="my-learning.php"><i class="fas fa-play-circle"></i> Learning</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 12px;">
                <button class="theme-toggle" id="mobileThemeToggle">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>
                <i class="fas fa-hand-wave"></i> 
                Welcome back, <?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?>!
            </h1>
            <p>Continue your learning journey and achieve your goals.</p>
            <div class="welcome-badge">
                <i class="fas fa-calendar-alt"></i> 
                <?= date('l, F j, Y') ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='my-learning.php'">
                <div class="stat-icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['enrolled_courses'] ?? 0) ?></h3>
                    <p>Enrolled Courses</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='certificates.php'">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['completed_courses'] ?? 0) ?></h3>
                    <p>Completed Courses</p>
                </div>
            </div>
            <div class="stat-card" onclick="window.location.href='courses.php'">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['total_courses'] ?? 0) ?></h3>
                    <p>Total Available</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-info">
                    <h3><?= round(($stats['completed_courses'] / max($stats['enrolled_courses'], 1)) * 100) ?>%</h3>
                    <p>Completion Rate</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="courses.php" class="action-btn">
                <i class="fas fa-search"></i> Browse All Courses
            </a>
            <a href="my-learning.php" class="action-btn">
                <i class="fas fa-chalkboard-user"></i> Continue Learning
            </a>
            <a href="certificates.php" class="action-btn">
                <i class="fas fa-certificate"></i> My Certificates
            </a>
            <a href="profile.php" class="action-btn">
                <i class="fas fa-cog"></i> Profile Settings
            </a>
        </div>

        <!-- Current Courses -->
        <h2 class="section-title">
            <i class="fas fa-play-circle"></i> 
            Your Current Courses
        </h2>
        <div class="courses-grid">
            <?php if(empty($enrolledCourses)): ?>
                <div class="course-card">
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <p>You haven't enrolled in any courses yet.</p>
                        <a href="courses.php" class="course-btn">Browse Courses</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($enrolledCourses as $course): ?>
                <div class="course-card">
                    <div class="course-image">
                        <?php if(!empty($course['thumbnail']) && file_exists("../" . $course['thumbnail'])): ?>
                            <img src="../<?= $course['thumbnail'] ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                        <?php else: ?>
                            <i class="fas fa-graduation-cap"></i>
                        <?php endif; ?>
                    </div>
                    <div class="course-content">
                        <div class="course-title"><?= htmlspecialchars($course['title'] ?? 'Untitled Course') ?></div>
                        <div class="course-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor'] ?? 'Admin') ?></span>
                            <span><i class="fas fa-clock"></i> <?= htmlspecialchars($course['duration'] ?? 'Self-paced') ?></span>
                        </div>
                        <?php if(!empty($course['description'])): ?>
                            <div class="course-description">
                                <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>...
                            </div>
                        <?php endif; ?>
                        <div class="course-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= intval($course['progress'] ?? 0) ?>%"></div>
                            </div>
                            <div class="progress-text">
                                <?= intval($course['progress'] ?? 0) ?>% Complete
                            </div>
                        </div>
                        <a href="my-learning.php?course_id=<?= $course['id'] ?>" class="course-btn">
                            Continue Learning <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Activities -->
        <h2 class="section-title">
            <i class="fas fa-history"></i> 
            Recent Activities
        </h2>
        <div class="activities-table">
            <?php if(empty($activities)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No recent activities to display.</p>
                    <p style="font-size: 14px;">Start exploring courses to see your activity here!</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Activity</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($activities as $activity): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="activity-icon">
                                        <i class="fas fa-<?= htmlspecialchars($activity['type'] ?? 'bell') ?>"></i>
                                    </span>
                                    <?= htmlspecialchars($activity['description'] ?? 'Activity recorded') ?>
                                </div>
                            </td>
                            <td><?= date('M d, Y h:i A', strtotime($activity['created_at'] ?? 'now')) ?></td>
                            <td><span style="color: #10b981;">✓ Completed</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme Toggle for Desktop
        const themeToggle = document.getElementById('themeToggle');
        const mobileThemeToggle = document.getElementById('mobileThemeToggle');
        const body = document.body;
        
        // Check for saved theme preference
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        } else if (savedTheme === 'light') {
            body.classList.remove('dark');
            themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-moon"></i>';
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            if (mobileThemeToggle) mobileThemeToggle.innerHTML = '<i class="fas fa-sun"></i>';
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

        // Animate progress bars on load
        document.addEventListener('DOMContentLoaded', () => {
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 200);
            });
        });
    </script>
</body>
</html>
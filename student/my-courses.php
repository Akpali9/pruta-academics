<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/utilities.php";

securePage();
requireLogin();

$user_id = $_SESSION['user_id'];

// Check what columns exist in enrollments table
$enrollmentColumns = [];
try {
    $columns = $pdo->query("SHOW COLUMNS FROM enrollments");
    while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
        $enrollmentColumns[] = $col['Field'];
    }
} catch (PDOException $e) {
    error_log("Failed to get columns: " . $e->getMessage());
}

// Build select query based on existing columns
$selectFields = [
    "e.id",
    "e.course_id",
    "e.user_id",
    "c.title",
    "c.description",
    "c.instructor",
    "c.duration",
    "c.level"
];

// Add optional enrollment fields if they exist
if (in_array('enrolled_at', $enrollmentColumns)) {
    $selectFields[] = "e.enrolled_at";
} else {
    $selectFields[] = "NOW() as enrolled_at";
}

if (in_array('status', $enrollmentColumns)) {
    $selectFields[] = "e.status";
} else {
    $selectFields[] = "'active' as status";
}

if (in_array('progress', $enrollmentColumns)) {
    $selectFields[] = "e.progress";
} else {
    $selectFields[] = "0 as progress";
}

if (in_array('payment_status', $enrollmentColumns)) {
    $selectFields[] = "e.payment_status";
} else {
    $selectFields[] = "'approved' as payment_status";
}

if (in_array('access_code', $enrollmentColumns)) {
    $selectFields[] = "e.access_code";
} else {
    $selectFields[] = "NULL as access_code";
}

if (in_array('expires_at', $enrollmentColumns)) {
    $selectFields[] = "e.expires_at";
} else {
    $selectFields[] = "NULL as expires_at";
}

$selectQuery = "SELECT " . implode(", ", $selectFields) . " 
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                WHERE e.user_id = ?
                ORDER BY e.id DESC";

$stmt = $pdo->prepare($selectQuery);
$stmt->execute([$user_id]);
$enrollments = $stmt->fetchAll();

// Get statistics
$totalCourses = count($enrollments);
$approvedCourses = 0;
$pendingCourses = 0;
$totalProgress = 0;

foreach ($enrollments as $e) {
    $paymentStatus = $e['payment_status'] ?? 'pending';
    if ($paymentStatus === 'approved') {
        $approvedCourses++;
    } else {
        $pendingCourses++;
    }
    $totalProgress += $e['progress'] ?? 0;
}
$avgProgress = $totalCourses > 0 ? round($totalProgress / $totalCourses) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Course Access | LearnHub</title>
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

        /* Stats Cards */
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
            animation: fadeInUp 0.5s ease;
        }

        body.dark .stat-card {
            background: #1e293b;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .course-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            animation: fadeInUp 0.6s ease;
            position: relative;
        }

        body.dark .course-card {
            background: #1e293b;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .payment-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-approved {
            background: #10b981;
            color: white;
        }

        .badge-pending {
            background: #f59e0b;
            color: white;
        }

        .badge-expired {
            background: #ef4444;
            color: white;
        }

        .course-image {
            height: 160px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            color: white;
            position: relative;
        }

        .course-level {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.6);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            color: white;
        }

        .course-content {
            padding: 20px;
        }

        .course-title {
            font-size: 20px;
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
            line-height: 1.5;
            margin-bottom: 15px;
        }

        body.dark .course-description {
            color: #94a3b8;
        }

        .course-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #94a3b8;
            flex-wrap: wrap;
        }

        .course-meta i {
            margin-right: 5px;
        }

        .access-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }

        body.dark .access-info {
            background: #0f172a;
        }

        .access-code {
            font-family: monospace;
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 2px;
            margin: 10px 0;
        }

        .expiry-date {
            font-size: 13px;
            color: #ef4444;
        }

        .progress-section {
            margin: 15px 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
            color: #64748b;
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

        .pending-message {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
        }

        body.dark .pending-message {
            background: rgba(245, 158, 11, 0.2);
        }

        .pending-message i {
            color: #f59e0b;
            margin-right: 10px;
        }

        .course-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateX(3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            grid-column: 1/-1;
        }

        body.dark .empty-state {
            background: #1e293b;
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
            .courses-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
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
                <a href="my-courses.php" class="active"><i class="fas fa-key"></i> Access</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
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
            <a href="my-courses.php" class="active"><i class="fas fa-key"></i> Access</a>
            <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 12px; gap: 15px;">
                <button class="theme-toggle" id="mobileThemeToggle" style="background: none; border: none; font-size: 20px; cursor: pointer;">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="../logout.php" class="logout-btn" style="flex: 1; text-align: center;">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-key"></i> My Course Access</h1>
            <p>View your enrolled courses and access information</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $totalCourses ?></h3>
                    <p>Total Enrolled</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $approvedCourses ?></h3>
                    <p>Approved Access</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $pendingCourses ?></h3>
                    <p>Pending Approval</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $avgProgress ?>%</h3>
                    <p>Average Progress</p>
                </div>
            </div>
        </div>

        <!-- Courses Grid -->
        <div class="courses-grid">
            <?php if(empty($enrollments)): ?>
                <div class="empty-state">
                    <i class="fas fa-key"></i>
                    <p>You haven't enrolled in any courses yet.</p>
                    <a href="courses.php" class="btn btn-primary" style="display: inline-block; width: auto;">
                        Browse Courses
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($enrollments as $e): 
                    $paymentStatus = $e['payment_status'] ?? 'pending';
                    $status = $e['status'] ?? 'active';
                    $progress = $e['progress'] ?? 0;
                    $accessCode = $e['access_code'] ?? null;
                    $expiresAt = $e['expires_at'] ?? null;
                    $isExpired = $expiresAt && strtotime($expiresAt) < time();
                ?>
                    <div class="course-card">
                        <div class="payment-badge">
                            <?php if($paymentStatus === 'approved'): ?>
                                <span class="badge badge-approved">
                                    <i class="fas fa-check-circle"></i> Access Approved
                                </span>
                            <?php elseif($isExpired): ?>
                                <span class="badge badge-expired">
                                    <i class="fas fa-exclamation-circle"></i> Expired
                                </span>
                            <?php else: ?>
                                <span class="badge badge-pending">
                                    <i class="fas fa-clock"></i> Pending Approval
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-image">
                            <i class="fas fa-graduation-cap"></i>
                            <span class="course-level">
                                <i class="fas fa-signal"></i> <?= ucfirst($e['level'] ?? 'Beginner') ?>
                            </span>
                        </div>
                        
                        <div class="course-content">
                            <h3 class="course-title"><?= htmlspecialchars($e['title'] ?? 'Untitled Course') ?></h3>
                            
                            <div class="course-meta">
                                <?php if(!empty($e['instructor'])): ?>
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($e['instructor']) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($e['duration'])): ?>
                                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($e['duration']) ?></span>
                                <?php endif; ?>
                                <?php if(!empty($e['enrolled_at'])): ?>
                                <span><i class="fas fa-calendar"></i> Enrolled: <?= date('M d, Y', strtotime($e['enrolled_at'])) ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if($paymentStatus === 'approved' && !$isExpired): ?>
                                <!-- Access Information -->
                                <div class="access-info">
                                    <strong><i class="fas fa-key"></i> Access Information:</strong>
                                    <?php if($accessCode): ?>
                                        <div class="access-code">
                                            <i class="fas fa-lock"></i> Access Code: <?= htmlspecialchars($accessCode) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if($expiresAt): ?>
                                        <div class="expiry-date">
                                            <i class="fas fa-hourglass-end"></i> Expires: <?= date('F j, Y', strtotime($expiresAt)) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="expiry-date" style="color: #10b981;">
                                            <i class="fas fa-infinity"></i> Lifetime Access
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="progress-section">
                                    <div class="progress-header">
                                        <span>Your Progress</span>
                                        <span><?= $progress ?>%</span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="course-actions">
                                    <?php if($status === 'active'): ?>
                                        <a href="learn.php?id=<?= $e['course_id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-play"></i> Continue Learning
                                        </a>
                                    <?php elseif($status === 'completed'): ?>
                                        <a href="certificate.php?id=<?= $e['course_id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-certificate"></i> Get Certificate
                                        </a>
                                    <?php endif; ?>
                                    <a href="course.php?id=<?= $e['course_id'] ?>" class="btn btn-outline">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                </div>
                                
                            <?php elseif($isExpired): ?>
                                <div class="pending-message">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Your access to this course has expired. Please contact support to renew.
                                </div>
                                <div class="course-actions">
                                    <a href="course.php?id=<?= $e['course_id'] ?>" class="btn btn-warning">
                                        <i class="fas fa-sync"></i> Renew Access
                                    </a>
                                </div>
                                
                            <?php else: ?>
                                <div class="pending-message">
                                    <i class="fas fa-clock"></i>
                                    Your enrollment is pending admin approval. You will receive access once your payment is verified.
                                </div>
                                <div class="course-actions">
                                    <a href="course.php?id=<?= $e['course_id'] ?>" class="btn btn-outline">
                                        <i class="fas fa-eye"></i> View Status
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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

        // Add animation delay to cards
        const cards = document.querySelectorAll('.course-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.05}s`;
        });
    </script>
</body>
</html>
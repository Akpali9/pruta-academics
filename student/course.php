<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/utilities.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get course details
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if(!$course){
    die("Course not found");
}

// Check if user is already enrolled
$checkEnrollment = $pdo->prepare("
    SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?
");
$checkEnrollment->execute([$_SESSION['user_id'], $id]);
$isEnrolled = $checkEnrollment->rowCount() > 0;

// Get enrollment details if enrolled
$enrollment = null;
if ($isEnrolled) {
    $stmt = $pdo->prepare("
        SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $id]);
    $enrollment = $stmt->fetch();
}

// Check if receipt already uploaded
$checkReceipt = $pdo->prepare("
    SELECT * FROM payment_receipts WHERE user_id = ? AND course_id = ?
");
$checkReceipt->execute([$_SESSION['user_id'], $id]);
$hasReceipt = $checkReceipt->rowCount() > 0;
$receipt = $hasReceipt ? $checkReceipt->fetch() : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> | LearnHub</title>
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

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        body.dark .back-btn {
            background: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }

        .back-btn:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateX(-5px);
        }

        /* Course Header */
        .course-header {
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

        .course-header h1 {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .course-meta {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .course-meta span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }

        /* Main Content */
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }

        /* Course Info */
        .course-info {
            background: white;
            border-radius: 15px;
            padding: 30px;
            animation: fadeInUp 0.5s ease;
        }

        body.dark .course-info {
            background: #1e293b;
        }

        .course-info h2 {
            font-size: 24px;
            margin-bottom: 15px;
            color: #1e293b;
        }

        body.dark .course-info h2 {
            color: #f1f5f9;
        }

        .course-info h3 {
            font-size: 18px;
            margin: 20px 0 10px;
            color: #475569;
        }

        body.dark .course-info h3 {
            color: #cbd5e1;
        }

        .course-info p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        body.dark .course-info p {
            color: #94a3b8;
        }

        /* Payment Card */
        .payment-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            animation: fadeInUp 0.6s ease;
        }

        body.dark .payment-card {
            background: #1e293b;
        }

        .payment-card h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        body.dark .payment-card h3 {
            color: #f1f5f9;
        }

        .payment-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        body.dark .payment-info {
            background: #0f172a;
        }

        .payment-info p {
            margin: 10px 0;
            color: #475569;
        }

        body.dark .payment-info p {
            color: #94a3b8;
        }

        .payment-info strong {
            color: #1e293b;
        }

        body.dark .payment-info strong {
            color: #f1f5f9;
        }

        .amount {
            font-size: 28px;
            font-weight: bold;
            color: #667eea;
            margin: 15px 0;
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

        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        body.dark .form-group input[type="file"] {
            background: #0f172a;
            border-color: #334155;
            color: #cbd5e1;
        }

        .form-group input[type="file"]:hover {
            border-color: #667eea;
        }

        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
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
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .alert-success {
            background: #dcfce7;
            color: #16a34a;
            border-left: 4px solid #16a34a;
        }

        .alert-info {
            background: #dbeafe;
            color: #2563eb;
            border-left: 4px solid #2563eb;
        }

        .alert-warning {
            background: #fed7aa;
            color: #ea580c;
            border-left: 4px solid #ea580c;
        }

        body.dark .alert-success {
            background: rgba(22, 163, 74, 0.2);
            color: #86efac;
        }

        body.dark .alert-info {
            background: rgba(37, 99, 235, 0.2);
            color: #93c5fd;
        }

        body.dark .alert-warning {
            background: rgba(234, 88, 12, 0.2);
            color: #fdba74;
        }

        /* Uploaded Receipt */
        .uploaded-receipt {
            background: #f8fafc;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        body.dark .uploaded-receipt {
            background: #0f172a;
        }

        .receipt-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fed7aa;
            color: #ea580c;
        }

        .status-approved {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-rejected {
            background: #fee2e2;
            color: #dc2626;
        }

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
            .course-header h1 {
                font-size: 24px;
            }
            .course-meta {
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
                <a href="my-learning.php"><i class="fas fa-play-circle"></i> My Learning</a>
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
            <a href="my-learning.php"><i class="fas fa-play-circle"></i> My Learning</a>
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
        <a href="courses.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Courses
        </a>

        <!-- Course Header -->
        <div class="course-header">
            <h1><?= htmlspecialchars($course['title']) ?></h1>
            <p><?= htmlspecialchars($course['description']) ?></p>
            <div class="course-meta">
                <span><i class="fas fa-user"></i> Instructor: <?= htmlspecialchars($course['instructor'] ?? 'Admin') ?></span>
                <span><i class="fas fa-clock"></i> Duration: <?= htmlspecialchars($course['duration'] ?? 'Self-paced') ?></span>
                <span><i class="fas fa-signal"></i> Level: <?= ucfirst(htmlspecialchars($course['level'] ?? 'Beginner')) ?></span>
            </div>
        </div>

        <div class="main-content">
            <!-- Course Information -->
            <div class="course-info">
                <h2>Course Overview</h2>
                <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                
                <?php if(!empty($course['objectives'])): ?>
                    <h3>Learning Objectives</h3>
                    <p><?= htmlspecialchars($course['objectives']) ?></p>
                <?php endif; ?>
                
                <?php if(!empty($course['requirements'])): ?>
                    <h3>Requirements</h3>
                    <p><?= htmlspecialchars($course['requirements']) ?></p>
                <?php endif; ?>
                
                <?php if($isEnrolled && $enrollment && $enrollment['status'] === 'active'): ?>
                    <div class="alert alert-success" style="margin-top: 20px;">
                        <i class="fas fa-check-circle"></i>
                        <span>You are already enrolled in this course! You can start learning now.</span>
                    </div>
                    <a href="learn.php?id=<?= $course['id'] ?>" class="course-btn" style="display: inline-block; margin-top: 20px; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 10px;">
                        <i class="fas fa-play"></i> Start Learning
                    </a>
                <?php endif; ?>
            </div>

            <!-- Payment Section -->
            <div class="payment-card">
                <h3>
                    <i class="fas fa-credit-card"></i>
                    Payment Information
                </h3>
                
                <div class="payment-info">
                    <p><strong>Course Fee:</strong></p>
                    <div class="amount">
                        ₦<?= number_format($course['price'] ?? 49.99, 2) ?>
                    </div>
                    <p><strong>Payment Method:</strong> Manual Bank Transfer</p>
                    <p><strong>Bank:</strong> Example Bank</p>
                    <p><strong>Account Name:</strong> LearnHub Education</p>
                    <p><strong>Account Number:</strong> 1234-5678-9012</p>
                    <p><strong>Reference:</strong> COURSE-<?= $course['id'] ?>-<?= $_SESSION['user_id'] ?></p>
                </div>

                <?php if($isEnrolled): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>You are already enrolled. No payment needed.</span>
                    </div>
                <?php elseif($hasReceipt): ?>
                    <div class="uploaded-receipt">
                        <div class="receipt-status">
                            <i class="fas fa-receipt"></i>
                            <strong>Receipt Uploaded</strong>
                            <span class="status-badge status-<?= $receipt['status'] ?? 'pending' ?>">
                                <?= ucfirst($receipt['status'] ?? 'Pending') ?>
                            </span>
                        </div>
                        <p>Uploaded on: <?= date('F j, Y', strtotime($receipt['created_at'] ?? 'now')) ?></p>
                        <?php if(($receipt['status'] ?? '') === 'pending'): ?>
                            <div class="alert alert-warning" style="margin-top: 10px;">
                                <i class="fas fa-clock"></i>
                                <span>Your receipt is pending review. You will get access once approved.</span>
                            </div>
                        <?php elseif(($receipt['status'] ?? '') === 'approved'): ?>
                            <div class="alert alert-success" style="margin-top: 10px;">
                                <i class="fas fa-check-circle"></i>
                                <span>Your payment has been approved! You now have access to this course.</span>
                            </div>
                            <a href="learn.php?id=<?= $course['id'] ?>" style="display: inline-block; margin-top: 15px; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 10px;">
                                <i class="fas fa-play"></i> Start Learning
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <form action="upload_receipt.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                        
                        <div class="form-group">
                            <label for="receipt">Upload Payment Receipt</label>
                            <input type="file" name="receipt" id="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                            <small style="color: #94a3b8; display: block; margin-top: 5px;">
                                Accepted formats: JPG, PNG, PDF (Max 5MB)
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="transaction_id">Transaction ID (Optional)</label>
                            <input type="text" name="transaction_id" id="transaction_id" placeholder="Enter transaction reference number" style="width: 100%; padding: 10px; border: 2px solid #e2e8f0; border-radius: 10px;">
                        </div>
                        
                        <button type="submit" id="submitBtn">
                            <i class="fas fa-upload"></i> Upload Receipt
                        </button>
                    </form>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #fef3c7; border-radius: 10px; color: #92400e;">
                        <i class="fas fa-info-circle"></i>
                        <small>After uploading your receipt, our team will verify it within 24-48 hours. You'll receive access to the course upon approval.</small>
                    </div>
                <?php endif; ?>
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

        // Form validation
        const uploadForm = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submitBtn');
        const fileInput = document.getElementById('receipt');

        if (uploadForm) {
            uploadForm.addEventListener('submit', function(e) {
                const file = fileInput.files[0];
                
                if (!file) {
                    e.preventDefault();
                    alert('Please select a file to upload');
                    return;
                }
                
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('File size must be less than 5MB');
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('Please upload JPG, PNG, or PDF file');
                    return;
                }
                
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                submitBtn.disabled = true;
            });
        }
    </script>
</body>
</html>
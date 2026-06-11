<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$user_id = $_SESSION['user_id'];

// Create certificates table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        certificate_code VARCHAR(50) NOT NULL UNIQUE,
        file_path VARCHAR(500),
        issued_at DATETIME NOT NULL,
        sent_by INT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table might already exist
}

// Get certificates for this student
$stmt = $pdo->prepare("
    SELECT c.*, cr.title as course_title, cr.description 
    FROM certificates c
    JOIN courses cr ON c.course_id = cr.id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
");
$stmt->execute([$user_id]);
$certificates = $stmt->fetchAll();

// Get completed courses without certificates
$stmt = $pdo->prepare("
    SELECT e.course_id, c.title 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ? 
    AND e.status = 'completed'
    AND e.course_id NOT IN (SELECT course_id FROM certificates WHERE user_id = ?)
");
$stmt->execute([$user_id, $user_id]);
$pendingCertificates = $stmt->fetchAll();

$totalCertificates = count($certificates);
$totalCompleted = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status = 'completed'");
$totalCompleted->execute([$user_id]);
$totalCompleted = $totalCompleted->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates | LearnHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; transition: all 0.3s ease; }
        body.dark { background: #0f172a; }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        body.dark .navbar { background: #1e293b; }
        
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
        .logo i { background: none; color: #667eea; }
        
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
        body.dark .nav-links a { color: #cbd5e1; }
        .nav-links a:hover, .nav-links a.active { color: #667eea; transform: translateY(-2px); }
        
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
        body.dark .mobile-menu-btn { color: #cbd5e1; }
        .mobile-menu-btn:hover { background: rgba(102, 126, 234, 0.1); }
        
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
        body.dark .mobile-nav a { color: #cbd5e1; }
        .mobile-nav a:hover {
            background: #f1f5f9;
            color: #667eea;
        }
        body.dark .mobile-nav a:hover { background: #0f172a; }
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
        body.dark .theme-toggle { color: #cbd5e1; }
        .theme-toggle:hover { background: rgba(102, 126, 234, 0.1); transform: rotate(15deg); }
        
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white !important;
            padding: 8px 20px;
            border-radius: 8px;
        }
        .logout-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4); }
        
        /* Container */
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        .page-header { text-align: center; margin-bottom: 40px; animation: fadeIn 0.5s ease; }
        .page-header h1 { font-size: 36px; color: #1e293b; }
        body.dark .page-header h1 { color: #f1f5f9; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: transform 0.3s;
        }
        body.dark .stat-card { background: #1e293b; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-number { font-size: 32px; font-weight: bold; color: #667eea; }
        .stat-label { color: #64748b; margin-top: 5px; }
        
        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        .certificate-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            cursor: pointer;
        }
        body.dark .certificate-card { background: #1e293b; }
        .certificate-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.15); }
        
        .certificate-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px 20px;
            text-align: center;
            color: white;
        }
        .certificate-header i { font-size: 48px; margin-bottom: 15px; }
        
        .certificate-body { padding: 20px; }
        .course-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #1e293b; }
        body.dark .course-title { color: #f1f5f9; }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        body.dark .detail-row { border-bottom-color: #334155; }
        
        .certificate-code {
            background: #f8fafc;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
            font-family: monospace;
            font-size: 12px;
        }
        body.dark .certificate-code { background: #0f172a; }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; width: 100%; }
        .btn-primary:hover { transform: translateY(-2px); }
        
        .empty-state { text-align: center; padding: 60px; background: white; border-radius: 15px; grid-column: 1/-1; }
        body.dark .empty-state { background: #1e293b; }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; display: block; }
        
        .pending-card {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        body.dark .pending-card { background: rgba(245, 158, 11, 0.1); border-left-color: #f59e0b; }
        .pending-card strong { color: #92400e; }
        body.dark .pending-card strong { color: #fbbf24; }
        .pending-card p { color: #b45309; font-size: 13px; margin-top: 5px; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .certificates-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
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
                <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
                <a href="my-learning.php"><i class="fas fa-play-circle"></i> My Learning</a>
                <a href="certificates.php" class="active"><i class="fas fa-certificate"></i> Certificates</a>
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
            <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="my-learning.php"><i class="fas fa-play-circle"></i> My Learning</a>
            <a href="certificates.php" class="active"><i class="fas fa-certificate"></i> Certificates</a>
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
        <div class="page-header">
            <h1><i class="fas fa-certificate"></i> My Certificates</h1>
            <p>Your achievements and certifications</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $totalCertificates ?></div>
                <div class="stat-label">Certificates Earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalCompleted ?></div>
                <div class="stat-label">Courses Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $totalCompleted > 0 ? round(($totalCertificates / $totalCompleted) * 100) : 0 ?>%</div>
                <div class="stat-label">Conversion Rate</div>
            </div>
        </div>

        <div class="certificates-grid">
            <?php if(empty($certificates)): ?>
                <div class="empty-state">
                    <i class="fas fa-certificate"></i>
                    <p>You haven't earned any certificates yet.</p>
                    <p style="font-size: 14px; margin-top: 10px;">Complete courses to receive your certificates!</p>
                    <a href="courses.php" class="btn btn-primary" style="display: inline-block; width: auto; margin-top: 20px;">
                        Browse Courses
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($certificates as $cert): ?>
                <div class="certificate-card" onclick="viewCertificate(<?= $cert['id'] ?>)">
                    <div class="certificate-header">
                        <i class="fas fa-certificate"></i>
                        <h3>CERTIFICATE OF COMPLETION</h3>
                    </div>
                    <div class="certificate-body">
                        <h3 class="course-title"><?= htmlspecialchars($cert['course_title']) ?></h3>
                        <div class="detail-row">
                            <span>Issued To:</span>
                            <span><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Issued Date:</span>
                            <span><?= date('F j, Y', strtotime($cert['issued_at'])) ?></span>
                        </div>
                        <div class="certificate-code">
                            <i class="fas fa-qrcode"></i><br>
                            <span><?= htmlspecialchars($cert['certificate_code']) ?></span>
                        </div>
                        <button class="btn btn-primary" onclick="event.stopPropagation(); printCertificate(<?= $cert['id'] ?>)">
                            <i class="fas fa-print"></i> Print / Save PDF
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if(!empty($pendingCertificates)): ?>
        <h2 style="margin: 40px 0 20px; color: #1e293b;">
            <i class="fas fa-clock"></i> Pending Certificates
        </h2>
        <?php foreach($pendingCertificates as $pending): ?>
        <div class="pending-card">
            <strong><?= htmlspecialchars($pending['title']) ?></strong>
            <p><i class="fas fa-info-circle"></i> Your certificate is being processed. You will receive it via email once approved.</p>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
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
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars'></i>";
            });
        }
        
        function viewCertificate(id) {
            window.open('view_certificate.php?id=' + id, '_blank');
        }
        
        function printCertificate(id) {
            window.open('view_certificate.php?id=' + id, '_blank');
        }
    </script>
</body>
</html>
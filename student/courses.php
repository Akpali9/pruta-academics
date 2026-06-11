<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/utilities.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

// Get all available courses
$courses = $pdo->query("SELECT * FROM courses ORDER BY created_at DESC")->fetchAll();

// Get user's enrolled courses
$stmt = $pdo->prepare("
    SELECT course_id, status, progress 
    FROM enrollments 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$enrollments = [];
while ($row = $stmt->fetch()) {
    $enrollments[$row['course_id']] = $row;
}

// Get user's payment receipts
$stmt = $pdo->prepare("
    SELECT course_id, status 
    FROM payment_receipts 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$receipts = [];
while ($row = $stmt->fetch()) {
    $receipts[$row['course_id']] = $row['status'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses | LearnHub</title>
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Header Section */
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

        /* Search and Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            animation: fadeInUp 0.5s ease;
        }

        body.dark .filter-bar {
            background: #1e293b;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        body.dark .search-box input {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            cursor: pointer;
        }

        body.dark .filter-select {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }

        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
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

        .course-badge {
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

        .badge-enrolled {
            background: #10b981;
            color: white;
        }

        .badge-pending {
            background: #f59e0b;
            color: white;
        }

        .badge-new {
            background: #ef4444;
            color: white;
        }

        .course-image {
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
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
        }

        .course-meta i {
            margin-right: 5px;
        }

        .course-price {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }

        body.dark .course-price {
            color: #818cf8;
        }

        .course-actions {
            display: flex;
            gap: 10px;
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
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }

        body.dark .btn-secondary {
            background: #334155;
            color: #cbd5e1;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
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
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a {
            padding: 10px 15px;
            background: white;
            border-radius: 8px;
            text-decoration: none;
            color: #475569;
            transition: all 0.3s ease;
        }

        body.dark .pagination a {
            background: #1e293b;
            color: #cbd5e1;
        }

        .pagination a:hover,
        .pagination a.active {
            background: #667eea;
            color: white;
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
            .filter-bar {
                flex-direction: column;
            }
            .search-box {
                width: 100%;
            }
            .filter-select {
                width: 100%;
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
                <a href="courses.php" class="active"><i class="fas fa-book"></i> All Courses</a>
                <a href="my-courses.php"><i class="fas fa-play-circle"></i> Access</a>
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
            <a href="courses.php" class="active"><i class="fas fa-book"></i> All Courses</a>
            <a href="my-courses.php"><i class="fas fa-play-circle"></i> Access</a>
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
            <h1><i class="fas fa-book-open"></i> Available Courses</h1>
            <p>Explore our collection of courses and start your learning journey today</p>
        </div>

        <!-- Search and Filter -->
        <div class="filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search courses by title, instructor...">
            </div>
            <select class="filter-select" id="levelFilter">
                <option value="all">All Levels</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
            <select class="filter-select" id="sortFilter">
                <option value="newest">Newest First</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
                <option value="title">Title A-Z</option>
            </select>
        </div>

        <!-- Courses Grid -->
        <div class="courses-grid" id="coursesGrid">
            <?php if(empty($courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <p>No courses available at the moment.</p>
                    <p style="margin-top: 10px;">Please check back later!</p>
                </div>
            <?php else: ?>
                <?php foreach($courses as $course): 
                    $isEnrolled = isset($enrollments[$course['id']]);
                    $receiptStatus = $receipts[$course['id']] ?? null;
                    $isPending = $receiptStatus === 'pending';
                ?>
                    <div class="course-card" 
                         data-title="<?= strtolower(htmlspecialchars($course['title'])) ?>"
                         data-level="<?= strtolower(htmlspecialchars($course['level'] ?? 'beginner')) ?>"
                         data-price="<?= $course['price'] ?? 0 ?>"
                         data-date="<?= $course['created_at'] ?? '' ?>">
                        
                        <div class="course-badge">
                            <?php if($isEnrolled): ?>
                                <span class="badge badge-enrolled">
                                    <i class="fas fa-check-circle"></i> Enrolled
                                </span>
                            <?php elseif($isPending): ?>
                                <span class="badge badge-pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            <?php else: ?>
                                <span class="badge badge-new">
                                    <i class="fas fa-star"></i> New
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="course-image">
                            <i class="fas fa-graduation-cap"></i>
                            <span class="course-level">
                                <i class="fas fa-signal"></i> 
                                <?= ucfirst($course['level'] ?? 'Beginner') ?>
                            </span>
                        </div>
                        
                        <div class="course-content">
                            <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                            <p class="course-description">
                                <?= htmlspecialchars(substr($course['description'] ?? '', 0, 120)) ?>...
                            </p>
                            
                            <div class="course-meta">
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor'] ?? 'Admin') ?></span>
                                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($course['duration'] ?? 'Self-paced') ?></span>
                            </div>
                            
                            <div class="course-price">
                                ₦<?= number_format($course['price'] ?? 0, 2) ?>
                            </div>
                            
                            <div class="course-actions">
                                <?php if($isEnrolled): ?>
                                    <a href="learn.php?id=<?= $course['id'] ?>" class="btn btn-success">
                                        <i class="fas fa-play"></i> Continue
                                    </a>
                                <?php elseif($isPending): ?>
                                    <a href="course.php?id=<?= $course['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-clock"></i> Pending Approval
                                    </a>
                                <?php else: ?>
                                    <a href="course.php?id=<?= $course['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart"></i> Enroll Now
                                    </a>
                                <?php endif; ?>
                                
                                <a href="course.php?id=<?= $course['id'] ?>" class="btn btn-outline">
                                    <i class="fas fa-info-circle"></i> Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if(count($courses) > 6): ?>
        <div class="pagination" id="pagination">
            <a href="#" data-page="prev">&laquo; Previous</a>
            <a href="#" data-page="1" class="active">1</a>
            <a href="#" data-page="2">2</a>
            <a href="#" data-page="3">3</a>
            <a href="#" data-page="next">Next &raquo;</a>
        </div>
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
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            });
        }

        // Search and Filter Functionality
        const searchInput = document.getElementById('searchInput');
        const levelFilter = document.getElementById('levelFilter');
        const sortFilter = document.getElementById('sortFilter');
        const coursesGrid = document.getElementById('coursesGrid');
        
        function filterAndSortCourses() {
            const searchTerm = searchInput.value.toLowerCase();
            const level = levelFilter.value;
            const sort = sortFilter.value;
            
            let cards = Array.from(document.querySelectorAll('.course-card'));
            
            // Filter
            cards = cards.filter(card => {
                const title = card.dataset.title || '';
                const cardLevel = card.dataset.level || '';
                
                const matchesSearch = title.includes(searchTerm);
                const matchesLevel = level === 'all' || cardLevel === level;
                
                return matchesSearch && matchesLevel;
            });
            
            // Sort
            cards.sort((a, b) => {
                if (sort === 'price-low') {
                    return (parseFloat(a.dataset.price) || 0) - (parseFloat(b.dataset.price) || 0);
                } else if (sort === 'price-high') {
                    return (parseFloat(b.dataset.price) || 0) - (parseFloat(a.dataset.price) || 0);
                } else if (sort === 'title') {
                    return (a.dataset.title || '').localeCompare(b.dataset.title || '');
                } else {
                    return (b.dataset.date || '') - (a.dataset.date || '');
                }
            });
            
            coursesGrid.innerHTML = '';
            if (cards.length === 0) {
                coursesGrid.innerHTML = `
                    <div class="empty-state" style="grid-column: 1/-1;">
                        <i class="fas fa-search"></i>
                        <p>No courses found matching your criteria.</p>
                        <p style="margin-top: 10px;">Try adjusting your search or filter settings!</p>
                    </div>
                `;
            } else {
                cards.forEach(card => coursesGrid.appendChild(card));
            }
        }
        
        searchInput.addEventListener('input', filterAndSortCourses);
        levelFilter.addEventListener('change', filterAndSortCourses);
        sortFilter.addEventListener('change', filterAndSortCourses);
        
        // Add animation delay to cards
        const cards = document.querySelectorAll('.course-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.05}s`;
        });
    </script>
</body>
</html>
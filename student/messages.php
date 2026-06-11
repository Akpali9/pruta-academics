<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$user_id = $_SESSION['user_id'];

// Get all messages for this student
$messages = $pdo->prepare("
    SELECT m.*, c.title as course_title, 
           (SELECT COUNT(*) FROM message_replies WHERE message_id = m.id) as reply_count
    FROM messages m
    JOIN courses c ON m.course_id = c.id
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$messages->execute([$user_id]);
$messages = $messages->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Messages | LearnHub</title>
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
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; }
        body.dark .card { background: #1e293b; }
        h1 { margin-bottom: 20px; color: #1e293b; }
        body.dark h1 { color: #f1f5f9; }
        
        .message-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        body.dark .message-card { border-color: #334155; }
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .reply-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        body.dark .reply-item { background: #0f172a; }
        .reply-instructor { background: #dbeafe; border-left: 3px solid #667eea; }
        body.dark .reply-instructor { background: rgba(102,126,234,0.1); }
        
        .empty-state { text-align: center; padding: 60px; }
        .empty-state i { font-size: 48px; color: #94a3b8; margin-bottom: 15px; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
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
                <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
                <a href="messages.php" class="active"><i class="fas fa-envelope"></i> Messages</a>
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
            <a href="messages.php" class="active"><i class="fas fa-envelope"></i> Messages</a>
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
        <div class="card">
            <h1><i class="fas fa-envelope"></i> My Messages</h1>
            <p>Conversations with instructors</p>
        </div>
        
        <?php if(empty($messages)): ?>
            <div class="card empty-state">
                <i class="fas fa-inbox"></i>
                <p>No messages yet.</p>
                <p style="font-size: 14px;">Go to a course and click "Message Instructor" to send a message.</p>
            </div>
        <?php else: ?>
            <?php foreach($messages as $msg): ?>
            <div class="message-card">
                <div class="message-header">
                    <strong><?= htmlspecialchars($msg['subject']) ?></strong>
                    <small><?= date('M d, Y g:i A', strtotime($msg['created_at'])) ?></small>
                </div>
                <div><strong>Course:</strong> <?= htmlspecialchars($msg['course_title']) ?></div>
                <div style="margin: 10px 0;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                
                <?php if($msg['attachment']): ?>
                    <a href="../<?= $msg['attachment'] ?>" target="_blank" style="display: inline-block; margin-top: 10px; color: #667eea;">
                        <i class="fas fa-paperclip"></i> Download Attachment
                    </a>
                <?php endif; ?>
                
                <?php
                $stmt = $pdo->prepare("
                    SELECT r.*, u.fullname, u.role 
                    FROM message_replies r
                    JOIN users u ON r.user_id = u.id
                    WHERE r.message_id = ?
                    ORDER BY r.created_at ASC
                ");
                $stmt->execute([$msg['id']]);
                $replies = $stmt->fetchAll();
                ?>
                
                <?php foreach($replies as $reply): ?>
                <div class="reply-item <?= $reply['role'] === 'admin' ? 'reply-instructor' : '' ?>">
                    <strong><?= htmlspecialchars($reply['fullname']) ?>:</strong>
                    <p style="margin-top: 5px;"><?= nl2br(htmlspecialchars($reply['reply'])) ?></p>
                    <small><?= date('M d, Y g:i A', strtotime($reply['created_at'])) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
        
        // Close mobile menu when clicking the theme toggle in mobile menu
        if (mobileThemeToggle) {
            mobileThemeToggle.addEventListener('click', function() {
                mobileNav.style.display = 'none';
                mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            });
        }
    </script>
</body>
</html>
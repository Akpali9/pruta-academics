<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireAdmin();

$id = (int)$_GET['id'];

// Get course details
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT m.id) as module_count,
           COUNT(DISTINCT e.id) as enrollment_count,
           SUM(CASE WHEN e.status = 'completed' THEN 1 ELSE 0 END) as completed_count
    FROM courses c
    LEFT JOIN modules m ON c.id = m.course_id
    LEFT JOIN enrollments e ON c.id = e.course_id
    WHERE c.id = ?
    GROUP BY c.id
");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    die("Course not found");
}

// Get modules for this course
$stmt = $pdo->prepare("
    SELECT m.*, 
           COUNT(DISTINCT a.id) as assignment_count,
           COUNT(DISTINCT s.id) as submission_count
    FROM modules m
    LEFT JOIN assignments a ON m.id = a.module_id
    LEFT JOIN submissions s ON a.id = s.assignment_id
    WHERE m.course_id = ?
    GROUP BY m.id
    ORDER BY m.order_number
");
$stmt->execute([$id]);
$modules = $stmt->fetchAll();

// Get recent enrollments
$stmt = $pdo->prepare("
    SELECT e.*, u.fullname, u.email 
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    WHERE e.course_id = ?
    ORDER BY e.enrolled_at DESC
    LIMIT 10
");
$stmt->execute([$id]);
$enrollments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; padding: 20px; transition: all 0.3s ease; }
        body.dark { background: #0f172a; }
        
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 20px;
            border-radius: 10px;
        }
        body.dark .navbar { background: #1e293b; }
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .nav-links { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        body.dark .nav-links a { color: #cbd5e1; }
        .nav-links a:hover, .nav-links a.active { color: #667eea; }
        .theme-toggle {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #475569;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white !important;
            padding: 6px 15px;
            border-radius: 8px;
        }
        
        .card { background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; }
        body.dark .card { background: #1e293b; }
        .card-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        body.dark .card-header { border-bottom-color: #334155; }
        .card-header h2 { font-size: 20px; color: #1e293b; }
        body.dark .card-header h2 { color: #f1f5f9; }
        
        .course-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin-bottom: 25px;
        }
        .course-header h1 { font-size: 28px; margin-bottom: 10px; }
        .course-header p { opacity: 0.9; line-height: 1.5; }
        .course-meta {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .course-meta span {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        body.dark .stat-card { background: #1e293b; }
        .stat-number { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-label { color: #64748b; margin-top: 5px; font-size: 13px; }
        body.dark .stat-label { color: #94a3b8; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        body.dark th, body.dark td { border-bottom-color: #334155; }
        th { background: #f8fafc; color: #475569; font-weight: 600; }
        body.dark th { background: #0f172a; color: #cbd5e1; }
        td { color: #64748b; }
        body.dark td { color: #94a3b8; }
        tr:hover { background: #f8fafc; }
        body.dark tr:hover { background: #0f172a; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-primary { background: #dbeafe; color: #2563eb; }
        .badge-warning { background: #fed7aa; color: #ea580c; }
        body.dark .badge-success { background: rgba(22,163,74,0.2); color: #86efac; }
        body.dark .badge-primary { background: rgba(37,99,235,0.2); color: #93c5fd; }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        
        .btn-icon {
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: transform 0.2s;
        }
        .btn-icon:hover { transform: translateY(-2px); }
        .btn-edit { background: #f59e0b; color: white; }
        .btn-primary { background: #667eea; color: white; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        
        .video-preview {
            width: 120px;
            border-radius: 8px;
            overflow: hidden;
        }
        .video-preview video { width: 100%; }
        
        @media (max-width: 768px) {
            .nav-container { flex-direction: column; height: auto; padding: 15px; }
            .nav-links { margin-top: 10px; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr; }
            .course-meta { gap: 10px; }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo"><i class="fas fa-graduation-cap"></i><span>LearnHub Admin</span></div>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
                <a href="modules.php"><i class="fas fa-video"></i> Modules</a>
                <a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a>
                <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <a href="courses.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Courses</a>
        
        <!-- Course Header -->
        <div class="course-header">
            <h1><i class="fas fa-book"></i> <?= htmlspecialchars($course['title']) ?></h1>
            <p><?= nl2br(htmlspecialchars($course['description'] ?? 'No description available')) ?></p>
            <div class="course-meta">
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor'] ?? 'Admin') ?></span>
                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($course['duration'] ?? 'Self-paced') ?></span>
                <span><i class="fas fa-signal"></i> <?= ucfirst($course['level'] ?? 'Beginner') ?></span>
                <span><i class="fas fa-dollar-sign"></i> $<?= number_format($course['price'] ?? 0, 2) ?></span>
                <span><i class="fas fa-calendar"></i> Created: <?= date('M d, Y', strtotime($course['created_at'] ?? 'now')) ?></span>
                <span><i class="fas fa-<?= ($course['status'] ?? 'active') === 'active' ? 'check-circle' : 'ban' ?>"></i> <?= ucfirst($course['status'] ?? 'Active') ?></span>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $course['module_count'] ?? 0 ?></div>
                <div class="stat-label">Total Modules</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $course['enrollment_count'] ?? 0 ?></div>
                <div class="stat-label">Total Enrollments</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $course['completed_count'] ?? 0 ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $course['module_count'] > 0 ? round(($course['completed_count'] / $course['enrollment_count']) * 100) : 0 ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
        </div>
        
        <!-- Modules Section -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-video"></i> Course Modules</h2>
                <a href="modules.php?course_id=<?= $course['id'] ?>" class="btn-icon btn-edit">
                    <i class="fas fa-plus-circle"></i> Manage Modules
                </a>
            </div>
            <?php if(empty($modules)): ?>
                <div class="empty-state">
                    <i class="fas fa-video" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No modules yet. Click "Manage Modules" to add your first module.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Module Title</th>
                                <th>Video</th>
                                <th>Assignments</th>
                                <th>Submissions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($modules as $m): ?>
                            <tr>
                                <td><?= $m['order_number'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($m['title']) ?></strong>
                                    <?php if($m['description']): ?>
                                        <br><small style="color: #94a3b8;"><?= htmlspecialchars(substr($m['description'], 0, 60)) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($m['video']): ?>
                                        <div class="video-preview">
                                            <video controls>
                                                <source src="../uploads/videos/<?= $m['video'] ?>" type="video/mp4">
                                            </video>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8;">No video</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-primary"><?= $m['assignment_count'] ?? 0 ?></span></td>
                                <td><span class="badge badge-success"><?= $m['submission_count'] ?? 0 ?></span></td>
                                <td>
                                    <a href="assignments.php?module_id=<?= $m['id'] ?>" class="btn-icon btn-primary" style="background: #8b5cf6;">
                                        <i class="fas fa-tasks"></i> Assignments
                                    </a>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Learning Objectives -->
        <?php if(!empty($course['objectives'])): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-bullseye"></i> Learning Objectives</h2>
            </div>
            <div style="line-height: 1.6; color: #64748b;">
                <?= nl2br(htmlspecialchars($course['objectives'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Requirements -->
        <?php if(!empty($course['requirements'])): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list-check"></i> Requirements</h2>
            </div>
            <div style="line-height: 1.6; color: #64748b;">
                <?= nl2br(htmlspecialchars($course['requirements'])) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Enrollments -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-users"></i> Recent Enrollments</h2>
                <a href="enrollments.php?course_id=<?= $course['id'] ?>" class="btn-icon btn-primary">View All</a>
            </div>
            <?php if(empty($enrollments)): ?>
                <div class="empty-state">
                    <i class="fas fa-user-plus" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No enrollments yet.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Email</th>
                                <th>Enrolled Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($enrollments as $e): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($e['fullname']) ?></strong>
                                 </td>
                                <td><?= htmlspecialchars($e['email']) ?></td>
                                <td><?= date('M d, Y', strtotime($e['enrolled_at'])) ?> </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span><?= $e['progress'] ?? 0 ?>%</span>
                                        <div style="flex: 1; background: #e2e8f0; height: 6px; border-radius: 3px;">
                                            <div style="width: <?= $e['progress'] ?? 0 ?>%; background: #667eea; height: 6px; border-radius: 3px;"></div>
                                        </div>
                                    </div>
                                 </td>
                                <td>
                                    <span class="badge <?= ($e['status'] ?? 'active') === 'active' ? 'badge-success' : 'badge-warning' ?>">
                                        <?= ucfirst($e['status'] ?? 'Active') ?>
                                    </span>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                    }<?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            if (body.classList.contains('dark')) {
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
            } else {
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
            }
        });
    </script>
</body>
</html>
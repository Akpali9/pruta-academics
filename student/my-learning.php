<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/utilities.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$user_id = $_SESSION['user_id'];

// Check what columns exist in courses table
$columns = $pdo->query("SHOW COLUMNS FROM courses");
$existingColumns = [];
while ($col = $columns->fetch(PDO::FETCH_ASSOC)) {
    $existingColumns[] = $col['Field'];
}

// Build SELECT query based on existing columns
$selectFields = [
    "e.id as enrollment_id",
    "e.course_id",
    "e.enrolled_at",
    "e.progress",
    "e.status as enrollment_status",
    "e.payment_status",
    "c.id as course_id",
    "c.title",
    "c.description"
];

// Add optional columns if they exist
if (in_array('instructor', $existingColumns)) {
    $selectFields[] = "c.instructor";
} else {
    $selectFields[] = "'Admin' as instructor";
}

if (in_array('duration', $existingColumns)) {
    $selectFields[] = "c.duration";
} else {
    $selectFields[] = "'Self-paced' as duration";
}

if (in_array('level', $existingColumns)) {
    $selectFields[] = "c.level";
} else {
    $selectFields[] = "'beginner' as level";
}

if (in_array('price', $existingColumns)) {
    $selectFields[] = "c.price";
} else {
    $selectFields[] = "0.00 as price";
}

if (in_array('thumbnail', $existingColumns) || in_array('image_url', $existingColumns)) {
    $imgField = in_array('thumbnail', $existingColumns) ? 'thumbnail' : 'image_url';
    $selectFields[] = "c.$imgField as image_url";
} else {
    $selectFields[] = "NULL as image_url";
}

if (in_array('created_at', $existingColumns)) {
    $selectFields[] = "c.created_at as course_created";
} else {
    $selectFields[] = "NOW() as course_created";
}

// Check if enrollments table has the columns we need
$enrollmentColumns = $pdo->query("SHOW COLUMNS FROM enrollments");
$enrollmentExisting = [];
while ($col = $enrollmentColumns->fetch(PDO::FETCH_ASSOC)) {
    $enrollmentExisting[] = $col['Field'];
}

// Build order by clause
$orderBy = "e.enrolled_at DESC";
if (in_array('last_accessed', $enrollmentExisting)) {
    $orderBy = "e.last_accessed DESC, e.enrolled_at DESC";
}

$selectQuery = "SELECT " . implode(", ", $selectFields) . " 
                FROM enrollments e
                JOIN courses c ON e.course_id = c.id
                WHERE e.user_id = ?
                ORDER BY $orderBy";

$stmt = $pdo->prepare($selectQuery);
$stmt->execute([$user_id]);
$enrolledCourses = $stmt->fetchAll();

// Get module counts for each course
$hasModulesTable = false;
try {
    $checkModules = $pdo->query("SHOW TABLES LIKE 'modules'");
    $hasModulesTable = $checkModules->rowCount() > 0;
} catch (PDOException $e) {
    $hasModulesTable = false;
}

foreach ($enrolledCourses as &$course) {
    $course['total_modules'] = 0;
    $course['completed_modules'] = 0;
    $course['last_module_id'] = null;
    $course['last_module_title'] = 'Start Learning';
    $course['progress'] = $course['progress'] ?? 0;
    
    if ($hasModulesTable) {
        // Get total modules
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM modules WHERE course_id = ?");
        $stmt->execute([$course['course_id']]);
        $total = $stmt->fetch();
        $course['total_modules'] = $total['total'] ?? 0;
        
        // Get completed modules
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as completed 
            FROM user_progress up
            JOIN modules m ON up.module_id = m.id
            WHERE up.user_id = ? AND m.course_id = ? AND up.completed = 1
        ");
        $stmt->execute([$user_id, $course['course_id']]);
        $completed = $stmt->fetch();
        $course['completed_modules'] = $completed['completed'] ?? 0;
        
        // Get first module
        $stmt = $pdo->prepare("
            SELECT m.id, m.title 
            FROM modules m
            WHERE m.course_id = ? 
            ORDER BY m.order_number ASC 
            LIMIT 1
        ");
        $stmt->execute([$course['course_id']]);
        $firstModule = $stmt->fetch();
        
        if ($firstModule) {
            $course['last_module_id'] = $firstModule['id'];
            $course['last_module_title'] = $firstModule['title'];
        }
        
        // Get next module based on progress
        $stmt = $pdo->prepare("
            SELECT m.id, m.title, up.completed_at
            FROM user_progress up
            JOIN modules m ON up.module_id = m.id
            WHERE up.user_id = ? AND m.course_id = ? AND up.completed = 1
            ORDER BY up.completed_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id, $course['course_id']]);
        $lastCompleted = $stmt->fetch();
        
        if ($lastCompleted) {
            $stmt = $pdo->prepare("
                SELECT id, title FROM modules 
                WHERE course_id = ? AND order_number > (
                    SELECT order_number FROM modules WHERE id = ?
                )
                ORDER BY order_number ASC LIMIT 1
            ");
            $stmt->execute([$course['course_id'], $lastCompleted['id']]);
            $nextModule = $stmt->fetch();
            
            if ($nextModule) {
                $course['last_module_id'] = $nextModule['id'];
                $course['last_module_title'] = $nextModule['title'];
            }
        }
        
        // Calculate progress
        if ($course['total_modules'] > 0) {
            $course['progress'] = max($course['progress'], round(($course['completed_modules'] / $course['total_modules']) * 100));
        }
    }
}
unset($course);

// Get statistics
$stats = [
    'total' => count($enrolledCourses),
    'in_progress' => 0,
    'completed' => 0,
    'total_progress' => 0
];

foreach ($enrolledCourses as $course) {
    if ($course['progress'] >= 100) {
        $stats['completed']++;
    } else {
        $stats['in_progress']++;
    }
    $stats['total_progress'] += $course['progress'];
}
$stats['avg_progress'] = $stats['total'] > 0 ? round($stats['total_progress'] / $stats['total']) : 0;

// Get recent activity
$recentActivities = [];
try {
    $checkProgress = $pdo->query("SHOW TABLES LIKE 'user_progress'");
    if ($checkProgress->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT up.*, c.title as course_title, m.title as module_title
            FROM user_progress up
            JOIN modules m ON up.module_id = m.id
            JOIN courses c ON m.course_id = c.id
            WHERE up.user_id = ? AND up.completed = 1
            ORDER BY up.completed_at DESC
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $recentActivities = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Failed to get recent activities: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Learning | LearnHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; transition: all 0.3s ease; }
        body.dark { background: #0f172a; }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
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
        .nav-links { display: flex; gap: 30px; align-items: center; flex-wrap: wrap; }
        .nav-links a {
            text-decoration: none;
            color: #475569;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        body.dark .nav-links a { color: #cbd5e1; }
        .nav-links a:hover, .nav-links a.active { color: #667eea; }
        .theme-toggle {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #475569;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white !important;
            padding: 8px 20px;
            border-radius: 8px;
        }
        
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        
        .page-header { margin-bottom: 30px; animation: slideDown 0.5s ease; }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .page-header h1 { font-size: 32px; color: #1e293b; }
        body.dark .page-header h1 { color: #f1f5f9; }
        .page-header p { color: #64748b; font-size: 16px; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        body.dark .stat-card { background: #1e293b; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .stat-info h3 { font-size: 28px; font-weight: bold; color: #1e293b; }
        body.dark .stat-info h3 { color: #f1f5f9; }
        .stat-info p { color: #64748b; margin-top: 5px; }
        
        .learning-layout {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        @media (max-width: 968px) { .learning-layout { grid-template-columns: 1fr; } }
        
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
            position: relative;
        }
        body.dark .course-card { background: #1e293b; }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
        
        .course-image {
            height: 140px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            position: relative;
        }
        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .course-level {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0,0,0,0.6);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            color: white;
        }
        .course-content { padding: 20px; }
        .course-title { font-size: 18px; font-weight: bold; margin-bottom: 8px; color: #1e293b; }
        body.dark .course-title { color: #f1f5f9; }
        .course-meta { display: flex; gap: 15px; margin-bottom: 15px; font-size: 12px; color: #94a3b8; }
        .progress-section { margin: 15px 0; }
        .progress-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 12px; color: #64748b; }
        .progress-bar { background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; }
        .progress-fill { background: linear-gradient(90deg, #667eea, #764ba2); height: 100%; transition: width 0.5s ease; }
        .module-stats { font-size: 12px; color: #64748b; margin: 10px 0; }
        .last-accessed { font-size: 11px; color: #94a3b8; margin: 10px 0; padding: 8px; background: #f8fafc; border-radius: 8px; }
        .course-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-outline { background: transparent; border: 2px solid #667eea; color: #667eea; }
        
        .sidebar { position: sticky; top: 100px; height: fit-content; }
        .card { background: white; border-radius: 15px; padding: 20px; margin-bottom: 20px; }
        body.dark .card { background: #1e293b; }
        .card h3 { font-size: 18px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: #1e293b; }
        body.dark .card h3 { color: #f1f5f9; }
        .activity-list { max-height: 400px; overflow-y: auto; }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .activity-icon {
            width: 35px;
            height: 35px;
            background: rgba(102,126,234,0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
        }
        .activity-title { font-size: 14px; font-weight: 500; color: #1e293b; }
        body.dark .activity-title { color: #f1f5f9; }
        .activity-time { font-size: 11px; color: #94a3b8; margin-top: 3px; }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            grid-column: 1/-1;
        }
        .empty-state i { font-size: 64px; color: #cbd5e1; margin-bottom: 20px; }
        
        @media (max-width: 768px) {
            .nav-container { flex-direction: column; height: auto; padding: 15px; }
            .nav-links { margin-top: 15px; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr; }
            .courses-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo"><i class="fas fa-graduation-cap"></i><span>LearnHub</span></div>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="courses.php"><i class="fas fa-book"></i> All Courses</a>
                <a href="my-learning.php" class="active"><i class="fas fa-play-circle"></i> My Learning</a>
                <a href="my-courses.php"><i class="fas fa-key"></i> Access</a>
                <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-play-circle"></i> My Learning</h1>
            <p>Continue where you left off and track your progress</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-book-open"></i></div><div class="stat-info"><h3><?= $stats['total'] ?></h3><p>Active Courses</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-spinner"></i></div><div class="stat-info"><h3><?= $stats['in_progress'] ?></h3><p>In Progress</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-info"><h3><?= $stats['completed'] ?></h3><p>Completed</p></div></div>
            <div class="stat-card"><div class="stat-icon"><i class="fas fa-chart-line"></i></div><div class="stat-info"><h3><?= $stats['avg_progress'] ?>%</h3><p>Average Progress</p></div></div>
        </div>

        <div class="learning-layout">
            <div>
                <?php if(empty($enrolledCourses)): ?>
                    <div class="empty-state"><i class="fas fa-book-open"></i><p>You haven't started any courses yet.</p><a href="courses.php" class="btn btn-primary" style="display: inline-block; width: auto;">Browse Courses</a></div>
                <?php else: ?>
                    <div class="courses-grid">
                        <?php foreach($enrolledCourses as $course): 
                            $progress = $course['progress'] ?? 0;
                            $totalModules = $course['total_modules'] ?? 0;
                            $completedModules = $course['completed_modules'] ?? 0;
                            $lastModuleId = $course['last_module_id'] ?? null;
                            $lastModuleTitle = $course['last_module_title'] ?? 'Start Learning';
                        ?>
                            <div class="course-card">
                                <div class="course-image">
                                    <?php if($course['image_url'] && file_exists("../" . $course['image_url'])): ?>
                                        <img src="../<?= $course['image_url'] ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-graduation-cap"></i>
                                    <?php endif; ?>
                                    <span class="course-level"><i class="fas fa-signal"></i> <?= ucfirst($course['level'] ?? 'Beginner') ?></span>
                                </div>
                                <div class="course-content">
                                    <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                                    <div class="course-meta">
                                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor'] ?? 'Admin') ?></span>
                                        <span><i class="fas fa-clock"></i> <?= htmlspecialchars($course['duration'] ?? 'Self-paced') ?></span>
                                    </div>
                                    <div class="progress-section">
                                        <div class="progress-header"><span>Course Progress</span><span><?= $progress ?>%</span></div>
                                        <div class="progress-bar"><div class="progress-fill" style="width: <?= $progress ?>%"></div></div>
                                    </div>
                                    <?php if($totalModules > 0): ?>
                                        <div class="module-stats"><i class="fas fa-list-check"></i> <?= $completedModules ?> of <?= $totalModules ?> modules completed</div>
                                    <?php endif; ?>
                                    <div class="last-accessed"><i class="fas fa-clock"></i> Continue: <?= htmlspecialchars($lastModuleTitle) ?></div>
                                    <div class="course-actions">
                                        <a href="learn.php?id=<?= $lastModuleId ? $lastModuleId : '#' ?>" class="btn btn-primary"><i class="fas fa-play"></i> Continue Learning</a>
                                        <a href="course.php?id=<?= $course['course_id'] ?>" class="btn btn-outline"><i class="fas fa-info-circle"></i> Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <div class="card">
                    <h3><i class="fas fa-history"></i> Recent Activity</h3>
                    <div class="activity-list">
                        <?php if(empty($recentActivities)): ?>
                            <div style="text-align: center; padding: 20px;"><i class="fas fa-inbox"></i><p>No recent activity</p></div>
                        <?php else: ?>
                            <?php foreach($recentActivities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon"><i class="fas fa-check-circle"></i></div>
                                    <div class="activity-details">
                                        <div class="activity-title">Completed: <?= htmlspecialchars($activity['module_title']) ?></div>
                                        <div class="activity-time"><i class="fas fa-calendar"></i> <?= date('M d, Y', strtotime($activity['completed_at'])) ?> • <?= htmlspecialchars($activity['course_title']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h3><i class="fas fa-lightbulb"></i> Learning Tips</h3>
                    <div style="padding: 10px;">
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;"><i class="fas fa-clock" style="color: #667eea;"></i><div><strong>Set a schedule</strong><p style="font-size: 12px;">Dedicate 30 minutes daily to your courses</p></div></div>
                        <div style="display: flex; gap: 10px; margin-bottom: 15px;"><i class="fas fa-pencil-alt" style="color: #667eea;"></i><div><strong>Take notes</strong><p style="font-size: 12px;">Writing helps retain information better</p></div></div>
                        <div style="display: flex; gap: 10px;"><i class="fas fa-users" style="color: #667eea;"></i><div><strong>Join discussions</strong><p style="font-size: 12px;">Engage with fellow students in forums</p></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') { body.classList.add('dark'); themeToggle.innerHTML = '<i class="fas fa-sun"></i>'; }
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            localStorage.setItem('theme', body.classList.contains('dark') ? 'dark' : 'light');
            themeToggle.innerHTML = body.classList.contains('dark') ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        });

        document.querySelectorAll('.progress-fill').forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => { bar.style.width = width; }, 200);
        });
    </script>
</body>
</html>
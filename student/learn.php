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
$module_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message_sent = isset($_GET['msg']) && $_GET['msg'] === 'sent';
$error_msg = isset($_GET['error']) ? urldecode($_GET['error']) : '';

if ($module_id <= 0) {
    die("Invalid module ID.");
}

// Get module details
$stmt = $pdo->prepare("
    SELECT m.*, c.title as course_title, c.instructor_id, c.id as course_id, c.description as course_description
    FROM modules m 
    JOIN courses c ON m.course_id = c.id 
    WHERE m.id = ?
");
$stmt->execute([$module_id]);
$module = $stmt->fetch();

if (!$module) {
    die("Module not found");
}

// Check enrollment and payment
$stmt = $pdo->prepare("
    SELECT * FROM enrollments 
    WHERE user_id = ? AND course_id = ? AND payment_status = 'approved' AND status = 'active'
");
$stmt->execute([$user_id, $module['course_id']]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    die("You don't have active access to this course.");
}

// Check expiry
if (!empty($enrollment['expires_at']) && strtotime($enrollment['expires_at']) < time()) {
    die("Your access to this course has expired. Please renew.");
}

// Ensure submissions table has required columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        user_id INT NOT NULL,
        answer TEXT NOT NULL,
        attachments VARCHAR(500),
        status VARCHAR(20) DEFAULT 'pending',
        score INT DEFAULT 0,
        feedback TEXT,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL
    )");
} catch (PDOException $e) {
    // Table might already exist
}

// Get assignment for this module
$stmt = $pdo->prepare("
    SELECT a.*, 
           s.id as submission_id,
           s.status as submission_status,
           s.score as submission_score,
           s.feedback as submission_feedback,
           s.answer as submission_answer,
           s.submitted_at as submission_date
    FROM assignments a
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
    WHERE a.module_id = ?
");
$stmt->execute([$user_id, $module_id]);
$currentAssignment = $stmt->fetch();

// Get all modules for progress tracking
$stmt = $pdo->prepare("
    SELECT m.*, 
           a.id as assignment_id,
           s.status as submission_status
    FROM modules m
    LEFT JOIN assignments a ON m.id = a.module_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.user_id = ?
    WHERE m.course_id = ?
    ORDER BY m.order_number
");
$stmt->execute([$user_id, $module['course_id']]);
$allModules = $stmt->fetchAll();

// Calculate progress
$completedModules = 0;
foreach ($allModules as $mod) {
    if ($mod['assignment_id'] && $mod['submission_status'] === 'passed') {
        $completedModules++;
    } elseif (!$mod['assignment_id']) {
        $completedModules++;
    }
}
$totalModules = count($allModules);
$progressPercentage = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;

// Find next and previous modules
$prevModule = null;
$nextModule = null;
foreach ($allModules as $index => $mod) {
    if ($mod['id'] == $module_id) {
        if (isset($allModules[$index - 1])) {
            $prevModule = $allModules[$index - 1];
        }
        if (isset($allModules[$index + 1])) {
            $nextModule = $allModules[$index + 1];
        }
        break;
    }
}

// Check if next module is locked
$nextModuleLocked = false;
if ($currentAssignment && $currentAssignment['submission_status'] !== 'passed') {
    $nextModuleLocked = true;
}

// Get instructor info
$stmt = $pdo->prepare("SELECT id, fullname, email FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$instructor = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($module['title']) ?> | LearnHub</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #f1f5f9; }
        
        /* Navbar */
        .navbar {
            background: #1e293b;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
        }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: bold; color: white; }
        .logo i { color: #667eea; }
        
        /* Desktop Navigation */
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .nav-links a {
            text-decoration: none;
            color: #cbd5e1;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links a:hover { color: #667eea; }
        .logout-btn { background: #ef4444; color: white !important; padding: 6px 16px; border-radius: 8px; }
        
        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
        }
        
        /* Mobile Navigation */
        .mobile-nav {
            display: none;
            position: absolute;
            top: 60px;
            left: 0;
            right: 0;
            background: #1e293b;
            flex-direction: column;
            padding: 20px;
            gap: 15px;
            border-top: 1px solid #334155;
            z-index: 999;
        }
        .mobile-nav a {
            text-decoration: none;
            color: #cbd5e1;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .mobile-nav a:hover {
            background: #334155;
            color: #667eea;
        }
        .mobile-nav .logout-btn {
            background: #ef4444;
            color: white;
            justify-content: center;
        }
        
        /* Main Layout - Responsive Grid */
        .learning-layout {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 25px;
        }
        
        /* Main Content */
        .main-content {
            background: #1e293b;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .video-container {
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            margin: 20px;
        }
        video { width: 100%; display: block; }
        
        .module-title { font-size: 28px; margin: 0 20px 10px; }
        .module-description { color: #94a3b8; line-height: 1.6; margin: 0 20px 20px; }
        
        /* Sidebar */
        .sidebar {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 80px;
        }
        
        .progress-bar {
            background: #334155;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            transition: width 0.5s ease;
        }
        
        .module-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 15px;
        }
        .module-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 5px;
        }
        .module-item.unlocked { cursor: pointer; }
        .module-item.unlocked:hover { background: #334155; }
        .module-item.locked { opacity: 0.5; cursor: not-allowed; }
        .module-item.active { background: linear-gradient(135deg, rgba(102,126,234,0.2), rgba(118,75,162,0.2)); }
        .module-icon { width: 30px; text-align: center; }
        
        /* Assignment Section */
        .assignment-section {
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            margin: 20px;
        }
        
        .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: #dcfce7; color: #16a34a; }
        .alert-info { background: #dbeafe; color: #2563eb; }
        .alert-warning { background: #fed7aa; color: #ea580c; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        
        .assignment-question {
            background: #0f172a;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
        .submission-status {
            background: #0f172a;
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-passed { background: #10b981; color: white; }
        .status-pending { background: #f59e0b; color: white; }
        .status-failed { background: #ef4444; color: white; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: #cbd5e1; }
        .form-group textarea, .form-group input {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-secondary { background: #334155; color: #cbd5e1; }
        .btn-disabled { background: #334155; color: #64748b; cursor: not-allowed; opacity: 0.5; }
        
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin: 20px;
            padding-top: 20px;
            border-top: 1px solid #334155;
        }
        
        .message-btn {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            background: #8b5cf6;
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .message-btn:hover { background: #7c3aed; transform: translateY(-2px); }
        
        .completion-section {
            margin: 20px;
            padding: 20px;
            background: #1e293b;
            border-radius: 12px;
            text-align: center;
        }
        .completion-success {
            background: #10b981;
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin: 20px;
        }
        .completion-success h3 { margin: 10px 0; }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: #1e293b;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #334155;
        }
        .close { cursor: pointer; font-size: 24px; color: #94a3b8; }
        .close:hover { color: white; }
        
        /* Responsive */
        @media (max-width: 968px) {
            .learning-layout {
                grid-template-columns: 1fr;
            }
            .sidebar {
                position: static;
                margin-top: 20px;
            }
            .nav-buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            .mobile-menu-btn {
                display: block;
            }
            .module-title {
                font-size: 22px;
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
                <a href="my-courses.php"><i class="fas fa-play-circle"></i> My Courses</a>
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
            <a href="my-courses.php"><i class="fas fa-play-circle"></i> My Courses</a>
            <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="learning-layout">
        <!-- Main Content -->
        <div class="main-content">
            <div class="video-container">
                <?php if($module['video'] && file_exists("../uploads/videos/" . $module['video'])): ?>
                    <video id="videoPlayer" controls>
                        <source src="../uploads/videos/<?= $module['video'] ?>" type="video/mp4">
                    </video>
                <?php else: ?>
                    <div style="background: #0f172a; padding: 60px; text-align: center;">
                        <i class="fas fa-video" style="font-size: 48px; color: #64748b;"></i>
                        <p style="margin-top: 15px;">Video content not available</p>
                    </div>
                <?php endif; ?>
            </div>

            <h1 class="module-title"><?= htmlspecialchars($module['title']) ?></h1>
            <div class="module-description"><?= nl2br(htmlspecialchars($module['description'] ?? 'No description available')) ?></div>

            <!-- ASSIGNMENT SECTION -->
            <div class="assignment-section">
                <h3><i class="fas fa-tasks"></i> Module Assignment</h3>
                
                <?php if($currentAssignment): ?>
                    <?php if($currentAssignment['submission_status'] === 'passed'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            <strong>Assignment Completed!</strong> You passed this assignment. The next module is now unlocked.
                        </div>
                    <?php elseif($currentAssignment['submission_status'] === 'pending'): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-clock"></i> 
                            <strong>Pending Review</strong> Your assignment has been submitted and is waiting for instructor review.
                        </div>
                    <?php elseif($currentAssignment['submission_status'] === 'failed'): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-times-circle"></i> 
                            <strong>Assignment Not Passed</strong> Please review the feedback and resubmit.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Assignment Required!</strong> You must complete and pass this assignment to unlock the next module.
                        </div>
                    <?php endif; ?>
                    
                    <div class="assignment-question">
                        <h4>Assignment: <?= htmlspecialchars($currentAssignment['title']) ?></h4>
                        <p style="margin-top: 10px;"><?= nl2br(htmlspecialchars($currentAssignment['question'])) ?></p>
                        <p style="margin-top: 10px; color: #667eea;"><strong>Points: <?= $currentAssignment['points'] ?></strong></p>
                    </div>
                    
                    <?php if($currentAssignment['submission_id']): ?>
                        <div class="submission-status">
                            <h4>Your Submission</h4>
                            <p style="margin-top: 10px;"><?= nl2br(htmlspecialchars($currentAssignment['submission_answer'])) ?></p>
                            <div style="margin-top: 10px;">
                                <span class="status-badge status-<?= $currentAssignment['submission_status'] ?>">
                                    <?= ucfirst($currentAssignment['submission_status']) ?>
                                </span>
                            </div>
                            <?php if($currentAssignment['submission_feedback']): ?>
                                <div style="margin-top: 15px; padding: 10px; background: #1e293b; border-radius: 8px;">
                                    <strong>Instructor Feedback:</strong>
                                    <p style="margin-top: 5px;"><?= nl2br(htmlspecialchars($currentAssignment['submission_feedback'])) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($currentAssignment['submission_status'] === 'failed'): ?>
                                <form method="POST" action="submit_assignment.php" enctype="multipart/form-data" style="margin-top: 20px;">
                                    <input type="hidden" name="assignment_id" value="<?= $currentAssignment['id'] ?>">
                                    <input type="hidden" name="module_id" value="<?= $module_id ?>">
                                    <input type="hidden" name="resubmit" value="1">
                                    <div class="form-group">
                                        <label>Resubmit Your Answer</label>
                                        <textarea name="answer" rows="5" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>Attachment (Optional)</label>
                                        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.txt,.jpg,.png">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Resubmit Assignment</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="submit_assignment.php" enctype="multipart/form-data">
                            <input type="hidden" name="assignment_id" value="<?= $currentAssignment['id'] ?>">
                            <input type="hidden" name="module_id" value="<?= $module_id ?>">
                            <div class="form-group">
                                <label>Your Answer *</label>
                                <textarea name="answer" rows="6" required placeholder="Type your answer here..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Attachment (Optional)</label>
                                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.txt,.jpg,.png">
                                <small style="color: #64748b;">PDF, DOC, TXT, JPG, PNG (Max 5MB)</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Assignment
                            </button>
                        </form>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        No assignment for this module. You can proceed to the next module.
                    </div>
                <?php endif; ?>
                
                <!-- Message Instructor Button -->
                <?php if($instructor): ?>
                <button class="message-btn" onclick="openMessageModal()">
                    <i class="fas fa-envelope"></i> Message Instructor
                </button>
                <?php endif; ?>
            </div>

            <!-- Navigation Buttons -->
            <div class="nav-buttons">
                <?php if($prevModule): ?>
                    <a href="learn.php?id=<?= $prevModule['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Previous Module
                    </a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
                
                <?php if($nextModule): ?>
                    <?php if($nextModuleLocked): ?>
                        <span class="btn btn-disabled">
                            <i class="fas fa-lock"></i> Complete Assignment to Unlock
                        </span>
                    <?php else: ?>
                        <a href="learn.php?id=<?= $nextModule['id'] ?>" class="btn btn-primary">
                            Next Module <i class="fas fa-arrow-right"></i>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <?php
                    $stmt = $pdo->prepare("SELECT status FROM enrollments WHERE user_id = ? AND course_id = ?");
                    $stmt->execute([$user_id, $module['course_id']]);
                    $courseStatus = $stmt->fetch();
                    $isCompleted = ($courseStatus['status'] ?? '') === 'completed';
                    ?>
                    
                    <?php if($isCompleted): ?>
                        <div class="completion-success">
                            <i class="fas fa-check-circle"></i>
                            <h3>Course Completed!</h3>
                            <p>Your certificate will be sent to your email after admin review.</p>
                        </div>
                    <?php else: ?>
                        <div class="completion-section">
                            <div class="alert alert-success">
                                <i class="fas fa-trophy"></i> 
                                <strong>Congratulations! You've reached the end of this course!</strong>
                                <p>Click the button below to mark this course as complete.</p>
                            </div>
                            <button onclick="markCourseComplete()" class="btn btn-primary" id="completeBtn">
                                <i class="fas fa-check-circle"></i> Mark as Complete
                            </button>
                            <div id="completionMessage" style="display: none; margin-top: 15px;"></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar">
            <h3><?= htmlspecialchars($module['course_title']) ?></h3>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progressPercentage ?>%"></div>
            </div>
            <p style="font-size: 12px; margin-top: 5px;"><?= $completedModules ?> of <?= $totalModules ?> modules completed</p>
            
            <div class="module-list">
                <?php foreach($allModules as $mod): 
                    $isActive = $mod['id'] == $module_id;
                    $isCompleted = $mod['submission_status'] === 'passed';
                    $isUnlocked = true;
                ?>
                    <?php if($isUnlocked): ?>
                        <a href="learn.php?id=<?= $mod['id'] ?>" class="module-item unlocked <?= $isActive ? 'active' : '' ?> <?= $isCompleted ? 'completed' : '' ?>" style="text-decoration: none;">
                            <div class="module-icon">
                                <?php if($isCompleted): ?>
                                    <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                <?php elseif($isActive): ?>
                                    <i class="fas fa-play"></i>
                                <?php else: ?>
                                    <i class="fas fa-file-video"></i>
                                <?php endif; ?>
                            </div>
                            <div><?= htmlspecialchars($mod['title']) ?></div>
                        </a>
                    <?php else: ?>
                        <div class="module-item locked">
                            <div class="module-icon"><i class="fas fa-lock"></i></div>
                            <div><?= htmlspecialchars($mod['title']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-envelope"></i> Message Instructor</h3>
                <span class="close" onclick="closeMessageModal()">&times;</span>
            </div>
            <form method="POST" action="send_message.php" enctype="multipart/form-data">
                <input type="hidden" name="course_id" value="<?= $module['course_id'] ?>">
                <input type="hidden" name="instructor_id" value="<?= $instructor['id'] ?? '' ?>">
                <input type="hidden" name="module_id" value="<?= $module_id ?>">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" rows="5" required></textarea>
                </div>
                <div class="form-group">
                    <label>Attachment (Optional)</label>
                    <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.png">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>
    </div>

    <script>
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
        
        function openMessageModal() {
            document.getElementById('messageModal').style.display = 'flex';
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        function markCourseComplete() {
            const btn = document.getElementById('completeBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
            
            fetch('mark_complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'course_id=<?= $module['course_id'] ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const messageDiv = document.getElementById('completionMessage');
                    messageDiv.style.display = 'block';
                    messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
                    btn.style.display = 'none';
                    setTimeout(() => { location.reload(); }, 2000);
                } else {
                    alert('Error: ' + data.message);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('Error marking course as complete. Please try again.');
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Video progress saving
        const video = document.getElementById('videoPlayer');
        if (video) {
            let saveTimeout;
            video.addEventListener('timeupdate', function() {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(() => {
                    const progressPercent = Math.floor((video.currentTime / video.duration) * 100);
                    fetch('save_progress.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'module_id=<?= $module_id ?>&progress=' + progressPercent
                    });
                }, 3000);
            });
            
            fetch('get_progress.php?module_id=<?= $module_id ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.progress && data.progress > 0 && video.duration) {
                        const savedTime = (data.progress / 100) * video.duration;
                        if (savedTime < video.duration - 5) {
                            video.currentTime = savedTime;
                        }
                    }
                });
        }
    </script>
</body>
</html>
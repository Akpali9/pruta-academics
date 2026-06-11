<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
require_once "../config/mailer.php";

enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireAdmin();

$message = "";
$messageType = "";

// Handle grading submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submission_id'])) {
    $submission_id = (int)$_POST['submission_id'];
    $status = $_POST['status'];
    $score = (int)$_POST['score'];
    $feedback = trim($_POST['feedback']);
    
    // Update submission
    $stmt = $pdo->prepare("
        UPDATE submissions 
        SET status = ?, score = ?, feedback = ?, reviewed_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$status, $score, $feedback, $submission_id]);
    
    // Get submission details for email
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.fullname, a.title as assignment_title, a.points, m.id as module_id, m.course_id
        FROM submissions s
        JOIN users u ON s.user_id = u.id
        JOIN assignments a ON s.assignment_id = a.id
        JOIN modules m ON a.module_id = m.id
        WHERE s.id = ?
    ");
    $stmt->execute([$submission_id]);
    $sub = $stmt->fetch();
    
    // Send email notification to student
    $subject = "Assignment Graded: " . $sub['assignment_title'];
    $message_body = "Dear {$sub['fullname']},\n\n";
    $message_body .= "Your assignment '{$sub['assignment_title']}' has been graded.\n\n";
    $message_body .= "Score: {$score}/{$sub['points']}\n";
    $message_body .= "Status: " . strtoupper($status) . "\n";
    $message_body .= "Feedback: {$feedback}\n\n";
    $message_body .= "Login to continue: http://localhost/pruta/student/learn.php?id={$sub['module_id']}\n\n";
    $message_body .= "Best regards,\nLearnHub Admin";
    
    sendMail($sub['email'], $subject, $message_body);
    
    $message = "Assignment graded successfully! Student has been notified.";
    $messageType = "success";
}

// Get all pending submissions
$submissions = $pdo->query("
    SELECT s.*, u.fullname, u.email, c.title as course_title, a.title as assignment_title, a.points, m.order_number
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN assignments a ON s.assignment_id = a.id
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE s.status = 'pending'
    ORDER BY s.submitted_at ASC
")->fetchAll();

// Get already graded submissions
$graded = $pdo->query("
    SELECT s.*, u.fullname, u.email, c.title as course_title, a.title as assignment_title, a.points
    FROM submissions s
    JOIN users u ON s.user_id = u.id
    JOIN assignments a ON s.assignment_id = a.id
    JOIN modules m ON a.module_id = m.id
    JOIN courses c ON m.course_id = c.id
    WHERE s.status != 'pending'
    ORDER BY s.reviewed_at DESC
    LIMIT 20
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Submissions | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; padding: 20px; }
        body.dark { background: #0f172a; }
        .container { max-width: 1200px; margin: 0 auto; }
        
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
        }
        h1 { font-size: 28px; color: #1e293b; margin-bottom: 5px; }
        body.dark h1 { color: #f1f5f9; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        body.dark .stat-card { background: #1e293b; }
        .stat-number { font-size: 28px; font-weight: bold; color: #667eea; }
        .stat-label { color: #64748b; margin-top: 5px; }
        
        .submission-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        body.dark .submission-card { border-color: #334155; }
        .student-info {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        body.dark .student-info { border-bottom-color: #334155; }
        .answer-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        body.dark .answer-box { background: #0f172a; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: 500; }
        select, input, textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        body.dark select, body.dark input, body.dark textarea {
            background: #0f172a;
            border-color: #334155;
            color: #f1f5f9;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-primary:hover { transform: translateY(-2px); }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending { background: #fed7aa; color: #ea580c; }
        .badge-passed { background: #dcfce7; color: #16a34a; }
        .badge-failed { background: #fee2e2; color: #dc2626; }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success { background: #dcfce7; color: #16a34a; }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .nav-container { flex-direction: column; height: auto; padding: 15px; }
            .nav-links { margin-top: 10px; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo"><i class="fas fa-graduation-cap"></i><span>LearnHub Admin</span></div>
            <div class="nav-links">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="review_submissions.php" class="active"><i class="fas fa-gavel"></i> Grade Submissions</a>
                <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></button>
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        
        <div class="card">
            <h1><i class="fas fa-gavel"></i> Grade Student Submissions</h1>
            <p>Review and grade assignment submissions. Students must pass to unlock next modules.</p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= count($submissions) ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count($graded) ?></div>
                <div class="stat-label">Graded</div>
            </div>
        </div>
        
        <?php if($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <!-- Pending Submissions -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-clock"></i> Pending Submissions (<?= count($submissions) ?>)</h2>
            </div>
            
            <?php if(empty($submissions)): ?>
                <p style="text-align: center; padding: 40px;">No pending submissions to grade.</p>
            <?php else: ?>
                <?php foreach($submissions as $sub): ?>
                <div class="submission-card">
                    <div class="student-info">
                        <strong><i class="fas fa-user"></i> <?= htmlspecialchars($sub['fullname']) ?></strong><br>
                        <small><i class="fas fa-envelope"></i> <?= htmlspecialchars($sub['email']) ?></small><br>
                        <small><i class="fas fa-book"></i> Course: <?= htmlspecialchars($sub['course_title']) ?></small><br>
                        <small><i class="fas fa-tasks"></i> Assignment: <?= htmlspecialchars($sub['assignment_title']) ?></small><br>
                        <small><i class="fas fa-calendar"></i> Submitted: <?= date('F j, Y g:i A', strtotime($sub['submitted_at'])) ?></small>
                    </div>
                    
                    <div class="answer-box">
                        <strong>Student's Answer:</strong>
                        <p style="margin-top: 10px;"><?= nl2br(htmlspecialchars($sub['answer'])) ?></p>
                        <?php if($sub['attachments']): ?>
                            <div style="margin-top: 10px;">
                                <a href="../<?= $sub['attachments'] ?>" target="_blank" class="btn-primary" style="padding: 5px 10px; font-size: 12px;">
                                    <i class="fas fa-download"></i> Download Attachment
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                        <div class="form-group">
                            <label>Grade Status</label>
                            <select name="status" required>
                                <option value="">Select Grade</option>
                                <option value="passed">Passed - Unlock Next Module</option>
                                <option value="failed">Failed - Student Must Resubmit</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Score (out of <?= $sub['points'] ?>)</label>
                            <input type="number" name="score" max="<?= $sub['points'] ?>" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>Feedback to Student</label>
                            <textarea name="feedback" rows="3" required placeholder="Provide feedback to help the student improve..."></textarea>
                        </div>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Submit Grade & Notify Student
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Already Graded Submissions -->
        <?php if(!empty($graded)): ?>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-check-circle"></i> Recently Graded</h2>
            </div>
            <table>
                <thead>
                    <tr><th>Student</th><th>Assignment</th><th>Score</th><th>Status</th><th>Graded On</th></tr>
                </thead>
                <tbody>
                    <?php foreach($graded as $g): ?>
                    <tr>
                        <td><?= htmlspecialchars($g['fullname']) ?></td>
                        <td><?= htmlspecialchars($g['assignment_title']) ?></td>
                        <td><?= $g['score'] ?>/<?= $g['points'] ?></td>
                        <td><span class="badge badge-<?= $g['status'] ?>"><?= ucfirst($g['status']) ?></span></td>
                        <td><?= date('M d, Y', strtotime($g['reviewed_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        if (localStorage.getItem('theme') === 'dark') {
            body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark');
            localStorage.setItem('theme', body.classList.contains('dark') ? 'dark' : 'light');
            themeToggle.innerHTML = body.classList.contains('dark') ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
        });
    </script>
</body>
</html>
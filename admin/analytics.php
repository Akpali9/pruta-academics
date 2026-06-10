<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireAdmin();

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$totalRevenue = $pdo->query("
SELECT COUNT(*) * 5000 FROM enrollments WHERE payment_status='approved'
")->fetchColumn();

$activeCourses = $pdo->query("
SELECT COUNT(*) FROM courses
")->fetchColumn();
?>

<h2>Analytical dashboard</h2>

<p>Total Users: <?= $totalUsers ?></p>
<p>Total Revenue: ₦<?= $totalRevenue ?></p>
<p>Active Courses: <?= $activeCourses ?></p>

<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireAdmin();

$totalStudents = $pdo->query("
SELECT COUNT(*) FROM users WHERE role='student'
")->fetchColumn();

$totalCourses = $pdo->query("
SELECT COUNT(*) FROM courses
")->fetchColumn();

$active = $pdo->query("
SELECT COUNT(*) FROM enrollments WHERE status='active'
")->fetchColumn();
?>

<h2>Analytics Dashboard</h2>

<p>Total Students: <?= $totalStudents ?></p>
<p>Total Courses: <?= $totalCourses ?></p>
<p>Active Enrollments: <?= $active ?></p>

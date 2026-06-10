<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireLogin();

$courses = $pdo->query("SELECT * FROM courses")->fetchAll();
?>

<h2>Available Courses</h2>

<?php foreach($courses as $course): ?>

<div style="border:1px solid #ccc; padding:10px; margin:10px;">
    <h3><?= $course['title'] ?></h3>
    <p><?= $course['description'] ?></p>
    <p>Price: ₦<?= $course['price'] ?></p>

    <a href="course.php?id=<?= $course['id'] ?>">
        View Course
    </a>
</div>

<?php endforeach; ?>

<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";

requireLogin();
?>

<h2>Welcome <?= $_SESSION['fullname'] ?></h2>

<a href="courses.php">View Courses</a> |
<a href="access.php">My Access</a> |
<a href="../logout.php">Logout</a>

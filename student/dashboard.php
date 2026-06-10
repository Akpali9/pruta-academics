<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();

requireLogin();
?>

<h2>Welcome <?= $_SESSION['fullname'] ?></h2>
<button onclick="toggleTheme()">Toggle Theme</button>
<script src="../assets/theme.js"></script>

<a href="courses.php">View Courses</a> |
<a href="access.php">My Access</a> |
<a href="../logout.php">Logout</a>

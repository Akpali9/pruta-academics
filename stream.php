<?php
require_once "config/database.php";
require_once "config/auth.php";
require_once "../config/secure.php";
securePage();

requireLogin();

$file = $_GET['file'];

// basic validation
$path = "uploads/videos/" . basename($file);

if(!file_exists($path)){
    die("File not found");
}

// force stream
header("Content-Type: video/mp4");
header("Content-Disposition: inline");

readfile($path);
exit;

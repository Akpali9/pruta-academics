<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";

requireLogin();

$user_id = $_SESSION['user_id'];

$assignment_id = $_POST['assignment_id'];
$answer = $_POST['answer'];

$file_path = null;

if(!empty($_FILES['file']['name'])){
    $file = time() . "_" . $_FILES['file']['name'];
    $tmp = $_FILES['file']['tmp_name'];

    $file_path = "../uploads/assignments/" . $file;
    move_uploaded_file($tmp, $file_path);
}

$stmt = $pdo->prepare("
INSERT INTO submissions
(assignment_id,user_id,answer,file)
VALUES (?,?,?,?)
");

$stmt->execute([
    $assignment_id,
    $user_id,
    $answer,
    $file_path
]);

echo "Submitted successfully";

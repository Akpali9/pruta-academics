<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireAdmin();

if($_SERVER['REQUEST_METHOD'] == "POST"){

    $title = $_POST['title'];
    $course_id = $_POST['course_id'];
    $order = $_POST['order'];

    $video = time() . "_" . $_FILES['video']['name'];
    $tmp = $_FILES['video']['tmp_name'];

    move_uploaded_file($tmp, "../uploads/videos/" . $video);

    $stmt = $pdo->prepare("
    INSERT INTO modules
    (course_id,title,video,module_order)
    VALUES (?,?,?,?)
    ");

    $stmt->execute([
        $course_id,
        $title,
        $video,
        $order
    ]);

    echo "Module uploaded";
}
?>

<form method="POST" enctype="multipart/form-data">

<input name="title" placeholder="Module title">

<input name="course_id" placeholder="Course ID">

<input name="order" placeholder="Module order">

<input type="file" name="video">

<button>Upload</button>

</form>

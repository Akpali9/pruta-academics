<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/session_lock.php";
require_once "../config/secure.php";
enforceSingleSession($pdo, $_SESSION['user_id']);
securePage();
requireLogin();

$module_id = $_GET['module_id'];

$stmt = $pdo->prepare("
SELECT * FROM assignments WHERE module_id=?
");
$stmt->execute([$module_id]);

$assignment = $stmt->fetch();
?>

<h2>Assignment</h2>

<p><?= $assignment['question'] ?></p>

<form method="POST" action="submit_assignment.php" enctype="multipart/form-data">

<input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">

<textarea name="answer" placeholder="Write answer"></textarea>

<input type="file" name="file">

<button type="submit">Submit</button>

</form>

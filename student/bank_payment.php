<?php
require_once "../config/database.php";
require_once "../config/auth.php";
require_once "../config/secure.php";
securePage();
requireLogin();

$course_id = $_GET['course_id'];
?>

<h2>Bank Payment</h2>

<h3>Send payment to:</h3>

<p>
Bank: GTBank <br>
Account: 0123456789 <br>
Name: Your Company Name
</p>

<form method="POST" action="submit_bank_payment.php" enctype="multipart/form-data">

<input type="hidden" name="course_id" value="<?= $course_id ?>">

<input type="text" name="bank_reference" placeholder="Bank Transfer Reference" required>

<input type="file" name="receipt" required>

<button type="submit">Submit Payment</button>

</form>

<?php
require_once "../config/secure.php";
securePage();
header("Location: login.php");
exit;

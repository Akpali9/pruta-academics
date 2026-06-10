<?php

session_start();

session_destroy();

header("Location: login.php");
require_once "../config/secure.php";
securePage();

exit;

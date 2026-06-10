<?php

require_once "device.php";

function runSecurityMiddleware()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }

    validateDevice();
}

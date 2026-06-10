<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth()
{
    if (!isset($_SESSION['user_id'])) {

        // prevent cached protected pages
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");

        header("Location: /login.php");
        exit;
    }
}

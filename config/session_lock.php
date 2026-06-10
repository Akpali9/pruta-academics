<?php

function enforceSingleSession($pdo, $user_id)
{
    if (!isset($_SESSION)) {
        session_start();
    }

    $current_session = session_id();

    // check existing session in DB
    $stmt = $pdo->prepare("
        SELECT session_id
        FROM user_sessions
        WHERE user_id = ?
    ");

    $stmt->execute([$user_id]);
    $saved_session = $stmt->fetchColumn();

    // if session exists and mismatch → block access
    if ($saved_session && $saved_session !== $current_session) {
        session_destroy();
        die("⚠ Account already active on another device.");
    }

    // store/update session
    $stmt = $pdo->prepare("
        REPLACE INTO user_sessions (user_id, session_id, last_active)
        VALUES (?, ?, NOW())
    ");

    $stmt->execute([$user_id, $current_session]);
}

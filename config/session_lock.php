<?php

function enforceSingleSession($pdo, $user_id)
{
    session_start();

    $session_id = session_id();

    $stmt = $pdo->prepare("
        SELECT session_id FROM user_sessions
        WHERE user_id=?
    ");

    $stmt->execute([$user_id]);
    $existing = $stmt->fetchColumn();

    if($existing && $existing !== $session_id){
        die("Another device is using this account.");
    }

    $stmt = $pdo->prepare("
        REPLACE INTO user_sessions (user_id, session_id)
        VALUES (?,?)
    ");

    $stmt->execute([$user_id, $session_id]);
}

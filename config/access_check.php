<?php

function checkExpiry($enrollment)
{
    // no expiry set → block access for safety
    if (!isset($enrollment['expires_at'])) {
        return true;
    }

    // convert to timestamp
    $expiryTime = strtotime($enrollment['expires_at']);

    // if expired
    if ($expiryTime < time()) {

        global $pdo;

        // auto mark as expired in DB (important for SaaS control)
        $stmt = $pdo->prepare("
            UPDATE enrollments
            SET status='expired'
            WHERE id=?
        ");

        $stmt->execute([$enrollment['id']]);

        return true;
    }

    return false;
}

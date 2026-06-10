<?php

function logAction($pdo, $user_id, $action)
{
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action)
        VALUES (?, ?)
    ");

    $stmt->execute([$user_id, $action]);
}

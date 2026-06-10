<?php

function checkLoginAttempts($pdo, $email)
{
    $stmt = $pdo->prepare("
        SELECT attempts, last_attempt
        FROM login_attempts
        WHERE email=?
    ");

    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if ($row) {

        if ($row['attempts'] >= 5) {

            $diff = time() - strtotime($row['last_attempt']);

            // lock for 15 minutes
            if ($diff < 900) {
                die("Too many attempts. Try again later.");
            } else {
                resetAttempts($pdo, $email);
            }
        }
    }
}

function addLoginAttempt($pdo, $email)
{
    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (email, attempts, last_attempt)
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE
        attempts = attempts + 1,
        last_attempt = NOW()
    ");

    $stmt->execute([$email]);
}

function resetAttempts($pdo, $email)
{
    $stmt = $pdo->prepare("
        UPDATE login_attempts
        SET attempts=0
        WHERE email=?
    ");

    $stmt->execute([$email]);
}

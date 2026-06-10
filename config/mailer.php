<?php

function sendMail($to, $subject, $message)
{
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/plain;charset=UTF-8" . "\r\n";
    $headers .= "From: Pruta Academy <no-reply@prutaacademy.com>" . "\r\n";

    $result = mail($to, $subject, $message, $headers);

    if (!$result) {
        error_log("Email failed to send to: " . $to);
        return false;
    }

    return true;
}

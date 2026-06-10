<?php

function getUserIP()
{
    return $_SERVER['REMOTE_ADDR'];
}

function getDeviceHash()
{
    return hash('sha256',
        $_SERVER['HTTP_USER_AGENT'] .
        ($_SERVER['REMOTE_ADDR'] ?? '')
    );
}

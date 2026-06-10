<?php

function secureSessionStart()
{
    session_start();

    session_regenerate_id(true); // prevents fixation

    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // use HTTPS only in production
}

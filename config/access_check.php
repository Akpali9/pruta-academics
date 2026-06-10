<?php

function checkExpiry($enrollment){

    if(!$enrollment['expires_at']) return false;

    if(strtotime($enrollment['expires_at']) < time()){
        return true; // expired
    }

    return false;
}

<?php
session_start();

define('BASE_PATH', dirname(__DIR__));

function base_path($p=''){ return BASE_PATH.($p?'/'.$p:''); }

function view($v,$d=[]){
    extract($d);
    ob_start();
    require base_path('resources/views/'.$v.'.php');
    return ob_get_clean();
}

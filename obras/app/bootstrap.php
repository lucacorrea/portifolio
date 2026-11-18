<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function(string $class): void {
 if (!str_starts_with($class,'App\\')) return;
 $path=BASE_PATH.'/app/'.str_replace('\\','/',substr($class,4)).'.php';
 if (is_file($path)) require $path;
});
\App\Core\Env::loadArrayFile(BASE_PATH.'/config/app.php');
\App\Core\Env::loadArrayFile(BASE_PATH.'/config/database.php');
\App\Core\Env::loadArrayFile(BASE_PATH.'/config/database.local.php');
date_default_timezone_set((string)\App\Core\Env::get('APP_TIMEZONE','America/Manaus'));
function base_session_path(): string {
 $dir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/index.php')));
 return ($dir==='/' || $dir==='.')?'/':rtrim($dir,'/').'/';
}
if (PHP_SAPI!=='cli') {
 $https=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['SERVER_PORT']??'')==='443');
 ini_set('session.use_strict_mode','1'); ini_set('session.use_only_cookies','1');
 ini_set('session.cookie_httponly','1');
 session_name('OBRAS360SESSID');
 session_set_cookie_params(['lifetime'=>0,'path'=>(base_session_path()),'secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
 header('X-Content-Type-Options: nosniff');
 header('X-Frame-Options: DENY');
 header('Referrer-Policy: strict-origin-when-cross-origin');
 header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
 header('Cache-Control: no-store, private');
 header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://unpkg.com; style-src \'self\' https://unpkg.com; img-src \'self\' data: https://tile.openstreetmap.org https://*.tile.openstreetmap.org; connect-src \'self\' https://tile.openstreetmap.org https://*.tile.openstreetmap.org; font-src \'self\'; object-src \'none\'; frame-ancestors \'none\'; base-uri \'self\'; form-action \'self\'');
}
require BASE_PATH.'/app/helpers.php';

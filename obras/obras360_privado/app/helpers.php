<?php
declare(strict_types=1);
use App\Core\Auth;
use App\Core\Csrf;
function h(mixed $value): string {return htmlspecialchars((string)($value??''),ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');}
function base_uri(): string {
 $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/index.php'));
 $dir=dirname($script);
 return ($dir==='/'||$dir==='.'||$dir==='\\')?'':rtrim($dir,'/');
}
function asset_url(string $asset): string {return base_uri().'/assets/'.ltrim($asset,'/');}
function url(string $route='dashboard', array $params=[]): string {return base_uri().'/index.php?'.http_build_query(['r'=>$route]+$params);}
function redirect(string $route='dashboard',array $params=[]): never {header('Location: '.url($route,$params),true,303);exit;}
function csrf_field(): string {return '<input type="hidden" name="_csrf" value="'.h(Csrf::token()).'">';}
function flash(string $message,string $type='success'): void {$_SESSION['flash']=['text'=>$message,'type'=>$type];}
function take_flash(): ?array {$x=$_SESSION['flash']??null;unset($_SESSION['flash']);return $x;}
function error_response(int $code,string $message='Requisição inválida.'): never {http_response_code($code);echo '<!doctype html><html lang="pt-BR"><meta charset="UTF-8"><title>OBRAS360</title><body style="font:18px Arial;padding:3rem"><h1>'.h($code).'</h1><p>'.h($message).'</p><a href="'.h(url('dashboard')).'">Voltar</a></body></html>';exit;}
function render(string $view,array $data=[]): void {
 $viewFile=BASE_PATH.'/app/Views/'.$view.'.php';
 if (!is_file($viewFile)) error_response(404,'Página não encontrada.');
 extract($data,EXTR_SKIP);
 $user=Auth::user();$modules=require BASE_PATH.'/config/modules.php';$flash=take_flash();
 require BASE_PATH.'/app/Views/layouts/header.php';
 require $viewFile;
 require BASE_PATH.'/app/Views/layouts/footer.php';
}
function money(mixed $v): string {return 'R$ '.number_format((float)$v,2,',','.');}
function numeric(mixed $v,int $digits=0): string {return number_format((float)$v,$digits,',','.');}
function datebr(mixed $v): string {if (!$v) return '—';try {return (new DateTimeImmutable((string)$v))->format('d/m/Y');} catch(Throwable) {return (string)$v;}}
function active_status(string $status): string {
 $s=function_exists('iconv')?(iconv('UTF-8','ASCII//TRANSLIT',mb_case_fallback($status))?:strtolower($status)):strtolower($status);
 if (preg_match('/conclu|aprov|normal|publicad|bom estado|em uso/',$s)) return 'ok';
 if (preg_match('/paralis|atras|critic|urgente|estoque baixo|manutencao/',$s)) return 'bad';
 if (preg_match('/pendente|atencao|aguard|analise|revisao/',$s)) return 'warn';
 return 'info';
}
function mb_case_fallback(string $value): string {return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value);}
function role_name(string $role): string {return ['admin'=>'Administrador','gestor'=>'Gestor','tecnico'=>'Técnico','consulta'=>'Consulta'][$role]??$role;}
function site_time(?string $dbDate): string {if (!$dbDate) return '—';try{return (new DateTimeImmutable($dbDate,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone((string)App\Core\Env::get('APP_TIMEZONE','America/Manaus')))->format('d/m/Y H:i');}catch(Throwable){return $dbDate;}}
function route_active(string $route): bool {return ($_GET['r']??'dashboard')===$route;}
function ic(string $name,string $class=''): string {return '<svg class="icon '.h($class).'" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="'.h(asset_url('icons.svg')).'#'.h($name).'"></use></svg>';}
function status_pill(string $status): string {return '<span class="pill '.h(active_status($status)).'"><span class="pill-dot"></span>'.h($status).'</span>';}
function display_cell(string $key,mixed $value,array $row=[],array $workChoices=[]): string {
 if ($key==='work_id')return h(\App\Core\Repository::workName($value===null?null:(int)$value,$workChoices));
 if ($key==='status'||$key==='condition_status'||$key==='priority')return status_pill((string)$value);
 if ($key==='progress')return '<div class="mini-progress"><div style="width:'.max(0,min(100,(float)$value)).'%"></div></div><span class="progress-label">'.numeric($value,0).'%</span>';
 if ($key==='value'||$key==='budget')return h(money($value));
 if (in_array($key,['date','start_date','end_date','due_date','inspection_date','opened_on','document_date'],true))return h(datebr($value));
 if (in_array($key,['quantity','minimum','area'],true))return h(numeric($value,2));
 return h($value?:'—');
}
function mb_substr_fallback(string $s,int $len): string {return function_exists('mb_substr')?mb_substr($s,0,$len,'UTF-8'):(iconv_substr($s,0,$len,'UTF-8')?:substr($s,0,$len));}

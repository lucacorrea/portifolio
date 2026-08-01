<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/admin-guard.php';
function admin_post(): void { global $csrf; if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; } $csrf->requireValid($_POST['csrf_token'] ?? null); }
function admin_action_redirect(string $target): never { global $application; header('Location: ' . $application->redirect()->applicationUrl($target)); exit; }
function admin_action_error(\Throwable $exception, string $target): never { global $session; error_log('Administrative action failed [' . get_class($exception) . ']: ' . $exception->getMessage()); $message=$exception instanceof \InvalidArgumentException&&trim($exception->getMessage())!==''?$exception->getMessage():'Não foi possível concluir a operação.';$session->flash('danger',$message);admin_action_redirect($target); }
function admin_company_data(array $source, ?string $documentOverride=null, array $fallback=[]): array
{
    $value=static function(string $key,int $max,bool $required=false)use($source,$fallback):string{$raw=array_key_exists($key,$source)?$source[$key]:($fallback[$key]??'');$text=trim((string)$raw);if(str_contains($text,"\0")||strlen($text)>$max||($required&&$text===''))throw new \InvalidArgumentException('Revise os campos obrigatórios e seus tamanhos.');return $text;};
    $document=preg_replace('/\D/','',$documentOverride??$value('documento',20))??'';
    if(!in_array(strlen($document),[11,14],true))throw new \InvalidArgumentException('Informe um CPF ou CNPJ com 11 ou 14 números.');
    $type=$value('tipo_pessoa',20,true);if(!in_array($type,['fisica','juridica'],true))throw new \InvalidArgumentException('Tipo de pessoa inválido.');if((strlen($document)===11&&$type!=='fisica')||(strlen($document)===14&&$type!=='juridica'))throw new \InvalidArgumentException('O tipo de pessoa não corresponde ao documento informado.');
    $status=$value('status',20)?:'pendente';if(!in_array($status,['pendente','ativo','inativo','bloqueado'],true))throw new \InvalidArgumentException('Status inválido.');
    $email=$value('email',150);if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('E-mail inválido.');
    return ['razao_social'=>$value('razao_social',180,true),'nome_fantasia'=>$value('nome_fantasia',150),'documento'=>$document,'tipo_pessoa'=>$type,'segmento'=>$value('segmento',120,true),'contato_responsavel'=>$value('contato_responsavel',150),'telefone'=>$value('telefone',30),'email'=>$email,'status'=>$status];
}

<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/repository.php';
$pageDefinition=['title'=>'Configurações','description'=>'Parâmetros operacionais do Programa Meu Primeiro Emprego.','demo'=>false,'show_states'=>false,'actions'=>[],'modal'=>['title'=>'Configurações']];
$dbReady=pe_db_ready()&&pe_program_schema_ready();$message=null;$values=['bolsa_valor_padrao'=>'800.00','frequencia_minima'=>'75.00','municipio_padrao'=>'Coari'];
if($dbReady){$pdo=pe_db();if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['pe_action']??'')==='save_config'){try{pe_verify_csrf();$bolsa=max(0,pe_decimal($_POST['bolsa_valor_padrao']??'0'));$freq=max(0,min(100,pe_decimal($_POST['frequencia_minima']??'75')));$mun=trim((string)($_POST['municipio_padrao']??'Coari'));pe_save_config($pdo,'bolsa_valor_padrao',number_format($bolsa,2,'.',''),'Valor padrão da bolsa do programa');pe_save_config($pdo,'frequencia_minima',number_format($freq,2,'.',''),'Frequência mínima percentual para acompanhamento');pe_save_config($pdo,'municipio_padrao',$mun?:'Coari','Município padrão do programa');$message=['type'=>'success','text'=>'Configurações salvas com sucesso.'];}catch(Throwable $e){$message=['type'=>'danger','text'=>$e->getMessage()];}}foreach(array_keys($values) as $k)$values[$k]=pe_config_value($pdo,$k,$values[$k]);}
ob_start();
?>
<section class="content-card pe-form-card">
<?php if(!$dbReady):?><div class="alert alert-warning">Execute <code>database/primeiroEmprego/0003-primeiroEmprego-programa.sql</code>.</div><?php endif;?><?php if($message):?><div class="alert alert-<?=pe_h($message['type'])?>"><?=pe_h($message['text'])?></div><?php endif;?>
<div class="pe-form-header"><div><div class="card-kicker">Parâmetros</div><h2>Configuração do programa</h2><p>Valores usados por frequência, bolsas e novos cadastros. Alterações não modificam registros históricos.</p></div></div>
<form method="post" class="row g-3"><?=pe_csrf_field()?><input type="hidden" name="pe_action" value="save_config"><div class="col-md-4"><label class="form-label">Valor padrão da bolsa (R$)</label><input class="form-control" name="bolsa_valor_padrao" inputmode="decimal" value="<?=pe_h(number_format((float)$values['bolsa_valor_padrao'],2,',','.'))?>"></div><div class="col-md-4"><label class="form-label">Frequência mínima (%)</label><input class="form-control" name="frequencia_minima" type="number" min="0" max="100" step="0.01" value="<?=pe_h($values['frequencia_minima'])?>"></div><div class="col-md-4"><label class="form-label">Município padrão</label><input class="form-control" name="municipio_padrao" maxlength="100" value="<?=pe_h($values['municipio_padrao'])?>"></div><div class="col-12 text-end"><button class="btn btn-primary"><i class="bi bi-floppy"></i> Salvar configurações</button></div></form>
</section>
<?php $pageCustomContent=(string)ob_get_clean();

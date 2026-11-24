<?php
/** Exportação e impressão institucional do OBRAS360.
 * Os formatos imprimível e XLSX sempre consultam o banco e aplicam os mesmos filtros.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/relatorios.php';
try {
    $who=require_login();
    $module=rpt_query_string('modulo',45) ?: 'obras';
    rpt_allowed($module,$who);
    $format=rpt_query_string('formato',8) ?: 'imprimir';
    if(!in_array($format,['imprimir','xlsx'],true))throw new HttpError('Formato não suportado.',400);
    $query=rpt_query();
    $set=rpt_dataset($module,$who,$query);
    if($format==='xlsx')rpt_download_xlsx($set);
    header('Content-Type: text/html; charset=utf-8');
    echo rpt_print_document($set);
} catch (HttpError $e) {
    http_response_code($e->httpCode);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>OBRAS360 — Relatório</title><body style="font-family:system-ui;max-width:640px;margin:8vh auto;color:#183b45;padding:24px"><h1>Relatório indisponível</h1><p>'.rpt_html($e->getMessage()).'</p><p><a href="relatorios.php">Voltar à Central de Relatórios</a></p></body></html>';
} catch (Throwable $e) {
    $ref=strtoupper(bin2hex(random_bytes(4)));
    error_log('[OBRAS360 REPORT] '.$ref.' '.$e->getMessage());
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>OBRAS360 — Relatório</title><body style="font-family:system-ui;max-width:640px;margin:8vh auto;color:#183b45;padding:24px"><h1>Não foi possível gerar o relatório.</h1><p>Verifique a conexão do banco. Referência: '.rpt_html($ref).'.</p><p><a href="relatorios.php">Voltar à Central de Relatórios</a></p></body></html>';
}

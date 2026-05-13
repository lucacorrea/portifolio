<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_once APP_PATH . '/Services/WhatsAppService.php';

require_tenant_user();
verify_csrf();

$empresaId = current_empresa_id();
$action = (string) ($_POST['acao'] ?? '');

if (!$empresaId) {
    flash('error', 'Empresa não identificada para configurar o WhatsApp.');
    redirect('/app/conexao.php');
}

$connection = whatsapp_get_connection($empresaId);
$instanceName = whatsapp_sanitize_instance_name((string) ($_POST['instancia_nome'] ?? ($connection['instancia_nome'] ?? '')), $empresaId);
$phone = (string) ($_POST['telefone_conectado'] ?? ($connection['telefone_conectado'] ?? ''));

try {
    if ($action === 'salvar') {
        whatsapp_update_connection($empresaId, [
            'instancia_nome' => $instanceName,
            'telefone_conectado' => whatsapp_normalize_phone($phone) ?: null,
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        flash('success', 'Dados da conexão atualizados.');
        redirect('/app/conexao.php');
    }

    if ($action === 'gerar_qr') {
        $result = whatsapp_connect_instance($empresaId, $instanceName, $phone);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/app/conexao.php');
    }

    if ($action === 'atualizar_status') {
        $result = whatsapp_refresh_connection($empresaId);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/app/conexao.php');
    }

    if ($action === 'desconectar') {
        $result = whatsapp_disconnect_instance($empresaId);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/app/conexao.php');
    }

    if ($action === 'processar_cobrancas') {
        $summary = whatsapp_processar_cobrancas_empresa($empresaId);
        flash(
            'success',
            'Processamento concluído: '
            . (int) $summary['enviadas'] . ' enviadas, '
            . (int) $summary['falhas'] . ' falhas, '
            . (int) $summary['duplicadas'] . ' duplicadas ignoradas e '
            . (int) $summary['sem_telefone'] . ' sem telefone válido.'
        );
        redirect('/app/conexao.php');
    }
} catch (Throwable $e) {
    error_log('[WHATSAPP CONEXAO] ' . $e->getMessage());
    flash('error', 'Não foi possível processar a conexão do WhatsApp. Verifique a configuração e tente novamente.');
    redirect('/app/conexao.php');
}

flash('error', 'Ação inválida para conexão do WhatsApp.');
redirect('/app/conexao.php');

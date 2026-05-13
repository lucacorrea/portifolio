<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_once APP_PATH . '/Services/WhatsAppService.php';

require_tenant_user();
verify_csrf();

$empresaId = current_empresa_id();
$clienteId = (int) ($_POST['cliente_id'] ?? 0);
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$tipo = (string) ($_POST['tipo'] ?? 'manual');
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

if (!$empresaId) {
    flash('error', 'Empresa não identificada para envio da mensagem.');
    redirect('/app/mensagens-enviar.php');
}

if (!in_array($tipo, ['lembrete', 'cobranca', 'confirmacao', 'manual'], true)) {
    $tipo = 'manual';
}

try {
    $cliente = null;

    if ($clienteId > 0) {
        $stmt = db()->prepare('SELECT id, nome, telefone FROM clientes WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $clienteId,
            ':empresa_id' => $empresaId,
        ]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            flash('error', 'Cliente não encontrado para esta empresa.');
            redirect('/app/mensagens-enviar.php');
        }

        if ($telefone === '') {
            $telefone = (string) ($cliente['telefone'] ?? '');
        }
    }

    $result = whatsapp_send_text(
        $empresaId,
        $telefone,
        $mensagem,
        $tipo,
        $cliente ? (int) $cliente['id'] : null,
        null
    );

    flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect($result['ok'] ? '/app/mensagens.php' : '/app/mensagens-enviar.php');
} catch (Throwable $e) {
    error_log('[ENVIAR WHATSAPP] ' . $e->getMessage());
    flash('error', 'Não foi possível enviar a mensagem. Verifique a conexão do WhatsApp e tente novamente.');
    redirect('/app/mensagens-enviar.php');
}

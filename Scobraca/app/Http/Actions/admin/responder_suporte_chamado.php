<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_platform_admin();
verify_csrf();

function suporte_admin_reply_error(string $message, int $chamadoId = 0): never
{
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    flash('error', $message);
    redirect('/admin/suporte.php' . ($chamadoId > 0 ? '?chamado_id=' . $chamadoId : ''));
}

$usuario = current_user() ?? [];
$usuarioId = (int) ($usuario['id'] ?? 0);
$autorNome = trim((string) ($usuario['nome'] ?? 'Suporte FluxPay'));
$chamadoId = (int) ($_POST['chamado_id'] ?? 0);
$status = (string) ($_POST['status'] ?? 'em_atendimento');
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

if ($chamadoId <= 0) {
    suporte_admin_reply_error('Chamado inválido.');
}

if (!in_array($status, ['aberto', 'em_atendimento', 'aguardando_empresa', 'resolvido', 'fechado'], true)) {
    $status = 'em_atendimento';
}

if ($mensagem !== '' && strlen($mensagem) > 5000) {
    suporte_admin_reply_error('A mensagem deve ter no máximo 5.000 caracteres.', $chamadoId);
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare(
        "SELECT id, empresa_id, status
         FROM suporte_chamados
         WHERE id = :id
         FOR UPDATE"
    );
    $stmt->execute([':id' => $chamadoId]);
    $chamado = $stmt->fetch();

    if (!$chamado) {
        suporte_admin_reply_error('Chamado de suporte não encontrado.', $chamadoId);
    }

    if ($mensagem === '' && $status === (string) $chamado['status']) {
        suporte_admin_reply_error('Informe uma resposta ou altere o status do chamado.', $chamadoId);
    }

    if ($mensagem !== '') {
        $stmt = db()->prepare(
            "INSERT INTO suporte_mensagens (
                chamado_id,
                empresa_id,
                usuario_id,
                autor_tipo,
                autor_nome,
                mensagem,
                criado_em
            ) VALUES (
                :chamado_id,
                :empresa_id,
                :usuario_id,
                'admin',
                :autor_nome,
                :mensagem,
                NOW()
            )"
        );
        $stmt->execute([
            ':chamado_id' => $chamadoId,
            ':empresa_id' => (int) $chamado['empresa_id'],
            ':usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            ':autor_nome' => $autorNome !== '' ? $autorNome : 'Suporte FluxPay',
            ':mensagem' => $mensagem,
        ]);
    }

    $stmt = db()->prepare(
        "UPDATE suporte_chamados
         SET status = :status,
             ultima_resposta_origem = 'admin',
             atualizado_em = NOW(),
             fechado_em = CASE WHEN :status_fechado IN ('resolvido', 'fechado') THEN NOW() ELSE NULL END
         WHERE id = :id"
    );
    $stmt->execute([
        ':status' => $status,
        ':status_fechado' => $status,
        ':id' => $chamadoId,
    ]);

    db()->commit();
    flash('success', 'Atendimento atualizado com sucesso.');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('[RESPONDER SUPORTE ADMIN] ' . $e->getMessage());
    flash('error', 'Não foi possível atualizar o atendimento.');
}

redirect('/admin/suporte.php?chamado_id=' . $chamadoId);

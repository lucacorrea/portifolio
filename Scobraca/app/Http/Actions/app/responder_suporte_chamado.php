<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_tenant_user();
verify_csrf();

function suporte_app_reply_error(string $message, int $chamadoId = 0): never
{
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    flash('error', $message);
    redirect('/app/suporte.php' . ($chamadoId > 0 ? '?chamado_id=' . $chamadoId : ''));
}

$empresaId = current_empresa_id();
$usuario = current_user() ?? [];
$usuarioId = (int) ($usuario['id'] ?? 0);
$autorNome = trim((string) ($usuario['nome'] ?? 'Usuário da empresa'));
$chamadoId = (int) ($_POST['chamado_id'] ?? 0);
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

if (!$empresaId || $usuarioId <= 0) {
    suporte_app_reply_error('Sessão da empresa inválida.', $chamadoId);
}

if ($chamadoId <= 0 || $mensagem === '') {
    suporte_app_reply_error('Informe uma mensagem para responder ao suporte.', $chamadoId);
}

if (strlen($mensagem) > 5000) {
    suporte_app_reply_error('A mensagem deve ter no máximo 5.000 caracteres.', $chamadoId);
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare(
        "SELECT id, status
         FROM suporte_chamados
         WHERE id = :id AND empresa_id = :empresa_id
         FOR UPDATE"
    );
    $stmt->execute([
        ':id' => $chamadoId,
        ':empresa_id' => $empresaId,
    ]);
    $chamado = $stmt->fetch();

    if (!$chamado) {
        suporte_app_reply_error('Chamado não encontrado para esta empresa.', $chamadoId);
    }

    if (in_array((string) $chamado['status'], ['resolvido', 'fechado'], true)) {
        suporte_app_reply_error('Este chamado já foi encerrado. Abra um novo chamado se precisar continuar.', $chamadoId);
    }

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
            'empresa',
            :autor_nome,
            :mensagem,
            NOW()
        )"
    );
    $stmt->execute([
        ':chamado_id' => $chamadoId,
        ':empresa_id' => $empresaId,
        ':usuario_id' => $usuarioId,
        ':autor_nome' => $autorNome !== '' ? $autorNome : 'Usuário da empresa',
        ':mensagem' => $mensagem,
    ]);

    db()->prepare(
        "UPDATE suporte_chamados
         SET status = 'aberto',
             ultima_resposta_origem = 'empresa',
             atualizado_em = NOW()
         WHERE id = :id AND empresa_id = :empresa_id"
    )->execute([
        ':id' => $chamadoId,
        ':empresa_id' => $empresaId,
    ]);

    db()->commit();
    flash('success', 'Resposta enviada para o suporte.');
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('[RESPONDER SUPORTE EMPRESA] ' . $e->getMessage());
    flash('error', 'Não foi possível enviar a resposta.');
}

redirect('/app/suporte.php?chamado_id=' . $chamadoId);

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_tenant_user();
verify_csrf();

if (!suporte_ensure_tables()) {
    flash('error', 'O suporte ainda não está preparado no banco de dados. Tente novamente em instantes ou aplique a migration de suporte.');
    redirect('/app/suporte.php');
}

function suporte_app_redirect_with_error(string $message): never
{
    flash('error', $message);
    redirect('/app/suporte.php');
}

$empresaId = current_empresa_id();
$usuario = current_user() ?? [];
$usuarioId = (int) ($usuario['id'] ?? 0);
$autorNome = trim((string) ($usuario['nome'] ?? 'Usuário da empresa'));
$assunto = trim((string) ($_POST['assunto'] ?? ''));
$categoria = (string) ($_POST['categoria'] ?? 'outro');
$prioridade = (string) ($_POST['prioridade'] ?? 'media');
$mensagem = trim((string) ($_POST['mensagem'] ?? ''));

if (!$empresaId || $usuarioId <= 0) {
    suporte_app_redirect_with_error('Sessão da empresa inválida.');
}

if ($assunto === '' || $mensagem === '') {
    suporte_app_redirect_with_error('Informe o assunto e a mensagem do chamado.');
}

if (strlen($assunto) > 160) {
    suporte_app_redirect_with_error('O assunto deve ter no máximo 160 caracteres.');
}

if (strlen($mensagem) > 5000) {
    suporte_app_redirect_with_error('A mensagem deve ter no máximo 5.000 caracteres.');
}

if (!in_array($categoria, ['financeiro', 'tecnico', 'acesso', 'automacao', 'outro'], true)) {
    $categoria = 'outro';
}

if (!in_array($prioridade, ['baixa', 'media', 'alta', 'urgente'], true)) {
    $prioridade = 'media';
}

try {
    db()->beginTransaction();

    $stmt = db()->prepare(
        "INSERT INTO suporte_chamados (
            empresa_id,
            usuario_id,
            assunto,
            categoria,
            prioridade,
            status,
            ultima_resposta_origem,
            criado_em
        ) VALUES (
            :empresa_id,
            :usuario_id,
            :assunto,
            :categoria,
            :prioridade,
            'aberto',
            'empresa',
            NOW()
        )"
    );
    $stmt->execute([
        ':empresa_id' => $empresaId,
        ':usuario_id' => $usuarioId,
        ':assunto' => $assunto,
        ':categoria' => $categoria,
        ':prioridade' => $prioridade,
    ]);

    $chamadoId = (int) db()->lastInsertId();

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

    db()->commit();
    flash('success', 'Chamado aberto com sucesso. Nossa equipe poderá responder pelo painel administrativo.');
    redirect('/app/suporte.php?chamado_id=' . $chamadoId);
} catch (Throwable $e) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('[SALVAR SUPORTE CHAMADO] ' . $e->getMessage());
    flash('error', 'Não foi possível abrir o chamado de suporte.');
    redirect('/app/suporte.php');
}

<?php

declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/bootstrap/app.php';
require_tenant_user();
verify_csrf();

function configuracoes_redirect_error(string $message): never
{
    flash('error', $message);
    redirect('/app/configuracoes.php');
}

function configuracoes_int_range(string $value, int $min, int $max, int $default): int
{
    $number = filter_var($value, FILTER_VALIDATE_INT);

    if ($number === false) {
        return $default;
    }

    return max($min, min($max, (int) $number));
}

$empresaId = current_empresa_id();

if (!$empresaId) {
    configuracoes_redirect_error('Empresa não identificada para salvar configurações.');
}

$stmt = db()->prepare('SELECT nome, cnpj FROM empresas WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $empresaId]);
$empresa = $stmt->fetch() ?: [];

$stmt = db()->prepare('SELECT gemini_api_key FROM configuracoes_automacao WHERE empresa_id = :empresa_id LIMIT 1');
$stmt->execute([':empresa_id' => $empresaId]);
$configAtual = $stmt->fetch() ?: [];

$empresaNome = trim((string) ($_POST['empresa_nome'] ?? ''));
$empresaCnpj = only_digits((string) ($_POST['empresa_cnpj'] ?? ''));
$pixNome = trim((string) ($_POST['pix_nome_recebedor'] ?? ''));
$pixTipo = trim((string) ($_POST['pix_tipo_chave'] ?? 'CPF/CNPJ'));
$pixChave = trim((string) ($_POST['pix_chave'] ?? ''));
$statusAtraso = (string) ($_POST['status_cliente_apos_atraso'] ?? 'bloqueado');
$geminiApiKey = trim((string) ($_POST['gemini_api_key'] ?? ''));

if ($empresaNome === '') {
    $empresaNome = (string) ($empresa['nome'] ?? 'Empresa');
}

if ($empresaCnpj === '' && !empty($empresa['cnpj'])) {
    $empresaCnpj = only_digits((string) $empresa['cnpj']);
}

if ($empresaCnpj !== '' && !cnpj_valido($empresaCnpj)) {
    configuracoes_redirect_error('Informe um CNPJ válido ou deixe o campo em branco.');
}

if (!in_array($pixTipo, ['CPF/CNPJ', 'E-mail', 'Telefone', 'Aleatória'], true)) {
    $pixTipo = 'CPF/CNPJ';
}

if (!in_array($statusAtraso, ['ativo', 'bloqueado'], true)) {
    $statusAtraso = 'bloqueado';
}

if ($geminiApiKey === '') {
    $geminiApiKey = isset($_POST['limpar_gemini_api_key'])
        ? null
        : ($configAtual['gemini_api_key'] ?? null);
}

$params = [
    ':empresa_id' => $empresaId,
    ':empresa_nome' => substr($empresaNome, 0, 150),
    ':empresa_cnpj' => $empresaCnpj !== '' ? $empresaCnpj : null,
    ':automacao_ativa' => isset($_POST['automacao_ativa']) ? 1 : 0,
    ':dia_vencimento_padrao' => configuracoes_int_range((string) ($_POST['dia_vencimento_padrao'] ?? '10'), 1, 31, 10),
    ':bloquear_apos_dias' => configuracoes_int_range((string) ($_POST['bloquear_apos_dias'] ?? '7'), 0, 365, 7),
    ':pix_nome_recebedor' => $pixNome !== '' ? substr($pixNome, 0, 150) : null,
    ':pix_tipo_chave' => $pixTipo,
    ':pix_chave' => $pixChave !== '' ? substr($pixChave, 0, 150) : null,
    ':mensagem_10_dias' => trim((string) ($_POST['mensagem_10_dias'] ?? '')),
    ':mensagem_5_dias' => trim((string) ($_POST['mensagem_5_dias'] ?? '')),
    ':mensagem_dia_vencimento' => trim((string) ($_POST['mensagem_dia_vencimento'] ?? '')),
    ':mensagem_7_dias_atraso' => trim((string) ($_POST['mensagem_7_dias_atraso'] ?? '')),
    ':status_cliente_apos_atraso' => $statusAtraso,
    ':gemini_api_key' => $geminiApiKey,
];

try {
    $stmt = db()->prepare(
        "INSERT INTO configuracoes_automacao (
            empresa_id,
            empresa_nome,
            empresa_cnpj,
            automacao_ativa,
            dia_vencimento_padrao,
            bloquear_apos_dias,
            pix_nome_recebedor,
            pix_tipo_chave,
            pix_chave,
            mensagem_10_dias,
            mensagem_5_dias,
            mensagem_dia_vencimento,
            mensagem_7_dias_atraso,
            status_cliente_apos_atraso,
            gemini_api_key,
            criado_em
        ) VALUES (
            :empresa_id,
            :empresa_nome,
            :empresa_cnpj,
            :automacao_ativa,
            :dia_vencimento_padrao,
            :bloquear_apos_dias,
            :pix_nome_recebedor,
            :pix_tipo_chave,
            :pix_chave,
            :mensagem_10_dias,
            :mensagem_5_dias,
            :mensagem_dia_vencimento,
            :mensagem_7_dias_atraso,
            :status_cliente_apos_atraso,
            :gemini_api_key,
            NOW()
        )
        ON DUPLICATE KEY UPDATE
            empresa_nome = VALUES(empresa_nome),
            empresa_cnpj = VALUES(empresa_cnpj),
            automacao_ativa = VALUES(automacao_ativa),
            dia_vencimento_padrao = VALUES(dia_vencimento_padrao),
            bloquear_apos_dias = VALUES(bloquear_apos_dias),
            pix_nome_recebedor = VALUES(pix_nome_recebedor),
            pix_tipo_chave = VALUES(pix_tipo_chave),
            pix_chave = VALUES(pix_chave),
            mensagem_10_dias = VALUES(mensagem_10_dias),
            mensagem_5_dias = VALUES(mensagem_5_dias),
            mensagem_dia_vencimento = VALUES(mensagem_dia_vencimento),
            mensagem_7_dias_atraso = VALUES(mensagem_7_dias_atraso),
            status_cliente_apos_atraso = VALUES(status_cliente_apos_atraso),
            gemini_api_key = VALUES(gemini_api_key),
            atualizado_em = NOW()"
    );
    $stmt->execute($params);

    flash('success', 'Configurações salvas com sucesso.');
} catch (Throwable $e) {
    error_log('[SALVAR CONFIGURAÇÕES] ' . $e->getMessage());
    flash('error', 'Não foi possível salvar as configurações.');
}

redirect('/app/configuracoes.php');

<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_validate(): void
{
    $token = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';

    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Sessão expirada. Atualize a página e tente novamente.');
    }
}

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem,
    ];
}

function render_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $tipo = in_array($flash['tipo'], ['success', 'warning', 'danger', 'info'], true) ? $flash['tipo'] : 'info';
    $mensagem = htmlspecialchars((string) $flash['mensagem'], ENT_QUOTES, 'UTF-8');

    return '<div class="card section-card" style="padding:14px 18px;margin-bottom:18px"><span class="badge ' . $tipo . '">' . $mensagem . '</span></div>';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function only_digits(?string $valor): string
{
    return preg_replace('/\D+/', '', (string) $valor) ?: '';
}

function normalize_plate(?string $placa): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $placa) ?: '');
}

function nullable_string($valor): ?string
{
    $valor = trim((string) $valor);
    return $valor === '' ? null : $valor;
}

function decimal_value($valor): float
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return 0.0;
    }

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return round((float) $valor, 2);
}

function money_br($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function date_br(?string $data): string
{
    if (!$data) {
        return '-';
    }

    $timestamp = strtotime($data);
    return $timestamp ? date('d/m/Y', $timestamp) : '-';
}

function datetime_br(?string $data): string
{
    if (!$data) {
        return '-';
    }

    $timestamp = strtotime($data);
    return $timestamp ? date('d/m/Y H:i', $timestamp) : '-';
}

function audit(PDO $pdo, string $entidade, ?int $entidadeId, string $acao, $antes = null, $depois = null): void
{
    try {
        $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, dados_anteriores, dados_novos, ip, user_agent) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $entidade,
            $entidadeId,
            $acao,
            $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $depois === null ? null : json_encode($depois, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $_SERVER['REMOTE_ADDR'] ?? null,
            isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ]);
    } catch (Throwable $e) {
    }
}

function next_number(PDO $pdo, string $tabela, string $prefixo): string
{
    $permitidas = ['orcamentos', 'ordens_servico'];
    if (!in_array($tabela, $permitidas, true)) {
        throw new InvalidArgumentException('Tabela inválida.');
    }

    $ano = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) AS maior_id FROM {$tabela}");
    $maior = (int) (($stmt->fetch()['maior_id'] ?? 0) + 1);

    return $prefixo . '-' . $ano . '-' . str_pad((string) $maior, 5, '0', STR_PAD_LEFT);
}

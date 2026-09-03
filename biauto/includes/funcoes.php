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

function usuario_logado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

function usuario_admin(): bool
{
    return ($_SESSION['usuario_nivel'] ?? '') === 'admin';
}

function nome_usuario(): string
{
    return trim((string) ($_SESSION['usuario_nome'] ?? 'Usuário'));
}

function nivel_usuario(): string
{
    return (string) ($_SESSION['usuario_nivel'] ?? '');
}

function iniciais_usuario(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];

    if (!$partes) {
        return 'US';
    }

    $primeira = strtoupper(substr($partes[0], 0, 1));
    $ultima = count($partes) > 1 ? strtoupper(substr($partes[count($partes) - 1], 0, 1)) : '';

    return $primeira . $ultima;
}

function registrar_tentativa_login(PDO $pdo, string $email, bool $sucesso): void
{
    $stmt = $pdo->prepare('INSERT INTO login_tentativas (identificador, ip, sucesso, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([
        $email,
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        $sucesso ? 1 : 0,
        isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
    ]);
}

function login_bloqueado(PDO $pdo, string $email): bool
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_tentativas WHERE sucesso = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (identificador = ? OR ip = ?)");
    $stmt->execute([$email, $ip]);

    return (int) $stmt->fetchColumn() >= 5;
}

function audit(PDO $pdo, string $entidade, ?int $entidadeId, string $acao, $antes = null, $depois = null): void
{
    try {
        $usuarioId = !empty($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
        $stmt = $pdo->prepare('INSERT INTO auditoria (usuario_id, entidade, entidade_id, acao, dados_anteriores, dados_novos, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $usuarioId,
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

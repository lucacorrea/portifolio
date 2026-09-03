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

function modulo_pagina(string $pagina): string
{
    $mapa = [
        'index.php' => 'dashboard',
        'ordens.php' => 'ordens',
        'ordem_nova.php' => 'ordens',
        'ordem_detalhe.php' => 'ordens',
        'orcamentos.php' => 'orcamentos',
        'orcamento_novo.php' => 'orcamentos',
        'orcamento_detalhe.php' => 'orcamentos',
        'clientes.php' => 'clientes',
        'veiculos.php' => 'veiculos',
        'mecanicos.php' => 'mecanicos',
        'servicos.php' => 'servicos',
        'pecas.php' => 'pecas',
        'relatorios.php' => 'relatorios',
        'configuracoes.php' => 'configuracoes',
        'cadastro.php' => 'usuarios',
        'busca.php' => 'busca',
        'logout.php' => 'logout',
        'login.php' => 'login',
    ];

    return $mapa[$pagina] ?? 'dashboard';
}

function pode_acessar(string $modulo): bool
{
    $nivel = nivel_usuario();

    $permissoes = [
        'admin' => ['dashboard', 'ordens', 'orcamentos', 'clientes', 'veiculos', 'mecanicos', 'servicos', 'pecas', 'relatorios', 'configuracoes', 'usuarios', 'busca', 'logout'],
        'gerente' => ['dashboard', 'ordens', 'orcamentos', 'clientes', 'veiculos', 'mecanicos', 'servicos', 'pecas', 'relatorios', 'busca', 'logout'],
        'atendente' => ['dashboard', 'ordens', 'orcamentos', 'clientes', 'veiculos', 'servicos', 'pecas', 'busca', 'logout'],
        'mecanico' => ['dashboard', 'ordens', 'clientes', 'veiculos', 'servicos', 'pecas', 'busca', 'logout'],
        'leitor' => ['dashboard', 'ordens', 'orcamentos', 'clientes', 'veiculos', 'mecanicos', 'servicos', 'pecas', 'relatorios', 'busca', 'logout'],
    ];

    return in_array($modulo, $permissoes[$nivel] ?? [], true);
}

function pode_alterar(string $modulo): bool
{
    $nivel = nivel_usuario();

    $permissoes = [
        'admin' => ['ordens', 'orcamentos', 'clientes', 'veiculos', 'mecanicos', 'servicos', 'pecas', 'configuracoes', 'usuarios', 'logout'],
        'gerente' => ['ordens', 'orcamentos', 'clientes', 'veiculos', 'mecanicos', 'servicos', 'pecas', 'logout'],
        'atendente' => ['ordens', 'orcamentos', 'clientes', 'veiculos', 'logout'],
        'mecanico' => ['ordens', 'logout'],
        'leitor' => ['logout'],
    ];

    return in_array($modulo, $permissoes[$nivel] ?? [], true);
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

function iniciar_sessao_usuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['usuario_nome'] = (string) $usuario['nome'];
    $_SESSION['usuario_email'] = (string) $usuario['email'];
    $_SESSION['usuario_nivel'] = (string) $usuario['nivel'];
    $_SESSION['ultima_atividade'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function encerrar_sessao(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'] ?? '',
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function registrar_tentativa_login(PDO $pdo, string $email, bool $sucesso): void
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

    $stmt = $pdo->prepare('INSERT INTO login_tentativas (identificador, ip, sucesso, user_agent) VALUES (?, ?, ?, ?)');
    $stmt->execute([$email, $ip, $sucesso ? 1 : 0, $userAgent]);

    if ($sucesso) {
        $stmt = $pdo->prepare('DELETE FROM login_tentativas WHERE identificador = ? AND ip = ? AND sucesso = 0');
        $stmt->execute([$email, $ip]);
    }
}

function login_bloqueado(PDO $pdo, string $email): bool
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_tentativas WHERE identificador = ? AND ip = ? AND sucesso = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
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

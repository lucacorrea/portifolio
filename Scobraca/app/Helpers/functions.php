<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    if (preg_match('/[\r\n]/', $path) || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path) || str_starts_with($path, '//')) {
        $path = '/';
    }

    header('Location: ' . public_url($path));
    exit;
}

function public_base_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $configuredBase = trim((string) env('APP_BASE_PATH', ''));

    if ($configuredBase !== '') {
        $basePath = '/' . trim($configuredBase, '/');
        return $basePath === '/' ? '' : $basePath;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $publicPosition = strpos($scriptName, '/public/');

    if ($publicPosition !== false) {
        $basePath = substr($scriptName, 0, $publicPosition + 7);
        return rtrim($basePath, '/');
    }

    $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if (str_ends_with($scriptDir, '/public')) {
        $basePath = $scriptDir;
        return rtrim($basePath, '/');
    }

    $basePath = '';
    return $basePath;
}

function public_url(string $path = ''): string
{
    $basePath = public_base_path();
    $path = '/' . ltrim($path, '/');

    if ($path === '/') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    return $basePath . $path;
}

function asset_url(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $publicFile = PUBLIC_PATH . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $version = is_file($publicFile) ? (string) filemtime($publicFile) : (string) time();

    return public_url($path) . '?v=' . rawurlencode($version);
}

function url(string $path = ''): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    return $msg;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $redirectPath = null): void
{
    $token = $_POST['_csrf_token'] ?? '';
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    if (!is_string($token) || $token === '' || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        if ($redirectPath !== null) {
            flash('error', 'Sua sessão expirou. Abra a tela de login novamente e tente mais uma vez.');
            redirect($redirectPath);
        }

        http_response_code(419);
        exit('Sessão expirada ou token inválido. Volte e tente novamente.');
    }
}

function current_user(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function current_empresa_id(): ?int
{
    $id = $_SESSION['usuario']['empresa_id'] ?? null;
    return $id !== null ? (int) $id : null;
}

function moeda_br(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function data_br(?string $data): string
{
    $timestamp = $data ? strtotime($data) : false;
    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
}

function data_hora_br(?string $data): string
{
    $timestamp = $data ? strtotime($data) : false;
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '-';
}

function decimal_from_input(string $value): float
{
    $value = trim($value);
    $value = preg_replace('/[^\d,.-]/', '', $value) ?? '';

    if (str_contains($value, ',')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }

    return (float) $value;
}

function only_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function usuario_documento_tipo(string $documento): ?string
{
    $digits = only_digits($documento);

    if (strlen($digits) === 11) {
        return 'cpf';
    }

    if (strlen($digits) === 14) {
        return 'cnpj';
    }

    return null;
}

function cpf_valido(string $cpf): bool
{
    $cpf = only_digits($cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $soma = 0;

        for ($i = 0; $i < $t; $i++) {
            $soma += (int) $cpf[$i] * (($t + 1) - $i);
        }

        $digito = ((10 * $soma) % 11) % 10;

        if ((int) $cpf[$t] !== $digito) {
            return false;
        }
    }

    return true;
}

function cnpj_valido(string $cnpj): bool
{
    $cnpj = only_digits($cnpj);

    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $pesos = [
        [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ];

    for ($etapa = 0; $etapa < 2; $etapa++) {
        $soma = 0;
        $limite = 12 + $etapa;

        for ($i = 0; $i < $limite; $i++) {
            $soma += (int) $cnpj[$i] * $pesos[$etapa][$i];
        }

        $resto = $soma % 11;
        $digito = $resto < 2 ? 0 : 11 - $resto;

        if ((int) $cnpj[$limite] !== $digito) {
            return false;
        }
    }

    return true;
}

function documento_cpf_cnpj_valido(string $documento): bool
{
    $tipo = usuario_documento_tipo($documento);

    return $tipo === 'cpf'
        ? cpf_valido($documento)
        : ($tipo === 'cnpj' && cnpj_valido($documento));
}

function formatar_documento(?string $documento): string
{
    $digits = only_digits((string) $documento);

    if (strlen($digits) === 11) {
        return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
    }

    if (strlen($digits) === 14) {
        return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
    }

    return $documento ?: '-';
}

function suporte_status_label(string $status): string
{
    return [
        'aberto' => 'Aberto',
        'em_atendimento' => 'Em atendimento',
        'aguardando_empresa' => 'Aguardando empresa',
        'resolvido' => 'Resolvido',
        'fechado' => 'Fechado',
    ][$status] ?? 'Aberto';
}

function suporte_status_badge(string $status): string
{
    return [
        'aberto' => 'aberto',
        'em_atendimento' => 'pendente',
        'aguardando_empresa' => 'pendente',
        'resolvido' => 'ativa',
        'fechado' => 'bloqueada',
    ][$status] ?? 'aberto';
}

function suporte_prioridade_label(string $prioridade): string
{
    return [
        'baixa' => 'Baixa',
        'media' => 'Média',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ][$prioridade] ?? 'Média';
}

function suporte_prioridade_badge(string $prioridade): string
{
    return [
        'baixa' => 'ativa',
        'media' => 'pendente',
        'alta' => 'vencida',
        'urgente' => 'vencida',
    ][$prioridade] ?? 'pendente';
}

function suporte_categoria_label(string $categoria): string
{
    return [
        'financeiro' => 'Financeiro',
        'tecnico' => 'Técnico',
        'acesso' => 'Acesso',
        'automacao' => 'Automação',
        'outro' => 'Outro',
    ][$categoria] ?? 'Outro';
}

function suporte_ensure_tables(): bool
{
    static $ready = null;

    if ($ready !== null) {
        return $ready;
    }

    try {
        db()->exec(
            "CREATE TABLE IF NOT EXISTS suporte_chamados (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED DEFAULT NULL,
                assunto VARCHAR(160) NOT NULL,
                categoria ENUM('financeiro','tecnico','acesso','automacao','outro') NOT NULL DEFAULT 'outro',
                prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
                status ENUM('aberto','em_atendimento','aguardando_empresa','resolvido','fechado') NOT NULL DEFAULT 'aberto',
                ultima_resposta_origem ENUM('empresa','admin') NOT NULL DEFAULT 'empresa',
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                fechado_em DATETIME DEFAULT NULL,
                INDEX idx_suporte_chamados_empresa (empresa_id),
                INDEX idx_suporte_chamados_status (status),
                INDEX idx_suporte_chamados_prioridade (prioridade),
                INDEX idx_suporte_chamados_criado (criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        db()->exec(
            "CREATE TABLE IF NOT EXISTS suporte_mensagens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                chamado_id INT UNSIGNED NOT NULL,
                empresa_id INT UNSIGNED NOT NULL,
                usuario_id INT UNSIGNED DEFAULT NULL,
                autor_tipo ENUM('empresa','admin') NOT NULL,
                autor_nome VARCHAR(120) NOT NULL,
                mensagem TEXT NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_suporte_mensagens_chamado (chamado_id),
                INDEX idx_suporte_mensagens_empresa (empresa_id),
                INDEX idx_suporte_mensagens_criado (criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $ready = true;
    } catch (Throwable $e) {
        error_log('[SUPORTE SETUP] ' . $e->getMessage());
        $ready = false;
    }

    return $ready;
}

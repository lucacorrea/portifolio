<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const APP_NAME = 'Controle Juridico Pessoal';
const DB_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'dados';
const SESSION_DIR = DB_DIR . DIRECTORY_SEPARATOR . 'sessoes';
const DB_HOST = 'localhost';
const DB_NAME = 'u784961086_projudy';
const DB_USER = 'u784961086_projudy';
const DB_PASS = '0|NanKJ@u+v';

if (!is_dir(DB_DIR)) {
    mkdir(DB_DIR, 0775, true);
}

if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0775, true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_save_path(SESSION_DIR);
    session_start();
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        database_unavailable();
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    try {
        initialize_database($pdo);
    } catch (Throwable $e) {
        error_log('Controle Juridico DB init error: ' . $e->getMessage());
        database_unavailable('Nao foi possivel preparar as tabelas do sistema. Confira as permissoes do usuario MySQL para criar e alterar tabelas.');
    }

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(160) NOT NULL,
            login VARCHAR(80) NOT NULL UNIQUE,
            senha_hash VARCHAR(255) NOT NULL,
            perfil VARCHAR(20) NOT NULL DEFAULT 'normal',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            ultimo_acesso DATETIME NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL,
            INDEX idx_usuarios_login (login),
            INDEX idx_usuarios_perfil (perfil)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS tipos_processo (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(140) NOT NULL UNIQUE,
            cor VARCHAR(20) NOT NULL DEFAULT '#2563eb',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            ordem INT NOT NULL DEFAULT 0,
            INDEX idx_tipos_ativo (ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS situacoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(140) NOT NULL UNIQUE,
            cor VARCHAR(20) NOT NULL DEFAULT '#64748b',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            finalizadora TINYINT(1) NOT NULL DEFAULT 0,
            ordem INT NOT NULL DEFAULT 0,
            INDEX idx_situacoes_ativo (ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS processos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cliente VARCHAR(180) NOT NULL,
            numero_processo VARCHAR(80) NOT NULL UNIQUE,
            tipo_processo VARCHAR(140) NOT NULL,
            situacao VARCHAR(140) NOT NULL,
            data_prazo DATE NULL,
            observacao TEXT NULL,
            valor_processo DECIMAL(12,2) NULL,
            porcentagem_cobrada DECIMAL(5,2) NULL,
            valor_cobrado DECIMAL(12,2) NULL,
            pago_em DATETIME NULL,
            pago_por INT NULL,
            criado_por INT NULL,
            atualizado_por INT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em DATETIME NULL,
            INDEX idx_processos_cliente (cliente),
            INDEX idx_processos_numero (numero_processo),
            INDEX idx_processos_tipo (tipo_processo),
            INDEX idx_processos_situacao (situacao),
            INDEX idx_processos_data_prazo (data_prazo),
            INDEX idx_processos_criado_em (criado_em),
            CONSTRAINT fk_processos_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
            CONSTRAINT fk_processos_atualizado_por FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
            CONSTRAINT fk_processos_pago_por FOREIGN KEY (pago_por) REFERENCES usuarios(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS auditoria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NULL,
            usuario_nome VARCHAR(160) NULL,
            acao VARCHAR(80) NOT NULL,
            tabela VARCHAR(80) NOT NULL,
            registro_id INT NULL,
            dados_anteriores TEXT NULL,
            dados_novos TEXT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auditoria_criado_em (criado_em),
            INDEX idx_auditoria_usuario (usuario_id),
            INDEX idx_auditoria_tabela (tabela)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }

    migrate_database($pdo);
    seed_database($pdo);
}

function migrate_database(PDO $pdo): void
{
    ensure_column($pdo, 'processos', 'data_prazo', 'DATE NULL AFTER situacao');
    ensure_column($pdo, 'processos', 'valor_processo', 'DECIMAL(12,2) NULL AFTER observacao');
    ensure_column($pdo, 'processos', 'porcentagem_cobrada', 'DECIMAL(5,2) NULL AFTER valor_processo');
    ensure_column($pdo, 'processos', 'valor_cobrado', 'DECIMAL(12,2) NULL AFTER porcentagem_cobrada');
    ensure_column($pdo, 'processos', 'pago_em', 'DATETIME NULL AFTER valor_cobrado');
    ensure_column($pdo, 'processos', 'pago_por', 'INT NULL AFTER pago_em');
    ensure_index($pdo, 'processos', 'idx_processos_data_prazo', 'data_prazo');
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));

    if (!$stmt || !$stmt->fetch()) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function ensure_index(PDO $pdo, string $table, string $index, string $column): void
{
    $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($index));

    if (!$stmt || !$stmt->fetch()) {
        $pdo->exec("ALTER TABLE {$table} ADD INDEX {$index} ({$column})");
    }
}

function database_unavailable(string $message = 'Nao foi possivel conectar ao MySQL. Confira host, nome da base, usuario e senha configurados no sistema.'): void
{
    http_response_code(500);

    $isApi = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'api.php'
        || strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'erro',
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Banco indisponivel</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#edf2f4;font-family:Arial,sans-serif;color:#17202a}.box{max-width:520px;background:#fff;border:1px solid #d9e2ec;border-radius:8px;padding:24px;box-shadow:0 14px 35px rgba(31,41,55,.1)}h1{margin:0 0 8px;font-size:22px}p{margin:0;color:#667085;line-height:1.5}</style></head><body><main class="box"><h1>Banco de dados indisponivel</h1><p>' . e($message) . '</p></main></body></html>';
    exit;
}

function seed_database(PDO $pdo): void
{
    $usuarios = [
        ['Suporte', 'suporte', 'suporte123', 'suporte'],
        ['Usuario Normal', 'normal', 'normal123', 'normal'],
    ];

    $stmtUser = $pdo->prepare(
        'INSERT IGNORE INTO usuarios (nome, login, senha_hash, perfil) VALUES (?, ?, ?, ?)'
    );

    foreach ($usuarios as [$nome, $login, $senha, $perfil]) {
        $stmtUser->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $perfil]);
    }
    $oldSupportName = 'Suporte ' . 'C' . 'G' . 'M';
    $stmtRenameSupport = $pdo->prepare("UPDATE usuarios SET nome = 'Suporte' WHERE login = 'suporte' AND nome = ?");
    $stmtRenameSupport->execute([$oldSupportName]);

    $tipos = [
        ['Civel', '#2563eb', 1],
        ['Trabalhista', '#0891b2', 2],
        ['Familia', '#7c3aed', 3],
        ['Previdenciario', '#f59e0b', 4],
        ['Consumidor', '#16a34a', 5],
        ['Criminal', '#db2777', 6],
        ['Pessoal', '#0f766e', 7],
        ['Outros', '#64748b', 8],
    ];
    $stmtTipo = $pdo->prepare(
        'INSERT IGNORE INTO tipos_processo (nome, cor, ordem) VALUES (?, ?, ?)'
    );
    foreach ($tipos as $tipo) {
        $stmtTipo->execute($tipo);
    }
    rename_catalog_option($pdo, 'tipos_processo', 'Auditoria', 'Civel');
    rename_catalog_option($pdo, 'tipos_processo', 'Prestacao de Contas', 'Trabalhista');
    rename_catalog_option($pdo, 'tipos_processo', 'Tomada de Contas', 'Familia');
    rename_catalog_option($pdo, 'tipos_processo', 'Licitacao', 'Previdenciario');
    rename_catalog_option($pdo, 'tipos_processo', 'Contratos', 'Consumidor');
    rename_catalog_option($pdo, 'tipos_processo', 'Ouvidoria', 'Criminal');
    rename_catalog_option($pdo, 'tipos_processo', 'Controle ' . 'Interno', 'Pessoal');

    $situacoes = [
        ['Aberto', '#2563eb', 0, 1],
        ['Em analise', '#f59e0b', 0, 2],
        ['Aguardando documentos', '#9333ea', 0, 3],
        ['Pendente', '#dc2626', 0, 4],
        ['Concluido', '#16a34a', 1, 5],
        ['Arquivado', '#475569', 1, 6],
    ];
    $stmtSituacao = $pdo->prepare(
        'INSERT IGNORE INTO situacoes (nome, cor, finalizadora, ordem) VALUES (?, ?, ?, ?)'
    );
    foreach ($situacoes as $situacao) {
        $stmtSituacao->execute($situacao);
    }
}

function rename_catalog_option(PDO $pdo, string $table, string $oldName, string $newName): void
{
    $oldStmt = $pdo->prepare("SELECT id FROM {$table} WHERE nome = ? LIMIT 1");
    $oldStmt->execute([$oldName]);
    $oldId = $oldStmt->fetchColumn();

    if (!$oldId) {
        return;
    }

    $newStmt = $pdo->prepare("SELECT id FROM {$table} WHERE nome = ? LIMIT 1");
    $newStmt->execute([$newName]);
    $newId = $newStmt->fetchColumn();

    if ($newId) {
        $deactivate = $pdo->prepare("UPDATE {$table} SET ativo = 0 WHERE id = ?");
        $deactivate->execute([$oldId]);
        return;
    }

    $rename = $pdo->prepare("UPDATE {$table} SET nome = ? WHERE id = ?");
    $rename->execute([$newName, $oldId]);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['usuario_id'],
        'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        'login' => (string) ($_SESSION['usuario_login'] ?? ''),
        'perfil' => (string) ($_SESSION['usuario_perfil'] ?? 'normal'),
    ];
}

function is_suporte(): bool
{
    return (current_user()['perfil'] ?? '') === 'suporte';
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function require_suporte(): void
{
    require_login();

    if (!is_suporte()) {
        header('Location: index.php');
        exit;
    }
}

function read_json_body(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input ?: '[]', true);

    return is_array($data) ? $data : [];
}

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $statusCode = 400): void
{
    json_response(['status' => 'erro', 'message' => $message], $statusCode);
}

function validate_required(array $data, array $fields): void
{
    foreach ($fields as $field => $label) {
        if (trim((string) ($data[$field] ?? '')) === '') {
            json_error("Informe o campo {$label}.");
        }
    }
}

function normalize_text(?string $value): string
{
    return preg_replace('/\s+/', ' ', trim((string) $value)) ?? '';
}

function register_audit(string $acao, string $tabela, ?int $registroId, ?array $antes = null, ?array $depois = null): void
{
    $user = current_user();
    $stmt = db()->prepare(
        'INSERT INTO auditoria (usuario_id, usuario_nome, acao, tabela, registro_id, dados_anteriores, dados_novos)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $user['id'] ?? null,
        $user['nome'] ?? 'Sistema',
        $acao,
        $tabela,
        $registroId,
        $antes ? json_encode($antes, JSON_UNESCAPED_UNICODE) : null,
        $depois ? json_encode($depois, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

db();

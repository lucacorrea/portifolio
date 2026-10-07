<?php
// config/database.php
//
// IMPORTANTE:
// Este arquivo deve apenas abrir a conexão com o banco e disponibilizar $pdo.
// Migrações/ALTER TABLE ficam em migracao_producao.php e nunca são executadas
// automaticamente durante login, dashboard ou outras requisições do sistema.

$host = 'localhost';
$db_user = 'u784961086_so';
$db_pass = '7#nVCRwF#';
$dbname = 'u784961086_so';

function db_table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function db_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetchColumn();
}

function db_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    if (!db_column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN $definition");
    }
}

function db_index_exists(PDO $pdo, string $table, string $index): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([$table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

function db_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $stmt->execute([$table, $constraint]);
    return (int)$stmt->fetchColumn() > 0;
}

function db_secretarias_relatorio_seed(): array {
    return [
        ['COMIÇÃO DE CONTRATAÇÃO DE COARI-CCC', 'CCC2026', '#D9E3F4'],
        ['CONTROLADORIA GERAL DO MUNICICÍPIO - CGM', 'CGM2026', '#E2F0D9'],
        ['COORDENADORIA REGIONAL DE EDUCAÇÃO DE COARI', 'CREC2026', '#FCE4D6'],
        ['PROCURADORIA GERAL DO MUNICÍPIO - PGM', 'PGM2026', '#FFF2CC'],
        ['SECRETARIA DE ESTADO DA EDUCAÇÃO E DESPORTO ESCOLAR COORDENADORIA REGIONAL DE EDUCAÇÃO DE COARI-SEDUC', 'SEDUC2026', '#EADCF8'],
        ['SECRETARIA MUNICIPAL DA CASA CIVIL - SMCC', 'SMCC2026', '#C9DAF8'],
        ['SECRETARIA MUNICIPAL DE ADMINISTRAÇÃO - SEMAD', 'SEMAD2026', '#D9EAD3'],
        ['SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL - SEMAS', 'SEMAS2026', '#F4CCCC'],
        ['SECRETARIA MUNICIPAL DE CIÊNCIA, TECNOLOGIA E INOVAÇÃO', 'SMCTI2026', '#D0E0E3'],
        ['SECRETARIA MUNICIPAL DE COMUNICAÇÃO - SEMCOM', 'SEMCOM2026', '#FCE5CD'],
        ['SECRETARIA MUNICIPAL DE CULTURA E TURISMO - SECULT', 'SECULT2026', '#D5A6BD'],
        ['SECRETARIA MUNICIPAL DE DESENVOLVIMENTO RURAL E ECONÔMICO - SMDRE', 'SMDRE2026', '#B6D7A8'],
        ['SECRETARIA MUNICIPAL DE EDUCAÇÃO - SEMED', 'SEMED2026', '#A4C2F4'],
        ['SECRETARIA MUNICIPAL DE ESPORTE - SEMESP', 'SEMESP2026', '#B4A7D6'],
        ['SECRETARIA MUNICIPAL DE FAZENDA - SEMFAZ', 'SEMFAZ2026', '#FFD966'],
        ['SECRETARIA MUNICIPAL DE INDÚSTRIA E COMERCIO - SEMIC', 'SEC2026', '#CFE2F3'],
        ['SECRETARIA MUNICIPAL DE LIMPEZA PÚBLICA - SEMLIP', 'SEMLIP2026', '#D9D2E9'],
        ['SECRETARIA MUNICIPAL DE MEIO AMBIENTE - SEMMA', 'SEMMA2026', '#B7E1CD'],
        ['SECRETARIA MUNICIPAL DE OBRAS - SEMOB', 'SEMOB2026', '#F9CB9C'],
        ['SECRETARIA MUNICIPAL DE PLANEJAMENTO - SEMPLAN', 'SEMPLAN2026', '#CFE2F3'],
        ['SECRETARIA MUNICIPAL DE SAÚDE - SEMSA', 'SEMSA2026', '#EA9999'],
        ['SECRETARIA MUNICIPAL DE SEGURANÇA PÚBLICA E DEFESA SOCIAL - AEROPORTO', 'AEROPORTO2026', '#B7B7B7'],
        ['SECRETARIA MUNICIPAL DE SEGURANÇA PÚBLICA E DEFESA SOCIAL - SMSPDS', 'SMSPDS2026', '#A2C4C9'],
        ['SECRETARIA MUNICIPAL DE TERRAS E HABITAÇÃO - SEMTH', 'SEMTH2026', '#D5E8D4'],
        ['SECRETARIA MUNICIPAL EXTRAORDINÁRIA', 'SME2026', '#E6B8AF'],
        ['SECRETÁRIO MUNICIPAL DE RELAÇÕES INSTITUCIONAIS', 'SMRI2026', '#D9D9D9'],
    ];
}

function db_sync_secretarias_relatorio(PDO $pdo): void {
    if (!db_table_exists($pdo, 'secretarias')) {
        return;
    }

    db_add_column_if_missing(
        $pdo,
        'secretarias',
        'cor_relatorio',
        "cor_relatorio VARCHAR(7) DEFAULT '#D9E3F4' AFTER codigo_acesso"
    );

    $find = $pdo->prepare("SELECT id FROM secretarias WHERE codigo_acesso = ? OR nome = ? LIMIT 1");
    $findAlias = $pdo->prepare("SELECT id FROM secretarias WHERE nome = ? LIMIT 1");
    $update = $pdo->prepare("UPDATE secretarias SET nome = ?, codigo_acesso = ?, cor_relatorio = ? WHERE id = ?");
    $insert = $pdo->prepare("INSERT INTO secretarias (nome, codigo_acesso, cor_relatorio, responsavel) VALUES (?, ?, ?, NULL)");

    $aliases = [
        'SEMSA2026' => ['Saúde'],
        'SEMED2026' => ['Educação'],
        'SEMOB2026' => ['Obras'],
        'SEMAS2026' => ['Assistência Social'],
    ];

    foreach (db_secretarias_relatorio_seed() as $secretaria) {
        [$nome, $codigo, $cor] = $secretaria;

        $find->execute([$codigo, $nome]);
        $id = $find->fetchColumn();

        if (!$id && isset($aliases[$codigo])) {
            foreach ($aliases[$codigo] as $alias) {
                $findAlias->execute([$alias]);
                $id = $findAlias->fetchColumn();

                if ($id) {
                    break;
                }
            }
        }

        if ($id) {
            $update->execute([$nome, $codigo, $cor, (int)$id]);
        } else {
            $insert->execute([$nome, $codigo, $cor]);
        }
    }
}

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} catch (PDOException $e) {
    error_log('SO database connection failed: ' . $e->getMessage());

    http_response_code(503);
    die('Não foi possível conectar ao banco de dados no momento. Tente novamente em alguns instantes.');
}

function getPDO(): PDO {
    global $pdo;
    return $pdo;
}

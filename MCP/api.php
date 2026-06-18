<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$acao = $_GET['acao'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($acao === 'logout') {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    if ($acao === 'login' && $metodo === 'POST') {
        $data = read_json_body();
        validate_required($data, ['login' => 'usuario', 'senha' => 'senha']);

        $stmt = db()->prepare('SELECT * FROM usuarios WHERE login = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([normalize_text($data['login'])]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify((string) $data['senha'], (string) $usuario['senha_hash'])) {
            json_error('Usuario ou senha invalidos.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = (string) $usuario['nome'];
        $_SESSION['usuario_login'] = (string) $usuario['login'];
        $_SESSION['usuario_perfil'] = (string) $usuario['perfil'];

        db()->prepare('UPDATE usuarios SET ultimo_acesso = CURRENT_TIMESTAMP WHERE id = ?')->execute([$usuario['id']]);
        register_audit('LOGIN', 'usuarios', (int) $usuario['id'], null, ['login' => $usuario['login']]);

        json_response(['status' => 'sucesso', 'message' => 'Login realizado com sucesso.']);
    }

    require_login();

    switch ($acao) {
        case 'perfil':
            json_response(['status' => 'sucesso', 'usuario' => current_user()]);

        case 'opcoes':
            json_response([
                'status' => 'sucesso',
                'tipos' => fetch_options('tipos_processo'),
                'situacoes' => fetch_options('situacoes'),
            ]);

        case 'resumo':
            ensure_get($metodo);
            json_response(['status' => 'sucesso', 'data' => dashboard_summary()]);

        case 'listar_processos':
            ensure_get($metodo);
            json_response(['status' => 'sucesso', 'data' => list_processes(true)]);

        case 'relatorio_processos':
            ensure_get($metodo);
            json_response(['status' => 'sucesso', 'data' => list_processes(false)]);

        case 'prazos_processos':
            ensure_get($metodo);
            json_response(['status' => 'sucesso', 'data' => list_deadlines()]);

        case 'obter_processo':
            ensure_get($metodo);
            $id = (int) ($_GET['id'] ?? 0);
            $processo = fetch_process($id);
            if (!$processo) {
                json_error('Processo nao encontrado.', 404);
            }
            json_response(['status' => 'sucesso', 'data' => $processo]);

        case 'salvar_processo':
            ensure_post($metodo);
            save_process();
            break;

        case 'pagar_processo':
            ensure_post($metodo);
            pay_process();
            break;

        case 'excluir_processo':
            ensure_delete($metodo);
            delete_process();
            break;

        case 'listar_usuarios':
            ensure_get($metodo);
            ensure_support_json();
            json_response(['status' => 'sucesso', 'data' => list_users()]);

        case 'salvar_usuario':
            ensure_post($metodo);
            ensure_support_json();
            save_user();
            break;

        case 'excluir_usuario':
            ensure_delete($metodo);
            ensure_support_json();
            deactivate_user();
            break;

        case 'salvar_tipo':
            ensure_post($metodo);
            ensure_support_json();
            save_catalog_item('tipos_processo');
            break;

        case 'salvar_situacao':
            ensure_post($metodo);
            ensure_support_json();
            save_catalog_item('situacoes');
            break;

        case 'excluir_tipo':
            ensure_delete($metodo);
            ensure_support_json();
            deactivate_catalog_item('tipos_processo');
            break;

        case 'excluir_situacao':
            ensure_delete($metodo);
            ensure_support_json();
            deactivate_catalog_item('situacoes');
            break;

        case 'auditoria':
            ensure_get($metodo);
            ensure_support_json();
            json_response(['status' => 'sucesso', 'data' => list_audit()]);

        default:
            json_error('Acao nao encontrada.', 404);
    }
} catch (Throwable $e) {
    json_error($e->getMessage(), 500);
}

function ensure_get(string $metodo): void
{
    if ($metodo !== 'GET') {
        json_error('Metodo nao permitido.', 405);
    }
}

function ensure_post(string $metodo): void
{
    if ($metodo !== 'POST') {
        json_error('Metodo nao permitido.', 405);
    }
}

function ensure_delete(string $metodo): void
{
    if ($metodo !== 'DELETE') {
        json_error('Metodo nao permitido.', 405);
    }
}

function ensure_support_json(): void
{
    if (!is_suporte()) {
        json_error('Acesso restrito ao usuario suporte.', 403);
    }
}

function fetch_options(string $table): array
{
    $stmt = db()->query("SELECT * FROM {$table} WHERE ativo = 1 ORDER BY ordem ASC, nome ASC");
    return $stmt->fetchAll();
}

function process_filters(): array
{
    $where = [];
    $params = [];

    $q = normalize_text($_GET['q'] ?? '');
    if ($q !== '') {
        $where[] = '(p.cliente LIKE ? OR p.numero_processo LIKE ? OR p.tipo_processo LIKE ? OR p.situacao LIKE ? OR p.observacao LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }

    foreach (['tipo' => 'p.tipo_processo', 'situacao' => 'p.situacao'] as $input => $column) {
        $value = normalize_text($_GET[$input] ?? '');
        if ($value !== '') {
            $where[] = "{$column} = ?";
            $params[] = $value;
        }
    }

    $inicio = normalize_text($_GET['inicio'] ?? '');
    if ($inicio !== '') {
        $dateColumn = normalize_text($_GET['date_by'] ?? '') === 'prazo' ? 'p.data_prazo' : 'DATE(p.pago_em)';
        $where[] = "{$dateColumn} >= ?";
        $params[] = $inicio;
    }

    $fim = normalize_text($_GET['fim'] ?? '');
    if ($fim !== '') {
        $dateColumn = normalize_text($_GET['date_by'] ?? '') === 'prazo' ? 'p.data_prazo' : 'DATE(p.pago_em)';
        $where[] = "{$dateColumn} <= ?";
        $params[] = $fim;
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function allowed_sort(): string
{
    $sort = $_GET['sort'] ?? 'recentes';
    $map = [
        'recentes' => 'p.criado_em DESC, p.id DESC',
        'antigos' => 'p.criado_em ASC, p.id ASC',
        'cliente' => 'p.cliente ASC',
        'numero' => 'p.numero_processo ASC',
        'situacao' => 'p.situacao ASC, p.criado_em DESC',
        'prazo' => 'p.data_prazo IS NULL ASC, p.data_prazo ASC, p.criado_em DESC',
    ];

    return $map[$sort] ?? $map['recentes'];
}

function list_processes(bool $paginated): array
{
    [$whereSql, $params] = process_filters();

    $countStmt = db()->prepare("SELECT COUNT(*) FROM processos p {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(100, max(5, (int) ($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;
    $limitSql = $paginated ? "LIMIT {$perPage} OFFSET {$offset}" : 'LIMIT 5000';

    $sql = "
        SELECT p.*,
               uc.nome AS criado_por_nome,
               ua.nome AS atualizado_por_nome,
               up.nome AS pago_por_nome
        FROM processos p
        LEFT JOIN usuarios uc ON uc.id = p.criado_por
        LEFT JOIN usuarios ua ON ua.id = p.atualizado_por
        LEFT JOIN usuarios up ON up.id = p.pago_por
        {$whereSql}
        ORDER BY " . allowed_sort() . "
        {$limitSql}
    ";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int) ceil($total / $perPage)),
        'summary' => filtered_summary($whereSql, $params),
    ];
}

function filtered_summary(string $whereSql, array $params): array
{
    $statusStmt = db()->prepare("SELECT p.situacao AS nome, COUNT(*) AS total FROM processos p {$whereSql} GROUP BY p.situacao ORDER BY total DESC");
    $statusStmt->execute($params);

    $typeStmt = db()->prepare("SELECT p.tipo_processo AS nome, COUNT(*) AS total FROM processos p {$whereSql} GROUP BY p.tipo_processo ORDER BY total DESC");
    $typeStmt->execute($params);

    $billingStmt = db()->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN p.pago_em IS NOT NULL THEN p.valor_cobrado ELSE 0 END), 0) AS faturamento_total,
            COUNT(CASE WHEN p.pago_em IS NOT NULL THEN 1 END) AS processos_pagos,
            COALESCE(AVG(CASE WHEN p.pago_em IS NOT NULL THEN p.valor_cobrado END), 0) AS ticket_medio,
            COALESCE(SUM(CASE WHEN p.pago_em IS NULL THEN p.valor_cobrado ELSE 0 END), 0) AS valor_pendente
        FROM processos p
        {$whereSql}
    ");
    $billingStmt->execute($params);
    $billing = $billingStmt->fetch() ?: [];

    return [
        'por_situacao' => $statusStmt->fetchAll(),
        'por_tipo' => $typeStmt->fetchAll(),
        'financeiro' => [
            'faturamento_total' => (float) ($billing['faturamento_total'] ?? 0),
            'processos_pagos' => (int) ($billing['processos_pagos'] ?? 0),
            'ticket_medio' => (float) ($billing['ticket_medio'] ?? 0),
            'valor_pendente' => (float) ($billing['valor_pendente'] ?? 0),
        ],
    ];
}

function dashboard_summary(): array
{
    $total = (int) db()->query('SELECT COUNT(*) FROM processos')->fetchColumn();
    $emAndamento = (int) db()->query("
        SELECT COUNT(*)
        FROM processos p
        LEFT JOIN situacoes s ON s.nome = p.situacao
        WHERE COALESCE(s.finalizadora, 0) = 0
    ")->fetchColumn();
    $finalizados = max(0, $total - $emAndamento);
    $mes = (int) db()->query("
        SELECT COUNT(*)
        FROM processos
        WHERE DATE_FORMAT(criado_em, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
    ")->fetchColumn();
    $proximos = (int) db()->query("
        SELECT COUNT(*)
        FROM processos p
        LEFT JOIN situacoes s ON s.nome = p.situacao
        WHERE p.data_prazo IS NOT NULL
          AND p.data_prazo >= CURDATE()
          AND p.data_prazo <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          AND COALESCE(s.finalizadora, 0) = 0
    ")->fetchColumn();
    $pagos = (int) db()->query('SELECT COUNT(*) FROM processos WHERE pago_em IS NOT NULL')->fetchColumn();

    $status = db()->query('SELECT situacao AS nome, COUNT(*) AS total FROM processos GROUP BY situacao ORDER BY total DESC')->fetchAll();
    $tipos = db()->query('SELECT tipo_processo AS nome, COUNT(*) AS total FROM processos GROUP BY tipo_processo ORDER BY total DESC')->fetchAll();

    return [
        'total' => $total,
        'em_andamento' => $emAndamento,
        'finalizados' => $finalizados,
        'mes' => $mes,
        'proximos' => $proximos,
        'pagos' => $pagos,
        'por_situacao' => $status,
        'por_tipo' => $tipos,
    ];
}

function list_deadlines(): array
{
    $stmt = db()->query("
        SELECT p.*,
               uc.nome AS criado_por_nome,
               ua.nome AS atualizado_por_nome,
               up.nome AS pago_por_nome
        FROM processos p
        LEFT JOIN usuarios uc ON uc.id = p.criado_por
        LEFT JOIN usuarios ua ON ua.id = p.atualizado_por
        LEFT JOIN usuarios up ON up.id = p.pago_por
        WHERE p.data_prazo IS NOT NULL
        ORDER BY p.data_prazo ASC, p.id DESC
        LIMIT 5000
    ");

    return ['items' => $stmt->fetchAll()];
}

function fetch_process(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM processos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    return $item ?: null;
}

function save_process(): void
{
    $data = read_json_body();
    validate_required($data, [
        'cliente' => 'nome do cliente',
        'numero_processo' => 'numero do processo',
        'tipo_processo' => 'tipo de processo',
        'situacao' => 'situacao',
        'data_prazo' => 'data do prazo',
    ]);

    $id = (int) ($data['id'] ?? 0);
    $payload = [
        'cliente' => normalize_text($data['cliente']),
        'numero_processo' => normalize_text($data['numero_processo']),
        'tipo_processo' => normalize_text($data['tipo_processo']),
        'situacao' => normalize_text($data['situacao']),
        'data_prazo' => normalize_text($data['data_prazo']),
        'observacao' => trim((string) ($data['observacao'] ?? '')),
    ];

    ensure_catalog_option('tipos_processo', $payload['tipo_processo']);
    ensure_catalog_option('situacoes', $payload['situacao']);

    $dup = db()->prepare('SELECT id FROM processos WHERE numero_processo = ? AND id <> ? LIMIT 1');
    $dup->execute([$payload['numero_processo'], $id]);
    if ($dup->fetch()) {
        json_error('Ja existe um processo cadastrado com este numero.');
    }

    $user = current_user();

    if ($id > 0) {
        $antes = fetch_process($id);
        if (!$antes) {
            json_error('Processo nao encontrado.', 404);
        }

        $stmt = db()->prepare('
            UPDATE processos
            SET cliente = ?, numero_processo = ?, tipo_processo = ?, situacao = ?, data_prazo = ?, observacao = ?,
                atualizado_por = ?, atualizado_em = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $stmt->execute([
            $payload['cliente'],
            $payload['numero_processo'],
            $payload['tipo_processo'],
            $payload['situacao'],
            $payload['data_prazo'],
            $payload['observacao'],
            $user['id'],
            $id,
        ]);
        register_audit('UPDATE', 'processos', $id, $antes, $payload);
        json_response(['status' => 'sucesso', 'message' => 'Processo atualizado com sucesso.']);
    }

    $stmt = db()->prepare('
        INSERT INTO processos (cliente, numero_processo, tipo_processo, situacao, data_prazo, observacao, criado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $payload['cliente'],
        $payload['numero_processo'],
        $payload['tipo_processo'],
        $payload['situacao'],
        $payload['data_prazo'],
        $payload['observacao'],
        $user['id'],
    ]);

    $newId = (int) db()->lastInsertId();
    register_audit('INSERT', 'processos', $newId, null, $payload);
    json_response(['status' => 'sucesso', 'message' => 'Processo cadastrado com sucesso.', 'id' => $newId]);
}

function pay_process(): void
{
    $data = read_json_body();
    validate_required($data, [
        'id' => 'processo',
        'valor_processo' => 'valor do processo',
        'porcentagem_cobrada' => 'porcentagem cobrada',
    ]);

    $id = (int) ($data['id'] ?? 0);
    $antes = fetch_process($id);
    if (!$antes) {
        json_error('Processo nao encontrado.', 404);
    }

    $valorProcesso = parse_decimal($data['valor_processo']);
    $porcentagem = parse_decimal($data['porcentagem_cobrada']);

    if ($valorProcesso <= 0) {
        json_error('Informe um valor de processo maior que zero.');
    }

    if ($porcentagem < 0 || $porcentagem > 100) {
        json_error('Informe uma porcentagem entre 0 e 100.');
    }

    $valorCobrado = round(($valorProcesso * $porcentagem) / 100, 2);
    $user = current_user();

    $stmt = db()->prepare('
        UPDATE processos
        SET valor_processo = ?, porcentagem_cobrada = ?, valor_cobrado = ?,
            pago_em = CURRENT_TIMESTAMP, pago_por = ?, atualizado_por = ?, atualizado_em = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute([$valorProcesso, $porcentagem, $valorCobrado, $user['id'], $user['id'], $id]);

    register_audit('PAYMENT', 'processos', $id, $antes, [
        'valor_processo' => $valorProcesso,
        'porcentagem_cobrada' => $porcentagem,
        'valor_cobrado' => $valorCobrado,
    ]);

    json_response([
        'status' => 'sucesso',
        'message' => 'Pagamento registrado com sucesso.',
        'data' => [
            'valor_processo' => $valorProcesso,
            'porcentagem_cobrada' => $porcentagem,
            'valor_cobrado' => $valorCobrado,
        ],
    ]);
}

function parse_decimal($value): float
{
    $value = trim((string) $value);
    $value = str_replace(['R$', ' '], '', $value);

    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }

    return (float) $value;
}

function ensure_catalog_option(string $table, string $name): void
{
    $name = normalize_text($name);
    if ($name === '') {
        return;
    }

    $stmt = db()->prepare("SELECT id, ativo FROM {$table} WHERE nome = ? LIMIT 1");
    $stmt->execute([$name]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ((int) $existing['ativo'] !== 1) {
            db()->prepare("UPDATE {$table} SET ativo = 1 WHERE id = ?")->execute([$existing['id']]);
            register_audit('REACTIVATE', $table, (int) $existing['id'], $existing, ['nome' => $name, 'ativo' => 1]);
        }
        return;
    }

    $nextOrderStmt = db()->query("SELECT COALESCE(MAX(ordem), 0) + 1 FROM {$table}");
    $nextOrder = (int) $nextOrderStmt->fetchColumn();

    if ($table === 'situacoes') {
        $color = '#64748b';
        $insert = db()->prepare("INSERT INTO {$table} (nome, cor, finalizadora, ordem, ativo) VALUES (?, ?, 0, ?, 1)");
        $insert->execute([$name, $color, $nextOrder]);
    } else {
        $color = '#2563eb';
        $insert = db()->prepare("INSERT INTO {$table} (nome, cor, ordem, ativo) VALUES (?, ?, ?, 1)");
        $insert->execute([$name, $color, $nextOrder]);
    }

    $newId = (int) db()->lastInsertId();
    register_audit('AUTO_INSERT', $table, $newId, null, ['nome' => $name, 'cor' => $color, 'ordem' => $nextOrder]);
}

function delete_process(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $antes = fetch_process($id);
    if (!$antes) {
        json_error('Processo nao encontrado.', 404);
    }

    db()->prepare('DELETE FROM processos WHERE id = ?')->execute([$id]);
    register_audit('DELETE', 'processos', $id, $antes, null);
    json_response(['status' => 'sucesso', 'message' => 'Processo removido com sucesso.']);
}

function list_users(): array
{
    $q = normalize_text($_GET['q'] ?? '');
    $where = 'WHERE ativo = 1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (nome LIKE ? OR login LIKE ? OR perfil LIKE ?)';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like];
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $count = db()->prepare("SELECT COUNT(*) FROM usuarios {$where}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = db()->prepare("
        SELECT id, nome, login, perfil, ativo, ultimo_acesso, criado_em
        FROM usuarios
        {$where}
        ORDER BY perfil DESC, nome ASC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

function save_user(): void
{
    $data = read_json_body();
    validate_required($data, ['nome' => 'nome', 'login' => 'login', 'perfil' => 'perfil']);

    $perfil = normalize_text($data['perfil']);
    if (!in_array($perfil, ['normal', 'suporte'], true)) {
        json_error('Perfil invalido.');
    }

    $id = (int) ($data['id'] ?? 0);
    $nome = normalize_text($data['nome']);
    $login = normalize_text($data['login']);
    $senha = (string) ($data['senha'] ?? '');

    $dup = db()->prepare('SELECT id FROM usuarios WHERE login = ? AND id <> ? LIMIT 1');
    $dup->execute([$login, $id]);
    if ($dup->fetch()) {
        json_error('Este login ja esta em uso.');
    }

    $current = current_user();
    if ($id > 0 && $id === (int) $current['id'] && $perfil !== 'suporte') {
        json_error('O usuario suporte logado nao pode remover o proprio acesso.');
    }

    if ($id > 0) {
        $antesStmt = db()->prepare('SELECT id, nome, login, perfil, ativo FROM usuarios WHERE id = ?');
        $antesStmt->execute([$id]);
        $antes = $antesStmt->fetch();
        if (!$antes) {
            json_error('Usuario nao encontrado.', 404);
        }

        if ($senha !== '') {
            $stmt = db()->prepare('UPDATE usuarios SET nome = ?, login = ?, perfil = ?, senha_hash = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$nome, $login, $perfil, password_hash($senha, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = db()->prepare('UPDATE usuarios SET nome = ?, login = ?, perfil = ?, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$nome, $login, $perfil, $id]);
        }

        register_audit('UPDATE', 'usuarios', $id, $antes, ['nome' => $nome, 'login' => $login, 'perfil' => $perfil]);
        json_response(['status' => 'sucesso', 'message' => 'Usuario atualizado com sucesso.']);
    }

    if ($senha === '') {
        json_error('Informe uma senha para o novo usuario.');
    }

    $stmt = db()->prepare('INSERT INTO usuarios (nome, login, senha_hash, perfil) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nome, $login, password_hash($senha, PASSWORD_DEFAULT), $perfil]);
    $newId = (int) db()->lastInsertId();

    register_audit('INSERT', 'usuarios', $newId, null, ['nome' => $nome, 'login' => $login, 'perfil' => $perfil]);
    json_response(['status' => 'sucesso', 'message' => 'Usuario cadastrado com sucesso.']);
}

function deactivate_user(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $current = current_user();

    if ($id === (int) $current['id']) {
        json_error('Voce nao pode desativar o proprio usuario.');
    }

    $stmt = db()->prepare('SELECT id, nome, login, perfil, ativo FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $antes = $stmt->fetch();
    if (!$antes) {
        json_error('Usuario nao encontrado.', 404);
    }

    db()->prepare('UPDATE usuarios SET ativo = 0, atualizado_em = CURRENT_TIMESTAMP WHERE id = ?')->execute([$id]);
    register_audit('DEACTIVATE', 'usuarios', $id, $antes, ['ativo' => 0]);
    json_response(['status' => 'sucesso', 'message' => 'Usuario desativado com sucesso.']);
}

function save_catalog_item(string $table): void
{
    $data = read_json_body();
    validate_required($data, ['nome' => 'nome']);

    $id = (int) ($data['id'] ?? 0);
    $nome = normalize_text($data['nome']);
    $cor = normalize_text($data['cor'] ?? '#2563eb');
    $ordem = (int) ($data['ordem'] ?? 0);
    $finalizadora = (int) !empty($data['finalizadora']);

    $dup = db()->prepare("SELECT id FROM {$table} WHERE nome = ? AND id <> ? LIMIT 1");
    $dup->execute([$nome, $id]);
    if ($dup->fetch()) {
        json_error('Ja existe um item com este nome.');
    }

    if ($id > 0) {
        $before = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
        $before->execute([$id]);
        $antes = $before->fetch();
        if (!$antes) {
            json_error('Item nao encontrado.', 404);
        }

        if ($table === 'situacoes') {
            $stmt = db()->prepare("UPDATE {$table} SET nome = ?, cor = ?, finalizadora = ?, ordem = ?, ativo = 1 WHERE id = ?");
            $stmt->execute([$nome, $cor, $finalizadora, $ordem, $id]);
        } else {
            $stmt = db()->prepare("UPDATE {$table} SET nome = ?, cor = ?, ordem = ?, ativo = 1 WHERE id = ?");
            $stmt->execute([$nome, $cor, $ordem, $id]);
        }

        register_audit('UPDATE', $table, $id, $antes, $data);
        json_response(['status' => 'sucesso', 'message' => 'Configuracao atualizada com sucesso.']);
    }

    if ($table === 'situacoes') {
        $stmt = db()->prepare("INSERT INTO {$table} (nome, cor, finalizadora, ordem) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $cor, $finalizadora, $ordem]);
    } else {
        $stmt = db()->prepare("INSERT INTO {$table} (nome, cor, ordem) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $cor, $ordem]);
    }

    $newId = (int) db()->lastInsertId();
    register_audit('INSERT', $table, $newId, null, $data);
    json_response(['status' => 'sucesso', 'message' => 'Configuracao cadastrada com sucesso.']);
}

function deactivate_catalog_item(string $table): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $antes = $stmt->fetch();
    if (!$antes) {
        json_error('Item nao encontrado.', 404);
    }

    db()->prepare("UPDATE {$table} SET ativo = 0 WHERE id = ?")->execute([$id]);
    register_audit('DEACTIVATE', $table, $id, $antes, ['ativo' => 0]);
    json_response(['status' => 'sucesso', 'message' => 'Item desativado com sucesso.']);
}

function list_audit(): array
{
    $q = normalize_text($_GET['q'] ?? '');
    $where = '';
    $params = [];

    if ($q !== '') {
        $where = 'WHERE usuario_nome LIKE ? OR acao LIKE ? OR tabela LIKE ?';
        $like = '%' . $q . '%';
        $params = [$like, $like, $like];
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = min(50, max(5, (int) ($_GET['per_page'] ?? 10)));
    $offset = ($page - 1) * $perPage;

    $count = db()->prepare("SELECT COUNT(*) FROM auditoria {$where}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = db()->prepare("
        SELECT *
        FROM auditoria
        {$where}
        ORDER BY criado_em DESC, id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int) ceil($total / $perPage)),
    ];
}

<?php

declare(strict_types=1);

/* AUTH (já é privado) */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

// Apenas usuários com perfil 'suporte' ou 'admin' podem acessar auditoria
$allowed_roles = ['suporte', 'admin'];
if (!in_array(($_SESSION['user_role'] ?? ''), $allowed_roles)) {
    header('Location: dashboard.php');
    exit();
}

/* DEBUG (remova em produção) */
ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

/* CONEXÃO */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('Erro de conexão');
}

// Função para criar tabela de auditoria focada em usuários
function criarTabelaAuditoria($pdo)
{
    $check_audit_table = $pdo->query("SHOW TABLES LIKE 'auditoria_usuarios'")->fetch();
    if (!$check_audit_table) {
        $pdo->exec("
            CREATE TABLE auditoria_usuarios (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                usuario VARCHAR(100) NOT NULL COMMENT 'Nome do usuário que realizou a ação',
                usuario_id INT UNSIGNED NULL COMMENT 'ID do usuário',
                acao VARCHAR(50) NOT NULL COMMENT 'Tipo de ação (Cadastrou, Editou, Entregou, etc)',
                entidade VARCHAR(50) NOT NULL COMMENT 'Tipo de entidade (Solicitante, Entrega, Benefício, etc)',
                entidade_id INT UNSIGNED NULL COMMENT 'ID da entidade afetada',
                detalhes TEXT NULL COMMENT 'Detalhes da ação em formato legível',
                dados_antigos JSON NULL COMMENT 'Dados antes da alteração',
                dados_novos JSON NULL COMMENT 'Dados após alteração',
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_usuario (usuario),
                INDEX idx_acao (acao),
                INDEX idx_entidade (entidade),
                INDEX idx_data (created_at),
                INDEX idx_usuario_data (usuario, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Inserir dados históricos das tabelas existentes
        inserirHistoricoSolicitantes($pdo);
        inserirHistoricoEntregas($pdo);
    }
}

// Função para inserir histórico de solicitantes
function inserirHistoricoSolicitantes($pdo)
{
    try {
        $sql = "SELECT id, nome, cpf, responsavel, created_at FROM solicitantes 
                WHERE responsavel IS NOT NULL AND responsavel != '' 
                LIMIT 1000";
        $solicitantes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("INSERT INTO auditoria_usuarios (usuario, acao, entidade, entidade_id, detalhes, created_at) 
                              VALUES (?, 'Cadastrou', 'Solicitante', ?, ?, ?)");

        foreach ($solicitantes as $solicitante) {
            $detalhes = "Cadastrou o solicitante: " . ($solicitante['nome'] ?? 'N/A') . " (CPF: " . ($solicitante['cpf'] ?? 'N/A') . ")";

            $stmt->execute([
                $solicitante['responsavel'],
                $solicitante['id'],
                $detalhes,
                $solicitante['created_at'] ?? date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        error_log("Erro ao inserir histórico de solicitantes: " . $e->getMessage());
    }
}

// Função para inserir histórico de entregas (ATUALIZADA - usa data_entrega como created_at)
function inserirHistoricoEntregas($pdo)
{
    try {
        $sql = "SELECT ae.id, ae.responsavel, ae.data_entrega, ae.hora_entrega, 
                       at.nome as tipo_ajuda, ae.entregue, ae.created_at,
                       s.nome as solicitante_nome, s.cpf as solicitante_cpf,
                       ae.pessoa_cpf as entrega_cpf
                FROM ajudas_entregas ae 
                LEFT JOIN solicitantes s ON ae.pessoa_id = s.id 
                LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id 
                WHERE ae.responsavel IS NOT NULL AND ae.responsavel != ''
                LIMIT 1000";
        $entregas = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("INSERT INTO auditoria_usuarios (usuario, acao, entidade, entidade_id, detalhes, created_at) 
                              VALUES (?, 'Entregou', 'Entrega', ?, ?, ?)");

        foreach ($entregas as $entrega) {
            $data_entrega = !empty($entrega['data_entrega']) ? date('d/m/Y', strtotime($entrega['data_entrega'])) : 'Data não informada';
            $hora_entrega = !empty($entrega['hora_entrega']) ? date('H:i', strtotime($entrega['hora_entrega'])) : '';
            $status_entrega = !empty($entrega['entregue']) && $entrega['entregue'] == 'Sim' ? 'Entregue' : 'Não entregue';

            // Usar pessoa_cpf da entrega se disponível, senão usar cpf do solicitante
            $cpf = !empty($entrega['entrega_cpf']) ? $entrega['entrega_cpf'] : ($entrega['solicitante_cpf'] ?? 'N/A');
            $nome = $entrega['solicitante_nome'] ?? 'N/A';

            $detalhes = "Entregou " . ($entrega['tipo_ajuda'] ?? 'Benefício') . " para " .
                $nome . " (CPF: " . $cpf . ") em " . $data_entrega;

            // Adiciona hora se existir
            if ($hora_entrega) {
                $detalhes .= " às " . $hora_entrega;
            }

            // Adiciona status de entrega
            $detalhes .= " - Status: " . $status_entrega;

            // Usar data_entrega como created_at para auditoria, combinando com hora se disponível
            $data_auditoria = $entrega['data_entrega'] ?? date('Y-m-d');
            if (!empty($entrega['hora_entrega'])) {
                $data_auditoria .= ' ' . $entrega['hora_entrega'];
            } else {
                $data_auditoria .= ' 00:00:00';
            }

            $stmt->execute([
                $entrega['responsavel'],
                $entrega['id'],
                $detalhes,
                $data_auditoria
            ]);
        }
    } catch (Exception $e) {
        error_log("Erro ao inserir histórico de entregas: " . $e->getMessage());
    }
}

// Função segura para formatar números
function format_number($num)
{
    if ($num === null || $num === '') {
        return '0';
    }
    if (!is_numeric($num)) {
        $num = (float) $num;
    }
    return number_format((float)$num, 0, ',', '.');
}

// Função segura para htmlspecialchars
function safe_html($string)
{
    if ($string === null) {
        return '';
    }
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

// Função segura para formatar data (trata valores nulos)
function safe_date($date, $format = 'd/m/Y')
{
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '—';
    }
    try {
        return date($format, strtotime($date));
    } catch (Exception $e) {
        return '—';
    }
}

// Função segura para formatar hora (trata valores nulos)
function safe_time($time, $format = 'H:i')
{
    if (empty($time) || $time === '00:00:00') {
        return '—';
    }
    try {
        return date($format, strtotime($time));
    } catch (Exception $e) {
        return '—';
    }
}

// Função para formatar data e hora juntas (para entregas)
function safe_datetime_entrega($date, $time = null)
{
    if (empty($date) || $date === '0000-00-00') {
        return '—';
    }

    $formatted_date = safe_date($date);

    if (!empty($time) && $time !== '00:00:00') {
        $formatted_time = safe_time($time);
        return $formatted_date . ' ' . $formatted_time;
    }

    return $formatted_date;
}

// Criar tabela de auditoria se não existir
try {
    criarTabelaAuditoria($pdo);
} catch (Exception $e) {
    error_log("Erro ao criar tabela de auditoria: " . $e->getMessage());
}

// Funções para carregar dados dos gráficos via AJAX
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] === 'grafico_usuarios') {
        header('Content-Type: application/json');

        try {
            $sql_users = "SELECT 
                usuario,
                COUNT(*) as total_acoes
            FROM auditoria_usuarios 
            WHERE usuario IS NOT NULL AND usuario != 'Sistema'
            GROUP BY usuario 
            ORDER BY total_acoes DESC 
            LIMIT 15";

            $stmt = $pdo->query($sql_users);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $series = [];

            foreach ($data as $row) {
                $labels[] = substr($row['usuario'], 0, 20) . (strlen($row['usuario']) > 20 ? '...' : '');
                $series[] = (int)$row['total_acoes'];
            }

            echo json_encode([
                'labels' => $labels,
                'series' => $series,
                'usuarios_completos' => array_column($data, 'usuario'),
                'total_usuarios' => count($data)
            ]);
        } catch (Exception $e) {
            echo json_encode(['labels' => [], 'series' => [], 'usuarios_completos' => [], 'total_usuarios' => 0]);
        }
        exit();
    } elseif ($_GET['ajax'] === 'grafico_diario') {
        header('Content-Type: application/json');

        try {
            // Agrupa por data da ação (usando created_at da auditoria)
            $sql_daily = "SELECT 
                DATE(created_at) as data_dia,
                COUNT(*) as total_acoes
            FROM auditoria_usuarios 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY data_dia";

            $stmt = $pdo->query($sql_daily);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $series = [];

            foreach ($data as $row) {
                $labels[] = date('d/m', strtotime($row['data_dia']));
                $series[] = (int)$row['total_acoes'];
            }

            echo json_encode([
                'labels' => $labels,
                'series' => $series,
                'total_dias' => count($data)
            ]);
        } catch (Exception $e) {
            echo json_encode(['labels' => [], 'series' => [], 'total_dias' => 0]);
        }
        exit();
    }
}

// Processar exportação para Excel
if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=auditoria_' . date('Y-m-d_H-i-s') . '.xls');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #4CAF50; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }';
    echo 'td { padding: 8px; border: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo 'tr:hover { background-color: #ddd; }';
    echo '</style></head><body>';

    echo '<table>';
    echo '<tr><th>Data/Hora</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Detalhes</th></tr>';

    $where_conditions = [];
    $params = [];

    if (!empty($_GET['usuario'])) {
        $where_conditions[] = 'usuario = ?';
        $params[] = $_GET['usuario'];
    }

    if (!empty($_GET['acao'])) {
        $where_conditions[] = 'acao = ?';
        $params[] = $_GET['acao'];
    }

    if (!empty($_GET['data_inicio'])) {
        $where_conditions[] = 'DATE(created_at) >= ?';
        $params[] = $_GET['data_inicio'];
    }

    if (!empty($_GET['data_fim'])) {
        $where_conditions[] = 'DATE(created_at) <= ?';
        $params[] = $_GET['data_fim'];
    }

    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    $sql = "SELECT created_at, usuario, acao, entidade, detalhes 
            FROM auditoria_usuarios 
            $where_sql 
            ORDER BY created_at DESC 
            LIMIT 1000";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo '<tr>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($row['created_at'])) . '</td>';
            echo '<td>' . safe_html($row['usuario']) . '</td>';
            echo '<td>' . safe_html($row['acao']) . '</td>';
            echo '<td>' . safe_html($row['entidade']) . '</td>';
            echo '<td>' . safe_html($row['detalhes']) . '</td>';
            echo '</tr>';
        }
    } catch (Exception $e) {
        echo '<tr><td colspan="5">Erro ao exportar dados</td></tr>';
    }

    echo '</table></body></html>';
    exit();
}

// Processar detalhes via AJAX (ATUALIZADA)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes') {
    $id = $_GET['id'] ?? 0;

    try {
        $stmt = $pdo->prepare("SELECT 
                                    au.*, 
                                    ae.data_entrega,
                                    ae.hora_entrega,
                                    ae.quantidade,
                                    ae.valor_aplicado,
                                    ae.observacao,
                                    ae.entregue,
                                    s.nome as solicitante_nome,
                                    s.cpf as solicitante_cpf,
                                    s.created_at as solicitante_cadastro,
                                    at.nome as tipo_ajuda,
                                    ae.pessoa_cpf as entrega_cpf
                                FROM auditoria_usuarios au
                                LEFT JOIN ajudas_entregas ae ON au.entidade_id = ae.id AND au.entidade = 'Entrega'
                                LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
                                LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id
                                WHERE au.id = ?");
        $stmt->execute([$id]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {
            echo '<div class="alert alert-danger">Registro não encontrado</div>';
            exit;
        }
?>
        <div class="card">
            <div class="card-header bg-light">
                <h6 class="mb-0">Detalhes da Ação</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <tr>
                            <th width="140" class="text-nowrap">Data/Hora:</th>
                            <td><?= safe_datetime_entrega($registro['created_at']) ?></td>
                        </tr>
                        <tr>
                            <th class="text-nowrap">Usuário:</th>
                            <td><span class="badge bg-dark"><?= safe_html($registro['usuario']) ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-nowrap">Ação:</th>
                            <td><span class="badge bg-info"><?= safe_html($registro['acao']) ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-nowrap">Entidade:</th>
                            <td><span class="badge bg-secondary"><?= safe_html($registro['entidade']) ?></span></td>
                        </tr>
                        <tr>
                            <th class="text-nowrap">Detalhes:</th>
                            <td class="text-break"><?= safe_html($registro['detalhes']) ?></td>
                        </tr>

                        <?php if ($registro['entidade'] === 'Entrega' && !empty($registro['data_entrega'])): ?>
                            <tr>
                                <th class="text-nowrap">Data Entrega:</th>
                                <td><?= safe_date($registro['data_entrega']) ?></td>
                            </tr>
                            <?php if (!empty($registro['hora_entrega'])): ?>
                                <tr>
                                    <th class="text-nowrap">Hora Entrega:</th>
                                    <td><?= safe_time($registro['hora_entrega']) ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th class="text-nowrap">Status:</th>
                                <td>
                                    <span class="badge <?= !empty($registro['entregue']) && $registro['entregue'] == 'Sim' ? 'bg-success' : 'bg-warning' ?>">
                                        <?= !empty($registro['entregue']) && $registro['entregue'] == 'Sim' ? 'Entregue' : 'Não entregue' ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if (!empty($registro['quantidade'])): ?>
                                <tr>
                                    <th class="text-nowrap">Quantidade:</th>
                                    <td><?= $registro['quantidade'] ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($registro['valor_aplicado'])): ?>
                                <tr>
                                    <th class="text-nowrap">Valor:</th>
                                    <td>R$ <?= number_format((float)$registro['valor_aplicado'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if (!empty($registro['observacao'])): ?>
                                <tr>
                                    <th class="text-nowrap">Observação:</th>
                                    <td class="text-break"><?= safe_html($registro['observacao']) ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (!empty($registro['solicitante_nome'])): ?>
                            <tr>
                                <th class="text-nowrap">Solicitante:</th>
                                <td><?= safe_html($registro['solicitante_nome']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-nowrap">CPF:</th>
                                <td>
                                    <?php
                                    // Mostrar pessoa_cpf da entrega se disponível, senão mostrar cpf do solicitante
                                    $cpf = !empty($registro['entrega_cpf']) ? $registro['entrega_cpf'] : $registro['solicitante_cpf'];
                                    echo safe_html($cpf);
                                    ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php if (!empty($registro['tipo_ajuda'])): ?>
                            <tr>
                                <th class="text-nowrap">Tipo de Ajuda:</th>
                                <td><?= safe_html($registro['tipo_ajuda']) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
<?php
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Erro ao carregar detalhes: ' . $e->getMessage() . '</div>';
    }
    exit();
}

// Processar filtros
$filtro_usuario = $_GET['usuario'] ?? '';
$filtro_acao = $_GET['acao'] ?? '';
$filtro_entidade = $_GET['entidade'] ?? '';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_termo = $_GET['termo'] ?? '';

// Construir consulta com filtros
$where_conditions = [];
$params = [];

if (!empty($filtro_usuario)) {
    $where_conditions[] = 'usuario = ?';
    $params[] = $filtro_usuario;
}

if (!empty($filtro_acao)) {
    $where_conditions[] = 'acao = ?';
    $params[] = $filtro_acao;
}

if (!empty($filtro_entidade)) {
    $where_conditions[] = 'entidade = ?';
    $params[] = $filtro_entidade;
}

if (!empty($filtro_data_inicio)) {
    $where_conditions[] = 'DATE(created_at) >= ?';
    $params[] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $where_conditions[] = 'DATE(created_at) <= ?';
    $params[] = $filtro_data_fim;
}

if (!empty($filtro_termo)) {
    $where_conditions[] = '(usuario LIKE ? OR acao LIKE ? OR entidade LIKE ? OR detalhes LIKE ?)';
    $termo_like = '%' . $filtro_termo . '%';
    for ($i = 0; $i < 4; $i++) {
        $params[] = $termo_like;
    }
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Contar total de registros
try {
    $sql_count = "SELECT COUNT(*) as total FROM auditoria_usuarios $where_sql";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_registros = (int)($result['total'] ?? 0);
} catch (Exception $e) {
    $total_registros = 0;
}

// Paginação
$registros_por_pagina = 30;
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;
$total_paginas = $total_registros > 0 ? ceil($total_registros / $registros_por_pagina) : 1;

// Buscar registros de auditoria com paginação (ATUALIZADA - mostra entregas)
$registros = [];
try {
    $sql = "SELECT 
                au.id, 
                au.usuario, 
                au.acao, 
                au.entidade, 
                au.detalhes, 
                au.created_at,
                ae.data_entrega,
                ae.hora_entrega,
                ae.entregue,
                ae.pessoa_cpf as entrega_cpf,
                s.nome as solicitante_nome,
                at.nome as tipo_ajuda
            FROM auditoria_usuarios au
            LEFT JOIN ajudas_entregas ae ON au.entidade_id = ae.id AND au.entidade = 'Entrega'
            LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
            LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id
            $where_sql 
            ORDER BY au.created_at DESC 
            LIMIT ? OFFSET ?";

    $params_pagination = array_merge($params, [$registros_por_pagina, $offset]);

    $stmt = $pdo->prepare($sql);
    foreach ($params_pagination as $index => $param) {
        $stmt->bindValue($index + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $registros = [];
}

// Buscar opções para filtros
try {
    $sql_usuarios = "SELECT DISTINCT usuario FROM auditoria_usuarios WHERE usuario IS NOT NULL ORDER BY usuario LIMIT 100";
    $usuarios = $pdo->query($sql_usuarios)->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $usuarios = [];
}

try {
    $sql_acoes = "SELECT DISTINCT acao FROM auditoria_usuarios WHERE acao IS NOT NULL ORDER BY acao LIMIT 20";
    $acoes = $pdo->query($sql_acoes)->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $acoes = [];
}

try {
    $sql_entidades = "SELECT DISTINCT entidade FROM auditoria_usuarios WHERE entidade IS NOT NULL ORDER BY entidade LIMIT 10";
    $entidades = $pdo->query($sql_entidades)->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $entidades = [];
}

// Estatísticas ATUALIZADAS - usando MIN e MAX de created_at
try {
    $sql_stats = "SELECT 
        COUNT(*) as total_acoes,
        COUNT(DISTINCT usuario) as usuarios_ativos,
        MIN(created_at) as primeira_acao,
        MAX(created_at) as ultima_acao
    FROM auditoria_usuarios";

    if (!empty($where_sql)) {
        $sql_stats = "SELECT 
            COUNT(*) as total_acoes,
            COUNT(DISTINCT usuario) as usuarios_ativos,
            MIN(created_at) as primeira_acao,
            MAX(created_at) as ultima_acao
        FROM auditoria_usuarios $where_sql";

        $stmt_stats = $pdo->prepare($sql_stats);
        $stmt_stats->execute($params);
        $estatisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    } else {
        $estatisticas = $pdo->query($sql_stats)->fetch(PDO::FETCH_ASSOC);
    }

    $estatisticas['total_acoes'] = (int)($estatisticas['total_acoes'] ?? 0);
    $estatisticas['usuarios_ativos'] = (int)($estatisticas['usuarios_ativos'] ?? 0);
    $estatisticas['entidades_afetadas'] = 3;
} catch (Exception $e) {
    $estatisticas = [
        'total_acoes' => 0,
        'usuarios_ativos' => 0,
        'entidades_afetadas' => 3,
        'primeira_acao' => null,
        'ultima_acao' => null
    ];
}

// Dados para linha do tempo (ATUALIZADA - mostra entregas e solicitantes)
try {
    $sql_timeline = "SELECT 
                         au.usuario, 
                         au.acao, 
                         au.detalhes, 
                         au.created_at,
                         ae.data_entrega,
                         ae.hora_entrega,
                         ae.entregue,
                         ae.pessoa_cpf as entrega_cpf,
                         s.nome as solicitante_nome,
                         at.nome as tipo_ajuda
                     FROM auditoria_usuarios au
                     LEFT JOIN ajudas_entregas ae ON au.entidade_id = ae.id AND au.entidade = 'Entrega'
                     LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
                     LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id
                     ORDER BY au.created_at DESC 
                     LIMIT 10";
    $timeline_data = $pdo->query($sql_timeline)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $timeline_data = [];
}

// Quantos por página
$perPage = 6;

// Página atual (GET ?u_page=2)
$page = isset($_GET['u_page']) ? (int)$_GET['u_page'] : 1;
if ($page < 1) $page = 1;

$offset = ($page - 1) * $perPage;

// Total de usuários (para saber quantas páginas existem)
try {
    $sql_count_users = "
        SELECT COUNT(*) FROM (
            SELECT usuario
            FROM auditoria_usuarios
            WHERE usuario IS NOT NULL AND usuario != 'Sistema'
            GROUP BY usuario
        ) t
    ";
    $totalUsers = (int)$pdo->query($sql_count_users)->fetchColumn();
} catch (Exception $e) {
    $totalUsers = 0;
}

$totalPages = ($totalUsers > 0) ? (int)ceil($totalUsers / $perPage) : 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Buscar a página atual
try {
    $sql_top_users = "
        SELECT 
            usuario,
            COUNT(*) as total_acoes,
            SUM(CASE WHEN acao = 'Cadastrou' AND entidade = 'Solicitante' THEN 1 ELSE 0 END) as cadastros,
            SUM(CASE WHEN acao = 'Entregou' AND entidade = 'Entrega' THEN 1 ELSE 0 END) as entregas,
            MAX(created_at) as ultima_acao
        FROM auditoria_usuarios 
        WHERE usuario IS NOT NULL AND usuario != 'Sistema'
        GROUP BY usuario 
        ORDER BY total_acoes DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql_top_users);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $top_users_table = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $top_users_table = [];
    $totalPages = 1;
    $page = 1;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoria de Usuários - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.css">

    <style>
        .audit-card {
            transition: transform 0.2s;
            cursor: pointer;
        }

        .audit-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .badge-acao {
            background-color: #007bff;
            color: white;
        }

        .badge-entidade {
            background-color: #6c757d;
            color: white;
        }

        .badge-usuario {
            background-color: #343a40;
            color: white;
        }

        .badge-entregue {
            background-color: #28a745;
            color: white;
        }

        .badge-nao-entregue {
            background-color: #dc3545;
            color: white;
        }

        .stat-card {
            border-left: 4px solid;
            min-height: 85px;
        }

        .stat-card-total {
            border-left-color: #007bff;
        }

        .stat-card-users {
            border-left-color: #28a745;
        }

        .stat-card-first {
            border-left-color: #17a2b8;
        }

        .stat-card-last {
            border-left-color: #dc3545;
        }

        .card-equal-height {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-equal-height .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 !important;
        }

        .timeline-container {
            max-height: 480px;
            overflow-y: auto;
            padding: 1rem;
        }

        .timeline {
            border-left: 3px solid #007bff;
            margin: 0;
            padding-left: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 12px;
            border-bottom: 1px solid #eee;
        }

        .timeline-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: -26px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
        }

        .timeline-date {
            font-size: 0.8em;
            color: #666;
            margin-bottom: 3px;
        }

        .timeline-user {
            font-weight: 600;
            color: #333;
            font-size: 0.9em;
            margin-bottom: 3px;
        }

        .timeline-action {
            font-size: 0.85em;
            color: #555;
            line-height: 1.3;
        }

        .timeline-container::-webkit-scrollbar {
            width: 6px;
        }

        .timeline-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .timeline-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .timeline-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .action-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .action-icon.cadastrou {
            background-color: #28a745;
            color: white;
        }

        .action-icon.editou {
            background-color: #ffc107;
            color: black;
        }

        .action-icon.entregou {
            background-color: #17a2b8;
            color: white;
        }

        .action-icon.excluiu {
            background-color: #dc3545;
            color: white;
        }

        .action-icon.visualizou {
            background-color: #6c757d;
            color: white;
        }

        .search-highlight {
            background-color: #fff3cd;
            padding: 0.1rem 0.3rem;
            border-radius: 0.2rem;
            font-weight: bold;
        }

        .audit-log {
            max-height: 500px;
            overflow-y: auto;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
            height: 100%;
            min-height: 350px;
            display: flex;
            flex-direction: column;
        }

        .chart-wrapper {
            flex: 1;
            position: relative;
            min-height: 300px;
        }

        #chartUsers,
        #chartDaily {
            height: 100%;
            min-height: 300px;
        }

        .chart-placeholder {
            text-align: center;
            color: #6c757d;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
            min-height: 300px;
        }

        .chart-placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 8px;
        }

        .chart-loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%;
            min-height: 300px;
        }

        .chart-tooltip {
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .chart-legend {
            font-size: 0.8rem;
            margin-top: 10px;
            color: #666;
        }

        .modal-detalhes .modal-dialog {
            max-width: 700px;
        }

        .modal-detalhes .modal-content {
            border-radius: 8px;
        }

        .modal-detalhes .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            padding: 0;
        }

        @media (max-width: 768px) {
            .modal-detalhes .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }

            .modal-detalhes .modal-body {
                max-height: 80vh;
            }
        }

        @media (max-width: 576px) {
            .modal-detalhes .modal-dialog {
                margin: 5px;
                max-width: calc(100% - 10px);
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between">
                        <div class="logo"><a href="#"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- MENU (ANEXO RESTRITO) -->
                <div class="sidebar-menu">
                    <ul class="menu">

                        <!-- DASHBOARD FINANCEIRO -->
                        <li class="sidebar-item">
                            <a href="dashboard.php" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>

                        <!-- ENTREGAS DE BENEFÍCIOS -->
                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-hand-thumbs-up-fill"></i>
                                <span>Entregas</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="registrarEntrega.php">Registrar Entrega</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="entregasRealizadas.php">Histórico de Entregas</a>
                                </li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item "><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
                            </ul>
                        </li>

                        <!-- CONTROLE DE VALORES -->
                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-cash-stack"></i>
                                <span>Controle Financeiro</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="valoresAplicados.php">Valores Aplicados</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a>
                                </li>
                            </ul>
                        </li>

                        <!-- 🔒 USUÁRIOS (ÚNICO COM CONTROLE DE PERFIL) -->
                        <?php if (($_SESSION['user_role'] ?? '') === 'suporte'): ?>
                            <li class="sidebar-item has-sub">
                                <a href="#" class="sidebar-link">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Usuários</span>
                                </a>
                                <ul class="submenu">
                                    <li class="submenu-item">
                                        <a href="usuariosPermitidos.php">Permitidos</a>
                                    </li>
                                    <li class="submenu-item">
                                        <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <!-- AUDITORIA / LOG -->
                        <li class="sidebar-item active">
                            <a href="auditoria.php" class="sidebar-link">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Auditoria</span>
                            </a>
                        </li>

                        <!-- SAIR -->
                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Sair</span>
                            </a>
                        </li>

                    </ul>
                </div>
                <!-- /MENU -->

            </div>
        </div>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>

            <div class="page-heading">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Auditoria de Usuários</h3>
                        <p class="text-muted">Registro de todas as ações realizadas pelos usuários no sistema</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-outline-success" onclick="exportarExcel()">
                            <i class="bi bi-file-excel"></i> Exportar Excel
                        </button>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFiltros">
                            <i class="bi bi-funnel"></i> Filtros
                        </button>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <section class="row">
                    <!-- Estatísticas RÁPIDAS -->
                    <div class="col-12">
                        <div class="row">
                            <div class="col-md-6 col-sm-6 mb-3">
                                <div class="card stat-card stat-card-total h-100">
                                    <div class="card-body">
                                        <p class="stat-number"><?= format_number($estatisticas['total_acoes']) ?></p>
                                        <p class="stat-label">Total de Ações</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6 mb-3">
                                <div class="card stat-card stat-card-users h-100">
                                    <div class="card-body">
                                        <p class="stat-number"><?= format_number($estatisticas['usuarios_ativos']) ?></p>
                                        <p class="stat-label">Usuários Ativos</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6 mb-3">
                                <div class="card stat-card stat-card-first h-100">
                                    <div class="card-body">
                                        <p class="stat-number">
                                            <?= !empty($estatisticas['primeira_acao']) ? safe_date($estatisticas['primeira_acao'], 'd/m/Y') : 'N/A' ?>
                                        </p>
                                        <p class="stat-label">Primeira Ação</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6 mb-3">
                                <div class="card stat-card stat-card-last h-100">
                                    <div class="card-body">
                                        <p class="stat-number">
                                            <?= !empty($estatisticas['ultima_acao']) ? safe_date($estatisticas['ultima_acao'], 'd/m/Y H:i') : 'N/A' ?>
                                        </p>
                                        <p class="stat-label">Última Ação</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos (carregamento diferido) -->
                    <div class="col-12 mb-4">
                        <div class="">
                            <div class="card-body">
                                <h5 class="card-title">Visão Geral das Ações</h5>
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-2">
                                        <div class="chart-container">
                                            <div class="chart-title">Top 15 Usuários Mais Ativos</div>
                                            <div class="chart-wrapper">
                                                <div id="chartUsers"></div>
                                                <div id="chartUsersLoading" class="chart-loading" style="display: none;">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Carregando...</span>
                                                    </div>
                                                    <p class="mt-2">Carregando gráfico...</p>
                                                </div>
                                            </div>
                                            <div class="chart-legend text-center">
                                                <span class="badge bg-primary me-2">Barra = Número de ações</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <div class="chart-container">
                                            <div class="chart-title">Ações nos Últimos 30 Dias</div>
                                            <div class="chart-wrapper">
                                                <div id="chartDaily"></div>
                                                <div id="chartDailyLoading" class="chart-loading" style="display: none;">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Carregando...</span>
                                                    </div>
                                                    <p class="mt-2">Carregando gráfico...</p>
                                                </div>
                                            </div>
                                            <div class="chart-legend text-center">
                                                <span class="badge bg-success me-2">Linha = Tendência de ações</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros Ativos -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Filtros Ativos</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!empty($filtro_usuario)): ?>
                                        <span class="badge bg-primary">
                                            Usuário: <?= safe_html($filtro_usuario) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['usuario' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($filtro_acao)): ?>
                                        <span class="badge bg-info">
                                            Ação: <?= safe_html($filtro_acao) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['acao' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($filtro_entidade)): ?>
                                        <span class="badge bg-success">
                                            Entidade: <?= safe_html($filtro_entidade) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['entidade' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($filtro_data_inicio) || !empty($filtro_data_fim)): ?>
                                        <span class="badge bg-warning text-dark">
                                            Período: <?= safe_html($filtro_data_inicio) ?> a <?= safe_html($filtro_data_fim) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['data_inicio' => '', 'data_fim' => ''])) ?>" class="text-dark ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($filtro_termo)): ?>
                                        <span class="badge bg-secondary">
                                            Termo: "<?= safe_html($filtro_termo) ?>"
                                            <a href="?<?= http_build_query(array_merge($_GET, ['termo' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty(array_filter([$filtro_usuario, $filtro_acao, $filtro_entidade, $filtro_data_inicio, $filtro_data_fim, $filtro_termo]))): ?>
                                        <a href="auditoria.php" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle"></i> Limpar Filtros
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Registros de Auditoria -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Registros de Ações dos Usuários</h5>
                                <p class="card-subtitle text-muted">
                                    Total de <?= format_number($total_registros) ?> ações registradas
                                </p>
                            </div>

                            <div class="card-body">
                                <div class="audit-log">
                                    <?php if (empty($registros)): ?>
                                        <div class="text-center text-muted py-5">
                                            <i class="bi bi-search display-6 d-block mb-2"></i>
                                            Nenhuma ação de usuário encontrada
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($registros as $registro): ?>
                                            <div class="card mb-3 audit-card audit-item"
                                                onclick="verDetalhes(<?= $registro['id'] ?>)">
                                                <div class="card-body">
                                                    <div class="audit-row">
                                                        <!-- Ícone -->
                                                        <div class="audit-icon">
                                                            <?php
                                                            $acao = strtolower($registro['acao'] ?? '');
                                                            $icon = match ($acao) {
                                                                'cadastrou' => 'bi-plus-circle',
                                                                'editou'    => 'bi-pencil-square',
                                                                'entregou'  => 'bi-box-seam',
                                                                default     => 'bi-activity'
                                                            };
                                                            ?>
                                                            <div class="action-icon <?= $acao ?>">
                                                                <i class="bi <?= $icon ?>"></i>
                                                            </div>
                                                        </div>

                                                        <!-- Conteúdo -->
                                                        <div class="audit-content">
                                                            <div class="audit-header">
                                                                <div class="audit-badges">
                                                                    <span class="badge badge-usuario"><?= safe_html($registro['usuario']) ?></span>
                                                                    <span class="badge badge-acao"><?= safe_html($registro['acao']) ?></span>
                                                                    <span class="badge badge-entidade"><?= safe_html($registro['entidade']) ?></span>

                                                                    <?php if ($registro['acao'] === 'Entregou' && !empty($registro['entregue'])): ?>
                                                                        <span class="badge <?= $registro['entregue'] === 'Sim'
                                                                                                ? 'badge-entregue'
                                                                                                : 'badge-nao-entregue' ?>">
                                                                            <?= $registro['entregue'] === 'Sim' ? 'Entregue' : 'Não entregue' ?>
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <button class="btn btn-sm btn-outline-primary audit-btn mt-2"
                                                                    onclick="event.stopPropagation(); verDetalhes(<?= $registro['id'] ?>)">
                                                                    <i class="bi bi-eye"></i> Detalhes
                                                                </button>
                                                            </div>

                                                            <p class="audit-text mb-1">
                                                                <?= safe_html($registro['detalhes']) ?>
                                                                <?php if ($registro['entidade'] === 'Entrega' && !empty($registro['tipo_ajuda'])): ?>
                                                                    <br><small class="text-muted">Tipo: <?= safe_html($registro['tipo_ajuda']) ?></small>
                                                                <?php endif; ?>
                                                            </p>

                                                            <small class="text-muted audit-date">
                                                                <i class="bi bi-clock"></i>
                                                                <?= safe_datetime_entrega($registro['created_at']) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Linha do tempo recente -->
                    <div class="col-md-6 mb-3">
                        <div class="card card-equal-height h-100">
                            <div class="card-header">
                                <h5 class="card-title">Linha do Tempo Recente</h5>
                                <p class="card-subtitle text-muted">Últimas 10 ações</p>
                            </div>
                            <div class="card-body p-3">
                                <div class="timeline-container">
                                    <div class="timeline">
                                        <?php if (!empty($timeline_data)): ?>
                                            <?php foreach ($timeline_data as $item): ?>
                                                <div class="timeline-item">
                                                    <div class="timeline-date">
                                                        <?= safe_datetime_entrega($item['created_at']) ?>
                                                    </div>
                                                    <div class="timeline-user">
                                                        <strong><?= safe_html($item['usuario'] ?? 'Desconhecido') ?></strong>
                                                        <span class="badge bg-info ms-2"><?= safe_html($item['acao'] ?? 'Ação') ?></span>
                                                        <?php if ($item['acao'] == 'Entregou' && !empty($item['entregue'])): ?>
                                                            <span class="badge <?= $item['entregue'] == 'Sim' ? 'bg-success' : 'bg-warning' ?> ms-1">
                                                                <?= $item['entregue'] == 'Sim' ? '✓' : '✗' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="timeline-action">
                                                        <?php
                                                        $detalhes = safe_html($item['detalhes'] ?? '');
                                                        echo $detalhes;
                                                        ?>
                                                        <?php if ($item['acao'] == 'Entregou' && !empty($item['tipo_ajuda'])): ?>
                                                            <br><small class="text-muted">Tipo: <?= safe_html($item['tipo_ajuda']) ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted text-center py-3">Nenhuma ação recente</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    $perPageUsers = 6;

                    $usersPage = isset($_GET['u_page']) ? (int)$_GET['u_page'] : 1;
                    if ($usersPage < 1) $usersPage = 1;

                    $usersOffset = ($usersPage - 1) * $perPageUsers;

                    // total de usuários (pra paginação)
                    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM contas_acesso")->fetchColumn();
                    $totalUsersPages = max(1, (int)ceil($totalUsers / $perPageUsers));
                    if ($usersPage > $totalUsersPages) $usersPage = $totalUsersPages;
                    $usersOffset = ($usersPage - 1) * $perPageUsers;

                    $sql = "
    SELECT
        u.nome AS usuario,

        COALESCE(s.cadastros, 0) AS cadastros,
        COALESCE(e.entregas, 0)  AS entregas,
        (COALESCE(s.cadastros, 0) + COALESCE(e.entregas, 0)) AS total_acoes,

        NULLIF(
          GREATEST(
            COALESCE(s.ultima_acao, '0000-00-00 00:00:00'),
            COALESCE(e.ultima_acao, '0000-00-00 00:00:00')
          ),
          '0000-00-00 00:00:00'
        ) AS ultima_acao

    FROM contas_acesso u

    LEFT JOIN (
        SELECT
            LOWER(TRIM(responsavel)) AS resp_key,
            COUNT(*) AS cadastros,
            MAX(created_at) AS ultima_acao
        FROM solicitantes
        WHERE responsavel IS NOT NULL AND TRIM(responsavel) <> ''
        GROUP BY LOWER(TRIM(responsavel))
    ) s ON s.resp_key = LOWER(TRIM(u.nome))

    LEFT JOIN (
        SELECT
            LOWER(TRIM(responsavel)) AS resp_key,
            COUNT(*) AS entregas,
            MAX(created_at) AS ultima_acao
        FROM ajudas_entregas
        WHERE responsavel IS NOT NULL AND TRIM(responsavel) <> ''
        GROUP BY LOWER(TRIM(responsavel))
    ) e ON e.resp_key = LOWER(TRIM(u.nome))

    ORDER BY total_acoes DESC, u.nome ASC
    LIMIT :limit OFFSET :offset
";

                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':limit', $perPageUsers, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $usersOffset, PDO::PARAM_INT);
                    $stmt->execute();
                    $top_users_table = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <div class="col-md-6">
                        <div class="card card-equal-h">
                            <div class="card-header">
                                <h5 class="card-title">Usuários Mais Ativos</h5>
                                <p class="card-subtitle text-muted">Detalhamento por tipo de ação</p>
                            </div>

                            <div class="card-body p-3">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Responsável</th>
                                                <th>Total</th>
                                                <th>Cadastros</th>
                                                <th>Entregas</th>
                                                <th>Última Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($top_users_table)): ?>
                                                <?php foreach ($top_users_table as $user): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-dark"><?= safe_html($user['usuario'] ?? 'Desconhecido') ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-primary"><?= format_number($user['total_acoes'] ?? 0) ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-success"><?= format_number($user['cadastros'] ?? 0) ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?= format_number($user['entregas'] ?? 0) ?></span>
                                                        </td>
                                                        <td>
                                                            <small><?= !empty($user['ultima_acao']) ? safe_date($user['ultima_acao'], 'd/m/Y H:i') : '—' ?></small>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-3">Nenhum dado disponível</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalUsersPages > 1): ?>
                                    <?php
                                    // preserva outros GETs e troca só u_page
                                    $qs = $_GET;

                                    $mkUrl = function (array $qs) {
                                        return '?' . http_build_query($qs);
                                    };

                                    $window = 2; // mostra +/-2 páginas ao redor
                                    $start = max(1, $usersPage - $window);
                                    $end   = min($totalUsersPages, $usersPage + $window);
                                    ?>
                                    <nav aria-label="Paginação usuários" class="mt-2">
                                        <ul class="pagination pagination-sm justify-content-end mb-0">
                                            <li class="page-item <?= ($usersPage <= 1) ? 'disabled' : '' ?>">
                                                <?php $qs['u_page'] = max(1, $usersPage - 1); ?>
                                                <a class="page-link" href="<?= $mkUrl($qs) ?>" tabindex="-1">Anterior</a>
                                            </li>

                                            <?php if ($start > 1): ?>
                                                <?php $qs['u_page'] = 1; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $mkUrl($qs) ?>">1</a>
                                                </li>
                                                <?php if ($start > 2): ?>
                                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                                <?php $qs['u_page'] = $p; ?>
                                                <li class="page-item <?= ($p === $usersPage) ? 'active' : '' ?>">
                                                    <a class="page-link" href="<?= $mkUrl($qs) ?>"><?= $p ?></a>
                                                </li>
                                            <?php endfor; ?>

                                            <?php if ($end < $totalUsersPages): ?>
                                                <?php if ($end < $totalUsersPages - 1): ?>
                                                    <li class="page-item disabled"><span class="page-link">…</span></li>
                                                <?php endif; ?>
                                                <?php $qs['u_page'] = $totalUsersPages; ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="<?= $mkUrl($qs) ?>"><?= $totalUsersPages ?></a>
                                                </li>
                                            <?php endif; ?>

                                            <li class="page-item <?= ($usersPage >= $totalUsersPages) ? 'disabled' : '' ?>">
                                                <?php $qs['u_page'] = min($totalUsersPages, $usersPage + 1); ?>
                                                <a class="page-link" href="<?= $mkUrl($qs) ?>">Próxima</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start text-black">
                        <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
                        <script>
                            document.getElementById('current-year').textContent = new Date().getFullYear();
                        </script>
                    </div>
                    <div class="float-end text-black">
                        <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <!-- Modal Filtros -->
    <div class="modal fade" id="modalFiltros" tabindex="-1" aria-labelledby="modalFiltrosLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="GET" action="">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalFiltrosLabel">Filtrar Auditoria</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Usuário</label>
                                <select class="form-select" name="usuario">
                                    <option value="">Todos</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?= safe_html($usuario) ?>" <?= $usuario == $filtro_usuario ? 'selected' : '' ?>>
                                            <?= safe_html($usuario) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ação</label>
                                <select class="form-select" name="acao">
                                    <option value="">Todas</option>
                                    <?php foreach ($acoes as $acao): ?>
                                        <option value="<?= safe_html($acao) ?>" <?= $acao == $filtro_acao ? 'selected' : '' ?>>
                                            <?= safe_html($acao) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Entidade</label>
                                <select class="form-select" name="entidade">
                                    <option value="">Todas</option>
                                    <?php foreach ($entidades as $entidade): ?>
                                        <option value="<?= safe_html($entidade) ?>" <?= $entidade == $filtro_entidade ? 'selected' : '' ?>>
                                            <?= safe_html($entidade) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data Início</label>
                                <input type="date" class="form-control" name="data_inicio" value="<?= safe_html($filtro_data_inicio) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data Fim</label>
                                <input type="date" class="form-control" name="data_fim" value="<?= safe_html($filtro_data_fim) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes SIMPLES E RESPONSIVA -->
    <div class="modal fade modal-detalhes" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalhesLabel">
                        <i class="bi bi-info-circle me-2"></i>Detalhes da Ação
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detalhesConteudo">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                        <p class="mt-2 text-muted">Carregando detalhes...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ApexCharts (carregamento diferido) -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>

    <script>
        // Variáveis globais para os gráficos
        let chartUsers = null;
        let chartDaily = null;

        // Função para carregar gráficos via AJAX
        function carregarGraficos() {
            // Mostrar loading nos gráficos
            document.getElementById('chartUsersLoading').style.display = 'flex';
            document.getElementById('chartDailyLoading').style.display = 'flex';

            // Gráfico de usuários mais ativos (coluna horizontal)
            fetch('auditoria.php?ajax=grafico_usuarios')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('chartUsersLoading').style.display = 'none';

                    if (data.series && data.labels && data.series.length > 0) {
                        const options = {
                            series: [{
                                name: 'Ações',
                                data: data.series
                            }],
                            chart: {
                                type: 'bar',
                                height: 350,
                                toolbar: {
                                    show: true,
                                    tools: {
                                        download: true,
                                        selection: false,
                                        zoom: false,
                                        zoomin: false,
                                        zoomout: false,
                                        pan: false,
                                        reset: false
                                    }
                                }
                            },
                            plotOptions: {
                                bar: {
                                    horizontal: true,
                                    borderRadius: 4,
                                    dataLabels: {
                                        position: 'right'
                                    }
                                }
                            },
                            colors: ['#007bff'],
                            dataLabels: {
                                enabled: true,
                                formatter: function(val) {
                                    return val;
                                },
                                offsetX: 10,
                                style: {
                                    fontSize: '12px',
                                    colors: ["#304758"]
                                }
                            },
                            xaxis: {
                                title: {
                                    text: 'Número de Ações',
                                    style: {
                                        fontSize: '14px',
                                        fontWeight: 'bold'
                                    }
                                },
                                labels: {
                                    formatter: function(val) {
                                        return Math.round(val);
                                    }
                                }
                            },
                            yaxis: {
                                categories: data.labels,
                                title: {
                                    text: 'Usuários',
                                    style: {
                                        fontSize: '14px',
                                        fontWeight: 'bold'
                                    }
                                },
                                labels: {
                                    style: {
                                        fontSize: '12px'
                                    },
                                    formatter: function(value) {
                                        const index = data.labels.indexOf(value);
                                        if (index !== -1 && data.usuarios_completos && data.usuarios_completos[index]) {
                                            return data.usuarios_completos[index];
                                        }
                                        return value;
                                    }
                                }
                            },
                            title: {
                                text: `Top ${data.total_usuarios} Usuários Mais Ativos`,
                                align: 'center',
                                style: {
                                    fontSize: '16px',
                                    fontWeight: 'bold',
                                    color: '#333'
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: function(val, opts) {
                                        const index = opts.dataPointIndex;
                                        const usuario = data.usuarios_completos && data.usuarios_completos[index] ?
                                            data.usuarios_completos[index] :
                                            data.labels[index];
                                        return `<div class="chart-tooltip">
                                            <strong>${usuario}</strong><br>
                                            <span>${val} ações realizadas</span>
                                        </div>`;
                                    }
                                }
                            },
                            responsive: [{
                                    breakpoint: 768,
                                    options: {
                                        chart: {
                                            height: 300
                                        },
                                        dataLabels: {
                                            enabled: false
                                        },
                                        yaxis: {
                                            labels: {
                                                style: {
                                                    fontSize: '10px'
                                                }
                                            }
                                        }
                                    }
                                },
                                {
                                    breakpoint: 480,
                                    options: {
                                        chart: {
                                            height: 250
                                        },
                                        title: {
                                            style: {
                                                fontSize: '14px'
                                            }
                                        },
                                        yaxis: {
                                            labels: {
                                                style: {
                                                    fontSize: '9px'
                                                },
                                                formatter: function(value) {
                                                    // Truncar nome do usuário em mobile muito pequeno
                                                    return value.length > 20 ? value.substring(0, 18) + '...' : value;
                                                }
                                            }
                                        },
                                        xaxis: {
                                            title: {
                                                style: {
                                                    fontSize: '12px'
                                                }
                                            }
                                        }
                                    }
                                }
                            ]
                        };

                        chartUsers = new ApexCharts(document.querySelector("#chartUsers"), options);
                        chartUsers.render();
                    } else {
                        document.querySelector("#chartUsers").innerHTML =
                            '<div class="chart-placeholder">' +
                            '<i class="bi bi-bar-chart"></i>' +
                            '<p>Nenhum dado disponível para o gráfico de usuários</p>' +
                            '</div>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar gráfico de usuários:', error);
                    document.getElementById('chartUsersLoading').style.display = 'none';
                    document.querySelector("#chartUsers").innerHTML =
                        '<div class="chart-placeholder">' +
                        '<i class="bi bi-exclamation-triangle"></i>' +
                        '<p>Erro ao carregar gráfico de usuários</p>' +
                        '</div>';
                });

            // Gráfico de ações diárias (área)
            fetch('auditoria.php?ajax=grafico_diario')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('chartDailyLoading').style.display = 'none';

                    if (data.series && data.labels && data.series.length > 0) {
                        const options = {
                            series: [{
                                name: 'Ações',
                                data: data.series
                            }],
                            chart: {
                                type: 'area',
                                height: 350,
                                toolbar: {
                                    show: true,
                                    tools: {
                                        download: true,
                                        selection: false,
                                        zoom: false,
                                        zoomin: false,
                                        zoomout: false,
                                        pan: false,
                                        reset: false
                                    }
                                }
                            },
                            colors: ['#28a745'],
                            stroke: {
                                curve: 'smooth',
                                width: 3
                            },
                            fill: {
                                type: 'gradient',
                                gradient: {
                                    shadeIntensity: 1,
                                    opacityFrom: 0.7,
                                    opacityTo: 0.3,
                                    stops: [0, 90, 100]
                                }
                            },
                            markers: {
                                size: 5,
                                hover: {
                                    size: 7
                                }
                            },
                            xaxis: {
                                categories: data.labels,
                                labels: {
                                    style: {
                                        fontSize: '12px'
                                    },
                                    rotate: -45
                                },
                                title: {
                                    text: 'Data',
                                    style: {
                                        fontSize: '14px',
                                        fontWeight: 'bold'
                                    }
                                }
                            },
                            yaxis: {
                                title: {
                                    text: 'Número de Ações',
                                    style: {
                                        fontSize: '14px',
                                        fontWeight: 'bold'
                                    }
                                },
                                min: 0,
                                labels: {
                                    formatter: function(val) {
                                        return Math.round(val);
                                    }
                                }
                            },
                            title: {
                                text: `Ações nos Últimos ${data.total_dias} Dias`,
                                align: 'center',
                                style: {
                                    fontSize: '16px',
                                    fontWeight: 'bold',
                                    color: '#333'
                                }
                            },
                            tooltip: {
                                y: {
                                    formatter: function(val, opts) {
                                        const date = data.labels[opts.dataPointIndex];
                                        return `<div class="chart-tooltip">
                                            <strong>${date}</strong><br>
                                            <span>${val} ações realizadas</span>
                                        </div>`;
                                    }
                                }
                            },
                            responsive: [{
                                    breakpoint: 768,
                                    options: {
                                        chart: {
                                            height: 300
                                        },
                                        xaxis: {
                                            labels: {
                                                rotate: -45,
                                                style: {
                                                    fontSize: '10px'
                                                }
                                            }
                                        }
                                    }
                                },
                                {
                                    breakpoint: 480,
                                    options: {
                                        chart: {
                                            height: 250
                                        },
                                        title: {
                                            style: {
                                                fontSize: '14px'
                                            }
                                        },
                                        xaxis: {
                                            labels: {
                                                style: {
                                                    fontSize: '9px'
                                                },
                                                rotate: -45
                                            },
                                            title: {
                                                style: {
                                                    fontSize: '12px'
                                                }
                                            }
                                        },
                                        yaxis: {
                                            title: {
                                                style: {
                                                    fontSize: '12px'
                                                }
                                            }
                                        }
                                    }
                                }
                            ]
                        };

                        chartDaily = new ApexCharts(document.querySelector("#chartDaily"), options);
                        chartDaily.render();
                    } else {
                        document.querySelector("#chartDaily").innerHTML =
                            '<div class="chart-placeholder">' +
                            '<i class="bi bi-graph-up"></i>' +
                            '<p>Nenhum dado disponível para o gráfico diário</p>' +
                            '</div>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar gráfico diário:', error);
                    document.getElementById('chartDailyLoading').style.display = 'none';
                    document.querySelector("#chartDaily").innerHTML =
                        '<div class="chart-placeholder">' +
                        '<i class="bi bi-exclamation-triangle"></i>' +
                        '<p>Erro ao carregar gráfico diário</p>' +
                        '</div>';
                });
        }

        // Carregar gráficos após a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Carregar gráficos com delay para não bloquear a renderização
            setTimeout(carregarGraficos, 800);

            // Recalcular gráficos ao redimensionar a janela
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    if (chartUsers) {
                        chartUsers.updateOptions({}, false, true);
                    }
                    if (chartDaily) {
                        chartDaily.updateOptions({}, false, true);
                    }
                }, 250);
            });
        });

        function verDetalhes(id) {
            // Mostrar loading otimizado
            document.getElementById('detalhesConteudo').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2 text-muted">Carregando detalhes...</p>
                </div>
            `;

            fetch(`auditoria.php?ajax=detalhes&id=${id}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('detalhesConteudo').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('detalhesConteudo').innerHTML = `
                        <div class="alert alert-danger m-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Erro ao carregar detalhes. Tente novamente.
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
                });
        }

        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            params.append('exportar', 'excel');
            window.location.href = `auditoria.php?${params.toString()}`;
        }

        // Função para destacar termos de busca
        function highlightSearchTerms() {
            const searchTerm = '<?= $filtro_termo ?>';
            if (searchTerm) {
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                document.querySelectorAll('.audit-card p').forEach(p => {
                    if (p.textContent.match(regex)) {
                        p.innerHTML = p.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                    }
                });
            }
        }

        // Inicializar quando a página carregar
        document.addEventListener('DOMContentLoaded', highlightSearchTerms);
    </script>

</body>

</html>
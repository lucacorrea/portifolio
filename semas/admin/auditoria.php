<?php

declare(strict_types=1);

/* AUTH */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* Apenas usuários com perfil 'suporte' ou 'admin' podem acessar auditoria */
$allowed_roles = ['suporte', 'admin', 'secretario', 'prefeito'];
if (!in_array(($_SESSION['user_role'] ?? ''), $allowed_roles, true)) {
    header('Location: index.php');
    exit();
}

/* DEBUG (remova em produção) */
ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

/* CONEXÃO */
require_once __DIR__ . '/../dist/assets/conexao.php';
if ((!isset($pdo) || !$pdo instanceof PDO) && function_exists('db')) {
    $pdo = db();
}
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('Erro de conexão');
}

/* =========================
   HELPERS
========================= */
function format_number($num): string
{
    if ($num === null || $num === '') {
        return '0';
    }
    return number_format((float)$num, 0, ',', '.');
}

function safe_html($string): string
{
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

function safe_date($date, string $format = 'd/m/Y'): string
{
    if (
        empty($date) ||
        $date === '0000-00-00' ||
        $date === '0000-00-00 00:00:00'
    ) {
        return '—';
    }

    $ts = strtotime((string)$date);
    if ($ts === false) {
        return '—';
    }

    return date($format, $ts);
}

function safe_time($time, string $format = 'H:i'): string
{
    if (empty($time) || $time === '00:00:00') {
        return '—';
    }

    $ts = strtotime((string)$time);
    if ($ts === false) {
        return '—';
    }

    return date($format, $ts);
}

function safe_datetime($datetime, string $format = 'd/m/Y H:i'): string
{
    if (
        empty($datetime) ||
        $datetime === '0000-00-00' ||
        $datetime === '0000-00-00 00:00:00'
    ) {
        return '—';
    }

    $ts = strtotime((string)$datetime);
    if ($ts === false) {
        return '—';
    }

    return date($format, $ts);
}

function truncate_text(string $text, int $limit = 20): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '...' : $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit) . '...' : $text;
}

function first_non_empty(array $row, array $keys): ?string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return null;
}

/* =========================
   SQL BASE DA AUDITORIA
   AGORA DIRETO DAS TABELAS:
   - solicitantes
   - ajudas_entregas
========================= */
function getBaseAuditoriaSql(): string
{
    return <<<SQL
        SELECT
            'Solicitante' AS origem_tipo,
            s.id AS origem_id,
            TRIM(s.responsavel) AS usuario,
            'Cadastrou' AS acao,
            'Solicitante' AS entidade,
            COALESCE(NULLIF(TRIM(s.nome), ''), 'N/A') AS pessoa_nome,
            COALESCE(NULLIF(TRIM(s.cpf), ''), 'N/A') AS pessoa_cpf,
            NULL AS tipo_ajuda,
            NULL AS quantidade,
            NULL AS valor_aplicado,
            NULL AS observacao,
            CASE
                WHEN s.created_at IS NOT NULL
                 AND s.created_at <> '0000-00-00 00:00:00'
                THEN s.created_at
                ELSE NULL
            END AS evento_data,
            CONCAT(
                'Cadastrou o solicitante: ',
                COALESCE(NULLIF(TRIM(s.nome), ''), 'N/A'),
                ' (CPF: ',
                COALESCE(NULLIF(TRIM(s.cpf), ''), 'N/A'),
                ')'
            ) AS detalhes
        FROM solicitantes s
        WHERE s.responsavel IS NOT NULL
          AND TRIM(s.responsavel) <> ''

        UNION ALL

        SELECT
            'Entrega' AS origem_tipo,
            ae.id AS origem_id,
            TRIM(ae.responsavel) AS usuario,
            'Entregou' AS acao,
            'Entrega' AS entidade,
            COALESCE(NULLIF(TRIM(s.nome), ''), 'N/A') AS pessoa_nome,
            COALESCE(NULLIF(TRIM(ae.pessoa_cpf), ''), NULLIF(TRIM(s.cpf), ''), 'N/A') AS pessoa_cpf,
            COALESCE(NULLIF(TRIM(at.nome), ''), 'Benefício') AS tipo_ajuda,
            ae.quantidade,
            ae.valor_aplicado,
            ae.observacao,
            CASE
                WHEN ae.data_entrega IS NOT NULL
                 AND ae.data_entrega <> '0000-00-00'
                THEN CONCAT(
                    ae.data_entrega,
                    ' ',
                    CASE
                        WHEN ae.hora_entrega IS NOT NULL
                         AND ae.hora_entrega <> ''
                         AND ae.hora_entrega <> '00:00:00'
                        THEN ae.hora_entrega
                        ELSE '00:00:00'
                    END
                )
                WHEN ae.created_at IS NOT NULL
                 AND ae.created_at <> '0000-00-00 00:00:00'
                THEN ae.created_at
                ELSE NULL
            END AS evento_data,
            CONCAT(
                'Entregou ',
                COALESCE(NULLIF(TRIM(at.nome), ''), 'Benefício'),
                ' para ',
                COALESCE(NULLIF(TRIM(s.nome), ''), 'N/A'),
                ' (CPF: ',
                COALESCE(NULLIF(TRIM(ae.pessoa_cpf), ''), NULLIF(TRIM(s.cpf), ''), 'N/A'),
                ')',
                CASE
                    WHEN ae.data_entrega IS NOT NULL
                     AND ae.data_entrega <> '0000-00-00'
                    THEN CONCAT(' em ', DATE_FORMAT(ae.data_entrega, '%d/%m/%Y'))
                    ELSE ''
                END,
                CASE
                    WHEN ae.hora_entrega IS NOT NULL
                     AND ae.hora_entrega <> ''
                     AND ae.hora_entrega <> '00:00:00'
                    THEN CONCAT(' às ', DATE_FORMAT(ae.hora_entrega, '%H:%i'))
                    ELSE ''
                END
            ) AS detalhes
        FROM ajudas_entregas ae
        LEFT JOIN solicitantes s
               ON s.id = ae.pessoa_id
        LEFT JOIN ajudas_tipos at
               ON at.id = ae.ajuda_tipo_id
        WHERE ae.responsavel IS NOT NULL
          AND TRIM(ae.responsavel) <> ''
          AND (
                (ae.data_entrega IS NOT NULL AND ae.data_entrega <> '0000-00-00')
                OR
                (ae.created_at IS NOT NULL AND ae.created_at <> '0000-00-00 00:00:00')
          )
    SQL;
}

function buildFilters(array $input): array
{
    $filtro_usuario     = trim((string)($input['usuario'] ?? ''));
    $filtro_acao        = trim((string)($input['acao'] ?? ''));
    $filtro_entidade    = trim((string)($input['entidade'] ?? ''));
    $filtro_data_inicio = trim((string)($input['data_inicio'] ?? ''));
    $filtro_data_fim    = trim((string)($input['data_fim'] ?? ''));
    $filtro_termo       = trim((string)($input['termo'] ?? ''));

    $where = [];
    $params = [];

    if ($filtro_usuario !== '') {
        $where[] = 'aud.usuario = ?';
        $params[] = $filtro_usuario;
    }

    if ($filtro_acao !== '') {
        $where[] = 'aud.acao = ?';
        $params[] = $filtro_acao;
    }

    if ($filtro_entidade !== '') {
        $where[] = 'aud.entidade = ?';
        $params[] = $filtro_entidade;
    }

    if ($filtro_data_inicio !== '') {
        $where[] = 'DATE(aud.evento_data) >= ?';
        $params[] = $filtro_data_inicio;
    }

    if ($filtro_data_fim !== '') {
        $where[] = 'DATE(aud.evento_data) <= ?';
        $params[] = $filtro_data_fim;
    }

    if ($filtro_termo !== '') {
        $where[] = '(
            aud.usuario LIKE ?
            OR aud.acao LIKE ?
            OR aud.entidade LIKE ?
            OR aud.detalhes LIKE ?
            OR aud.pessoa_nome LIKE ?
            OR aud.pessoa_cpf LIKE ?
        )';
        $like = '%' . $filtro_termo . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    return [$whereSql, $params];
}

/* =========================
   FILTROS
========================= */
$filtro_usuario     = trim((string)($_GET['usuario'] ?? ''));
$filtro_acao        = trim((string)($_GET['acao'] ?? ''));
$filtro_entidade    = trim((string)($_GET['entidade'] ?? ''));
$filtro_data_inicio = trim((string)($_GET['data_inicio'] ?? ''));
$filtro_data_fim    = trim((string)($_GET['data_fim'] ?? ''));
$filtro_termo       = trim((string)($_GET['termo'] ?? ''));

$baseSql = getBaseAuditoriaSql();
[$whereSql, $params] = buildFilters($_GET);

/* =========================
   AJAX - GRÁFICO USUÁRIOS
   RESPEITA OS FILTROS ATUAIS
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'grafico_usuarios') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $sql = "
            SELECT
                aud.usuario,
                COUNT(*) AS total_acoes
            FROM ($baseSql) aud
            $whereSql
            GROUP BY aud.usuario
            ORDER BY total_acoes DESC, aud.usuario ASC
            LIMIT 15
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = [];
        $series = [];
        $usuariosCompletos = [];

        foreach ($rows as $row) {
            $usuario = (string)($row['usuario'] ?? 'Desconhecido');
            $usuariosCompletos[] = $usuario;
            $labels[] = $usuario;
            $series[] = (int)($row['total_acoes'] ?? 0);
        }

        echo json_encode([
            'labels' => $labels,
            'series' => $series,
            'usuarios_completos' => $usuariosCompletos,
            'total_usuarios' => count($rows),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'labels' => [],
            'series' => [],
            'usuarios_completos' => [],
            'total_usuarios' => 0,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* =========================
   AJAX - GRÁFICO DIÁRIO
   RESPEITA OS FILTROS + 30 DIAS
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'grafico_diario') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $whereDaily = $whereSql;
        $dailyParams = $params;

        if ($whereDaily === '') {
            $whereDaily = 'WHERE aud.evento_data >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } else {
            $whereDaily .= ' AND aud.evento_data >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }

        $sql = "
            SELECT
                DATE(aud.evento_data) AS data_dia,
                COUNT(*) AS total_acoes
            FROM ($baseSql) aud
            $whereDaily
            GROUP BY DATE(aud.evento_data)
            ORDER BY data_dia ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($dailyParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $labels = [];
        $series = [];

        foreach ($rows as $row) {
            $labels[] = safe_date($row['data_dia'], 'd/m');
            $series[] = (int)($row['total_acoes'] ?? 0);
        }

        echo json_encode([
            'labels' => $labels,
            'series' => $series,
            'total_dias' => count($rows),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode([
            'labels' => [],
            'series' => [],
            'total_dias' => 0,
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* =========================
   AJAX - DETALHES
========================= */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes') {
    $tipo = trim((string)($_GET['tipo'] ?? ''));
    $id = (int)($_GET['id'] ?? 0);

    if ($id <= 0 || !in_array($tipo, ['Solicitante', 'Entrega'], true)) {
        echo '<div class="alert alert-danger m-3">Parâmetros inválidos.</div>';
        exit;
    }

    try {
        if ($tipo === 'Solicitante') {
            $stmt = $pdo->prepare("SELECT * FROM solicitantes WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo '<div class="alert alert-danger m-3">Cadastro não encontrado.</div>';
                exit;
            }

            $telefone   = first_non_empty($row, ['telefone', 'fone', 'celular', 'whatsapp']);
            $endereco   = first_non_empty($row, ['endereco', 'logradouro', 'rua']);
            $numero     = first_non_empty($row, ['numero', 'n_casa']);
            $bairro     = first_non_empty($row, ['bairro', 'bairro_nome']);
            $obs        = first_non_empty($row, ['observacao', 'obs', 'assunto', 'descricao']);
            $nascimento = first_non_empty($row, ['data_nascimento', 'nascimento']);
            $renda      = first_non_empty($row, ['renda_familiar', 'renda', 'valor_renda']);

?>
            <div class="card border-0">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Detalhes do Cadastro</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tr>
                                <th width="170">Tipo:</th>
                                <td><span class="badge bg-success">Cadastro de Solicitante</span></td>
                            </tr>
                            <tr>
                                <th>ID:</th>
                                <td><?= (int)$row['id'] ?></td>
                            </tr>
                            <tr>
                                <th>Responsável:</th>
                                <td><span class="badge bg-dark"><?= safe_html($row['responsavel'] ?? '') ?></span></td>
                            </tr>
                            <tr>
                                <th>Nome:</th>
                                <td><?= safe_html($row['nome'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>CPF:</th>
                                <td><?= safe_html($row['cpf'] ?? '') ?></td>
                            </tr>

                            <?php if ($telefone): ?>
                                <tr>
                                    <th>Telefone:</th>
                                    <td><?= safe_html($telefone) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($nascimento): ?>
                                <tr>
                                    <th>Data Nascimento:</th>
                                    <td><?= safe_date($nascimento) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($renda): ?>
                                <tr>
                                    <th>Renda:</th>
                                    <td><?= safe_html($renda) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if ($endereco || $numero || $bairro): ?>
                                <tr>
                                    <th>Endereço:</th>
                                    <td>
                                        <?= safe_html(trim(($endereco ?? '') . ($numero ? ', ' . $numero : ''))) ?>
                                        <?= $bairro ? '<br><small class="text-muted">Bairro: ' . safe_html($bairro) . '</small>' : '' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <tr>
                                <th>Data do Cadastro:</th>
                                <td><?= safe_datetime($row['created_at'] ?? '') ?></td>
                            </tr>

                            <?php if ($obs): ?>
                                <tr>
                                    <th>Observação:</th>
                                    <td class="text-break"><?= safe_html($obs) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        <?php
            exit;
        }

        if ($tipo === 'Entrega') {
            $stmt = $pdo->prepare("
                SELECT
                    ae.*,
                    s.nome AS solicitante_nome,
                    s.cpf AS solicitante_cpf,
                    at.nome AS tipo_ajuda
                FROM ajudas_entregas ae
                LEFT JOIN solicitantes s ON s.id = ae.pessoa_id
                LEFT JOIN ajudas_tipos at ON at.id = ae.ajuda_tipo_id
                WHERE ae.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                echo '<div class="alert alert-danger m-3">Entrega não encontrada.</div>';
                exit;
            }

            $cpf = !empty($row['pessoa_cpf']) ? $row['pessoa_cpf'] : ($row['solicitante_cpf'] ?? '');

        ?>
            <div class="card border-0">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Detalhes da Entrega</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <tr>
                                <th width="170">Tipo:</th>
                                <td><span class="badge bg-info">Entrega</span></td>
                            </tr>
                            <tr>
                                <th>ID:</th>
                                <td><?= (int)$row['id'] ?></td>
                            </tr>
                            <tr>
                                <th>Responsável:</th>
                                <td><span class="badge bg-dark"><?= safe_html($row['responsavel'] ?? '') ?></span></td>
                            </tr>
                            <tr>
                                <th>Solicitante:</th>
                                <td><?= safe_html($row['solicitante_nome'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>CPF:</th>
                                <td><?= safe_html($cpf) ?></td>
                            </tr>
                            <tr>
                                <th>Benefício:</th>
                                <td><?= safe_html($row['tipo_ajuda'] ?? 'Benefício') ?></td>
                            </tr>
                            <tr>
                                <th>Data da Entrega:</th>
                                <td><?= safe_date($row['data_entrega'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>Hora da Entrega:</th>
                                <td><?= safe_time($row['hora_entrega'] ?? '') ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td><span class="badge bg-success">Entregue</span></td>
                            </tr>

                            <?php if (!empty($row['quantidade'])): ?>
                                <tr>
                                    <th>Quantidade:</th>
                                    <td><?= safe_html($row['quantidade']) ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if (!empty($row['valor_aplicado'])): ?>
                                <tr>
                                    <th>Valor Aplicado:</th>
                                    <td>R$ <?= number_format((float)$row['valor_aplicado'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endif; ?>

                            <?php if (!empty($row['observacao'])): ?>
                                <tr>
                                    <th>Observação:</th>
                                    <td class="text-break"><?= safe_html($row['observacao']) ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
<?php
            exit;
        }
    } catch (Throwable $e) {
        echo '<div class="alert alert-danger m-3">Erro ao carregar detalhes.</div>';
        exit;
    }
}

/* =========================
   EXPORTAÇÃO EXCEL
========================= */
if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename=auditoria_anexo_' . date('Y-m-d_H-i-s') . '.xls');
    header('Pragma: no-cache');
    header('Expires: 0');

    $gerado_em_dispositivo = trim((string)($_GET['gerado_em'] ?? ''));
    if ($gerado_em_dispositivo === '') {
        $gerado_em_dispositivo = date('d/m/Y H:i:s');
    }

    $filtros_exportacao = [];

    if ($filtro_usuario !== '') {
        $filtros_exportacao[] = 'Usuário: ' . $filtro_usuario;
    }

    if ($filtro_acao !== '') {
        $filtros_exportacao[] = 'Ação: ' . $filtro_acao;
    }

    if ($filtro_entidade !== '') {
        $filtros_exportacao[] = 'Entidade: ' . $filtro_entidade;
    }

    if ($filtro_data_inicio !== '') {
        $filtros_exportacao[] = 'Data inicial: ' . safe_date($filtro_data_inicio);
    }

    if ($filtro_data_fim !== '') {
        $filtros_exportacao[] = 'Data final: ' . safe_date($filtro_data_fim);
    }

    if ($filtro_termo !== '') {
        $filtros_exportacao[] = 'Termo pesquisado: ' . $filtro_termo;
    }

    $texto_filtros_exportacao = !empty($filtros_exportacao)
        ? implode(' | ', $filtros_exportacao)
        : 'Nenhum filtro aplicado';

    $linhas_exportacao = [];

    try {
        $sql = "
            SELECT
                aud.evento_data,
                aud.usuario,
                aud.acao,
                aud.entidade,
                aud.detalhes
            FROM ($baseSql) aud
            $whereSql
            ORDER BY aud.evento_data DESC, aud.origem_id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $linhas_exportacao = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $linhas_exportacao = [];
    }

    $total_exportado = count($linhas_exportacao);

    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html>';
    echo '<html lang="pt-br">';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>
        body {
            margin: 0;
            padding: 0;
            font-family: Calibri, Arial, sans-serif;
            color: #000;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-family: Calibri, Arial, sans-serif;
        }

        td, th {
            border: 1px solid #000;
            padding: 7px 8px;
            vertical-align: middle;
            white-space: normal;
        }

        .titulo {
            background: #F2F4F7;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            height: 38px;
        }

        .meta {
            background: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            text-align: left;
            height: 30px;
        }

        .cabecalho {
            background: #F2F4F7;
            color: #000;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            height: 32px;
        }

        .centro {
            text-align: center;
        }

        .esquerda {
            text-align: left;
        }

        .linha-par {
            background: #FFFFFF;
        }

        .linha-impar {
            background: #FFFFFF;
        }

        .rodape {
            background: #F2F4F7;
            font-weight: 700;
            text-align: left;
        }

        .col-data {
            width: 18%;
        }

        .col-usuario {
            width: 18%;
        }

        .col-acao {
            width: 13%;
        }

        .col-entidade {
            width: 13%;
        }

        .col-detalhes {
            width: 38%;
        }
    </style>';
    echo '</head>';
    echo '<body>';
    echo '<table>';

    echo '<colgroup>';
    echo '<col class="col-data">';
    echo '<col class="col-usuario">';
    echo '<col class="col-acao">';
    echo '<col class="col-entidade">';
    echo '<col class="col-detalhes">';
    echo '</colgroup>';

    echo '<tr>';
    echo '<th colspan="5" class="titulo">Auditoria - ANEXO</th>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="5" class="meta">Total: ' . format_number($total_exportado) . ' registros</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="5" class="meta">Filtros: ' . safe_html($texto_filtros_exportacao) . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<td colspan="5" class="meta">Gerado em: ' . safe_html($gerado_em_dispositivo) . '</td>';
    echo '</tr>';

    echo '<tr>';
    echo '<th class="cabecalho">Data/Hora</th>';
    echo '<th class="cabecalho">Usuário</th>';
    echo '<th class="cabecalho">Ação</th>';
    echo '<th class="cabecalho">Entidade</th>';
    echo '<th class="cabecalho">Detalhes</th>';
    echo '</tr>';

    if (!empty($linhas_exportacao)) {
        foreach ($linhas_exportacao as $indice => $row) {
            $classe_linha = ($indice % 2 === 0) ? 'linha-par' : 'linha-impar';

            echo '<tr class="' . $classe_linha . '">';
            echo '<td class="centro">' . safe_datetime($row['evento_data'] ?? '', 'd/m/Y H:i:s') . '</td>';
            echo '<td class="esquerda">' . safe_html($row['usuario'] ?? '') . '</td>';
            echo '<td class="centro">' . safe_html($row['acao'] ?? '') . '</td>';
            echo '<td class="centro">' . safe_html($row['entidade'] ?? '') . '</td>';
            echo '<td class="esquerda">' . safe_html($row['detalhes'] ?? '') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr>';
        echo '<td colspan="5" class="centro">Nenhum registro encontrado.</td>';
        echo '</tr>';
    }

    echo '<tr>';
    echo '<td colspan="5" class="rodape">Total exportado: ' . format_number($total_exportado) . ' registros</td>';
    echo '</tr>';

    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit;
}

/* =========================
   TOTAL / ESTATÍSTICAS
========================= */
try {
    $sqlCount = "SELECT COUNT(*) AS total FROM ($baseSql) aud $whereSql";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $total_registros = (int)($stmtCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Throwable $e) {
    $total_registros = 0;
}

try {
    $sqlStats = "
        SELECT
            COUNT(*) AS total_acoes,
            COUNT(DISTINCT aud.usuario) AS usuarios_ativos,
            MIN(aud.evento_data) AS primeira_acao,
            MAX(aud.evento_data) AS ultima_acao
        FROM ($baseSql) aud
        $whereSql
    ";
    $stmtStats = $pdo->prepare($sqlStats);
    $stmtStats->execute($params);
    $estatisticas = $stmtStats->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $estatisticas = [];
}

$estatisticas = array_merge([
    'total_acoes' => 0,
    'usuarios_ativos' => 0,
    'primeira_acao' => null,
    'ultima_acao' => null,
], $estatisticas);

/* =========================
   PAGINAÇÃO REGISTROS
========================= */
$opcoes_por_pagina = [10, 20, 30, 50, 100];
$registros_por_pagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : 10;
if (!in_array($registros_por_pagina, $opcoes_por_pagina, true)) {
    $registros_por_pagina = 10;
}
$pagina_atual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$total_paginas = max(1, (int)ceil($total_registros / $registros_por_pagina));
if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
}
$offset_registros = ($pagina_atual - 1) * $registros_por_pagina;

/* =========================
   REGISTROS DE AUDITORIA
========================= */
$registros = [];
try {
    $sqlRegistros = "
        SELECT *
        FROM ($baseSql) aud
        $whereSql
        ORDER BY aud.evento_data DESC, aud.origem_id DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sqlRegistros);
    $i = 1;
    foreach ($params as $param) {
        $stmt->bindValue($i++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($i++, $registros_por_pagina, PDO::PARAM_INT);
    $stmt->bindValue($i, $offset_registros, PDO::PARAM_INT);
    $stmt->execute();
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $registros = [];
}

/* =========================
   LINHA DO TEMPO RECENTE
   RESPEITA FILTROS
========================= */
$timeline_data = [];
try {
    $sqlTimeline = "
        SELECT *
        FROM ($baseSql) aud
        $whereSql
        ORDER BY aud.evento_data DESC, aud.origem_id DESC
        LIMIT 10
    ";
    $stmtTimeline = $pdo->prepare($sqlTimeline);
    $stmtTimeline->execute($params);
    $timeline_data = $stmtTimeline->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $timeline_data = [];
}

/* =========================
   OPÇÕES DOS FILTROS
========================= */
$acoes = ['Cadastrou', 'Entregou'];
$entidades = ['Solicitante', 'Entrega'];

$usuarios = [];
try {
    $sqlUsuarios = "
        SELECT aud.usuario
        FROM ($baseSql) aud
        GROUP BY aud.usuario
        ORDER BY aud.usuario ASC
        LIMIT 200
    ";
    $usuarios = $pdo->query($sqlUsuarios)->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $usuarios = [];
}

/* =========================
   USUÁRIOS MAIS ATIVOS
   AGORA DIRETO DAS DUAS TABELAS
   E RESPEITANDO FILTROS
========================= */
$opcoes_usuarios_por_pagina = [5, 10, 20, 50];
$perPageUsers = isset($_GET['u_por_pagina']) ? (int)$_GET['u_por_pagina'] : 10;
if (!in_array($perPageUsers, $opcoes_usuarios_por_pagina, true)) {
    $perPageUsers = 10;
}
$usersPage = isset($_GET['u_page']) ? max(1, (int)$_GET['u_page']) : 1;

try {
    $sqlUsersCount = "
        SELECT COUNT(*) AS total FROM (
            SELECT aud.usuario
            FROM ($baseSql) aud
            $whereSql
            GROUP BY aud.usuario
        ) x
    ";
    $stmtUsersCount = $pdo->prepare($sqlUsersCount);
    $stmtUsersCount->execute($params);
    $totalUsers = (int)($stmtUsersCount->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
} catch (Throwable $e) {
    $totalUsers = 0;
}

$totalUsersPages = max(1, (int)ceil($totalUsers / $perPageUsers));
if ($usersPage > $totalUsersPages) {
    $usersPage = $totalUsersPages;
}
$usersOffset = ($usersPage - 1) * $perPageUsers;

$top_users_table = [];
try {
    $sqlTopUsers = "
        SELECT
            aud.usuario,
            COUNT(*) AS total_acoes,
            SUM(CASE WHEN aud.acao = 'Cadastrou' THEN 1 ELSE 0 END) AS cadastros,
            SUM(CASE WHEN aud.acao = 'Entregou' THEN 1 ELSE 0 END) AS entregas,
            MAX(aud.evento_data) AS ultima_acao
        FROM ($baseSql) aud
        $whereSql
        GROUP BY aud.usuario
        ORDER BY total_acoes DESC, aud.usuario ASC
        LIMIT ? OFFSET ?
    ";

    $stmtTopUsers = $pdo->prepare($sqlTopUsers);
    $i = 1;
    foreach ($params as $param) {
        $stmtTopUsers->bindValue($i++, $param, PDO::PARAM_STR);
    }
    $stmtTopUsers->bindValue($i++, $perPageUsers, PDO::PARAM_INT);
    $stmtTopUsers->bindValue($i, $usersOffset, PDO::PARAM_INT);
    $stmtTopUsers->execute();
    $top_users_table = $stmtTopUsers->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $top_users_table = [];
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

    <style>
        .card,
        .audit-card,
        .stat-card,
        .chart-container {
            border: 1px solid #e6e9ef !important;
            border-left: 1px solid #e6e9ef !important;
            border-radius: 14px !important;
            box-shadow: none !important;
            background: #fff !important;
        }

        .audit-card {
            cursor: pointer;
            transition: background-color .2s ease, border-color .2s ease;
        }

        .audit-card:hover {
            background: #f8fafc !important;
            border-color: #d8dee8 !important;
        }

        .badge-acao {
            background-color: #0d6efd;
            color: #fff;
        }

        .badge-entidade {
            background-color: #6c757d;
            color: #fff;
        }

        .badge-usuario {
            background-color: #212529;
            color: #fff;
        }

        .badge-entregue {
            background-color: #198754;
            color: #fff;
        }

        .stat-card {
            min-height: 85px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0;
            line-height: 1.2;
        }

        .stat-label {
            font-size: .85rem;
            color: #6c757d;
            margin-bottom: 0;
        }

        .chart-container {
            padding: 15px;
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

        #chartUsers .apexcharts-text,
        #chartUsers .apexcharts-title-text,
        #chartUsers .apexcharts-xaxis text,
        #chartUsers .apexcharts-yaxis text,
        #chartUsers .apexcharts-data-labels text,
        #chartUsers .apexcharts-datalabel,
        #chartUsers .apexcharts-xaxis-title-text,
        #chartUsers .apexcharts-yaxis-title-text {
            fill: #000000 !important;
            color: #000000 !important;
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
            flex-direction: column;
        }

        .chart-legend {
            font-size: .8rem;
            margin-top: 10px;
            color: #666;
        }

        .card-equal-height {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .card-equal-height .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 0 !important;
        }

        .timeline-container,
        .users-table-container {
            max-height: 480px;
            min-height: 480px;
            overflow-y: auto;
            padding: 1rem;
        }

        .timeline {
            border-left: 3px solid #0d6efd;
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
            left: -27px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0d6efd;
        }

        .timeline-date {
            font-size: .82rem;
            color: #666;
            margin-bottom: 4px;
        }

        .timeline-user {
            font-weight: 600;
            color: #333;
            font-size: .95rem;
            margin-bottom: 4px;
        }

        .timeline-action {
            font-size: .87rem;
            color: #555;
            line-height: 1.35;
        }

        .timeline-container::-webkit-scrollbar,
        .users-table-container::-webkit-scrollbar,
        .audit-log::-webkit-scrollbar {
            width: 6px;
        }

        .timeline-container::-webkit-scrollbar-track,
        .users-table-container::-webkit-scrollbar-track,
        .audit-log::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .timeline-container::-webkit-scrollbar-thumb,
        .users-table-container::-webkit-scrollbar-thumb,
        .audit-log::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .timeline-container::-webkit-scrollbar-thumb:hover,
        .users-table-container::-webkit-scrollbar-thumb:hover,
        .audit-log::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .audit-log {
            max-height: 540px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .audit-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .audit-icon {
            flex: 0 0 auto;
        }

        .audit-content {
            flex: 1;
            min-width: 0;
        }

        .audit-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }

        .audit-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .audit-text {
            color: #4b5563;
            line-height: 1.45;
        }

        .audit-date {
            display: inline-block;
            margin-top: 6px;
        }

        .action-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-icon.cadastrou {
            background-color: #198754;
            color: #fff;
        }

        .action-icon.entregou {
            background-color: #0dcaf0;
            color: #fff;
        }

        .action-icon.default {
            background-color: #0d6efd;
            color: #fff;
        }

        .search-highlight {
            background-color: #fff3cd;
            padding: .08rem .25rem;
            border-radius: .2rem;
            font-weight: 700;
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

        .users-table-container .table {
            margin-bottom: 0;
        }

        /* Paginação clean no mesmo padrão das demais telas */
        .custom-pagination-bar {
            display: grid;
            grid-template-columns: minmax(180px, 1fr) auto minmax(180px, 1fr);
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .custom-pagination-left,
        .custom-pagination-center,
        .custom-pagination-right {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        .custom-pagination-left {
            justify-content: flex-start;
        }

        .custom-pagination-center {
            justify-content: center;
        }

        .custom-pagination-right {
            justify-content: flex-end;
        }

        .custom-page-btn {
            min-width: 96px;
            padding: .5rem 1rem;
            border: 1px solid #d0d7de;
            background: #fff;
            color: #435ebe;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
        }

        .custom-page-btn:hover {
            border-color: #435ebe;
            color: #435ebe;
            background: #f8f9ff;
        }

        .custom-page-btn.disabled,
        .custom-page-btn:disabled {
            background: #f5f6f8;
            color: #b5b8bf;
            border-color: #dfe3e8;
            pointer-events: none;
            cursor: not-allowed;
        }

        button.custom-page-btn {
            cursor: pointer;
        }

        .pagination-loading {
            opacity: .55;
            pointer-events: none;
        }

        .custom-page-info {
            font-size: 1.05rem;
            font-weight: 800;
            color: #435ebe;
            white-space: nowrap;
        }

        .custom-length-label {
            color: #6c757d;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
        }

        .custom-length-select {
            min-width: 88px;
            padding: .45rem 2rem .45rem .75rem;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            background-color: #fff;
            color: #495057;
            font-weight: 600;
            outline: none;
        }



        /* Paginação dinâmica no padrão das outras telas */
        .custom-pagination-bar {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 1rem !important;
            margin-top: 1rem !important;
            padding-top: 1rem !important;
            border-top: 1px solid #e9ecef !important;
            flex-wrap: wrap !important;
        }

        .custom-pagination-left,
        .custom-pagination-center,
        .custom-pagination-right {
            display: flex !important;
            align-items: center !important;
            gap: .75rem !important;
        }

        .custom-pagination-center {
            flex: 1 1 auto !important;
            justify-content: center !important;
        }

        .custom-pagination-right {
            justify-content: flex-end !important;
        }

        .custom-page-btn {
            min-width: 96px !important;
            padding: .5rem 1rem !important;
            border: 1px solid #d0d7de !important;
            background: #fff !important;
            color: #6c757d !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            text-align: center !important;
            transition: all .2s ease !important;
        }

        .custom-page-btn:hover:not(:disabled) {
            border-color: #435ebe !important;
            color: #435ebe !important;
            background: #f8f9ff !important;
        }

        /* Modal de detalhes centralizada e com conteúdo responsivo */
        .modal-detalhes .modal-dialog {
            width: min(700px, calc(100% - 2rem));
            max-width: 700px;
            margin: 1rem auto;
        }

        .modal-detalhes .modal-content {
            overflow: hidden;
        }

        .modal-detalhes .modal-body .card {
            border: 0 !important;
            border-radius: 0 !important;
        }

        .modal-detalhes .modal-body table th,
        .modal-detalhes .modal-body table td {
            vertical-align: middle;
        }

        @media (max-width: 768px) {
            .modal-detalhes .modal-dialog {
                width: calc(100% - 20px);
                max-width: none;
                margin: 10px auto;
            }

            .modal-detalhes .modal-body {
                max-height: 80vh;
            }

            .custom-pagination-bar {
                grid-template-columns: 1fr;
            }

            .custom-pagination-left,
            .custom-pagination-center,
            .custom-pagination-right {
                justify-content: center;
                width: 100%;
            }

            .timeline-container,
            .users-table-container {
                min-height: auto;
                max-height: 420px;
            }
        }

        @media (max-width: 576px) {
            .modal-detalhes .modal-dialog {
                width: calc(100% - 10px);
                margin: 5px auto;
            }

            .modal-detalhes .modal-header,
            .modal-detalhes .modal-footer {
                padding-left: .85rem;
                padding-right: .85rem;
            }

            .modal-detalhes .modal-body .table-responsive {
                overflow: visible;
            }

            .modal-detalhes .modal-body table,
            .modal-detalhes .modal-body tbody,
            .modal-detalhes .modal-body tr,
            .modal-detalhes .modal-body th,
            .modal-detalhes .modal-body td {
                display: block;
                width: 100% !important;
            }

            .modal-detalhes .modal-body tr {
                padding: .8rem .9rem;
                border-bottom: 1px solid #e9ecef;
                text-align: center;
            }

            .modal-detalhes .modal-body th,
            .modal-detalhes .modal-body td {
                border: 0 !important;
                padding: .15rem 0 !important;
                text-align: center !important;
            }

            .modal-detalhes .modal-body th {
                margin-bottom: .25rem;
                color: #25396f;
                font-weight: 800;
            }

            .modal-detalhes .modal-body td {
                color: #52697f;
                overflow-wrap: anywhere;
            }

            .audit-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .chart-container {
                min-height: 300px;
            }

            .chart-wrapper,
            #chartUsers,
            #chartDaily {
                min-height: 240px;
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
                        <div class="logo">
                            <a href="#"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a>
                        </div>
                        <div class="toggler">
                            <a href="#" class="sidebar-hide d-xl-none d-block">
                                <i class="bi bi-x bi-middle"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item">
                            <a href="dashboard.php" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-hand-thumbs-up-fill"></i>
                                <span>Entregas</span>
                            </a>
                            <ul class="submenu">
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
                                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-cash-stack"></i>
                                <span>Controle Financeiro</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="valoresAplicados.php">Valores Aplicados</a></li>
                                <li class="submenu-item"><a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a></li>
                            </ul>
                        </li>

                        <?php if (($_SESSION['user_role'] ?? '') === 'suporte'): ?>
                            <li class="sidebar-item has-sub">
                                <a href="#" class="sidebar-link">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Usuários</span>
                                </a>
                                <ul class="submenu">
                                    <li class="submenu-item"><a href="usuariosPermitidos.php">Permitidos</a></li>
                                    <li class="submenu-item"><a href="usuariosNaoPermitidos.php">Não Permitidos</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="sidebar-item active">
                            <a href="auditoria.php" class="sidebar-link">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Auditoria</span>
                            </a>
                        </li>

                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Sair</span>
                            </a>
                        </li>
                    </ul>
                </div>
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
                        <p class="text-muted">Registros montados diretamente de cadastros de solicitantes e entregas</p>
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

                    <!-- Estatísticas -->
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
                                            <?= !empty($estatisticas['primeira_acao']) ? safe_date($estatisticas['primeira_acao']) : 'N/A' ?>
                                        </p>
                                        <p class="stat-label">Primeira Ação</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-6 mb-3">
                                <div class="card stat-card stat-card-last h-100">
                                    <div class="card-body">
                                        <p class="stat-number">
                                            <?= !empty($estatisticas['ultima_acao']) ? safe_datetime($estatisticas['ultima_acao']) : 'N/A' ?>
                                        </p>
                                        <p class="stat-label">Última Ação</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Visão Geral das Ações</h5>
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-2">
                                        <div class="chart-container">
                                            <div class="chart-title">Top 15 Usuários Mais Ativos</div>
                                            <div class="chart-wrapper">
                                                <div id="chartUsers"></div>
                                                <div id="chartUsersLoading" class="chart-loading" style="display:none;">
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
                                                <div id="chartDailyLoading" class="chart-loading" style="display:none;">
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
                                    <?php if ($filtro_usuario !== ''): ?>
                                        <span class="badge bg-primary">
                                            Usuário: <?= safe_html($filtro_usuario) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['usuario' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($filtro_acao !== ''): ?>
                                        <span class="badge bg-info">
                                            Ação: <?= safe_html($filtro_acao) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['acao' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($filtro_entidade !== ''): ?>
                                        <span class="badge bg-success">
                                            Entidade: <?= safe_html($filtro_entidade) ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['entidade' => ''])) ?>" class="text-white ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($filtro_data_inicio !== '' || $filtro_data_fim !== ''): ?>
                                        <span class="badge bg-warning text-dark">
                                            Período:
                                            <?= $filtro_data_inicio !== '' ? safe_html($filtro_data_inicio) : '...' ?>
                                            a
                                            <?= $filtro_data_fim !== '' ? safe_html($filtro_data_fim) : '...' ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['data_inicio' => '', 'data_fim' => ''])) ?>" class="text-dark ms-2">×</a>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($filtro_termo !== ''): ?>
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

                    <!-- Registros -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title">Registros de Ações dos Usuários</h5>
                                <p class="card-subtitle text-muted">
                                    Total de <?= format_number($total_registros) ?> ações registradas
                                </p>
                            </div>

                            <div class="card-body">
                                <div class="audit-log" id="auditLogRegistros">
                                    <?php if (empty($registros)): ?>
                                        <div class="text-center text-muted py-5">
                                            <i class="bi bi-search display-6 d-block mb-2"></i>
                                            Nenhum registro encontrado
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($registros as $registro): ?>
                                            <?php
                                            $acaoLower = strtolower((string)($registro['acao'] ?? ''));
                                            $icon = match ($acaoLower) {
                                                'cadastrou' => 'bi-plus-circle',
                                                'entregou'  => 'bi-box-seam',
                                                default     => 'bi-activity'
                                            };
                                            $actionClass = in_array($acaoLower, ['cadastrou', 'entregou'], true) ? $acaoLower : 'default';
                                            ?>
                                            <div class="card mb-3 audit-card"
                                                onclick="verDetalhes('<?= safe_html($registro['origem_tipo']) ?>', <?= (int)$registro['origem_id'] ?>)">
                                                <div class="card-body">
                                                    <div class="audit-row">
                                                        <div class="audit-icon">
                                                            <div class="action-icon <?= $actionClass ?>">
                                                                <i class="bi <?= $icon ?>"></i>
                                                            </div>
                                                        </div>

                                                        <div class="audit-content">
                                                            <div class="audit-header">
                                                                <div class="audit-badges">
                                                                    <span class="badge badge-usuario"><?= safe_html($registro['usuario']) ?></span>
                                                                    <span class="badge badge-acao"><?= safe_html($registro['acao']) ?></span>
                                                                    <span class="badge badge-entidade"><?= safe_html($registro['entidade']) ?></span>

                                                                    <?php if (($registro['acao'] ?? '') === 'Entregou'): ?>
                                                                        <span class="badge badge-entregue">Entregue</span>
                                                                    <?php endif; ?>
                                                                </div>

                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-primary mt-2"
                                                                    onclick="event.stopPropagation(); verDetalhes('<?= safe_html($registro['origem_tipo']) ?>', <?= (int)$registro['origem_id'] ?>)">
                                                                    <i class="bi bi-eye"></i> Detalhes
                                                                </button>
                                                            </div>

                                                            <p class="audit-text mb-1">
                                                                <?= safe_html($registro['detalhes']) ?>
                                                                <?php if (($registro['acao'] ?? '') === 'Entregou' && !empty($registro['tipo_ajuda'])): ?>
                                                                    <br><small class="text-muted">Tipo: <?= safe_html($registro['tipo_ajuda']) ?></small>
                                                                <?php endif; ?>
                                                            </p>

                                                            <small class="text-muted audit-date">
                                                                <i class="bi bi-clock"></i>
                                                                <?= safe_datetime($registro['evento_data']) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($total_paginas > 1 || $total_registros > 0): ?>
                                    <div class="custom-pagination-bar" id="paginationRegistros" aria-label="Paginação de registros">
                                        <div class="custom-pagination-left">
                                            <button type="button"
                                                    class="custom-page-btn js-registros-page"
                                                    data-page="<?= max(1, $pagina_atual - 1) ?>"
                                                    <?= $pagina_atual <= 1 ? 'disabled' : '' ?>>
                                                Anterior
                                            </button>
                                            <button type="button"
                                                    class="custom-page-btn js-registros-page"
                                                    data-page="<?= min($total_paginas, $pagina_atual + 1) ?>"
                                                    <?= $pagina_atual >= $total_paginas ? 'disabled' : '' ?>>
                                                Próximo
                                            </button>
                                        </div>

                                        <div class="custom-pagination-center">
                                            <span class="custom-page-info">
                                                Página <?= $total_registros > 0 ? $pagina_atual : 0 ?> de <?= $total_registros > 0 ? $total_paginas : 0 ?>
                                            </span>
                                        </div>

                                        <div class="custom-pagination-right">
                                            <label class="custom-length-label" for="registrosPorPagina">por página</label>
                                            <select id="registrosPorPagina"
                                                    class="custom-length-select"
                                                    onchange="carregarPaginaRegistros(1, this.value)">
                                                <?php foreach ($opcoes_por_pagina as $opcao): ?>
                                                    <option value="<?= $opcao ?>" <?= $registros_por_pagina === $opcao ? 'selected' : '' ?>>
                                                        <?= $opcao ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Linha do tempo -->
                    <div class="col-md-6 mb-3 d-flex">
                        <div class="card card-equal-height h-100 w-100">
                            <div class="card-header">
                                <h5 class="card-title">Linha do Tempo Recente</h5>
                                <p class="card-subtitle text-muted">Últimas 10 ações</p>
                            </div>
                            <div class="card-body">
                                <div class="timeline-container">
                                    <div class="timeline">
                                        <?php if (!empty($timeline_data)): ?>
                                            <?php foreach ($timeline_data as $item): ?>
                                                <div class="timeline-item">
                                                    <div class="timeline-date">
                                                        <?= safe_datetime($item['evento_data']) ?>
                                                    </div>

                                                    <div class="timeline-user">
                                                        <strong><?= safe_html($item['usuario'] ?? 'Desconhecido') ?></strong>
                                                        <span class="badge bg-info ms-2"><?= safe_html($item['acao'] ?? '') ?></span>

                                                        <?php if (($item['acao'] ?? '') === 'Entregou'): ?>
                                                            <span class="badge bg-success ms-1">Entregue</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="timeline-action">
                                                        <?= safe_html($item['detalhes'] ?? '') ?>
                                                        <?php if (($item['acao'] ?? '') === 'Entregou' && !empty($item['tipo_ajuda'])): ?>
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

                    <!-- Usuários Mais Ativos -->
                    <div class="col-md-6 mb-3 d-flex">
                        <div class="card card-equal-height h-100 w-100">
                            <div class="card-header">
                                <h5 class="card-title">Usuários Mais Ativos</h5>
                                <p class="card-subtitle text-muted">Detalhamento por tipo de ação</p>
                            </div>

                            <div class="card-body">
                                <div class="users-table-container">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Responsável</th>
                                                    <th>Total</th>
                                                    <th>Cadastros</th>
                                                    <th>Entregas</th>
                                                    <th>Última Ação</th>
                                                </tr>
                                            </thead>
                                            <tbody id="usersTableBody">
                                                <?php if (!empty($top_users_table)): ?>
                                                    <?php foreach ($top_users_table as $user): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="badge bg-dark"><?= safe_html($user['usuario'] ?? '') ?></span>
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
                                                                <small><?= !empty($user['ultima_acao']) ? safe_datetime($user['ultima_acao']) : '—' ?></small>
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
                                </div>

                                <?php if ($totalUsersPages > 1 || $totalUsers > 0): ?>
                                    <div class="px-3 pb-3" id="paginationUsuarios">
                                        <div class="custom-pagination-bar" aria-label="Paginação de usuários">
                                            <div class="custom-pagination-left">
                                                <button type="button"
                                                        class="custom-page-btn js-usuarios-page"
                                                        data-page="<?= max(1, $usersPage - 1) ?>"
                                                        <?= $usersPage <= 1 ? 'disabled' : '' ?>>
                                                    Anterior
                                                </button>
                                                <button type="button"
                                                        class="custom-page-btn js-usuarios-page"
                                                        data-page="<?= min($totalUsersPages, $usersPage + 1) ?>"
                                                        <?= $usersPage >= $totalUsersPages ? 'disabled' : '' ?>>
                                                    Próximo
                                                </button>
                                            </div>

                                            <div class="custom-pagination-center">
                                                <span class="custom-page-info">
                                                    Página <?= $totalUsers > 0 ? $usersPage : 0 ?> de <?= $totalUsers > 0 ? $totalUsersPages : 0 ?>
                                                </span>
                                            </div>

                                            <div class="custom-pagination-right">
                                                <label class="custom-length-label" for="usuariosPorPagina">por página</label>
                                                <select id="usuariosPorPagina"
                                                        class="custom-length-select"
                                                        onchange="carregarPaginaUsuarios(1, this.value)">
                                                    <?php foreach ($opcoes_usuarios_por_pagina as $opcao): ?>
                                                        <option value="<?= $opcao ?>" <?= $perPageUsers === $opcao ? 'selected' : '' ?>>
                                                            <?= $opcao ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
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
                                        <option value="<?= safe_html($usuario) ?>" <?= ((string)$usuario === $filtro_usuario) ? 'selected' : '' ?>>
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
                                        <option value="<?= safe_html($acao) ?>" <?= ((string)$acao === $filtro_acao) ? 'selected' : '' ?>>
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
                                        <option value="<?= safe_html($entidade) ?>" <?= ((string)$entidade === $filtro_entidade) ? 'selected' : '' ?>>
                                            <?= safe_html($entidade) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Pesquisar termo</label>
                                <input type="text" class="form-control" name="termo" value="<?= safe_html($filtro_termo) ?>" placeholder="Usuário, ação, detalhes, nome, CPF">
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
                        <a href="auditoria.php" class="btn btn-outline-danger">Limpar</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detalhes -->
    <div class="modal fade modal-detalhes" id="modalDetalhes" tabindex="-1" aria-labelledby="modalDetalhesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
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

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>

    <script>
        let chartUsers = null;
        let chartDaily = null;

        function getCurrentParams() {
            return new URLSearchParams(window.location.search);
        }

        function getAjaxUrl(ajaxName) {
            const params = getCurrentParams();
            params.set('ajax', ajaxName);
            params.delete('exportar');
            return `${window.location.pathname}?${params.toString()}`;
        }

        function carregarGraficos() {
            const loadingUsers = document.getElementById('chartUsersLoading');
            const loadingDaily = document.getElementById('chartDailyLoading');

            if (loadingUsers) loadingUsers.style.display = 'flex';
            if (loadingDaily) loadingDaily.style.display = 'flex';

            fetch(getAjaxUrl('grafico_usuarios'))
                .then(r => r.json())
                .then(data => {
                    if (loadingUsers) loadingUsers.style.display = 'none';

                    if (chartUsers) {
                        chartUsers.destroy();
                        chartUsers = null;
                    }

                    if (data.series && data.series.length > 0) {
                        const options = {
                            series: [{
                                name: 'Ações',
                                data: data.series
                            }],
                            chart: {
                                type: 'bar',
                                height: 350,
                                foreColor: '#000000',
                                offsetX: 8,
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
                            colors: ['#0d6efd'],
                            dataLabels: {
                                enabled: true,
                                offsetX: 4,
                                style: {
                                    colors: ['#000000'],
                                    fontSize: '12px',
                                    fontWeight: 700
                                },
                                background: {
                                    enabled: false
                                },
                                formatter: function(val) {
                                    return val;
                                }
                            },
                            grid: {
                                padding: {
                                    left: 12,
                                    right: 12
                                }
                            },
                            xaxis: {
                                categories: data.labels,
                                labels: {
                                    style: {
                                        colors: '#000000',
                                        fontSize: '12px',
                                        fontWeight: 600
                                    }
                                },
                                title: {
                                    text: 'Número de Ações',
                                    style: {
                                        color: '#000000'
                                    }
                                }
                            },
                            yaxis: {
                                labels: {
                                    minWidth: 130,
                                    maxWidth: 180,
                                    offsetX: 0,
                                    style: {
                                        colors: '#000000',
                                        fontSize: '12px',
                                        fontWeight: 600
                                    },
                                    formatter: function(value, index) {
                                        const nomeCompleto = (
                                            typeof index === 'number' &&
                                            data.usuarios_completos &&
                                            data.usuarios_completos[index]
                                        ) ? String(data.usuarios_completos[index]) : String(value || '');

                                        return nomeCompleto.length > 24
                                            ? nomeCompleto.substring(0, 24).trimEnd() + '...'
                                            : nomeCompleto;
                                    }
                                }
                            },
                            title: {
                                text: `Top ${data.total_usuarios} Usuários Mais Ativos`,
                                align: 'center',
                                style: {
                                    color: '#000000',
                                    fontWeight: 700
                                }
                            },
                            tooltip: {
                                custom: function({
                                    series,
                                    seriesIndex,
                                    dataPointIndex
                                }) {
                                    const usuario = data.usuarios_completos && data.usuarios_completos[dataPointIndex] ?
                                        data.usuarios_completos[dataPointIndex] :
                                        '';
                                    const total = series[seriesIndex][dataPointIndex] ?? 0;
                                    return `<div class="p-2"><strong>${usuario}</strong><br><span>${total} ações realizadas</span></div>`;
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
                                    }
                                }
                            }, {
                                breakpoint: 480,
                                options: {
                                    chart: {
                                        height: 250
                                    }
                                }
                            }]
                        };

                        chartUsers = new ApexCharts(document.querySelector("#chartUsers"), options);
                        chartUsers.render();
                    } else {
                        document.querySelector("#chartUsers").innerHTML = `
                            <div class="chart-placeholder">
                                <i class="bi bi-bar-chart"></i>
                                <p>Nenhum dado disponível</p>
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    if (loadingUsers) loadingUsers.style.display = 'none';
                    document.querySelector("#chartUsers").innerHTML = `
                        <div class="chart-placeholder">
                            <i class="bi bi-exclamation-triangle"></i>
                            <p>Erro ao carregar gráfico</p>
                        </div>
                    `;
                });

            fetch(getAjaxUrl('grafico_diario'))
                .then(r => r.json())
                .then(data => {
                    if (loadingDaily) loadingDaily.style.display = 'none';

                    if (chartDaily) {
                        chartDaily.destroy();
                        chartDaily = null;
                    }

                    if (data.series && data.series.length > 0) {
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
                            colors: ['#198754'],
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
                                size: 4,
                                hover: {
                                    size: 6
                                }
                            },
                            xaxis: {
                                categories: data.labels,
                                labels: {
                                    rotate: -45
                                },
                                title: {
                                    text: 'Data'
                                }
                            },
                            yaxis: {
                                min: 0,
                                title: {
                                    text: 'Número de Ações'
                                },
                                labels: {
                                    formatter: function(val) {
                                        return Math.round(val);
                                    }
                                }
                            },
                            title: {
                                text: `Ações nos Últimos ${data.total_dias} Dias`,
                                align: 'center'
                            },
                            tooltip: {
                                custom: function({
                                    series,
                                    seriesIndex,
                                    dataPointIndex
                                }) {
                                    const dataLabel = data.labels[dataPointIndex] || '';
                                    const total = series[seriesIndex][dataPointIndex] ?? 0;
                                    return `<div class="p-2"><strong>${dataLabel}</strong><br><span>${total} ações realizadas</span></div>`;
                                }
                            },
                            responsive: [{
                                breakpoint: 768,
                                options: {
                                    chart: {
                                        height: 300
                                    }
                                }
                            }, {
                                breakpoint: 480,
                                options: {
                                    chart: {
                                        height: 250
                                    }
                                }
                            }]
                        };

                        chartDaily = new ApexCharts(document.querySelector("#chartDaily"), options);
                        chartDaily.render();
                    } else {
                        document.querySelector("#chartDaily").innerHTML = `
                            <div class="chart-placeholder">
                                <i class="bi bi-graph-up"></i>
                                <p>Nenhum dado disponível</p>
                            </div>
                        `;
                    }
                })
                .catch(() => {
                    if (loadingDaily) loadingDaily.style.display = 'none';
                    document.querySelector("#chartDaily").innerHTML = `
                        <div class="chart-placeholder">
                            <i class="bi bi-exclamation-triangle"></i>
                            <p>Erro ao carregar gráfico</p>
                        </div>
                    `;
                });
        }

        function verDetalhes(tipo, id) {
            document.getElementById('detalhesConteudo').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-2 text-muted">Carregando detalhes...</p>
                </div>
            `;

            const url = `${window.location.pathname}?ajax=detalhes&tipo=${encodeURIComponent(tipo)}&id=${encodeURIComponent(id)}`;

            fetch(url)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('detalhesConteudo').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
                })
                .catch(() => {
                    document.getElementById('detalhesConteudo').innerHTML = `
                        <div class="alert alert-danger m-3">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Erro ao carregar detalhes.
                        </div>
                    `;
                    new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
                });
        }

        function montarUrlPaginacao(atualizacoes) {
            const params = new URLSearchParams(window.location.search);

            Object.keys(atualizacoes).forEach(function(chave) {
                const valor = atualizacoes[chave];
                if (valor === null || valor === undefined || valor === '') {
                    params.delete(chave);
                } else {
                    params.set(chave, String(valor));
                }
            });

            params.delete('ajax');
            params.delete('exportar');

            const query = params.toString();
            return query ? `${window.location.pathname}?${query}` : window.location.pathname;
        }

        async function atualizarTrechosPaginados(url, seletores) {
            const posicaoAtual = window.scrollY;

            seletores.forEach(function(seletor) {
                const alvo = document.querySelector(seletor);
                if (alvo) alvo.classList.add('pagination-loading');
            });

            try {
                const resposta = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!resposta.ok) {
                    throw new Error('Falha ao carregar página.');
                }

                const html = await resposta.text();
                const documento = new DOMParser().parseFromString(html, 'text/html');

                seletores.forEach(function(seletor) {
                    const atual = document.querySelector(seletor);
                    const novo = documento.querySelector(seletor);

                    if (atual && novo) {
                        atual.outerHTML = novo.outerHTML;
                    }
                });

                highlightSearchTerms();
                window.scrollTo(0, posicaoAtual);
            } catch (erro) {
                console.error(erro);
                alert('Não foi possível carregar a página. Tente novamente.');
            } finally {
                seletores.forEach(function(seletor) {
                    const alvo = document.querySelector(seletor);
                    if (alvo) alvo.classList.remove('pagination-loading');
                });
            }
        }

        function carregarPaginaRegistros(pagina, porPagina = null) {
            const seletorQuantidade = document.getElementById('registrosPorPagina');
            const quantidade = porPagina || (seletorQuantidade ? seletorQuantidade.value : <?= (int)$registros_por_pagina ?>);

            const url = montarUrlPaginacao({
                pagina: Math.max(1, parseInt(pagina, 10) || 1),
                por_pagina: quantidade
            });

            return atualizarTrechosPaginados(url, ['#auditLogRegistros', '#paginationRegistros']);
        }

        function carregarPaginaUsuarios(pagina, porPagina = null) {
            const seletorQuantidade = document.getElementById('usuariosPorPagina');
            const quantidade = porPagina || (seletorQuantidade ? seletorQuantidade.value : <?= (int)$perPageUsers ?>);

            const url = montarUrlPaginacao({
                u_page: Math.max(1, parseInt(pagina, 10) || 1),
                u_por_pagina: quantidade
            });

            return atualizarTrechosPaginados(url, ['#usersTableBody', '#paginationUsuarios']);
        }

        document.addEventListener('click', function(event) {
            const botaoRegistros = event.target.closest('.js-registros-page');
            if (botaoRegistros) {
                event.preventDefault();
                if (!botaoRegistros.disabled) {
                    carregarPaginaRegistros(botaoRegistros.dataset.page || 1);
                }
                return;
            }

            const botaoUsuarios = event.target.closest('.js-usuarios-page');
            if (botaoUsuarios) {
                event.preventDefault();
                if (!botaoUsuarios.disabled) {
                    carregarPaginaUsuarios(botaoUsuarios.dataset.page || 1);
                }
            }
        });

        function alterarQuantidadePagina(parametro, valor, parametroPagina) {
            if (parametro === 'por_pagina') {
                carregarPaginaRegistros(1, valor);
                return;
            }

            if (parametro === 'u_por_pagina') {
                carregarPaginaUsuarios(1, valor);
                return;
            }

            const url = montarUrlPaginacao({
                [parametro]: valor,
                [parametroPagina]: 1
            });
            window.location.href = url;
        }

        function exportarExcel() {
            const params = new URLSearchParams(window.location.search);
            const agora = new Date();

            const dataDispositivo = agora.toLocaleDateString('pt-BR');
            const horaDispositivo = agora.toLocaleTimeString('pt-BR', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });

            params.set('exportar', 'excel');
            params.set('gerado_em', `${dataDispositivo} ${horaDispositivo}`);
            params.delete('ajax');
            params.delete('pagina');
            params.delete('u_page');

            window.location.href = `${window.location.pathname}?${params.toString()}`;
        }

        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightSearchTerms() {
            const searchTerm = <?= json_encode((string)$filtro_termo, JSON_UNESCAPED_UNICODE) ?>;
            if (!searchTerm) return;

            const escapedTerm = escapeRegExp(searchTerm);
            const regex = new RegExp(`(${escapedTerm})`, 'gi');

            document.querySelectorAll('.audit-text, .timeline-action').forEach(el => {
                if (regex.test(el.textContent)) {
                    el.innerHTML = el.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
                }
            });
        }


        function limparParametrosPaginacaoDaUrl() {
            const url = new URL(window.location.href);
            let alterou = false;

            ['pagina', 'u_page'].forEach(function(parametro) {
                if (url.searchParams.has(parametro)) {
                    url.searchParams.delete(parametro);
                    alterou = true;
                }
            });

            if (alterou) {
                const query = url.searchParams.toString();
                const novaUrl = query ? `${url.pathname}?${query}` : url.pathname;
                window.history.replaceState({}, '', novaUrl);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            limparParametrosPaginacaoDaUrl();

            const yearEl = document.getElementById('current-year');
            if (yearEl) {
                yearEl.textContent = new Date().getFullYear();
            }

            setTimeout(carregarGraficos, 500);
            highlightSearchTerms();

            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    if (chartUsers) chartUsers.updateOptions({}, false, true);
                    if (chartDaily) chartDaily.updateOptions({}, false, true);
                }, 250);
            });
        });
    </script>
</body>

</html>
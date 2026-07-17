<?php

declare(strict_types=1);

/* =========================
   AUTH (ÁREA PRIVADA)
========================= */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* DEBUG (remover em produção) */
ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

/* =========================
   CONEXÃO
========================= */
require_once __DIR__ . '/../dist/assets/conexao.php';

if ((!isset($pdo) || !($pdo instanceof PDO)) && function_exists('db')) {
    $pdo = db();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Erro de conexão');
}

// Função para formatar valor monetário
function formatarMoeda($valor)
{
    if ($valor === null || $valor === '' || $valor === 0 || $valor === '0') {
        return 'R$ 0,00';
    }

    if (is_string($valor)) {
        $valor = str_replace(['R$', ' ', '.'], '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    $valor_float = (float)$valor;
    return 'R$ ' . number_format($valor_float, 2, ',', '.');
}

/* =========================
   PROCESSAMENTO DE FILTROS E DADOS
========================= */
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_bairro = $_GET['bairro'] ?? '';
$filtro_beneficio = $_GET['beneficio'] ?? '';
$filtro_cpf = $_GET['cpf'] ?? '';
$filtro_responsavel = $_GET['responsavel'] ?? '';
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_valor_min = $_GET['valor_min'] ?? '';
$filtro_valor_max = $_GET['valor_max'] ?? '';

$tem_filtro_data = !empty($filtro_data_inicio) && !empty($filtro_data_fim);

if ($tem_filtro_data) {
    if (!strtotime($filtro_data_inicio)) $filtro_data_inicio = '';
    if (!strtotime($filtro_data_fim)) $filtro_data_fim = '';
    if (empty($filtro_data_inicio) || empty($filtro_data_fim)) {
        $tem_filtro_data = false;
    }
}

$whereConditions = [];
$params = [];

if ($tem_filtro_data) {
    $whereConditions[] = "ae.data_entrega BETWEEN :data_inicio AND :data_fim";
    $params[':data_inicio'] = $filtro_data_inicio;
    $params[':data_fim'] = $filtro_data_fim;
}

if ($filtro_status === 'entregue') {
    $whereConditions[] = "ae.entregue = 'SIM'";
} elseif ($filtro_status === 'nao_entregue') {
    $whereConditions[] = "ae.entregue = 'NAO'";
}

if (!empty($filtro_cpf)) {
    $whereConditions[] = "s.cpf LIKE :cpf";
    $params[':cpf'] = '%' . preg_replace('/[^0-9]/', '', $filtro_cpf) . '%';
}

if (!empty($filtro_bairro) && $filtro_bairro !== 'todos') {
    $whereConditions[] = "s.bairro_id = :bairro";
    $params[':bairro'] = $filtro_bairro;
}

if (!empty($filtro_beneficio) && $filtro_beneficio !== 'todos') {
    $whereConditions[] = "ae.ajuda_tipo_id = :beneficio";
    $params[':beneficio'] = $filtro_beneficio;
}

if (!empty($filtro_responsavel)) {
    $whereConditions[] = "ae.responsavel LIKE :responsavel";
    $params[':responsavel'] = '%' . $filtro_responsavel . '%';
}

if (!empty($filtro_valor_min)) {
    $whereConditions[] = "COALESCE(ae.valor_aplicado, 0) >= :valor_min";
    $params[':valor_min'] = (float) str_replace(',', '.', $filtro_valor_min);
}

if (!empty($filtro_valor_max)) {
    $whereConditions[] = "COALESCE(ae.valor_aplicado, 0) <= :valor_max";
    $params[':valor_max'] = (float) str_replace(',', '.', $filtro_valor_max);
}

$sql = "
    SELECT 
        ae.id,
        ae.data_entrega,
        ae.hora_entrega,
        ae.quantidade,
        ae.valor_aplicado,
        ae.responsavel,
        ae.observacao,
        ae.foto_path as foto_entrega,
        ae.entregue,
        ae.pessoa_cpf,
        s.nome as solicitante_nome,
        s.cpf as solicitante_cpf,
        s.telefone,
        s.endereco,
        s.numero,
        s.complemento,
        s.bairro_id,
        s.foto_path as foto_solicitante,
        s.genero,
        s.estado_civil,
        s.data_nascimento,
        s.created_at as data_cadastro,
        at.nome as beneficio_nome,
        at.categoria as beneficio_categoria,
        at.periodicidade,
        f.nome as familiar_nome,
        f.parentesco
    FROM ajudas_entregas ae
    LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
    LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id
    LEFT JOIN familiares f ON ae.familia_id = f.id
";

if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

$sql .= " ORDER BY ae.data_entrega DESC, ae.hora_entrega DESC";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_valor_filtrado = 0;
$total_quantidade_filtrado = 0;
foreach ($entregas as $entrega) {
    $total_valor_filtrado += (float)($entrega['valor_aplicado'] ?? 0);
    $total_quantidade_filtrado += (int)($entrega['quantidade'] ?? 0);
}

$sql_stats = "
    SELECT 
        COUNT(*) as total_entregas,
        COUNT(DISTINCT COALESCE(ae.pessoa_id, ae.pessoa_cpf)) as beneficiarios_unicos,
        SUM(COALESCE(ae.valor_aplicado, 0)) as valor_total,
        SUM(COALESCE(ae.quantidade, 0)) as itens_total,
        AVG(COALESCE(ae.valor_aplicado, 0)) as valor_medio,
        MAX(COALESCE(ae.valor_aplicado, 0)) as valor_maximo,
        MIN(COALESCE(ae.valor_aplicado, 0)) as valor_minimo
    FROM ajudas_entregas ae
    WHERE 1=1
";

$params_stats = [];
$whereStatsConditions = [];

if ($tem_filtro_data) {
    $whereStatsConditions[] = "ae.data_entrega BETWEEN :stats_data_inicio AND :stats_data_fim";
    $params_stats[':stats_data_inicio'] = $filtro_data_inicio;
    $params_stats[':stats_data_fim'] = $filtro_data_fim;
}

if ($filtro_status === 'entregue') {
    $whereStatsConditions[] = "ae.entregue = 'SIM'";
} elseif ($filtro_status === 'nao_entregue') {
    $whereStatsConditions[] = "ae.entregue = 'NAO'";
}

if (!empty($whereStatsConditions)) {
    $sql_stats .= " AND " . implode(" AND ", $whereStatsConditions);
}

$stmt_stats = $pdo->prepare($sql_stats);
foreach ($params_stats as $key => $value) {
    $stmt_stats->bindValue($key, $value);
}
$stmt_stats->execute();
$estatisticas = $stmt_stats->fetch(PDO::FETCH_ASSOC);

if ($estatisticas) {
    $estatisticas['valor_total'] = (float)($estatisticas['valor_total'] ?? 0);
    $estatisticas['valor_medio'] = (float)($estatisticas['valor_medio'] ?? 0);
    $estatisticas['valor_maximo'] = (float)($estatisticas['valor_maximo'] ?? 0);
    $estatisticas['valor_minimo'] = (float)($estatisticas['valor_minimo'] ?? 0);
    $estatisticas['total_entregas'] = (int)($estatisticas['total_entregas'] ?? 0);
    $estatisticas['beneficiarios_unicos'] = (int)($estatisticas['beneficiarios_unicos'] ?? 0);
    $estatisticas['itens_total'] = (int)($estatisticas['itens_total'] ?? 0);
}

$sql_total_geral = "SELECT COUNT(*) as total FROM ajudas_entregas";
$total_geral = $pdo->query($sql_total_geral)->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$sql_valor_total_geral = "SELECT SUM(COALESCE(valor_aplicado, 0)) as valor_total FROM ajudas_entregas";
$valor_total_geral = $pdo->query($sql_valor_total_geral)->fetch(PDO::FETCH_ASSOC)['valor_total'] ?? 0;

try {
    $bairros = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $bairros = [];
}

$beneficios = $pdo->query("SELECT id, nome FROM ajudas_tipos WHERE status = 'Ativa' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$responsaveis = $pdo->query("SELECT DISTINCT responsavel FROM ajudas_entregas WHERE responsavel IS NOT NULL AND responsavel != '' ORDER BY responsavel")->fetchAll(PDO::FETCH_ASSOC);

$sql_grafico = "
    SELECT 
        COALESCE(at.nome, 'Não identificado') as beneficio,
        COUNT(*) as quantidade,
        SUM(COALESCE(ae.valor_aplicado, 0)) as valor_total
    FROM ajudas_entregas ae
    LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id
    WHERE 1=1
";

$params_grafico = [];
$whereGraficoConditions = [];

if ($tem_filtro_data) {
    $whereGraficoConditions[] = "ae.data_entrega BETWEEN :graf_data_inicio AND :graf_data_fim";
    $params_grafico[':graf_data_inicio'] = $filtro_data_inicio;
    $params_grafico[':graf_data_fim'] = $filtro_data_fim;
}

if ($filtro_status === 'entregue') {
    $whereGraficoConditions[] = "ae.entregue = 'SIM'";
} elseif ($filtro_status === 'nao_entregue') {
    $whereGraficoConditions[] = "ae.entregue = 'NAO'";
}

if (!empty($whereGraficoConditions)) {
    $sql_grafico .= " AND " . implode(" AND ", $whereGraficoConditions);
}

$sql_grafico .= " GROUP BY at.nome ORDER BY quantidade DESC LIMIT 10";

$stmt_grafico = $pdo->prepare($sql_grafico);
foreach ($params_grafico as $key => $value) {
    $stmt_grafico->bindValue($key, $value);
}
$stmt_grafico->execute();
$dados_grafico = $stmt_grafico->fetchAll(PDO::FETCH_ASSOC);

$labels_grafico = [];
$quantidades_grafico = [];
$valores_grafico = [];

foreach ($dados_grafico as $item) {
    $labels_grafico[] = $item['beneficio'] ?: 'Sem nome';
    $quantidades_grafico[] = (int)($item['quantidade'] ?? 0);
    $valores_grafico[] = (float)($item['valor_total'] ?? 0);
}

$sql_tendencia = "
    SELECT 
        DATE(ae.data_entrega) as data,
        COUNT(*) as quantidade,
        SUM(COALESCE(ae.valor_aplicado, 0)) as valor_total
    FROM ajudas_entregas ae
    WHERE 1=1
";

$params_tendencia = [];
$whereTendenciaConditions = [];

if ($tem_filtro_data) {
    $whereTendenciaConditions[] = "ae.data_entrega BETWEEN :tend_data_inicio AND :tend_data_fim";
    $params_tendencia[':tend_data_inicio'] = $filtro_data_inicio;
    $params_tendencia[':tend_data_fim'] = $filtro_data_fim;
} else {
    $whereTendenciaConditions[] = "ae.data_entrega >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

if ($filtro_status === 'entregue') {
    $whereTendenciaConditions[] = "ae.entregue = 'SIM'";
} elseif ($filtro_status === 'nao_entregue') {
    $whereTendenciaConditions[] = "ae.entregue = 'NAO'";
}

if (!empty($whereTendenciaConditions)) {
    $sql_tendencia .= " AND " . implode(" AND ", $whereTendenciaConditions);
}

$sql_tendencia .= " GROUP BY DATE(ae.data_entrega) ORDER BY data";

$stmt_tendencia = $pdo->prepare($sql_tendencia);
foreach ($params_tendencia as $key => $value) {
    $stmt_tendencia->bindValue($key, $value);
}
$stmt_tendencia->execute();
$dados_tendencia = $stmt_tendencia->fetchAll(PDO::FETCH_ASSOC);

$datas_tendencia = [];
$quantidades_tendencia = [];
$valores_tendencia = [];

foreach ($dados_tendencia as $item) {
    $datas_tendencia[] = date('d/m', strtotime($item['data']));
    $quantidades_tendencia[] = (int)($item['quantidade'] ?? 0);
    $valores_tendencia[] = (float)($item['valor_total'] ?? 0);
}

function h($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmt_cpf(?string $cpf): string
{
    if (!$cpf) return '';
    $d = preg_replace('/\D+/', '', (string)$cpf);
    if (strlen($d) !== 11) return $cpf;
    return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes' && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');

    $id = (int)$_GET['id'];

    $sql_detalhes = "
        SELECT 
            ae.id,
            ae.data_entrega,
            ae.hora_entrega,
            ae.quantidade,
            ae.valor_aplicado,
            ae.responsavel,
            ae.observacao,
            ae.foto_path as foto_entrega,
            ae.entregue,
            s.nome,
            s.cpf,
            s.telefone,
            s.endereco,
            s.numero,
            s.complemento,
            s.foto_path as foto_solicitante,
            s.genero,
            s.estado_civil,
            s.data_nascimento,
            s.created_at,
            at.nome as beneficio_nome,
            at.categoria as beneficio_categoria,
            at.periodicidade,
            f.nome as familiar_nome,
            f.parentesco
        FROM ajudas_entregas ae
        LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
        LEFT JOIN ajudas_tipos at ON ae.ajuda_tipo_id = at.id
        LEFT JOIN familiares f ON ae.familia_id = f.id
        WHERE ae.id = :id
    ";

    $stmt_detalhes = $pdo->prepare($sql_detalhes);
    $stmt_detalhes->execute([':id' => $id]);
    $entrega_completa = $stmt_detalhes->fetch(PDO::FETCH_ASSOC);

    if ($entrega_completa) {
        $dados_entrega = [
            'id' => $entrega_completa['id'],
            'data_entrega' => $entrega_completa['data_entrega'],
            'hora_entrega' => $entrega_completa['hora_entrega'],
            'quantidade' => $entrega_completa['quantidade'],
            'valor_aplicado' => $entrega_completa['valor_aplicado'],
            'responsavel' => $entrega_completa['responsavel'],
            'observacao' => $entrega_completa['observacao'],
            'foto_entrega' => $entrega_completa['foto_entrega'],
            'entregue' => $entrega_completa['entregue'],
            'beneficio_nome' => $entrega_completa['beneficio_nome'],
            'beneficio_categoria' => $entrega_completa['beneficio_categoria'],
            'periodicidade' => $entrega_completa['periodicidade'],
            'familiar_nome' => $entrega_completa['familiar_nome'],
            'parentesco' => $entrega_completa['parentesco']
        ];

        $dados_solicitante = [
            'nome' => $entrega_completa['nome'],
            'cpf' => $entrega_completa['cpf'],
            'telefone' => $entrega_completa['telefone'],
            'endereco' => $entrega_completa['endereco'],
            'numero' => $entrega_completa['numero'],
            'complemento' => $entrega_completa['complemento'],
            'foto_solicitante' => $entrega_completa['foto_solicitante'],
            'genero' => $entrega_completa['genero'],
            'estado_civil' => $entrega_completa['estado_civil'],
            'data_nascimento' => $entrega_completa['data_nascimento'],
            'created_at' => $entrega_completa['created_at']
        ];

        echo json_encode([
            'ok' => true,
            'entrega' => $dados_entrega,
            'solicitante' => $dados_solicitante
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => false, 'mensagem' => 'Entrega não encontrada']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Entregas Realizadas - ANEXO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .card-stat {
            border-left: 4px solid #435ebe;
            transition: all 0.3s ease;
        }

        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .badge-entregue {
            background-color: #28a745;
            color: white;
        }

        .badge-nao-entregue {
            background-color: #dc3545;
            color: white;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .valor-destaque {
            font-size: 1.8rem;
            font-weight: bold;
            color: #435ebe;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 94, 190, 0.05);
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #tabelaEntregas {
            width: 100% !important;
            table-layout: auto;
            border-collapse: separate;
            border-spacing: 0;
        }

        #tabelaEntregas th {
            background-color: #ffffff;
            color: #212529;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            padding: 12px 10px;
            border: 1px solid #000000;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        #tabelaEntregas thead tr:first-child th:first-child,
        #tabelaEntregas thead tr:first-child th:last-child {
            border-radius: 0;
        }

        .card-header {
            border-bottom: 1px solid #d9deea;
        }

        #tabelaEntregas td {
            padding: 10px 8px;
            vertical-align: middle;
            border: 1px solid #000000;
            white-space: nowrap;
        }

        #tabelaEntregas tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        #tabelaEntregas tr:hover {
            background-color: #e9ecef;
        }

        #tabelaEntregas tbody tr:last-child td {
            border-bottom: 1px solid #000000;
        }

        #tabelaEntregas tfoot {
            background-color: #f8f9fa;
        }

        #tabelaEntregas tfoot td {
            font-weight: bold;
            padding: 12px 8px;
            border-top: 1px solid #000000;
        }

        .profile-wrap {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-photo {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            object-fit: cover;
            border: 3px solid #435ebe;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .profile-subline {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .pill {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            color: #6c757d;
        }

        .kv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .kv {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 0.75rem;
            border: 1px solid #e9ecef;
        }

        .kv-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }

        .kv-value {
            font-size: 0.95rem;
            color: #212529;
            word-break: break-word;
        }

        .whats-wrap {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .whats-link {
            color: #25D366;
            text-decoration: none;
        }

        .whats-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .foto-entrega-container {
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }

        .foto-entrega-img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 6px;
            object-fit: contain;
        }

        .total-geral-info {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .total-geral-info strong {
            color: #0056b3;
        }

        .data-importante {
            background-color: #e7f3ff;
            border-left: 3px solid #007bff;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .data-importante-label {
            font-weight: 600;
            color: #0056b3;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            display: none !important;
        }

        .entregas-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .toolbar-buttons {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
        }

        .toolbar-buttons .dt-buttons {
            display: flex;
            gap: .4rem;
            flex-wrap: wrap;
            margin: 0;
        }

        .toolbar-buttons .dt-buttons .btn {
            margin: 0;
        }

        .toolbar-search {
            margin-left: auto;
        }

        .dt-search-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }

        .dt-search-input {
            width: 100%;
            min-width: 320px;
            max-width: 420px;
            height: 38px;
            padding: 0.55rem 0.9rem;
            border: 1px solid #9bb4f5;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            font-size: 15px;
            box-shadow: none;
            outline: none;
            transition: all .2s ease;
        }

        .dt-search-input::placeholder {
            color: #7f8a99;
            opacity: 1;
        }

        .dt-search-input:focus {
            border-color: #9ab0f5;
            box-shadow: 0 0 0 0.12rem rgba(67, 94, 190, .12);
        }

        .dt-search-clear {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border: 1px solid #cfd6df;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .2s ease;
            padding: 0;
        }

        .dt-search-clear i {
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        .dt-search-clear:hover {
            border-color: #435ebe;
            color: #435ebe;
            background: #f8f9ff;
        }

        .dt-search-clear:focus {
            outline: none;
            box-shadow: 0 0 0 0.12rem rgba(67, 94, 190, .12);
        }

        .custom-pagination-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .custom-pagination-left,
        .custom-pagination-center,
        .custom-pagination-right {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .custom-pagination-center {
            flex: 1;
            justify-content: center;
        }

        .custom-page-btn {
            min-width: 80px;
            border: 1px solid #d0d7de;
            background: #fff;
            color: #6c757d;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .custom-page-btn:hover:not(:disabled) {
            border-color: #435ebe;
            color: #435ebe;
            background: #f8f9ff;
        }

        .custom-page-btn:disabled {
            background: #f5f6f8;
            color: #b5b8bf;
            border-color: #dfe3e8;
            cursor: not-allowed;
        }

        .custom-page-info {
            font-size: 1.05rem;
            font-weight: 700;
            color: #435ebe;
            white-space: nowrap;
        }

        .custom-length-label {
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0;
            white-space: nowrap;
        }

        .custom-length-select {
            min-width: 72px;
            padding: 0.45rem 2rem 0.45rem 0.75rem;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            background-color: #fff;
            color: #495057;
            font-weight: 600;
            outline: none;
        }

        .custom-length-select:focus {
            border-color: #435ebe;
            box-shadow: 0 0 0 0.15rem rgba(67, 94, 190, 0.15);
        }

        @media (max-width: 991.98px) {
            .entregas-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .toolbar-search {
                margin-left: 0;
                width: 100%;
            }

            .dt-search-wrap {
                width: 100%;
            }

            .dt-search-input {
                min-width: 0;
                max-width: 100%;
                flex: 1 1 auto;
            }
        }

        @media (max-width: 768px) {
            .custom-pagination-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .custom-pagination-left,
            .custom-pagination-center,
            .custom-pagination-right {
                justify-content: center;
                width: 100%;
            }
        }

        @media print {

            .sidebar,
            .page-heading,
            .card-header .btn,
            .entregas-toolbar,
            .custom-pagination-bar,
            .foto-entrega-container,
            .modal-photo,
            .profile-wrap img,
            .card-stat,
            .chart-container,
            .btn-primary,
            .card-header,
            .badge,
            .btn-sm {
                display: none !important;
            }

            .card {
                border: 1px solid #ddd !important;
                box-shadow: none !important;
                page-break-inside: avoid;
                margin: 0 !important;
            }

            .card-body {
                padding: 5px !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 11px !important;
                margin: 5px 0 !important;
            }

            th,
            td {
                padding: 3px 4px !important;
                border: 1px solid #000 !important;
                page-break-inside: avoid;
            }

            @page {
                size: A4 landscape;
                margin: 5mm;
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
                        <div class="toggler"><a href="#" class="sidebar-hide d-lg-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item">
                            <a href="dashboard.php" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i><span>Dashboard</span>
                            </a>
                        </li>

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-hand-thumbs-up-fill"></i><span>Entregas</span>
                            </a>
                            <ul class="submenu active">
                                <li class="submenu-item active"><a href="entregasRealizadas.php">Histórico de Entregas</a></li>
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
                                <i class="bi bi-cash-stack"></i><span>Controle Financeiro</span>
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

                        <li class="sidebar-item">
                            <a href="auditoria.php" class="sidebar-link">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Auditoria</span>
                            </a>
                        </li>

                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link">
                                <i class="bi bi-box-arrow-right"></i><span>Sair</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div id="main">
            <div class="page-heading">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Entregas Realizadas</h3>
                        <p class="text-subtitle text-muted">Histórico completo de entregas realizadas pelo ANEXO</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalFiltros">
                            <i class="bi bi-funnel-fill me-2"></i>Filtros Avançados
                        </button>
                    </div>
                </div>
            </div>

            <div class="page-content">
                <div class="total-geral-info">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Total geral:</strong> <?= number_format((int)$total_geral, 0, ',', '.') ?> entregas |
                    <strong>Valor geral:</strong> <?= formatarMoeda($valor_total_geral) ?>

                    <?php if (!empty($whereConditions)): ?>
                        <span class="ms-3">
                            <i class="bi bi-filter me-1"></i>
                            <strong>Com filtros aplicados:</strong> <?= count($entregas) ?> entregas |
                            <strong>Valor filtrado:</strong> <?= formatarMoeda($total_valor_filtrado) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <section class="row">
                    <div class="col-12">
                        <div class="row">
                            <div class="col-lg-3 col-md-6">
                                <div class="card card-stat">
                                    <div class="card-body">
                                        <h6 class="text-muted font-semibold">Total de Entregas</h6>
                                        <h2 class="valor-destaque"><?= number_format($estatisticas['total_entregas'] ?? 0, 0, ',', '.') ?></h2>
                                        <div class="pt-1">
                                            <?php if ($tem_filtro_data): ?>
                                                <small>Período: <?= date('d/m/Y', strtotime($filtro_data_inicio)) ?> - <?= date('d/m/Y', strtotime($filtro_data_fim)) ?></small>
                                            <?php else: ?>
                                                <small>Todos os períodos</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="card card-stat">
                                    <div class="card-body">
                                        <h6 class="text-muted font-semibold">Beneficiários Únicos</h6>
                                        <h2 class="valor-destaque"><?= number_format($estatisticas['beneficiarios_unicos'] ?? 0, 0, ',', '.') ?></h2>
                                        <div class="pt-1"><small>Pessoas atendidas</small></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="card card-stat">
                                    <div class="card-body">
                                        <h6 class="text-muted font-semibold">Valor Total Aplicado</h6>
                                        <h2 class="valor-destaque"><?= formatarMoeda($estatisticas['valor_total'] ?? 0) ?></h2>
                                        <div class="pt-1"><small>Investimento total</small></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-3 col-md-6">
                                <div class="card card-stat">
                                    <div class="card-body">
                                        <h6 class="text-muted font-semibold">Valor Médio</h6>
                                        <h2 class="valor-destaque"><?= formatarMoeda($estatisticas['valor_medio'] ?? 0) ?></h2>
                                        <div class="pt-1"><small>Por entrega</small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mt-4">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Distribuição por Tipo de Benefício</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="chartBeneficios"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Tendência Diária de Entregas</h4>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="chartTendencia"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Detalhes das Entregas</h4>
                                <p class="text-muted mb-0">
                                    <?php if ($filtro_status === 'entregue'): ?>
                                        <span class="badge badge-entregue me-2">APENAS ENTREGUES</span>
                                    <?php elseif ($filtro_status === 'nao_entregue'): ?>
                                        <span class="badge badge-nao-entregue me-2">APENAS NÃO ENTREGUES</span>
                                    <?php endif; ?>
                                    <?= count($entregas) ?> registros encontrados
                                </p>
                            </div>
                            <div class="card-body">
                                <div class="entregas-toolbar">
                                    <div id="toolbarButtonsEntregas" class="toolbar-buttons"></div>

                                    <div class="toolbar-search">
                                        <div class="dt-search-wrap">
                                            <input type="text" id="pesquisaEntregas" class="dt-search-input" placeholder="Buscar por nome/CPF/benefício/responsável...">
                                            <button type="button" class="dt-search-clear" id="btnClearSearchEntregas" title="Limpar pesquisa" aria-label="Limpar pesquisa">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover" id="tabelaEntregas">
                                        <thead>
                                            <tr>
                                                <th>Data/Hora</th>
                                                <th>Beneficiário</th>
                                                <th>CPF</th>
                                                <th>Benefício</th>
                                                <th>Qtde</th>
                                                <th>Valor</th>
                                                <th>Responsável</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($entregas as $entrega):
                                                $valor_aplicado = (float)($entrega['valor_aplicado'] ?? 0);
                                                $cpf = $entrega['solicitante_cpf'] ?? $entrega['pessoa_cpf'] ?? '';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <?= date('d/m/Y', strtotime($entrega['data_entrega'])) ?>
                                                        <?= $entrega['hora_entrega'] ? 'às ' . substr($entrega['hora_entrega'], 0, 5) : '' ?>
                                                    </td>
                                                    <td>
                                                        <strong><?= h($entrega['solicitante_nome'] ?? 'Não identificado') ?></strong>
                                                        <?php if ($entrega['familiar_nome']): ?>
                                                            <div class="mt-1">
                                                                <small class="text-info">
                                                                    <i class="bi bi-person"></i>
                                                                    <?= h($entrega['familiar_nome']) ?> (<?= h($entrega['parentesco']) ?>)
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= fmt_cpf($cpf) ?></td>
                                                    <td><?= h($entrega['beneficio_nome'] ?? 'Não identificado') ?></td>
                                                    <td><span class="badge bg-primary rounded-pill"><?= (int)($entrega['quantidade'] ?? 0) ?></span></td>
                                                    <td><strong><?= formatarMoeda($valor_aplicado) ?></strong></td>
                                                    <td><?= h($entrega['responsavel'] ?? 'Não informado') ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-primary btnDetalhes"
                                                            data-entrega-id="<?= $entrega['id'] ?>"
                                                            title="Ver detalhes">
                                                            Ver
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="4" class="text-end"><strong>TOTAIS:</strong></td>
                                                <td><strong><?= $total_quantidade_filtrado ?></strong></td>
                                                <td><strong><?= formatarMoeda($total_valor_filtrado) ?></strong></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>

                                <div id="customPaginationEntregas" class="custom-pagination-bar"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFiltros" tabindex="-1" aria-labelledby="modalFiltrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="GET" action="" id="formFiltros">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalFiltrosLabel">Filtros Avançados</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Nota:</strong> Deixe as datas vazias para ver todas as entregas.
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="data_inicio" class="form-label">Data Início (opcional)</label>
                                    <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?= h($filtro_data_inicio) ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="data_fim" class="form-label">Data Fim (opcional)</label>
                                    <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?= h($filtro_data_fim) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="cpf" class="form-label">CPF do Beneficiário (opcional)</label>
                                    <input type="text" class="form-control" id="cpf" name="cpf" value="<?= h($filtro_cpf) ?>" placeholder="000.000.000-00">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status da Entrega</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="todos" <?= $filtro_status === 'todos' ? 'selected' : '' ?>>Todos os Status</option>
                                        <option value="entregue" <?= $filtro_status === 'entregue' ? 'selected' : '' ?>>Apenas Entregues</option>
                                        <option value="nao_entregue" <?= $filtro_status === 'nao_entregue' ? 'selected' : '' ?>>Apenas Não Entregues</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bairro" class="form-label">Bairro (opcional)</label>
                                    <select class="form-select" id="bairro" name="bairro">
                                        <option value="todos">Todos os Bairros</option>
                                        <?php foreach ($bairros as $bairro): ?>
                                            <option value="<?= h($bairro['id']) ?>" <?= $filtro_bairro == $bairro['id'] ? 'selected' : '' ?>>
                                                <?= h($bairro['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="beneficio" class="form-label">Tipo de Benefício (opcional)</label>
                                    <select class="form-select" id="beneficio" name="beneficio">
                                        <option value="todos">Todos os Benefícios</option>
                                        <?php foreach ($beneficios as $beneficio): ?>
                                            <option value="<?= h($beneficio['id']) ?>" <?= $filtro_beneficio == $beneficio['id'] ? 'selected' : '' ?>>
                                                <?= h($beneficio['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="responsavel" class="form-label">Responsável pela Entrega (opcional)</label>
                                    <select class="form-select" id="responsavel" name="responsavel">
                                        <option value="">Todos os Responsáveis</option>
                                        <?php foreach ($responsaveis as $resp): ?>
                                            <option value="<?= h($resp['responsavel']) ?>" <?= $filtro_responsavel == $resp['responsavel'] ? 'selected' : '' ?>>
                                                <?= h($resp['responsavel']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="valor_min" class="form-label">Valor Mínimo (opcional)</label>
                                    <input type="number" class="form-control" id="valor_min" name="valor_min" value="<?= h($filtro_valor_min) ?>" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="valor_max" class="form-label">Valor Máximo (opcional)</label>
                                    <input type="number" class="form-control" id="valor_max" name="valor_max" value="<?= h($filtro_valor_max) ?>" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <a href="entregasRealizadas.php" class="btn btn-danger">Limpar Filtros</a>
                        <button type="submit" class="btn btn-primary" id="btnAplicarFiltros">Aplicar Filtros</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes da Entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="profile-wrap">
                        <img id="md-foto-solicitante" class="modal-photo" src="../dist/assets/images/dashboard/top-image.jpg" alt="Foto do Solicitante">
                        <div class="profile-info">
                            <h5 class="profile-name" id="md-nome">—</h5>
                            <div class="profile-subline">
                                <span class="pill"><i class="bi bi-person"></i> <span id="md-genero">—</span></span>
                                <span class="pill"><i class="bi bi-heart"></i> <span id="md-ec">—</span></span>
                                <span class="pill"><i class="bi bi-calendar2"></i> <span id="md-nasc">—</span></span>
                                <span class="pill"><i class="bi bi-person-badge"></i> <span id="md-resp-pill">—</span></span>
                            </div>
                            <div class="data-importante mt-2">
                                <div><span class="data-importante-label">Data do Cadastro:</span> <span id="md-data-cadastro-display">—</span></div>
                            </div>
                        </div>
                    </div>

                    <div id="md-foto-entrega-container" class="foto-entrega-container d-none">
                        <h6 class="mb-2">Foto da Entrega</h6>
                        <div class="text-center">
                            <img id="md-foto-entrega-img" src="" alt="Foto da entrega" class="foto-entrega-img mb-2">
                            <div class="text-center">
                                <small class="text-muted">Foto registrada no momento da entrega</small>
                            </div>
                        </div>
                    </div>

                    <h6 class="mb-2">I. Informações da Entrega</h6>
                    <div class="kv-grid mb-3">
                        <div class="kv">
                            <div class="kv-label">Data da Entrega</div>
                            <div class="kv-value" id="md-data-entrega">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Hora da Entrega</div>
                            <div class="kv-value" id="md-hora-entrega">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Responsável da Entrega</div>
                            <div class="kv-value" id="md-responsavel-entrega">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Quantidade</div>
                            <div class="kv-value" id="md-quantidade">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Valor Aplicado</div>
                            <div class="kv-value" id="md-valor">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Observações</div>
                            <div class="kv-value" id="md-observacoes">—</div>
                        </div>
                    </div>

                    <h6 class="mb-2">II. Informações do Benefício</h6>
                    <div class="kv-grid mb-3">
                        <div class="kv">
                            <div class="kv-label">Tipo de Benefício</div>
                            <div class="kv-value" id="md-beneficio">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Categoria</div>
                            <div class="kv-value" id="md-beneficio-categoria">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Periodicidade</div>
                            <div class="kv-value" id="md-periodicidade">—</div>
                        </div>
                    </div>

                    <h6 class="mb-2">III. Informações do Beneficiário</h6>
                    <div class="kv-grid mb-3">
                        <div class="kv">
                            <div class="kv-label">Nome</div>
                            <div class="kv-value" id="md-nome-beneficiario">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">CPF</div>
                            <div class="kv-value" id="md-cpf">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Telefone</div>
                            <div class="kv-value whats-wrap">
                                <span id="md-telefone">—</span>
                                <a id="md-whats" class="whats-link disabled" href="#" target="_blank" rel="noopener" title="Abrir WhatsApp">
                                    <i class="bi bi-whatsapp fs-5"></i>
                                </a>
                            </div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Data de Nascimento</div>
                            <div class="kv-value" id="md-data-nascimento">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Gênero</div>
                            <div class="kv-value" id="md-genero-2">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Estado Civil</div>
                            <div class="kv-value" id="md-estado-civil">—</div>
                        </div>
                    </div>

                    <h6 class="mb-2">IV. Endereço</h6>
                    <div class="kv-grid mb-3">
                        <div class="kv">
                            <div class="kv-label">Endereço</div>
                            <div class="kv-value" id="md-endereco">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Número</div>
                            <div class="kv-value" id="md-numero">—</div>
                        </div>
                        <div class="kv">
                            <div class="kv-label">Complemento</div>
                            <div class="kv-value" id="md-complemento">—</div>
                        </div>
                    </div>

                    <div id="md-familiar-container" class="d-none">
                        <h6 class="mb-2">Familiar Beneficiado</h6>
                        <div class="kv-grid mb-3">
                            <div class="kv">
                                <div class="kv-label">Nome do Familiar</div>
                                <div class="kv-value" id="md-familiar-nome">—</div>
                            </div>
                            <div class="kv">
                                <div class="kv-label">Parentesco</div>
                                <div class="kv-value" id="md-familiar-parentesco">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        $(document).ready(function() {
            function verificarFiltrosAtivos() {
                const params = new URLSearchParams(window.location.search);
                const hasFilters = params.toString() !== '';
                const btnFiltros = $('.btn[data-bs-target="#modalFiltros"]');

                if (hasFilters) {
                    btnFiltros.removeClass('btn-primary').addClass('btn-success');
                    btnFiltros.html('<i class="bi bi-funnel-fill me-2"></i>Filtros Ativos');
                } else {
                    btnFiltros.removeClass('btn-success').addClass('btn-primary');
                    btnFiltros.html('<i class="bi bi-funnel-fill me-2"></i>Filtros Avançados');
                }
            }

            function normalizarTextoExportacao(data, column) {
                let texto = $('<div>').html(data).text();

                texto = texto.replace(/\u00A0/g, ' ');
                texto = texto.replace(/\s+/g, ' ').trim();

                if (column === 0) {
                    const m = texto.match(/^(\d{2}\/\d{2}\/\d{4})(?:\s*(?:às)?\s*(\d{2}:\d{2}))?$/i);
                    if (m) {
                        texto = m[1] + (m[2] ? ' às ' + m[2] : '');
                    } else {
                        texto = texto.replace(/\s*às\s*/gi, ' às ');
                    }
                }

                return texto;
            }

            verificarFiltrosAtivos();

            let table = $('#tabelaEntregas').DataTable({
                dom: 'Brt',
                buttons: [{
                        extend: 'excelHtml5',
                        text: '<i class="bi bi-file-earmark-excel me-1"></i>Excel',
                        className: 'btn btn-sm btn-success',
                        title: 'ENTREGAS REALIZADAS - ANEXO',
                        messageTop: function() {
                            let periodo = '';
                            <?php if ($tem_filtro_data): ?>
                                periodo = 'Período: <?= date("d/m/Y", strtotime($filtro_data_inicio)) ?> - <?= date("d/m/Y", strtotime($filtro_data_fim)) ?>\n';
                            <?php else: ?>
                                periodo = 'Todas as entregas\n';
                            <?php endif; ?>
                            return periodo + 'Total: <?= count($entregas) ?> registros | Valor Total: <?= formatarMoeda($total_valor_filtrado) ?>';
                        },
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6],
                            format: {
                                header: function(data, column) {
                                    return normalizarTextoExportacao(data, column);
                                },
                                body: function(data, row, column, node) {
                                    return normalizarTextoExportacao(data, column);
                                },
                                footer: function(data, column) {
                                    return normalizarTextoExportacao(data, column);
                                }
                            }
                        },
                        customize: function(xlsx) {
                            const sheet = xlsx.xl.worksheets['sheet1.xml'];
                            const styles = xlsx.xl['styles.xml'];

                            const larguras = [24, 42, 18, 28, 10, 16, 36];
                            $('col', sheet).each(function(i) {
                                if (larguras[i]) {
                                    $(this).attr('width', larguras[i]);
                                    $(this).attr('customWidth', '1');
                                    $(this).attr('bestFit', '1');
                                }
                            });

                            function appendXml(parent, xml) {
                                const node = $.parseXML(xml).documentElement;
                                parent.appendChild(styles.importNode(node, true));
                            }

                            const fonts = $('fonts', styles)[0];
                            const borders = $('borders', styles)[0];
                            const cellXfs = $('cellXfs', styles)[0];

                            const titleFontId = parseInt($(fonts).attr('count'), 10);
                            appendXml(fonts, '<font><sz val="16"/><b/><color rgb="FF000000"/><name val="Calibri"/></font>');
                            $(fonts).attr('count', titleFontId + 1);

                            const headerFontId = parseInt($(fonts).attr('count'), 10);
                            appendXml(fonts, '<font><sz val="12"/><b/><color rgb="FF000000"/><name val="Calibri"/></font>');
                            $(fonts).attr('count', headerFontId + 1);

                            const borderId = parseInt($(borders).attr('count'), 10);
                            appendXml(borders, '<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border>');
                            $(borders).attr('count', borderId + 1);

                            function addCellStyle(xml) {
                                const styleId = parseInt($(cellXfs).attr('count'), 10);
                                appendXml(cellXfs, xml);
                                $(cellXfs).attr('count', styleId + 1);
                                return styleId;
                            }

                            const titleStyle = addCellStyle(
                                '<xf numFmtId="0" fontId="' + titleFontId + '" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
                            );
                            const infoStyle = addCellStyle(
                                '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
                            );
                            const headerStyle = addCellStyle(
                                '<xf numFmtId="0" fontId="' + headerFontId + '" fillId="0" borderId="' + borderId + '" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
                            );
                            const bodyCenterStyle = addCellStyle(
                                '<xf numFmtId="0" fontId="0" fillId="0" borderId="' + borderId + '" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
                            );
                            const bodyLeftStyle = addCellStyle(
                                '<xf numFmtId="0" fontId="0" fillId="0" borderId="' + borderId + '" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
                            );

                            let mergeCells = $('mergeCells', sheet);
                            if (!mergeCells.length) {
                                const mergeNode = sheet.createElement('mergeCells');
                                mergeNode.setAttribute('count', '0');
                                const sheetData = $('sheetData', sheet)[0];
                                sheetData.parentNode.insertBefore(mergeNode, sheetData.nextSibling);
                                mergeCells = $('mergeCells', sheet);
                            }

                            const jaMesclado = mergeCells.find('mergeCell[ref="A1:G1"]').length > 0;
                            if (!jaMesclado) {
                                const titleMerge = sheet.createElement('mergeCell');
                                titleMerge.setAttribute('ref', 'A1:G1');
                                mergeCells[0].appendChild(titleMerge);
                                mergeCells.attr('count', mergeCells.children('mergeCell').length);
                            }

                            let headerRowNum = 0;
                            $('row', sheet).each(function() {
                                const rowText = $('c', this).map(function() {
                                    const type = $(this).attr('t');
                                    if (type === 'inlineStr') return $('t', this).text();
                                    const value = $('v', this).text();
                                    if (type === 's') {
                                        const idx = parseInt(value, 10);
                                        return $('si', xlsx.xl['sharedStrings.xml']).eq(idx).text();
                                    }
                                    return value;
                                }).get().join(' | ');
                                if (rowText.includes('Data/Hora') && rowText.includes('Beneficiário')) {
                                    headerRowNum = parseInt($(this).attr('r'), 10) || 0;
                                }
                            });

                            $('row', sheet).each(function() {
                                const rowNum = parseInt($(this).attr('r'), 10) || 0;
                                $(this).removeAttr('customHeight').removeAttr('ht');

                                if (rowNum === 1) {
                                    $(this).attr('ht', '28').attr('customHeight', '1');
                                } else if (rowNum === headerRowNum) {
                                    $(this).attr('ht', '24').attr('customHeight', '1');
                                }

                                $('c', this).each(function() {
                                    const ref = $(this).attr('r') || '';
                                    const col = ref.replace(/[0-9]/g, '');

                                    if (rowNum === 1) {
                                        $(this).attr('s', titleStyle);
                                    } else if (headerRowNum && rowNum === headerRowNum) {
                                        $(this).attr('s', headerStyle);
                                    } else if (!headerRowNum || rowNum < headerRowNum) {
                                        $(this).attr('s', infoStyle);
                                    } else {
                                        $(this).attr('s', col === 'B' ? bodyLeftStyle : bodyCenterStyle);
                                    }
                                });
                            });
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="bi bi-printer me-1"></i>Imprimir',
                        className: 'btn btn-sm btn-primary',
                        title: '',
                        messageTop: function() {
                            let periodo = '';
                            <?php if ($tem_filtro_data): ?>
                                periodo = '<p>Período: <?= date("d/m/Y", strtotime($filtro_data_inicio)) ?> - <?= date("d/m/Y", strtotime($filtro_data_fim)) ?></p>';
                            <?php else: ?>
                                periodo = '<p>Todas as entregas</p>';
                            <?php endif; ?>

                            return '<div class="print-header">' +
                                '<h3>ENTREGAS REALIZADAS - ANEXO</h3>' +
                                periodo +
                                '<p>Total: <?= count($entregas) ?> registros | Valor Total: <?= formatarMoeda($total_valor_filtrado) ?></p>' +
                                '<p>Gerado em: ' + new Date().toLocaleDateString('pt-BR') + ' ' + new Date().toLocaleTimeString('pt-BR') + '</p>' +
                                '</div>';
                        },
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
                        },
                        customize: function(win) {
                            const doc = $(win.document);

                            doc.find('head').append(`
                                <style>
                                    @page {
                                        size: A4 landscape;
                                        margin: 8mm;
                                    }

                                    body {
                                        font-family: Arial, sans-serif !important;
                                        color: #000 !important;
                                        background: #fff !important;
                                    }

                                    .print-header {
                                        padding: 0 0 8px 0 !important;
                                        margin: 0 0 8px 0 !important;
                                        border: 0 !important;
                                    }

                                    .print-header h3 {
                                        margin: 0 0 8px 0 !important;
                                        text-align: center !important;
                                        font-size: 20px !important;
                                        font-weight: 700 !important;
                                        color: #000 !important;
                                    }

                                    .print-header p {
                                        margin: 3px 0 !important;
                                        font-size: 12px !important;
                                        color: #000 !important;
                                    }

                                    table.dataTable {
                                        width: 100% !important;
                                        border-collapse: collapse !important;
                                        border-spacing: 0 !important;
                                        font-size: 10px !important;
                                        color: #000 !important;
                                    }

                                    table.dataTable thead th {
                                        font-size: 11px !important;
                                        font-weight: 700 !important;
                                        text-align: center !important;
                                        background: #fff !important;
                                        color: #000 !important;
                                        border: 1px solid #000 !important;
                                        padding: 5px 4px !important;
                                    }

                                    table.dataTable tbody td,
                                    table.dataTable tfoot td {
                                        background: #fff !important;
                                        color: #000 !important;
                                        border: 1px solid #000 !important;
                                        padding: 4px !important;
                                        vertical-align: middle !important;
                                        white-space: normal !important;
                                        word-break: normal !important;
                                    }

                                    table.dataTable tbody td:nth-child(2) {
                                        text-align: left !important;
                                    }

                                    table.dataTable tbody td:not(:nth-child(2)) {
                                        text-align: center !important;
                                    }
                                </style>
                            `);

                            doc.find('body').css('background', '#fff');
                            doc.find('table').removeClass('table-striped table-hover');
                            doc.find('table thead th, table tbody td, table tfoot td').css('border', '1px solid #000');
                        }
                    }
                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json'
                },
                order: [
                    [1, 'asc']
                ],
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                scrollX: true,
                scrollCollapse: true,
                fixedHeader: true,
                responsive: false,
                columnDefs: [{
                        orderable: false,
                        targets: [7],
                        className: 'dt-body-center text-center'
                    },
                    {
                        targets: [0, 2, 3, 4, 5, 6],
                        className: 'text-center'
                    },
                    {
                        targets: [1],
                        className: 'text-start'
                    },
                    {
                        className: 'dt-head-center text-center',
                        targets: '_all'
                    }
                ],
                initComplete: function() {
                    $('#tabelaEntregas_wrapper .dt-buttons').appendTo('#toolbarButtonsEntregas');
                    renderCustomPagination();
                },
                drawCallback: function() {
                    renderCustomPagination();
                }
            });

            function renderCustomPagination() {
                const info = table.page.info();
                const currentPage = info.pages > 0 ? (info.page + 1) : 1;
                const totalPages = info.pages > 0 ? info.pages : 1;
                const currentLength = info.length;

                const html = `
                    <div class="custom-pagination-left">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrevPage" ${info.page <= 0 ? 'disabled' : ''}>Anterior</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNextPage" ${info.page >= (info.pages - 1) ? 'disabled' : ''}>Próximo</button>
                    </div>
                    <div class="custom-pagination-center">
                        <span class="custom-page-info">Página ${currentPage} de ${totalPages}</span>
                    </div>
                    <div class="custom-pagination-right">
                        <label for="customPageLength" class="custom-length-label">por página</label>
                        <select id="customPageLength" class="custom-length-select">
                            <option value="10" ${currentLength === 10 ? 'selected' : ''}>10</option>
                            <option value="25" ${currentLength === 25 ? 'selected' : ''}>25</option>
                            <option value="50" ${currentLength === 50 ? 'selected' : ''}>50</option>
                            <option value="100" ${currentLength === 100 ? 'selected' : ''}>100</option>
                        </select>
                    </div>
                `;

                $('#customPaginationEntregas').html(html);
            }

            $('#pesquisaEntregas').on('input', function() {
                table.search(this.value).draw();
            });

            $('#btnClearSearchEntregas').on('click', function() {
                $('#pesquisaEntregas').val('');
                table.search('').draw();
                $('#pesquisaEntregas').trigger('focus');
            });

            $(document).on('click', '#btnPrevPage', function() {
                table.page('previous').draw('page');
            });

            $(document).on('click', '#btnNextPage', function() {
                table.page('next').draw('page');
            });

            $(document).on('change', '#customPageLength', function() {
                const novoLimite = parseInt($(this).val(), 10) || 10;
                table.page.len(novoLimite).draw();
            });

            $(document).on('click', '.btnDetalhes', async function() {
                const entregaId = $(this).data('entrega-id');
                if (!entregaId) return;

                try {
                    const response = await fetch(`?ajax=detalhes&id=${entregaId}`);
                    const data = await response.json();

                    if (!data.ok || !data.entrega) {
                        alert('Falha ao carregar detalhes da entrega.');
                        return;
                    }

                    const entrega = data.entrega;
                    const solicitante = data.solicitante;

                    function setText(id, val) {
                        const el = document.getElementById(id);
                        if (el) el.textContent = (val ?? '').toString().trim() !== '' ? String(val) : '—';
                    }

                    function fmtCPF(cpf) {
                        if (!cpf) return '—';
                        cpf = cpf.toString().replace(/\D/g, '');
                        if (cpf.length !== 11) return cpf;
                        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
                    }

                    function fmtDate(dateStr) {
                        if (!dateStr) return '—';
                        const date = new Date(dateStr);
                        return date.toLocaleDateString('pt-BR');
                    }

                    function fmtTime(timeStr) {
                        if (!timeStr) return '—';
                        return timeStr.substring(0, 5);
                    }

                    function fmtDateTime(dateTimeStr) {
                        if (!dateTimeStr) return '—';
                        const date = new Date(dateTimeStr);
                        return date.toLocaleString('pt-BR');
                    }

                    setText('md-nome', solicitante.nome || '—');
                    setText('md-genero', solicitante.genero || '—');
                    setText('md-ec', solicitante.estado_civil || '—');
                    setText('md-nasc', solicitante.data_nascimento ? fmtDate(solicitante.data_nascimento) : '—');
                    setText('md-resp-pill', entrega.responsavel || '—');

                    const dataCadastroDisplay = solicitante.created_at ? fmtDateTime(solicitante.created_at) : '—';
                    setText('md-data-cadastro-display', dataCadastroDisplay);

                    const fotoSolicitante = document.getElementById('md-foto-solicitante');
                    if (fotoSolicitante) {
                        const fotoPath = solicitante.foto_solicitante || '';
                        fotoSolicitante.src = fotoPath ? '../dist/' + fotoPath : '../dist/assets/images/user.png';
                        fotoSolicitante.onerror = function() {
                            this.src = '../dist/assets/images/user.png';
                        };
                    }

                    const fotoEntregaContainer = document.getElementById('md-foto-entrega-container');
                    const fotoEntregaImg = document.getElementById('md-foto-entrega-img');
                    if (entrega.foto_entrega && entrega.foto_entrega.trim() !== '') {
                        fotoEntregaContainer.classList.remove('d-none');
                        fotoEntregaImg.src = '../dist/' + entrega.foto_entrega;
                        fotoEntregaImg.onerror = function() {
                            fotoEntregaContainer.classList.add('d-none');
                        };
                    } else {
                        fotoEntregaContainer.classList.add('d-none');
                    }

                    setText('md-data-entrega', fmtDate(entrega.data_entrega));
                    setText('md-hora-entrega', entrega.hora_entrega ? fmtTime(entrega.hora_entrega) : '—');
                    setText('md-responsavel-entrega', entrega.responsavel || '—');
                    setText('md-quantidade', entrega.quantidade || '0');
                    setText('md-valor', entrega.valor_aplicado ? 'R$ ' + parseFloat(entrega.valor_aplicado).toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }) : 'R$ 0,00');
                    setText('md-observacoes', entrega.observacao || '—');

                    setText('md-beneficio', entrega.beneficio_nome || '—');
                    setText('md-beneficio-categoria', entrega.beneficio_categoria || '—');
                    setText('md-periodicidade', entrega.periodicidade || '—');

                    setText('md-nome-beneficiario', solicitante.nome || '—');
                    setText('md-cpf', fmtCPF(solicitante.cpf));
                    setText('md-telefone', solicitante.telefone || '—');
                    setText('md-data-nascimento', solicitante.data_nascimento ? fmtDate(solicitante.data_nascimento) : '—');
                    setText('md-genero-2', solicitante.genero || '—');
                    setText('md-estado-civil', solicitante.estado_civil || '—');

                    const whatsLink = document.getElementById('md-whats');
                    const telefone = solicitante.telefone || '';
                    if (telefone && telefone.trim() !== '') {
                        const numeroLimpo = telefone.replace(/\D/g, '');
                        whatsLink.href = 'https://wa.me/55' + numeroLimpo;
                        whatsLink.classList.remove('disabled');
                    } else {
                        whatsLink.href = '#';
                        whatsLink.classList.add('disabled');
                    }

                    setText('md-endereco', solicitante.endereco || '—');
                    setText('md-numero', solicitante.numero || '—');
                    setText('md-complemento', solicitante.complemento || '—');

                    const familiarContainer = document.getElementById('md-familiar-container');
                    if (entrega.familiar_nome && entrega.parentesco) {
                        familiarContainer.classList.remove('d-none');
                        setText('md-familiar-nome', entrega.familiar_nome);
                        setText('md-familiar-parentesco', entrega.parentesco);
                    } else {
                        familiarContainer.classList.add('d-none');
                    }

                    const modal = new bootstrap.Modal(document.getElementById('modalDetalhes'));
                    modal.show();

                } catch (error) {
                    console.error('Erro ao carregar detalhes:', error);
                    alert('Erro ao carregar detalhes da entrega.');
                }
            });

            $('#cpf').on('input', function() {
                var valor = $(this).val().replace(/\D/g, '');
                if (valor.length <= 11) {
                    valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                    valor = valor.replace(/(\d{3})(\d)/, '$1.$2');
                    valor = valor.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                    $(this).val(valor);
                }
            });

            const chartBeneficiosEl = document.getElementById('chartBeneficios');
            if (chartBeneficiosEl && <?= json_encode($labels_grafico) ?>.length > 0) {
                new Chart(chartBeneficiosEl.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($labels_grafico) ?>,
                        datasets: [{
                            label: 'Quantidade de Entregas',
                            data: <?= json_encode($quantidades_grafico) ?>,
                            backgroundColor: 'rgba(54, 162, 235, 0.7)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantidade'
                                }
                            }
                        }
                    }
                });
            } else if (chartBeneficiosEl) {
                chartBeneficiosEl.parentNode.innerHTML = '<div class="alert alert-info text-center py-5">Nenhum dado disponível para o gráfico no período selecionado.</div>';
            }

            const chartTendenciaEl = document.getElementById('chartTendencia');
            if (chartTendenciaEl && <?= json_encode($datas_tendencia) ?>.length > 0) {
                new Chart(chartTendenciaEl.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($datas_tendencia) ?>,
                        datasets: [{
                            label: 'Quantidade de Entregas',
                            data: <?= json_encode($quantidades_tendencia) ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 2,
                            tension: 0.1,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Quantidade de Entregas'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Data'
                                }
                            }
                        }
                    }
                });
            } else if (chartTendenciaEl) {
                chartTendenciaEl.parentNode.innerHTML = '<div class="alert alert-info text-center py-5">Nenhum dado disponível para o gráfico no período selecionado.</div>';
            }

            $('#formFiltros').submit(function() {
                $('#btnAplicarFiltros').prop('disabled', true);
            });
        });
    </script>
</body>

</html>
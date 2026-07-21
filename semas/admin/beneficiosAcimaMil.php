<?php

declare(strict_types=1);

/* AUTH (já é privado) */
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

if ((!isset($pdo) || !($pdo instanceof PDO)) && function_exists('db')) {
    $pdo = db();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Erro de conexão');
}

/* Função para formatar valor monetário */
function formatarMoeda($valor): string
{
    if ($valor === null || $valor === '' || (float)$valor === 0.0) {
        return 'R$ 0,00';
    }
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/* Função para formatar CPF */
function formatarCPF($cpf): string
{
    $cpf = preg_replace('/\D+/', '', (string)$cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return (string)$cpf;
}

/* Processar filtros */
$filtros_aplicados   = false;
$filtro_mes          = $_GET['mes'] ?? 'todos';
$filtro_ano          = $_GET['ano'] ?? 'todos';
$filtro_beneficio    = $_GET['beneficio'] ?? '';
$filtro_bairro       = $_GET['bairro'] ?? '';
$filtro_status       = $_GET['status'] ?? 'todos';
$filtro_valor_min    = isset($_GET['valor_min']) && $_GET['valor_min'] !== '' ? (float)$_GET['valor_min'] : 1000;
$ordenacao           = $_GET['ordenacao'] ?? 'valor_desc';
$filtro_data_inicio  = $_GET['data_inicio'] ?? '';
$filtro_data_fim     = $_GET['data_fim'] ?? '';
$filtro_responsavel  = $_GET['responsavel'] ?? '';

if (
    isset($_GET['aplicar_filtros']) ||
    $filtro_mes !== 'todos' ||
    $filtro_ano !== 'todos' ||
    $filtro_beneficio !== '' ||
    $filtro_bairro !== '' ||
    $filtro_status !== 'todos' ||
    $filtro_valor_min != 1000 ||
    $filtro_data_inicio !== '' ||
    $filtro_data_fim !== '' ||
    $filtro_responsavel !== ''
) {
    $filtros_aplicados = true;
}

/* Garantir valor mínimo de 1000 */
if ($filtro_valor_min < 1000) {
    $filtro_valor_min = 1000;
}

/* Construir query base */
$sql_base = "
    SELECT 
        ae.id,
        ae.data_entrega,
        ae.hora_entrega,
        ae.quantidade,
        ae.valor_aplicado,
        ae.observacao,
        ae.responsavel AS responsavel_entrega,
        ae.entregue,
        ae.pessoa_cpf,
        
        s.id AS solicitante_id,
        s.nome AS solicitante_nome,
        s.cpf AS solicitante_cpf_formatado,
        s.telefone,
        s.bairro_id,
        s.endereco,
        s.numero,
        s.complemento,
        s.referencia,
        s.total_rendimentos,
        s.renda_familiar,
        
        atp.id AS tipo_id,
        atp.nome AS tipo_nome,
        atp.categoria AS tipo_categoria,
        atp.valor_padrao AS tipo_valor_padrao,
        atp.periodicidade AS tipo_periodicidade
        
    FROM ajudas_entregas ae
    INNER JOIN solicitantes s ON ae.pessoa_id = s.id
    INNER JOIN ajudas_tipos atp ON ae.ajuda_tipo_id = atp.id
    WHERE ae.valor_aplicado >= :valor_min
      AND ae.valor_aplicado IS NOT NULL
";

$params = [':valor_min' => $filtro_valor_min];

/* Aplicar filtros */
if (!empty($filtro_mes) && $filtro_mes !== 'todos') {
    $sql_base .= " AND DATE_FORMAT(ae.data_entrega, '%Y-%m') = :mes";
    $params[':mes'] = $filtro_mes;
}

if (!empty($filtro_ano) && $filtro_ano !== 'todos') {
    $sql_base .= " AND YEAR(ae.data_entrega) = :ano";
    $params[':ano'] = $filtro_ano;
}

if (!empty($filtro_beneficio)) {
    $sql_base .= " AND atp.id = :beneficio";
    $params[':beneficio'] = $filtro_beneficio;
}

if (!empty($filtro_bairro)) {
    $sql_base .= " AND s.bairro_id = :bairro";
    $params[':bairro'] = $filtro_bairro;
}

if ($filtro_status !== 'todos') {
    $sql_base .= " AND ae.entregue = :status";
    $params[':status'] = $filtro_status;
}

if (!empty($filtro_data_inicio)) {
    $sql_base .= " AND ae.data_entrega >= :data_inicio";
    $params[':data_inicio'] = $filtro_data_inicio;
}

if (!empty($filtro_data_fim)) {
    $sql_base .= " AND ae.data_entrega <= :data_fim";
    $params[':data_fim'] = $filtro_data_fim;
}

if (!empty($filtro_responsavel)) {
    $sql_base .= " AND ae.responsavel LIKE :responsavel";
    $params[':responsavel'] = '%' . $filtro_responsavel . '%';
}

/* Aplicar ordenação */
$ordenacoes = [
    'valor_desc' => 'ae.valor_aplicado DESC',
    'valor_asc'  => 'ae.valor_aplicado ASC',
    'data_desc'  => 'ae.data_entrega DESC, ae.hora_entrega DESC',
    'data_asc'   => 'ae.data_entrega ASC, ae.hora_entrega ASC',
    'nome_asc'   => 's.nome ASC'
];

$sql_base .= " ORDER BY " . ($ordenacoes[$ordenacao] ?? 'ae.valor_aplicado DESC');

/* Executar query */
$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$beneficios = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Calcular estatísticas */
$total_valor      = 0.0;
$total_entregas   = count($beneficios);
$total_pessoas    = 0;
$total_quantidade = 0;
$cpfs_unicos      = [];

foreach ($beneficios as $beneficio) {
    $total_valor += (float)$beneficio['valor_aplicado'];
    $total_quantidade += (int)$beneficio['quantidade'];

    if (!in_array($beneficio['pessoa_cpf'], $cpfs_unicos, true)) {
        $cpfs_unicos[] = $beneficio['pessoa_cpf'];
        $total_pessoas++;
    }
}

$classe_total = ($total_valor >= 5000) ? 'valor-alto' : 'valor-cell';

/* Buscar lista de benefícios para filtro */
$sql_tipos = "SELECT id, nome, valor_padrao FROM ajudas_tipos WHERE status = 'Ativa' ORDER BY nome";
$stmt_tipos = $pdo->query($sql_tipos);
$tipos_beneficios = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

/* Buscar lista de bairros */
$sql_bairros = "SELECT id, nome FROM bairros ORDER BY nome";
$stmt_bairros = $pdo->query($sql_bairros);
$bairros = $stmt_bairros->fetchAll(PDO::FETCH_ASSOC);

/* Buscar anos disponíveis */
$sql_anos = "SELECT DISTINCT YEAR(data_entrega) AS ano FROM ajudas_entregas WHERE valor_aplicado >= 1000 ORDER BY ano DESC";
$stmt_anos = $pdo->query($sql_anos);
$anos = $stmt_anos->fetchAll(PDO::FETCH_ASSOC);

/* Buscar lista de responsáveis */
$sql_responsaveis = "SELECT DISTINCT responsavel FROM ajudas_entregas WHERE responsavel IS NOT NULL AND responsavel != '' ORDER BY responsavel";
$stmt_responsaveis = $pdo->query($sql_responsaveis);
$responsaveis = $stmt_responsaveis->fetchAll(PDO::FETCH_ASSOC);

/* Buscar distribuição por mês (para gráfico) */
$sql_dist_mes = "
    SELECT 
        DATE_FORMAT(data_entrega, '%Y-%m') AS mes,
        COUNT(*) AS quantidade,
        SUM(valor_aplicado) AS total_valor
    FROM ajudas_entregas
    WHERE valor_aplicado >= :valor_min_grafico
      AND YEAR(data_entrega) = :ano_atual
    GROUP BY DATE_FORMAT(data_entrega, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12
";
$stmt_dist_mes = $pdo->prepare($sql_dist_mes);
$stmt_dist_mes->execute([
    ':valor_min_grafico' => $filtro_valor_min,
    ':ano_atual' => date('Y')
]);
$distribuicao_mes = $stmt_dist_mes->fetchAll(PDO::FETCH_ASSOC);

/* Preparar dados para gráfico mensal */
$labels_mes = [];
$valores_mes = [];
$quantidades_mes = [];

foreach ($distribuicao_mes as $mes) {
    $data = DateTime::createFromFormat('Y-m', $mes['mes']);
    $labels_mes[] = $data ? $data->format('M/Y') : $mes['mes'];
    $valores_mes[] = (float)$mes['total_valor'];
    $quantidades_mes[] = (int)$mes['quantidade'];
}

if (empty($distribuicao_mes)) {
    for ($i = 11; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-$i months");
        $labels_mes[] = $date->format('M/Y');
        $valores_mes[] = 0;
        $quantidades_mes[] = 0;
    }
}

/* Calcular distribuição por tipo para gráfico */
$dist_tipos = [];
foreach ($beneficios as $beneficio) {
    $tipo_nome = $beneficio['tipo_nome'] ?: 'Sem tipo';
    if (!isset($dist_tipos[$tipo_nome])) {
        $dist_tipos[$tipo_nome] = 0;
    }
    $dist_tipos[$tipo_nome] += (float)$beneficio['valor_aplicado'];
}

arsort($dist_tipos);
$labels_tipos = array_keys($dist_tipos);
$valores_tipos = array_values($dist_tipos);

if (empty($dist_tipos)) {
    $labels_tipos = ['Nenhum registro'];
    $valores_tipos = [1];
}

/* Preparar Top 5 */
$top5_beneficios = [];
if (!empty($beneficios)) {
    $beneficios_top = $beneficios;
    usort($beneficios_top, function ($a, $b) {
        return ((float)$b['valor_aplicado']) <=> ((float)$a['valor_aplicado']);
    });
    $top5_beneficios = array_slice($beneficios_top, 0, 5);
}

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benefícios Acima de R$ 1.000 - ANEXO</title>

    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        body,
        .page-heading,
        .card,
        .table,
        .btn,
        input,
        select,
        textarea,
        .modal-content,
        .form-control,
        .form-select {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif !important;
        }

        body {
            font-size: 14px;
        }

        .card-statistic {
            border-radius: 8px;
            border: 1px solid #e0e0e0 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
            background: white !important;
            color: #333;
            border-left: 4px solid !important;
            margin-bottom: 1rem;
            height: 100%;
        }

        .card-statistic:nth-child(1) {
            border-left-color: #28a745 !important;
        }

        .card-statistic:nth-child(2) {
            border-left-color: #007bff !important;
        }

        .card-statistic:nth-child(3) {
            border-left-color: #ffc107 !important;
        }

        .card-statistic:nth-child(4) {
            border-left-color: #dc3545 !important;
        }

        .statistic-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .card-statistic:nth-child(1) .statistic-icon {
            background-color: rgba(40, 167, 69, 0.1) !important;
            color: #28a745 !important;
        }

        .card-statistic:nth-child(2) .statistic-icon {
            background-color: rgba(0, 123, 255, 0.1) !important;
            color: #007bff !important;
        }

        .card-statistic:nth-child(3) .statistic-icon {
            background-color: rgba(255, 193, 7, 0.1) !important;
            color: #ffc107 !important;
        }

        .card-statistic:nth-child(4) .statistic-icon {
            background-color: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        .valor-destaque {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            line-height: 1.2;
        }

        .valor-cell {
            color: #0d6efd;
            font-weight: 600;
            white-space: nowrap;
        }

        .valor-alto {
            color: #dc3545;
            font-weight: 700;
            white-space: nowrap;
        }

        .text-muted {
            color: #6c757d !important;
        }

        .row.equal-height {
            display: flex;
            flex-wrap: wrap;
        }

        .row.equal-height>[class*="col-"] {
            display: flex;
            flex-direction: column;
        }

        .row.equal-height .card {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .row.equal-height .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .top5-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 250px;
        }

        .top5-scroll {
            flex: 1;
            overflow-y: auto;
            max-height: 100%;
        }

        .top5-scroll::-webkit-scrollbar {
            width: 6px;
        }

        .top5-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .top5-scroll::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .top5-scroll::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .top5-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            min-height: 70px;
        }

        .top5-item:last-child {
            border-bottom: none;
        }

        .empty-state {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #6c757d;
            padding: 1.5rem;
            text-align: center;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: #dee2e6;
        }

        .grafico-container {
            flex: 1;
            display: flex;
            min-height: 180px;
            max-height: 220px;
        }

        .grafico-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .chart-container {
            height: 250px;
            position: relative;
            width: 100%;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .btn-action-group {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
        }

        .filtros-ativos {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }

        .filtros-ativos h6 {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .filtro-badge {
            display: inline-block;
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
        }

        .valor-minimo-container {
            position: relative;
        }

        .valor-minimo-label {
            position: absolute;
            top: 50%;
            left: 10px;
            transform: translateY(-50%);
            background: #fff;
            padding: 0 5px;
            font-size: 12px;
            color: #6c757d;
            z-index: 1;
        }

        .valor-minimo-input {
            padding-left: 70px;
        }

        .badge-filtro {
            background-color: #0d6efd;
            color: white;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }

        .badge-entregue {
            background-color: #28c76f;
        }

        .badge-pendente {
            background-color: #ff9f43;
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .btn-filtro-applied {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }

        .btn-filtro-applied:hover {
            background-color: #218838;
            border-color: #1e7e34;
            color: white;
        }

        .page-heading .row {
            align-items: center;
        }

        .card-height-fixed {
            height: 300px;
            overflow: hidden;
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dt-buttons {
            display: none !important;
        }

        .beneficios-toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .toolbar-search {
            margin-left: auto;
        }

        .dt-search-wrap {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            width: 100%;
        }

        .dt-search-input {
            width: 100%;
            min-width: 360px;
            max-width: 520px;
            height: 38px;
            padding: 0.55rem 0.9rem;
            border: 1px solid #b8c7f7;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            font-size: 14px;
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
            background: #f8f9fa;
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
            background: #eef2ff;
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
            min-width: 96px;
            padding: 0.5rem 1rem;
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

        @media (max-width: 768px) {

            #tabelaBeneficios th:nth-child(3),
            #tabelaBeneficios td:nth-child(3),
            #tabelaBeneficios th:nth-child(9),
            #tabelaBeneficios td:nth-child(9) {
                display: none;
            }

            .valor-destaque {
                font-size: 1.3rem;
            }

            .statistic-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .chart-container {
                height: 200px;
            }

            .grafico-container {
                min-height: 160px;
                max-height: 180px;
            }

            .page-heading .text-end {
                text-align: left !important;
                margin-top: 1rem;
            }

            .page-heading .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .beneficios-toolbar {
                justify-content: stretch;
            }

            .toolbar-search {
                width: 100%;
                margin-left: 0;
            }

            .dt-search-wrap {
                width: 100%;
            }

            .dt-search-input {
                min-width: 0;
                max-width: 100%;
                flex: 1 1 auto;
            }

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

            .custom-pagination-left {
                order: 1;
            }

            .custom-pagination-center {
                order: 2;
            }

            .custom-pagination-right {
                order: 3;
            }

            .card-height-fixed {
                height: 280px;
            }
        }

        @media (max-width: 576px) {
            .page-heading h3 {
                font-size: 1.5rem;
            }

            .card-header h5,
            .card-header h6 {
                font-size: 1rem;
            }

            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }

            .top5-item {
                flex-direction: column;
                align-items: flex-start;
                min-height: auto;
                padding: 0.5rem;
            }

            .top5-item>div {
                width: 100%;
            }

            .top5-item .text-end {
                text-align: left !important;
                margin-top: 0.5rem;
            }

            .filtros-ativos .badge-filtro {
                font-size: 0.65rem;
                margin-bottom: 0.25rem;
            }

            .top5-container {
                min-height: 200px;
            }

            .empty-state {
                padding: 1rem;
            }

            .empty-state i {
                font-size: 1.5rem;
                margin-bottom: 0.5rem;
            }
        }

        @media (max-width: 360px) {
            body {
                font-size: 13px;
            }

            .card-body {
                padding: 0.75rem;
            }

            .valor-destaque {
                font-size: 1.2rem;
            }

            .statistic-icon {
                width: 35px;
                height: 35px;
                font-size: 16px;
                margin-right: 0.5rem;
            }
        }

        @media (min-width: 768px) and (max-width: 1024px) {
            .grafico-container {
                min-height: 200px;
                max-height: 240px;
            }

            .top5-container {
                min-height: 200px;
            }

            .chart-container {
                height: 220px;
            }
        }

        @media (min-width: 1200px) {
            .grafico-container {
                min-height: 220px;
                max-height: 260px;
            }

            .top5-container {
                min-height: 220px;
            }

            .chart-container {
                height: 270px;
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
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
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

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-cash-stack"></i>
                                <span>Controle Financeiro</span>
                            </a>
                            <ul class="submenu active">
                                <li class="submenu-item">
                                    <a href="valoresAplicados.php">Valores Aplicados</a>
                                </li>
                                <li class="submenu-item active">
                                    <a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a>
                                </li>
                            </ul>
                        </li>

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

                        <li class="sidebar-item">
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
                <div class="row align-items-center">
                    <div class="col-12 col-md-8">
                        <h3>Benefícios Acima de R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?></h3>
                        <p class="text-muted mb-2 mb-md-0">Controle e monitoramento de benefícios de alto valor entregues pelo Anexo</p>
                    </div>
                    <div class="col-12 col-md-4 mt-3 mt-lg-0 text-md-end">
                        <div class="d-flex flex-column flex-lg-row gap-2 justify-content-md-end">
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalFiltros">
                                <i class="bi bi-funnel"></i>
                                <?= $filtros_aplicados ? 'Filtros Aplicados' : 'Aplicar Filtros' ?>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalAjuda">
                                <i class="bi bi-question-circle"></i> Ajuda
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($filtros_aplicados): ?>
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="filtros-ativos">
                            <h6 class="mb-2"><i class="bi bi-filter"></i> Filtros Aplicados:</h6>
                            <div class="d-flex flex-wrap">
                                <?php if ($filtro_mes !== 'todos'): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Mês: <?= date('m/Y', strtotime($filtro_mes . '-01')) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('mes')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_ano !== 'todos'): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Ano: <?= htmlspecialchars((string)$filtro_ano) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('ano')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_beneficio): ?>
                                    <?php
                                    $beneficio_nome = '';
                                    foreach ($tipos_beneficios as $tipo) {
                                        if ((string)$tipo['id'] === (string)$filtro_beneficio) {
                                            $beneficio_nome = $tipo['nome'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge-filtro filtro-badge">
                                        Benefício: <?= htmlspecialchars($beneficio_nome) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('beneficio')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_valor_min != 1000): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Valor Mínimo: R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('valor_min')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_status !== 'todos'): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Status: <?= $filtro_status === 'Sim' ? 'Entregue' : 'Pendente' ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('status')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_data_inicio): ?>
                                    <span class="badge-filtro filtro-badge">
                                        De: <?= date('d/m/Y', strtotime($filtro_data_inicio)) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('data_inicio')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_data_fim): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Até: <?= date('d/m/Y', strtotime($filtro_data_fim)) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('data_fim')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_responsavel): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Responsável: <?= htmlspecialchars($filtro_responsavel) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('responsavel')">×</a>
                                    </span>
                                <?php endif; ?>

                                <a href="beneficiosAcimaMil.php" class="btn btn-sm btn-outline-danger align-self-center ms-2">
                                    <i class="bi bi-x-circle"></i> Limpar Todos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="page-content">

                <section class="mb-4">
                    <div class="row g-3">
                        <div class="col-12 col-lg-6 col-md-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Valor Total</h6>
                                            <div class="valor-destaque"><?= formatarMoeda($total_valor) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6 col-md-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Entregas</h6>
                                            <div class="valor-destaque"><?= $total_entregas ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6 col-md-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Pessoas Atendidas</h6>
                                            <div class="valor-destaque"><?= $total_pessoas ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-6 col-md-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-bar-chart-fill"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Valor Médio</h6>
                                            <div class="valor-destaque">
                                                <?= $total_entregas > 0 ? formatarMoeda($total_valor / $total_entregas) : 'R$ 0,00' ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Distribuição Mensal (<?= date('Y') ?>)</h5>
                            </div>
                            <div class="card-body p-2 p-md-3">
                                <div class="chart-container">
                                    <canvas id="graficoMensal"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <h5 class="mb-2 mb-md-0">Detalhamento das Entregas (Valor mínimo: R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?>)</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button id="btnExcel" class="btn btn-success btn-sm flex-grow-1 flex-md-grow-0">
                                        <i class="bi bi-file-earmark-excel"></i> <span>Excel</span>
                                    </button>
                                    <button id="btnPDF" class="btn btn-danger btn-sm flex-grow-1 flex-md-grow-0">
                                        <i class="bi bi-file-earmark-pdf"></i> <span>PDF</span>
                                    </button>
                                </div>
                            </div>

                            <div class="card-body">
                                <?php if (empty($beneficios)): ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                        <h5 class="mt-3">Nenhum benefício encontrado</h5>
                                        <p class="text-muted mb-0">
                                            Não há registros de benefícios acima de R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?> com os filtros aplicados.
                                        </p>
                                    </div>
                                <?php else: ?>

                                    <div class="beneficios-toolbar">
                                        <div class="toolbar-search">
                                            <div class="dt-search-wrap">
                                                <input type="text" id="pesquisaBeneficios" class="dt-search-input" placeholder="Buscar por nome/CPF/telefone/benefício/responsável...">
                                                <button type="button" class="dt-search-clear" id="btnClearSearchBeneficios" title="Limpar pesquisa" aria-label="Limpar pesquisa">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="tabelaBeneficios" class="table table-hover table-striped w-100">
                                            <thead>
                                                <tr>
                                                    <th>Data/Hora</th>
                                                    <th>Beneficiário</th>
                                                    <th class="d-none d-md-table-cell">CPF</th>
                                                    <th>Benefício</th>
                                                    <th>Qtd</th>
                                                    <th>Valor Unit.</th>
                                                    <th>Valor Total</th>
                                                    <th>Status</th>
                                                    <th class="d-none d-md-table-cell">Responsável</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($beneficios as $beneficio): ?>
                                                    <?php
                                                    $quantidade = (int)$beneficio['quantidade'];
                                                    $valor_aplicado = (float)$beneficio['valor_aplicado'];
                                                    $valor_unitario = $quantidade > 0 ? ($valor_aplicado / $quantidade) : 0;
                                                    $classe_valor = ($valor_aplicado >= 5000) ? 'valor-alto' : 'valor-cell';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div><?= date('d/m/Y', strtotime($beneficio['data_entrega'])) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars(substr((string)$beneficio['hora_entrega'], 0, 5)) ?></small>
                                                        </td>
                                                        <td>
                                                            <strong class="d-block"><?= htmlspecialchars((string)$beneficio['solicitante_nome']) ?></strong>
                                                            <?php if (!empty($beneficio['telefone'])): ?>
                                                                <small class="text-muted"><?= htmlspecialchars((string)$beneficio['telefone']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?= formatarCPF($beneficio['pessoa_cpf']) ?></td>
                                                        <td>
                                                            <span class="badge bg-primary d-inline-block text-truncate" style="max-width: 120px;">
                                                                <?= htmlspecialchars((string)$beneficio['tipo_nome']) ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center"><?= $quantidade ?></td>
                                                        <td class="<?= $classe_valor ?>"><?= formatarMoeda($valor_unitario) ?></td>
                                                        <td class="<?= $classe_valor ?>">
                                                            <strong><?= formatarMoeda($valor_aplicado) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if (($beneficio['entregue'] ?? '') === 'Sim'): ?>
                                                                <span class="badge badge-entregue bg-success">Entregue</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-pendente bg-warning text-dark">Pendente</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?= htmlspecialchars((string)($beneficio['responsavel_entrega'] ?? 'N/A')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th class="text-start">Total Geral:</th>
                                                    <th></th>
                                                    <th></th>
                                                    <th class="text-center"><?= count($beneficios) ?> reg.</th>
                                                    <th class="text-center"><?= (int)$total_quantidade ?></th>
                                                    <th></th>
                                                    <th class="<?= $classe_total ?> text-center">
                                                        <strong><?= formatarMoeda($total_valor) ?></strong>
                                                    </th>
                                                    <th></th>
                                                    <th></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div id="customPaginationBeneficios" class="custom-pagination-bar"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row equal-height">
                    <div class="col-12 col-lg-6 mb-3 mb-lg-0">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Top 5 Benefícios Mais Caros</h6>
                            </div>
                            <div class="card-body p-0 d-flex flex-column">
                                <div class="top5-container">
                                    <div class="top5-scroll">
                                        <?php if (empty($top5_beneficios)): ?>
                                            <div class="empty-state">
                                                <i class="bi bi-inbox"></i>
                                                <p class="mb-0">Nenhum benefício encontrado</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($top5_beneficios as $index => $item): ?>
                                                <div class="top5-item">
                                                    <div class="flex-grow-1">
                                                        <strong><?= $index + 1 ?>. <?= htmlspecialchars((string)$item['solicitante_nome']) ?></strong>
                                                        <div class="small text-muted">
                                                            <?= htmlspecialchars((string)$item['tipo_nome']) ?> •
                                                            <?= date('d/m/Y', strtotime($item['data_entrega'])) ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-end flex-shrink-0">
                                                        <div class="valor-destaque text-danger"><?= formatarMoeda($item['valor_aplicado']) ?></div>
                                                        <small class="text-muted"><?= (int)$item['quantidade'] ?> un.</small>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card h-100 card-height-fixed">
                            <div class="card-header">
                                <h6 class="mb-0">Distribuição por Tipo de Benefício</h6>
                            </div>
                            <div class="card-body d-flex flex-column p-2 p-md-3">
                                <div class="grafico-container">
                                    <canvas id="graficoDistribuicao"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer class="mt-4">
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start text-black">
                        <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
                        <script>
                            document.getElementById('current-year').textContent = new Date().getFullYear();
                        </script>
                    </div>
                    <div class="float-end text-black">
                        <p class="mb-0">Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <div class="modal fade" id="modalFiltros" tabindex="-1" aria-labelledby="modalFiltrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalFiltrosLabel">
                        <i class="bi bi-funnel-fill"></i> Filtros Personalizados
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <form method="GET" action="">
                    <div class="modal-body">
                        <input type="hidden" name="aplicar_filtros" value="1">

                        <div class="row g-3">
                            <div class="col-12 col-lg-6">
                                <div class="filtro-item">
                                    <label class="form-label fw-bold">Período</label>
                                    <div class="row g-2">
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Data Início</label>
                                            <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($filtro_data_inicio) ?>">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Data Fim</label>
                                            <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($filtro_data_fim) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Mês/Ano</label>
                                    <div class="row g-2">
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Mês</label>
                                            <select name="mes" class="form-select">
                                                <option value="todos">Todos os meses</option>
                                                <?php
                                                $meses = [
                                                    '01' => 'Janeiro',
                                                    '02' => 'Fevereiro',
                                                    '03' => 'Março',
                                                    '04' => 'Abril',
                                                    '05' => 'Maio',
                                                    '06' => 'Junho',
                                                    '07' => 'Julho',
                                                    '08' => 'Agosto',
                                                    '09' => 'Setembro',
                                                    '10' => 'Outubro',
                                                    '11' => 'Novembro',
                                                    '12' => 'Dezembro'
                                                ];

                                                foreach ($meses as $num => $nome) {
                                                    $valor = date('Y') . '-' . $num;
                                                    $selected = ($filtro_mes === $valor) ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($valor) . "\" $selected>" . htmlspecialchars($nome) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Ano</label>
                                            <select name="ano" class="form-select">
                                                <option value="todos">Todos os anos</option>
                                                <?php foreach ($anos as $ano): ?>
                                                    <option value="<?= htmlspecialchars((string)$ano['ano']) ?>" <?= ($filtro_ano == $ano['ano']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars((string)$ano['ano']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Valor</label>
                                    <div class="valor-minimo-container">
                                        <div class="valor-minimo-label">R$</div>
                                        <input type="number" name="valor_min" class="form-control valor-minimo-input"
                                            value="<?= htmlspecialchars((string)$filtro_valor_min) ?>" min="1000" step="100" required>
                                        <div class="form-text">Valor mínimo para exibição (mínimo: R$ 1.000)</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 col-lg-6">
                                <div class="filtro-item">
                                    <label class="form-label fw-bold">Benefício</label>
                                    <select name="beneficio" class="form-select">
                                        <option value="">Todos os benefícios</option>
                                        <?php foreach ($tipos_beneficios as $tipo): ?>
                                            <option value="<?= (int)$tipo['id'] ?>" <?= ((string)$filtro_beneficio === (string)$tipo['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$tipo['nome']) ?>
                                                <?php if (!empty($tipo['valor_padrao'])): ?>
                                                    (R$ <?= number_format((float)$tipo['valor_padrao'], 2, ',', '.') ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="todos">Todos os status</option>
                                        <option value="Sim" <?= ($filtro_status === 'Sim') ? 'selected' : '' ?>>Entregue</option>
                                        <option value="Não" <?= ($filtro_status === 'Não') ? 'selected' : '' ?>>Pendente</option>
                                    </select>
                                </div>

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Responsável</label>
                                    <select name="responsavel" class="form-select">
                                        <option value="">Todos os responsáveis</option>
                                        <?php foreach ($responsaveis as $resp): ?>
                                            <option value="<?= htmlspecialchars((string)$resp['responsavel']) ?>" <?= ($filtro_responsavel === $resp['responsavel']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string)$resp['responsavel']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Ordenação</label>
                                    <select name="ordenacao" class="form-select">
                                        <option value="valor_desc" <?= ($ordenacao === 'valor_desc') ? 'selected' : '' ?>>Maior Valor</option>
                                        <option value="valor_asc" <?= ($ordenacao === 'valor_asc') ? 'selected' : '' ?>>Menor Valor</option>
                                        <option value="data_desc" <?= ($ordenacao === 'data_desc') ? 'selected' : '' ?>>Data Mais Recente</option>
                                        <option value="data_asc" <?= ($ordenacao === 'data_asc') ? 'selected' : '' ?>>Data Mais Antiga</option>
                                        <option value="nome_asc" <?= ($ordenacao === 'nome_asc') ? 'selected' : '' ?>>Nome A-Z</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Dica:</strong> Combine múltiplos filtros para resultados mais precisos. Use datas específicas para períodos customizados.
                        </div>
                    </div>

                    <div class="modal-footer">
                        <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end w-100">
                            <button type="submit" class="btn btn-primary col-12 col-md-auto">
                                <i class="bi bi-check-circle"></i> Aplicar Filtros
                            </button>
                            <a href="beneficiosAcimaMil.php" class="btn btn-outline-danger col-12 col-md-auto">
                                <i class="bi bi-x-circle"></i> Limpar Filtros
                            </a>
                            <button type="button" class="btn btn-outline-secondary col-12 col-md-auto" data-bs-dismiss="modal">
                                <i class="bi bi-x-lg"></i> Fechar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalAjuda" tabindex="-1" aria-labelledby="modalAjudaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAjudaLabel">
                        <i class="bi bi-question-circle text-primary"></i> Ajuda - Benefícios Acima de R$ 1.000
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Informações sobre esta página:</h6>
                        <p class="mb-0">Esta página exibe <strong>apenas benefícios com valor igual ou superior a R$ 1.000,00</strong> (valor ajustável), permitindo um controle mais rigoroso sobre os recursos de alto valor.</p>
                    </div>

                    <h6>Funcionalidades Disponíveis:</h6>
                    <ul>
                        <li><strong>Filtros Personalizados:</strong> Modal com filtros avançados por data, tipo, status, responsável, etc.</li>
                        <li><strong>Ordenação:</strong> Ordene por valor, data ou nome do beneficiário</li>
                        <li><strong>Gráficos:</strong> Visualize a distribuição mensal e por tipo de benefício</li>
                        <li><strong>Exportação:</strong> Exporte os dados para Excel ou PDF</li>
                        <li><strong>Estatísticas:</strong> Veja totais, médias e distribuições em tempo real</li>
                        <li><strong>Top 5:</strong> Lista dos 5 benefícios mais caros do período</li>
                        <li><strong>Filtros Ativos:</strong> Visualização dos filtros aplicados com opção de remoção individual</li>
                    </ul>

                    <h6>Como usar os filtros:</h6>
                    <ol>
                        <li>Clique em "Aplicar Filtros" para abrir o modal</li>
                        <li>Selecione os critérios desejados</li>
                        <li>Clique em "Aplicar Filtros" para confirmar</li>
                        <li>Use "Limpar Filtros" para voltar ao estado inicial</li>
                        <li>Remova filtros individuais clicando no × nos badges</li>
                    </ol>

                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle"></i> Aviso Importante:</h6>
                        <p class="mb-0">Esta página contém informações sensíveis. Certifique-se de manter a confidencialidade dos dados e utilizar apenas para fins oficiais.</p>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
        let tabelaBeneficios = null;

        function renderCustomPaginationBeneficios() {
            if (!tabelaBeneficios) return;

            const info = tabelaBeneficios.page.info();
            const currentPage = info.pages > 0 ? (info.page + 1) : 1;
            const totalPages = info.pages > 0 ? info.pages : 1;
            const currentLength = info.length;

            const html = `
                <div class="custom-pagination-left">
                    <button type="button" class="custom-page-btn" id="btnPrevPageBeneficios" ${info.page <= 0 ? 'disabled' : ''}>
                        Anterior
                    </button>
                    <button type="button" class="custom-page-btn" id="btnNextPageBeneficios" ${info.page >= (info.pages - 1) ? 'disabled' : ''}>
                        Próximo
                    </button>
                </div>

                <div class="custom-pagination-center">
                    <span class="custom-page-info">Página ${currentPage} de ${totalPages}</span>
                </div>

                <div class="custom-pagination-right">
                    <label for="customPageLengthBeneficios" class="custom-length-label">por página</label>
                    <select id="customPageLengthBeneficios" class="custom-length-select">
                        <option value="10" ${currentLength === 10 ? 'selected' : ''}>10</option>
                        <option value="25" ${currentLength === 25 ? 'selected' : ''}>25</option>
                        <option value="50" ${currentLength === 50 ? 'selected' : ''}>50</option>
                        <option value="100" ${currentLength === 100 ? 'selected' : ''}>100</option>
                    </select>
                </div>
            `;

            $('#customPaginationBeneficios').html(html);
        }

        $(document).ready(function() {
            if ($('#tabelaBeneficios').length) {
                tabelaBeneficios = $('#tabelaBeneficios').DataTable({
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
                        decimal: ',',
                        thousands: '.'
                    },
                    pageLength: 10,
                    lengthMenu: [
                        [10, 25, 50, 100],
                        [10, 25, 50, 100]
                    ],
                    dom: 'Brt',
                    buttons: [{
                            extend: 'excelHtml5',
                            text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                            className: 'btn btn-success btn-sm d-none',
                            title: null,
                            filename: 'beneficios_acima_mil_<?= date('Y-m-d') ?>',
                            footer: true,
                            exportOptions: {
                                columns: ':visible',
                                stripHtml: true,
                                format: {
                                    header: function(data) {
                                        return $('<div>').html(data).text()
                                            .replace(/\u00A0/g, ' ')
                                            .replace(/\s+/g, ' ')
                                            .trim();
                                    },
                                    body: function(data, row, column) {
                                        const conteudo = $('<div>').html(data);

                                        if (column === 1) {
                                            const nome = conteudo.find('strong').first().text()
                                                .replace(/\u00A0/g, ' ')
                                                .replace(/\s+/g, ' ')
                                                .trim();

                                            const telefone = conteudo.find('small').first().text()
                                                .replace(/\u00A0/g, ' ')
                                                .replace(/\s+/g, ' ')
                                                .trim();

                                            if (nome || telefone) {
                                                return telefone ? nome + '\n' + telefone : nome;
                                            }
                                        }

                                        let texto = conteudo.text()
                                            .replace(/\u00A0/g, ' ')
                                            .replace(/\s+/g, ' ')
                                            .trim();

                                        if (column === 0) {
                                            const partes = texto.match(/^(\d{2}\/\d{2}\/\d{4})\s*(\d{2}:\d{2})?$/);
                                            if (partes) {
                                                texto = partes[1] + (partes[2] ? ' às ' + partes[2] : '');
                                            }
                                        }

                                        return texto;
                                    },
                                    footer: function(data, row, column) {
                                        if (column === 0) {
                                            return 'Total Geral:';
                                        }
                                        if (column === 3) {
                                            return <?= json_encode($total_entregas . ' reg.', JSON_UNESCAPED_UNICODE) ?>;
                                        }
                                        if (column === 4) {
                                            return <?= json_encode((string)$total_quantidade, JSON_UNESCAPED_UNICODE) ?>;
                                        }
                                        if (column === 6) {
                                            return <?= json_encode(formatarMoeda($total_valor), JSON_UNESCAPED_UNICODE) ?>;
                                        }
                                        return '';
                                    }
                                }
                            },
                            customize: function(xlsx) {
                                const sheet = xlsx.xl.worksheets['sheet1.xml'];
                                const styles = xlsx.xl['styles.xml'];
                                const sheetData = $('sheetData', sheet)[0];

                                const tituloTexto = <?= json_encode('BENEFÍCIOS ACIMA DE R$ ' . number_format($filtro_valor_min, 2, ',', '.') . ' - ANEXO', JSON_UNESCAPED_UNICODE) ?>;
                                const resumoTexto = <?= json_encode(
                                    'Total: ' . $total_entregas . ' entregas | Quantidade: ' . $total_quantidade .
                                    ' | Pessoas atendidas: ' . $total_pessoas .
                                    ' | Valor total: ' . formatarMoeda($total_valor) .
                                    ' | Valor médio: ' . ($total_entregas > 0 ? formatarMoeda($total_valor / $total_entregas) : 'R$ 0,00'),
                                    JSON_UNESCAPED_UNICODE
                                ) ?>;

                                const filtros = [];
                                <?php if ($filtro_mes !== 'todos'): ?>
                                    filtros.push(<?= json_encode('Mês: ' . date('m/Y', strtotime($filtro_mes . '-01')), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_ano !== 'todos'): ?>
                                    filtros.push(<?= json_encode('Ano: ' . $filtro_ano, JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_beneficio): ?>
                                    filtros.push(<?= json_encode('Benefício: ' . ($beneficio_nome ?? 'Selecionado'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_bairro): ?>
                                    <?php
                                    $bairro_nome_exportacao = '';
                                    foreach ($bairros as $bairro_item_exportacao) {
                                        if ((string)$bairro_item_exportacao['id'] === (string)$filtro_bairro) {
                                            $bairro_nome_exportacao = (string)$bairro_item_exportacao['nome'];
                                            break;
                                        }
                                    }
                                    ?>
                                    filtros.push(<?= json_encode('Bairro: ' . ($bairro_nome_exportacao !== '' ? $bairro_nome_exportacao : 'Selecionado'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_status !== 'todos'): ?>
                                    filtros.push(<?= json_encode('Status: ' . ($filtro_status === 'Sim' ? 'Entregue' : 'Pendente'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_valor_min != 1000): ?>
                                    filtros.push(<?= json_encode('Valor mínimo: R$ ' . number_format($filtro_valor_min, 2, ',', '.'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php else: ?>
                                    filtros.push(<?= json_encode('Valor mínimo: R$ 1.000,00', JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_data_inicio): ?>
                                    filtros.push(<?= json_encode('Data inicial: ' . date('d/m/Y', strtotime($filtro_data_inicio)), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_data_fim): ?>
                                    filtros.push(<?= json_encode('Data final: ' . date('d/m/Y', strtotime($filtro_data_fim)), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_responsavel): ?>
                                    filtros.push(<?= json_encode('Responsável: ' . $filtro_responsavel, JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                filtros.push(<?= json_encode(
                                    'Ordenação: ' .
                                    ($ordenacao === 'valor_desc' ? 'Maior valor' :
                                    ($ordenacao === 'valor_asc' ? 'Menor valor' :
                                    ($ordenacao === 'data_desc' ? 'Data mais recente' :
                                    ($ordenacao === 'data_asc' ? 'Data mais antiga' : 'Nome A-Z')))),
                                    JSON_UNESCAPED_UNICODE
                                ) ?>);

                                const pesquisaAtual = tabelaBeneficios ? tabelaBeneficios.search().trim() : '';
                                if (pesquisaAtual) {
                                    filtros.push('Pesquisa: ' + pesquisaAtual);
                                }

                                const filtrosTexto = 'Filtros: ' + (filtros.length ? filtros.join(' | ') : 'Nenhum filtro aplicado');
                                const agora = new Date();
                                const geradoTexto = 'Gerado em: ' +
                                    agora.toLocaleDateString('pt-BR') + ' ' +
                                    agora.toLocaleTimeString('pt-BR');

                                function appendXml(parent, xml) {
                                    const parsed = $.parseXML('<root>' + xml + '</root>');
                                    $(parsed).find('root').children().each(function() {
                                        parent.append(this);
                                    });
                                }

                                function addFont(options) {
                                    const fonts = $('fonts', styles);
                                    const id = $('font', fonts).length;

                                    appendXml(fonts,
                                        '<font>' +
                                            (options.bold ? '<b/>' : '') +
                                            '<sz val="' + (options.size || 11) + '"/>' +
                                            '<color rgb="' + (options.color || 'FF000000') + '"/>' +
                                            '<name val="Calibri"/>' +
                                            '<family val="2"/>' +
                                        '</font>'
                                    );

                                    fonts.attr('count', $('font', fonts).length);
                                    return id;
                                }

                                function addFill(color) {
                                    const fills = $('fills', styles);
                                    const id = $('fill', fills).length;

                                    appendXml(fills,
                                        '<fill><patternFill patternType="solid">' +
                                            '<fgColor rgb="' + color + '"/>' +
                                            '<bgColor indexed="64"/>' +
                                        '</patternFill></fill>'
                                    );

                                    fills.attr('count', $('fill', fills).length);
                                    return id;
                                }

                                function addBorder() {
                                    const borders = $('borders', styles);
                                    const id = $('border', borders).length;

                                    appendXml(borders,
                                        '<border>' +
                                            '<left style="thin"><color rgb="FF000000"/></left>' +
                                            '<right style="thin"><color rgb="FF000000"/></right>' +
                                            '<top style="thin"><color rgb="FF000000"/></top>' +
                                            '<bottom style="thin"><color rgb="FF000000"/></bottom>' +
                                            '<diagonal/>' +
                                        '</border>'
                                    );

                                    borders.attr('count', $('border', borders).length);
                                    return id;
                                }

                                function addStyle(options) {
                                    const cellXfs = $('cellXfs', styles);
                                    const id = $('xf', cellXfs).length;

                                    appendXml(cellXfs,
                                        '<xf numFmtId="0" fontId="' + (options.fontId || 0) +
                                        '" fillId="' + (options.fillId || 0) +
                                        '" borderId="' + (options.borderId || 0) +
                                        '" xfId="0"' +
                                            (options.fontId ? ' applyFont="1"' : '') +
                                            (options.fillId ? ' applyFill="1"' : '') +
                                            (options.borderId ? ' applyBorder="1"' : '') +
                                            ' applyAlignment="1">' +
                                            '<alignment horizontal="' + (options.horizontal || 'center') +
                                            '" vertical="center" wrapText="1"/>' +
                                        '</xf>'
                                    );

                                    cellXfs.attr('count', $('xf', cellXfs).length);
                                    return id;
                                }

                                const fontTitle = addFont({ bold: true, size: 16 });
                                const fontMeta = addFont({ bold: true, size: 12 });
                                const fontHeader = addFont({ bold: true, size: 12 });

                                const grayFill = addFill('FFF2F4F7');
                                const blackBorder = addBorder();

                                const styleTitle = addStyle({
                                    fontId: fontTitle,
                                    fillId: grayFill,
                                    borderId: blackBorder,
                                    horizontal: 'center'
                                });
                                const styleMeta = addStyle({
                                    fontId: fontMeta,
                                    borderId: blackBorder,
                                    horizontal: 'left'
                                });
                                const styleHeader = addStyle({
                                    fontId: fontHeader,
                                    fillId: grayFill,
                                    borderId: blackBorder,
                                    horizontal: 'center'
                                });
                                const styleCenter = addStyle({
                                    borderId: blackBorder,
                                    horizontal: 'center'
                                });
                                const styleLeft = addStyle({
                                    borderId: blackBorder,
                                    horizontal: 'left'
                                });

                                function columnName(index) {
                                    let name = '';
                                    index++;

                                    while (index > 0) {
                                        const remainder = (index - 1) % 26;
                                        name = String.fromCharCode(65 + remainder) + name;
                                        index = Math.floor((index - 1) / 26);
                                    }

                                    return name;
                                }

                                function shiftCellReference(reference, offset) {
                                    return String(reference || '').replace(/([A-Z]+)(\d+)/, function(match, col, row) {
                                        return col + (parseInt(row, 10) + offset);
                                    });
                                }

                                const existingRows = $('row', sheet).toArray();

                                existingRows.forEach(function(row) {
                                    const newRowNumber = parseInt($(row).attr('r'), 10) + 4;
                                    $(row).attr('r', newRowNumber);

                                    $('c', row).each(function() {
                                        $(this).attr('r', shiftCellReference($(this).attr('r'), 4));
                                    });
                                });

                                $('mergeCells mergeCell', sheet).each(function() {
                                    const ref = $(this).attr('ref');
                                    if (!ref) return;

                                    const parts = ref.split(':');
                                    $(this).attr(
                                        'ref',
                                        parts.map(function(part) {
                                            return shiftCellReference(part, 4);
                                        }).join(':')
                                    );
                                });

                                function xmlEscape(value) {
                                    return String(value)
                                        .replace(/&/g, '&amp;')
                                        .replace(/</g, '&lt;')
                                        .replace(/>/g, '&gt;')
                                        .replace(/"/g, '&quot;')
                                        .replace(/'/g, '&apos;');
                                }

                                function createMergedRow(rowNumber, text, styleId) {
                                    let cells = '';

                                    for (let columnIndex = 0; columnIndex < 9; columnIndex++) {
                                        const column = columnName(columnIndex);

                                        if (columnIndex === 0) {
                                            cells += '<c r="' + column + rowNumber + '" t="inlineStr" s="' + styleId + '">' +
                                                '<is><t xml:space="preserve">' + xmlEscape(text) + '</t></is>' +
                                            '</c>';
                                        } else {
                                            cells += '<c r="' + column + rowNumber + '" t="inlineStr" s="' + styleId + '">' +
                                                '<is><t xml:space="preserve"></t></is>' +
                                            '</c>';
                                        }
                                    }

                                    return '<row r="' + rowNumber + '" ht="' + (rowNumber === 1 ? 26 : 21) + '" customHeight="1">' +
                                        cells +
                                    '</row>';
                                }

                                const temp = $.parseXML(
                                    '<root>' +
                                        createMergedRow(1, tituloTexto, styleTitle) +
                                        createMergedRow(2, resumoTexto, styleMeta) +
                                        createMergedRow(3, filtrosTexto, styleMeta) +
                                        createMergedRow(4, geradoTexto, styleMeta) +
                                    '</root>'
                                );

                                const firstRow = sheetData.firstChild;
                                $($(temp).find('row').get()).each(function() {
                                    sheetData.insertBefore(this, firstRow);
                                });

                                let mergeCells = $('mergeCells', sheet);
                                if (!mergeCells.length) {
                                    const mergeNode = $.parseXML('<mergeCells count="0"></mergeCells>').documentElement;
                                    $('worksheet', sheet)[0].appendChild(mergeNode);
                                    mergeCells = $('mergeCells', sheet);
                                }

                                ['A1:I1', 'A2:I2', 'A3:I3', 'A4:I4'].forEach(function(ref) {
                                    const mergeCell = $.parseXML('<mergeCell ref="' + ref + '"/>').documentElement;
                                    mergeCells[0].appendChild(mergeCell);
                                });
                                mergeCells.attr('count', $('mergeCell', mergeCells).length);

                                $('row', sheet).each(function() {
                                    const rowNumber = parseInt($(this).attr('r'), 10);

                                    $('c', this).each(function() {
                                        const ref = $(this).attr('r') || '';
                                        const col = ref.replace(/[0-9]/g, '');

                                        if (rowNumber === 5) {
                                            $(this).attr('s', styleHeader);
                                        } else if (rowNumber > 5) {
                                            if (col === 'B') {
                                                $(this).attr('s', styleLeft);
                                            } else {
                                                $(this).attr('s', styleCenter);
                                            }
                                        }
                                    });
                                });

                                $('cols', sheet).remove();

                                const colsXml =
                                    '<cols>' +
                                        '<col min="1" max="1" width="19" customWidth="1"/>' +
                                        '<col min="2" max="2" width="34" customWidth="1"/>' +
                                        '<col min="3" max="3" width="18" customWidth="1"/>' +
                                        '<col min="4" max="4" width="28" customWidth="1"/>' +
                                        '<col min="5" max="5" width="10" customWidth="1"/>' +
                                        '<col min="6" max="7" width="17" customWidth="1"/>' +
                                        '<col min="8" max="8" width="14" customWidth="1"/>' +
                                        '<col min="9" max="9" width="27" customWidth="1"/>' +
                                    '</cols>';

                                const colsNode = $.parseXML(colsXml).documentElement;
                                const worksheet = $('worksheet', sheet)[0];
                                worksheet.insertBefore(colsNode, sheetData);

                                $('dimension', sheet).attr('ref', 'A1:I' + $('row', sheet).length);
                                $('autoFilter', sheet).remove();
                            }
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                            className: 'btn btn-danger btn-sm d-none',
                            title: null,
                            filename: 'beneficios_acima_mil_<?= date('Y-m-d') ?>',
                            footer: true,
                            exportOptions: {
                                columns: ':visible',
                                stripHtml: true,
                                format: {
                                    header: function(data) {
                                        return $('<div>').html(data).text()
                                            .replace(/\u00A0/g, ' ')
                                            .replace(/\s+/g, ' ')
                                            .trim();
                                    },
                                    body: function(data) {
                                        return $('<div>').html(data).text()
                                            .replace(/\u00A0/g, ' ')
                                            .replace(/\s+/g, ' ')
                                            .trim();
                                    },
                                    footer: function(data) {
                                        return $('<div>').html(data).text()
                                            .replace(/\u00A0/g, ' ')
                                            .replace(/\s+/g, ' ')
                                            .trim();
                                    }
                                }
                            },
                            customize: function(doc) {
                                const tituloTexto = <?= json_encode('BENEFÍCIOS ACIMA DE R$ ' . number_format($filtro_valor_min, 2, ',', '.') . ' - ANEXO', JSON_UNESCAPED_UNICODE) ?>;
                                const resumoTexto = <?= json_encode(
                                    'Total: ' . $total_entregas . ' entregas | Quantidade: ' . $total_quantidade .
                                    ' | Pessoas atendidas: ' . $total_pessoas .
                                    ' | Valor total: ' . formatarMoeda($total_valor) .
                                    ' | Valor médio: ' . ($total_entregas > 0 ? formatarMoeda($total_valor / $total_entregas) : 'R$ 0,00'),
                                    JSON_UNESCAPED_UNICODE
                                ) ?>;

                                const filtros = [];
                                <?php if ($filtro_mes !== 'todos'): ?>
                                    filtros.push(<?= json_encode('Mês: ' . date('m/Y', strtotime($filtro_mes . '-01')), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_ano !== 'todos'): ?>
                                    filtros.push(<?= json_encode('Ano: ' . $filtro_ano, JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_beneficio): ?>
                                    filtros.push(<?= json_encode('Benefício: ' . ($beneficio_nome ?? 'Selecionado'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_bairro): ?>
                                    filtros.push(<?= json_encode('Bairro selecionado', JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_status !== 'todos'): ?>
                                    filtros.push(<?= json_encode('Status: ' . ($filtro_status === 'Sim' ? 'Entregue' : 'Pendente'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                filtros.push(<?= json_encode('Valor mínimo: R$ ' . number_format($filtro_valor_min, 2, ',', '.'), JSON_UNESCAPED_UNICODE) ?>);
                                <?php if ($filtro_data_inicio): ?>
                                    filtros.push(<?= json_encode('Data inicial: ' . date('d/m/Y', strtotime($filtro_data_inicio)), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_data_fim): ?>
                                    filtros.push(<?= json_encode('Data final: ' . date('d/m/Y', strtotime($filtro_data_fim)), JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                <?php if ($filtro_responsavel): ?>
                                    filtros.push(<?= json_encode('Responsável: ' . $filtro_responsavel, JSON_UNESCAPED_UNICODE) ?>);
                                <?php endif; ?>
                                filtros.push(<?= json_encode(
                                    'Ordenação: ' .
                                    ($ordenacao === 'valor_desc' ? 'Maior valor' :
                                    ($ordenacao === 'valor_asc' ? 'Menor valor' :
                                    ($ordenacao === 'data_desc' ? 'Data mais recente' :
                                    ($ordenacao === 'data_asc' ? 'Data mais antiga' : 'Nome A-Z')))),
                                    JSON_UNESCAPED_UNICODE
                                ) ?>);

                                const pesquisaAtual = tabelaBeneficios ? tabelaBeneficios.search().trim() : '';
                                if (pesquisaAtual) {
                                    filtros.push('Pesquisa: ' + pesquisaAtual);
                                }

                                const filtrosTexto = 'Filtros: ' + (filtros.length ? filtros.join(' | ') : 'Nenhum filtro aplicado');
                                const agora = new Date();
                                const geradoTexto = 'Gerado em: ' +
                                    agora.toLocaleDateString('pt-BR') + ' ' +
                                    agora.toLocaleTimeString('pt-BR');

                                const tableIndex = doc.content.findIndex(function(item) {
                                    return item && item.table;
                                });

                                const cabecalho = {
                                    margin: [0, 0, 0, 10],
                                    table: {
                                        widths: ['*'],
                                        body: [
                                            [{
                                                text: tituloTexto,
                                                alignment: 'center',
                                                bold: true,
                                                fontSize: 16,
                                                color: '#FFFFFF',
                                                fillColor: '#435EBE',
                                                margin: [6, 8, 6, 8]
                                            }],
                                            [{
                                                text: resumoTexto,
                                                alignment: 'left',
                                                bold: true,
                                                fontSize: 10,
                                                color: '#000000',
                                                fillColor: '#F2F4F7',
                                                margin: [5, 5, 5, 5]
                                            }],
                                            [{
                                                text: filtrosTexto,
                                                alignment: 'left',
                                                bold: true,
                                                fontSize: 9,
                                                color: '#000000',
                                                fillColor: '#FFFFFF',
                                                margin: [5, 5, 5, 5]
                                            }],
                                            [{
                                                text: geradoTexto,
                                                alignment: 'left',
                                                bold: true,
                                                fontSize: 9,
                                                color: '#000000',
                                                fillColor: '#FFFFFF',
                                                margin: [5, 5, 5, 5]
                                            }]
                                        ]
                                    },
                                    layout: {
                                        hLineWidth: function() { return 0.7; },
                                        vLineWidth: function() { return 0.7; },
                                        hLineColor: function() { return '#000000'; },
                                        vLineColor: function() { return '#000000'; },
                                        paddingLeft: function() { return 0; },
                                        paddingRight: function() { return 0; },
                                        paddingTop: function() { return 0; },
                                        paddingBottom: function() { return 0; }
                                    }
                                };

                                if (tableIndex >= 0) {
                                    doc.content.splice(tableIndex, 0, cabecalho);
                                    const tabela = doc.content[tableIndex + 1];

                                    tabela.table.widths = ['10%', '18%', '12%', '15%', '6%', '11%', '11%', '8%', '9%'];

                                    tabela.table.body.forEach(function(row, rowIndex) {
                                        row.forEach(function(cell, colIndex) {
                                            if (typeof cell !== 'object') {
                                                row[colIndex] = { text: String(cell ?? '') };
                                                cell = row[colIndex];
                                            }

                                            cell.margin = [3, 4, 3, 4];
                                            cell.fontSize = rowIndex === 0 ? 9 : 8;
                                            cell.alignment = colIndex === 1 ? 'left' : 'center';

                                            if (rowIndex === 0) {
                                                cell.fillColor = '#F2F4F7';
                                                cell.color = '#000000';
                                                cell.bold = true;
                                                cell.alignment = 'center';
                                            }
                                        });
                                    });

                                    tabela.layout = {
                                        hLineWidth: function() { return 0.6; },
                                        vLineWidth: function() { return 0.6; },
                                        hLineColor: function() { return '#000000'; },
                                        vLineColor: function() { return '#000000'; },
                                        paddingLeft: function() { return 2; },
                                        paddingRight: function() { return 2; },
                                        paddingTop: function() { return 2; },
                                        paddingBottom: function() { return 2; }
                                    };
                                } else {
                                    doc.content.unshift(cabecalho);
                                }

                                doc.pageOrientation = 'landscape';
                                doc.pageSize = 'A4';
                                doc.pageMargins = [18, 18, 18, 18];
                                doc.defaultStyle = {
                                    fontSize: 8,
                                    color: '#000000'
                                };

                                doc.footer = function(currentPage, pageCount) {
                                    return {
                                        text: 'Página ' + currentPage + ' de ' + pageCount,
                                        alignment: 'center',
                                        fontSize: 8,
                                        margin: [0, 6, 0, 0]
                                    };
                                };
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="bi bi-printer"></i> Imprimir',
                            className: 'btn btn-primary btn-sm d-none',
                            title: 'Benefícios Acima de R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?>',
                            exportOptions: {
                                columns: ':visible',
                                format: {
                                    body: function(data) {
                                        return $('<div>').html(data).text().trim().replace(/\s+/g, ' ');
                                    }
                                }
                            }
                        }
                    ],
                    order: [],
                    searching: true,
                    paging: true,
                    info: true,
                    responsive: true,
                    initComplete: function() {
                        renderCustomPaginationBeneficios();
                    },
                    drawCallback: function() {
                        renderCustomPaginationBeneficios();
                    }
                });

                $('#btnExcel').on('click', function() {
                    tabelaBeneficios.button('.buttons-excel').trigger();
                });

                $('#btnPDF').on('click', function() {
                    tabelaBeneficios.button('.buttons-pdf').trigger();
                });

                $('#pesquisaBeneficios').on('input', function() {
                    if (tabelaBeneficios) {
                        tabelaBeneficios.search(this.value).draw();
                    }
                });

                $('#btnClearSearchBeneficios').on('click', function() {
                    $('#pesquisaBeneficios').val('');
                    if (tabelaBeneficios) {
                        tabelaBeneficios.search('').draw();
                    }
                    $('#pesquisaBeneficios').trigger('focus');
                });

                $(document).on('click', '#btnPrevPageBeneficios', function() {
                    if (tabelaBeneficios) {
                        tabelaBeneficios.page('previous').draw('page');
                    }
                });

                $(document).on('click', '#btnNextPageBeneficios', function() {
                    if (tabelaBeneficios) {
                        tabelaBeneficios.page('next').draw('page');
                    }
                });

                $(document).on('change', '#customPageLengthBeneficios', function() {
                    const novoLimite = parseInt($(this).val(), 10) || 10;
                    if (tabelaBeneficios) {
                        tabelaBeneficios.page.len(novoLimite).draw();
                    }
                });
            }

            <?php if ($filtros_aplicados): ?>
                $('button[data-bs-target="#modalFiltros"]').addClass('btn-filtro-applied');
            <?php endif; ?>

            function equalizeCardHeights() {
                const cards = document.querySelectorAll('.equal-height .card');
                if (!cards.length) return;

                let maxHeight = 0;

                cards.forEach(card => {
                    card.style.height = 'auto';
                });

                cards.forEach(card => {
                    const height = card.offsetHeight;
                    if (height > maxHeight) {
                        maxHeight = height;
                    }
                });

                cards.forEach(card => {
                    card.style.height = maxHeight + 'px';
                });
            }

            window.addEventListener('load', function() {
                equalizeCardHeights();
                setTimeout(equalizeCardHeights, 500);
            });

            window.addEventListener('resize', equalizeCardHeights);

            let chartsLoaded = 0;
            const totalCharts = 2;

            function checkChartsLoaded() {
                chartsLoaded++;
                if (chartsLoaded === totalCharts) {
                    setTimeout(equalizeCardHeights, 100);
                }
            }

            window.chartLoaded = checkChartsLoaded;
        });

        const ctxMensal = document.getElementById('graficoMensal');
        if (ctxMensal) {
            new Chart(ctxMensal, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($labels_mes) ?>,
                    datasets: [{
                        label: 'Valor Total (R$)',
                        data: <?= json_encode($valores_mes) ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Quantidade de Entregas',
                        data: <?= json_encode($quantidades_mes) ?>,
                        type: 'line',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        borderWidth: 2,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Valor (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + Number(value).toLocaleString('pt-BR');
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Quantidade'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Valor')) {
                                        return label + ': R$ ' + Number(context.parsed.y).toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        });
                                    }
                                    return label + ': ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });

            if (typeof window.chartLoaded === 'function') {
                window.chartLoaded();
            }
        }

        const ctxDist = document.getElementById('graficoDistribuicao');
        if (ctxDist) {
            new Chart(ctxDist, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($labels_tipos) ?>,
                    datasets: [{
                        data: <?= json_encode($valores_tipos) ?>,
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                            '#9966FF', '#FF9F40', '#8AC926', '#1982C4',
                            '#6A4C93', '#F15BB5', '#FF6B6B', '#48DBFB'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '50%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                },
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = Number(context.parsed || 0);
                                    const total = context.dataset.data.reduce((a, b) => Number(a) + Number(b), 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return label + ': R$ ' + value.toLocaleString('pt-BR', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            if (typeof window.chartLoaded === 'function') {
                window.chartLoaded();
            }
        }

        function verDetalhes(id) {
            alert('Detalhes do benefício ID: ' + id + '\n\nEm desenvolvimento: Modal com informações completas.');
        }

        function removerFiltro(filtro) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filtro);
            window.location.href = url.toString();
        }

        function atualizarTitulo() {
            const input = document.querySelector('input[name="valor_min"]');
            const titulo = document.querySelector('h3');

            if (!input || !titulo) return;

            const valorMin = parseFloat(input.value || '0');
            const valorFormatado = valorMin.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            titulo.textContent = 'Benefícios Acima de R$ ' + valorFormatado;
        }

        if (document.querySelector('input[name="valor_min"]')) {
            document.querySelector('input[name="valor_min"]').addEventListener('input', atualizarTitulo);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const dataInicio = document.querySelector('input[name="data_inicio"]');
            const dataFim = document.querySelector('input[name="data_fim"]');

            if (dataInicio && dataFim) {
                dataInicio.addEventListener('change', function() {
                    if (dataFim.value && this.value > dataFim.value) {
                        alert('Data inicial não pode ser maior que data final!');
                        this.value = '';
                    }
                });

                dataFim.addEventListener('change', function() {
                    if (dataInicio.value && this.value < dataInicio.value) {
                        alert('Data final não pode ser menor que data inicial!');
                        this.value = '';
                    }
                });
            }
        });

        <?php if (isset($_GET['aplicar_filtros'])): ?>
            $(document).ready(function() {
                $('#modalFiltros').modal('hide');
            });
        <?php endif; ?>
    </script>
</body>

</html>
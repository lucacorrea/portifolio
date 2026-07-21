<?php

declare(strict_types=1);

/* AUTH (já é privado) */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* DEBUG (remova em produção) */
ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

/* CONEXÃO */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('Erro de conexão');
}

// Função para formatar valor monetário
function formatarMoeda($valor)
{
    if ($valor === null || $valor === '' || $valor === 0) return 'R$ 0,00';
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

// Função para formatar CPF
function formatarCPF($cpf)
{
    $cpf = preg_replace('/\D+/', '', (string)$cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return (string)$cpf;
}

// Primeiro, vamos verificar quantas entregas existem no total
$sql_total_entregas = "SELECT COUNT(*) as total FROM ajudas_entregas";
$stmt_total = $pdo->query($sql_total_entregas);
$total_geral = (int)($stmt_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Verificar valor total aplicado
$sql_valor_total = "SELECT SUM(COALESCE(valor_aplicado, 0)) as total FROM ajudas_entregas";
$stmt_valor = $pdo->query($sql_valor_total);
$valor_total_geral = (float)($stmt_valor->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

// Processar filtros
$filtros_aplicados = false;
$filtro_mes = $_GET['mes'] ?? 'todos';
$filtro_ano = $_GET['ano'] ?? 'todos';
$filtro_beneficio = $_GET['beneficio'] ?? '';
$filtro_bairro = $_GET['bairro'] ?? '';
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_responsavel = $_GET['responsavel'] ?? '';
$ordenacao = $_GET['ordenacao'] ?? 'data_desc';

// Verificar se há filtros aplicados
if (
    isset($_GET['aplicar_filtros']) ||
    $filtro_mes !== 'todos' ||
    $filtro_ano !== 'todos' ||
    $filtro_beneficio !== '' ||
    $filtro_bairro !== '' ||
    $filtro_status !== 'todos' ||
    $filtro_data_inicio !== '' ||
    $filtro_data_fim !== '' ||
    $filtro_responsavel !== ''
) {
    $filtros_aplicados = true;
}

// Construir query base
$sql_base = "
    SELECT 
        ae.id,
        ae.data_entrega,
        ae.hora_entrega,
        ae.quantidade,
        ae.valor_aplicado,
        ae.observacao,
        ae.responsavel as responsavel_entrega,
        ae.entregue AS status_banco_original,
        'Sim' AS entregue,
        ae.pessoa_cpf,
        
        s.id as solicitante_id,
        s.nome as solicitante_nome,
        s.cpf as solicitante_cpf_formatado,
        s.telefone,
        s.bairro_id,
        s.endereco,
        s.numero,
        s.complemento,
        s.referencia,
        s.total_rendimentos,
        s.renda_familiar,
        
        atp.id as tipo_id,
        atp.nome as tipo_nome,
        atp.categoria as tipo_categoria,
        atp.valor_padrao as tipo_valor_padrao,
        atp.periodicidade as tipo_periodicidade,
        
        b.nome as bairro_nome
        
    FROM ajudas_entregas ae
    LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
    LEFT JOIN ajudas_tipos atp ON ae.ajuda_tipo_id = atp.id
    LEFT JOIN bairros b ON s.bairro_id = b.id
    WHERE 1=1
";

// Aplicar filtros
$params = [];

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

if ($filtro_status !== 'todos' && $filtro_status !== 'Sim') {
    // Todo registro presente em ajudas_entregas representa uma entrega concluída.
    // Portanto, esta página não possui registros pendentes.
    $sql_base .= " AND 1 = 0";
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

// Aplicar ordenação
$ordenacoes = [
    'data_desc' => 'ae.data_entrega DESC, ae.hora_entrega DESC',
    'data_asc' => 'ae.data_entrega ASC, ae.hora_entrega ASC',
    'valor_desc' => 'ae.valor_aplicado DESC',
    'valor_asc' => 'ae.valor_aplicado ASC',
    'nome_asc' => 'COALESCE(s.nome, ae.pessoa_cpf) ASC'
];

$sql_base .= " ORDER BY " . ($ordenacoes[$ordenacao] ?? 'ae.data_entrega DESC');

// Executar query
$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas
$total_valor = 0;
$total_entregas = count($entregas);
$total_pessoas = 0;
$total_quantidade = 0;
$cpfs_unicos = [];

foreach ($entregas as $entrega) {
    $valor = $entrega['valor_aplicado'] ?? 0;
    $total_valor += (float)$valor;
    $total_quantidade += (int)($entrega['quantidade'] ?? 0);

    $cpf = preg_replace('/\D+/', '', (string)($entrega['pessoa_cpf'] ?? ''));
    if ($cpf !== '' && !in_array($cpf, $cpfs_unicos, true)) {
        $cpfs_unicos[] = $cpf;
        $total_pessoas++;
    }
}

// Buscar lista de benefícios para filtro
$sql_tipos = "SELECT id, nome, valor_padrao FROM ajudas_tipos WHERE status = 'Ativa' ORDER BY nome";
$stmt_tipos = $pdo->query($sql_tipos);
$tipos_beneficios = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

// Buscar lista de bairros
$sql_bairros = "SELECT id, nome FROM bairros ORDER BY nome";
$stmt_bairros = $pdo->query($sql_bairros);
$bairros = $stmt_bairros->fetchAll(PDO::FETCH_ASSOC);

// Buscar anos disponíveis
$sql_anos = "SELECT DISTINCT YEAR(data_entrega) as ano FROM ajudas_entregas WHERE data_entrega IS NOT NULL ORDER BY ano DESC";
$stmt_anos = $pdo->query($sql_anos);
$anos = $stmt_anos->fetchAll(PDO::FETCH_ASSOC);

// Buscar lista de responsáveis
$sql_responsaveis = "SELECT DISTINCT responsavel FROM ajudas_entregas WHERE responsavel IS NOT NULL AND responsavel != '' ORDER BY responsavel";
$stmt_responsaveis = $pdo->query($sql_responsaveis);
$responsaveis = $stmt_responsaveis->fetchAll(PDO::FETCH_ASSOC);

// Buscar estatísticas por tipo de benefício
$sql_stats_tipos = "
    SELECT 
        COALESCE(atp.nome, 'Tipo não identificado') as nome,
        COUNT(*) as total_entregas,
        SUM(COALESCE(ae.valor_aplicado, 0)) as total_valor,
        SUM(ae.quantidade) as total_quantidade,
        AVG(COALESCE(ae.valor_aplicado, 0)) as valor_medio
    FROM ajudas_entregas ae
    LEFT JOIN ajudas_tipos atp ON ae.ajuda_tipo_id = atp.id
    GROUP BY atp.id, atp.nome
    ORDER BY total_valor DESC
";
$stmt_stats_tipos = $pdo->query($sql_stats_tipos);
$stats_tipos = $stmt_stats_tipos->fetchAll(PDO::FETCH_ASSOC);

// Buscar estatísticas por mês
$sql_stats_mes = "
    SELECT 
        DATE_FORMAT(data_entrega, '%Y-%m') as mes,
        COUNT(*) as total_entregas,
        SUM(COALESCE(valor_aplicado, 0)) as total_valor
    FROM ajudas_entregas 
    WHERE data_entrega >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(data_entrega, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12
";
$stmt_stats_mes = $pdo->query($sql_stats_mes);
$stats_mes = $stmt_stats_mes->fetchAll(PDO::FETCH_ASSOC);

// Preparar dados para gráfico mensal
$labels_mes = [];
$valores_mes = [];
$quantidades_mes = [];

foreach ($stats_mes as $mes) {
    $data = DateTime::createFromFormat('Y-m', $mes['mes']);
    $labels_mes[] = $data ? $data->format('M/Y') : $mes['mes'];
    $valores_mes[] = (float)$mes['total_valor'];
    $quantidades_mes[] = (int)$mes['total_entregas'];
}

if (empty($stats_mes)) {
    for ($i = 11; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-$i months");
        $labels_mes[] = $date->format('M/Y');
        $valores_mes[] = 0;
        $quantidades_mes[] = 0;
    }
}

// Preparar dados para gráfico de distribuição por tipo
$labels_tipos = [];
$valores_tipos = [];

foreach ($stats_tipos as $tipo) {
    $labels_tipos[] = $tipo['nome'];
    $valores_tipos[] = (float)$tipo['total_valor'];
}

// Buscar top 10 entregas por valor
$sql_top10 = "
    SELECT 
        COALESCE(ae.valor_aplicado, 0) as valor_aplicado,
        ae.data_entrega,
        COALESCE(s.nome, 'Não identificado') as solicitante_nome,
        COALESCE(atp.nome, 'Tipo não identificado') as tipo_nome
    FROM ajudas_entregas ae
    LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
    LEFT JOIN ajudas_tipos atp ON ae.ajuda_tipo_id = atp.id
    ORDER BY valor_aplicado DESC
    LIMIT 10
";
$stmt_top10 = $pdo->query($sql_top10);
$top10_entregas = $stmt_top10->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Valores Aplicados - ANEXO</title>

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

        .grafico-container {
            flex: 1;
            display: flex;
            min-height: 250px;
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

        .distribuicao-container {
            height: 250px;
            position: relative;
            width: 100%;
        }

        .badge-entregue {
            background-color: #28c76f;
        }

        .badge-pendente {
            background-color: #ff9f43;
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

        .badge-filtro {
            background-color: #0d6efd;
            color: white;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
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

        .valor-cell {
            font-weight: 600;
            color: #198754;
            font-size: 0.9rem;
        }

        .valor-alto {
            color: #dc3545;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .top10-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            min-height: 60px;
        }

        .top10-item:last-child {
            border-bottom: none;
        }

        .top10-nome {
            font-size: 0.9rem;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

        .table-stats {
            font-size: 0.875rem;
        }

        .table-stats th {
            font-weight: 600;
            background-color: #f8f9fa;
        }

        .valor-nulo {
            color: #6c757d;
            font-style: italic;
        }

        .dado-incompleto {
            color: #dc3545;
            font-style: italic;
        }

        .badge-incompleto {
            background-color: #dc3545;
        }

        /* ===== TABELA AJUSTADA E ALINHADA ===== */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #tabelaValores {
            width: 100% !important;
            min-width: 1490px;
            border-collapse: collapse !important;
            table-layout: fixed;
        }

        #tabelaValores thead th,
        #tabelaValores tbody td,
        #tabelaValores tfoot th,
        #tabelaValores tfoot td {
            vertical-align: middle;
            box-sizing: border-box;
        }

        #tabelaValores thead th {
            white-space: nowrap;
            font-weight: 700;
            color: #495057;
            background: #fff;
            border-bottom: 2px solid #dee2e6;
            padding: 0.9rem 0.65rem;
            font-size: 0.92rem;
        }

        #tabelaValores tbody td {
            padding: 0.82rem 0.65rem;
            white-space: nowrap;
            border-bottom: 1px solid #eceff3;
            font-size: 0.91rem;
        }

        #tabelaValores tfoot th,
        #tabelaValores tfoot td {
            padding: 0.9rem 0.65rem;
            border-top: 2px solid #dee2e6;
            background: #fff;
            white-space: nowrap;
        }

        #tabelaValores tbody tr:hover {
            background: rgba(13, 110, 253, 0.04);
        }

        #tabelaValores .cell-ellipsis {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            width: 100%;
        }

        #tabelaValores .badge {
            font-size: 0.72rem;
            padding: 0.35rem 0.55rem;
            border-radius: 0.35rem;
        }

        /* ===== PESQUISA CUSTOMIZADA ===== */
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate,
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dt-buttons {
            display: none !important;
        }

        .valores-toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
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

        /* ===== PAGINAÇÃO CUSTOMIZADA ===== */
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
            .valor-destaque {
                font-size: 1.3rem;
            }

            .statistic-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .chart-container,
            .distribuicao-container {
                height: 220px;
            }

            .grafico-container {
                min-height: 220px;
            }

            .valores-toolbar {
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

            .top10-item {
                flex-direction: column;
                align-items: flex-start;
                min-height: auto;
                padding: 0.5rem;
            }

            .top10-item>div {
                width: 100%;
            }

            .top10-item .text-end {
                text-align: left !important;
                margin-top: 0.5rem;
            }
        }
    

        /* ===== ESTILO CLEAN PADRÃO DAS TABELAS DO ANEXO ===== */
        .card-statistic {
            border: 1px solid #e6e9ef !important;
            border-left: 0 !important;
            border-radius: 14px !important;
            box-shadow: none !important;
            background: #fff !important;
            color: #2d3748 !important;
        }

        .card-statistic:nth-child(1),
        .card-statistic:nth-child(2),
        .card-statistic:nth-child(3),
        .card-statistic:nth-child(4) {
            border-left: 0 !important;
            border-left-color: transparent !important;
        }

        .card-statistic .statistic-icon,
        .card-statistic:nth-child(1) .statistic-icon,
        .card-statistic:nth-child(2) .statistic-icon,
        .card-statistic:nth-child(3) .statistic-icon,
        .card-statistic:nth-child(4) .statistic-icon {
            background: #f6f7f9 !important;
            color: #52697f !important;
            border: 1px solid #e1e6ec !important;
        }

        .valor-destaque,
        .valor-cell,
        .valor-alto,
        .valor-nulo {
            color: #52697f !important;
        }

        .valor-destaque {
            color: #25396f !important;
        }

        .valor-cell,
        .valor-alto {
            font-weight: 700;
        }

        .filtros-ativos,
        .alert-info {
            background: #fff !important;
            border: 1px solid #e1e6ec !important;
            color: #52697f !important;
            border-radius: 10px !important;
        }

        .badge-filtro {
            background: #f6f7f9 !important;
            color: #52697f !important;
            border: 1px solid #e1e6ec !important;
            border-radius: 999px !important;
            font-weight: 700;
        }

        .badge-filtro a {
            color: #52697f !important;
            text-decoration: none;
        }

        .dt-search-input {
            height: 38px;
            border: 1px solid #9bb4f5;
            border-radius: 4px;
            background: #fff;
            color: #495057;
            box-shadow: none;
        }

        .dt-search-input:focus {
            border-color: #9ab0f5;
            box-shadow: 0 0 0 .12rem rgba(67, 94, 190, .12);
        }

        .dt-search-clear {
            border: 1px solid #cfd6df;
            background: #fff;
            color: #495057;
        }

        .dt-search-clear:hover {
            border-color: #435ebe;
            color: #435ebe;
            background: #f8f9ff;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        #tabelaValores {
            border-collapse: separate !important;
            border-spacing: 0 !important;
            margin-bottom: 0 !important;
            color: #52697f !important;
            background: #fff !important;
        }

        #tabelaValores thead th {
            background: #fff !important;
            color: #2d3748 !important;
            font-size: .95rem !important;
            font-weight: 800 !important;
            border: 0 !important;
            border-bottom: 1px solid #d6dce5 !important;
            padding: .95rem .75rem !important;
            vertical-align: middle !important;
            position: relative !important;
            text-align: center !important;
            white-space: nowrap !important;
        }

        #tabelaValores tbody td {
            border: 0 !important;
            border-bottom: 1px solid #e1e6ec !important;
            padding: .8rem .75rem !important;
            vertical-align: middle !important;
            color: #52697f !important;
            font-size: .95rem !important;
            background: transparent !important;
        }

        #tabelaValores tbody tr:nth-child(odd) td {
            background: #fff !important;
        }

        #tabelaValores tbody tr:nth-child(even) td {
            background: #f6f7f9 !important;
        }

        #tabelaValores tbody tr:hover td {
            background: #eef1f5 !important;
        }

        #tabelaValores tbody tr.table-warning td {
            background: inherit !important;
            color: #52697f !important;
        }

        #tabelaValores tfoot th,
        #tabelaValores tfoot td {
            background: #fff !important;
            border: 0 !important;
            border-top: 1px solid #d6dce5 !important;
            color: #52697f !important;
            padding: .9rem .75rem !important;
        }

        #tabelaValores .cell-ellipsis {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #tabelaValores .clean-text-pill,
        #tabelaValores .status-clean {
            display: inline-block;
            max-width: 100%;
            color: #52697f !important;
            font-weight: 600;
            background: transparent !important;
            border: 0 !important;
            padding: 0 !important;
            border-radius: 0 !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        #tabelaValores .status-clean {
            font-weight: 700;
        }

        #tabelaValores .text-danger,
        #tabelaValores .text-success,
        #tabelaValores .text-warning,
        #tabelaValores .text-info,
        #tabelaValores .text-primary {
            color: #52697f !important;
        }

        #tabelaValores thead th.sorting,
        #tabelaValores thead th.sorting_asc,
        #tabelaValores thead th.sorting_desc,
        #tabelaValores thead th.sorting_asc_disabled,
        #tabelaValores thead th.sorting_desc_disabled {
            cursor: pointer;
            user-select: none;
            padding-right: 1.65rem !important;
        }

        table.dataTable#tabelaValores thead > tr > th.sorting::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_asc::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_asc_disabled::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc_disabled::before {
            content: "▲" !important;
            right: .55rem !important;
            top: calc(50% - 8px) !important;
            bottom: auto !important;
            font-size: 10px !important;
            line-height: 8px !important;
            color: #dfe3e8 !important;
            opacity: 1 !important;
        }

        table.dataTable#tabelaValores thead > tr > th.sorting::after,
        table.dataTable#tabelaValores thead > tr > th.sorting_asc::after,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc::after,
        table.dataTable#tabelaValores thead > tr > th.sorting_asc_disabled::after,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc_disabled::after {
            content: "▼" !important;
            right: .55rem !important;
            top: calc(50% + 1px) !important;
            bottom: auto !important;
            font-size: 10px !important;
            line-height: 8px !important;
            color: #dfe3e8 !important;
            opacity: 1 !important;
        }

        table.dataTable#tabelaValores thead > tr > th.sorting_asc::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc::after {
            color: #8d98a7 !important;
        }

        table.dataTable#tabelaValores thead > tr > th.sorting:hover::before,
        table.dataTable#tabelaValores thead > tr > th.sorting:hover::after,
        table.dataTable#tabelaValores thead > tr > th.sorting_asc:hover::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_asc:hover::after,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc:hover::before,
        table.dataTable#tabelaValores thead > tr > th.sorting_desc:hover::after {
            color: #b7c0cc !important;
        }

        .custom-pagination-bar {
            border-top: 1px solid #e9ecef;
        }

        .custom-page-info {
            color: #435ebe;
            font-weight: 800;
        }

        .custom-length-select {
            background-color: #fff;
        }

        .table-stats thead th {
            background: #fff !important;
            border-bottom: 1px solid #d6dce5 !important;
        }

        .table-stats tbody td,
        .table-stats tbody th {
            border-bottom: 1px solid #e1e6ec !important;
        }

        .progress-bar {
            background-color: #8d98a7 !important;
        }

        /* ===== CORES DE DESTAQUE FINANCEIRO E STATUS ===== */
        .valor-total-aplicado {
            color: #198754 !important;
        }

        #tabelaValores .status-clean.status-entregue {
            color: #198754 !important;
        }

        #tabelaValores .status-clean.status-pendente {
            color: #b7791f !important;
        }

        /* Barra percentual: verde, com texto branco somente quando couber dentro */
        .table-stats .progress.progress-percentual {
            position: relative;
            overflow: hidden;
            background-color: #e9ecef;
            border-radius: 4px;
        }

        .table-stats .progress.progress-percentual .progress-bar {
            background-color: #198754 !important;
            transition: width .25s ease;
        }

        .table-stats .progress-percentual-label {
            position: absolute;
            inset: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            color: #212529;
            font-size: .75rem;
            font-weight: 700;
            line-height: 20px;
            white-space: nowrap;
            pointer-events: none;
        }

        .table-stats .progress-percentual-label.label-in-bar {
            color: #fff;
            text-shadow: 0 1px 1px rgba(0, 0, 0, .25);
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
                                <li class="submenu-item "><a href="relatoriosCadastros.php">Cadastros</a></li>
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
                                <li class="submenu-item active">
                                    <a href="valoresAplicados.php">Valores Aplicados</a>
                                </li>
                                <li class="submenu-item">
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
                        <h3>Valores Aplicados em Benefícios</h3>
                        <p class="text-muted mb-2 mb-md-0">Controle financeiro completo de todas as entregas do ANEXO</p>
                        <small class="text-info">
                            <i class="bi bi-info-circle"></i> Mostrando dados de todas as <?= $total_geral ?> entregas cadastradas no sistema
                        </small>
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

                                <?php if ($filtro_bairro): ?>
                                    <?php
                                    $bairro_nome = '';
                                    foreach ($bairros as $b) {
                                        if ((string)$b['id'] === (string)$filtro_bairro) {
                                            $bairro_nome = $b['nome'];
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="badge-filtro filtro-badge">
                                        Bairro: <?= htmlspecialchars($bairro_nome) ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('bairro')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_status !== 'todos'): ?>
                                    <span class="badge-filtro filtro-badge">
                                        Status: Entregue
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

                                <a href="valoresAplicados.php" class="btn btn-sm btn-outline-danger align-self-center ms-2">
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
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-cash-stack"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Valor Total Aplicado</h6>
                                            <div class="valor-destaque valor-total-aplicado"><?= formatarMoeda($total_valor) ?></div>
                                            <small class="text-muted d-block mt-1">
                                                <?= $total_entregas ?> entregas encontradas
                                                <?php if ($filtros_aplicados): ?>
                                                    (de <?= $total_geral ?> no total)
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Total de Entregas</h6>
                                            <div class="valor-destaque"><?= $total_entregas ?></div>
                                            <small class="text-muted d-block mt-1">
                                                <?= $total_quantidade ?> itens entregues
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Pessoas Atendidas</h6>
                                            <div class="valor-destaque"><?= $total_pessoas ?></div>
                                            <small class="text-muted d-block mt-1">
                                                CPFs únicos registrados
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-bar-chart-fill"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Valor Médio por Entrega</h6>
                                            <div class="valor-destaque">
                                                <?= $total_entregas > 0 ? formatarMoeda($total_valor / $total_entregas) : 'R$ 0,00' ?>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Média geral
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mb-4 equal-height">
                    <div class="col-12 col-lg-6 mb-4 mb-lg-0">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Evolução Mensal (Últimos 12 meses)</h5>
                            </div>
                            <div class="card-body p-2 p-md-3">
                                <div class="chart-container">
                                    <canvas id="graficoMensal"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Distribuição por Tipo de Benefício</h5>
                            </div>
                            <div class="card-body p-2 p-md-3">
                                <div class="distribuicao-container">
                                    <canvas id="graficoDistribuicao"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Top 10 Entregas por Valor</h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($top10_entregas)): ?>
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p class="mb-0">Nenhuma entrega encontrada</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($top10_entregas as $index => $entrega): ?>
                                        <div class="top10-item">
                                            <div class="flex-grow-1">
                                                <strong class="top10-nome"><?= $index + 1 ?>. <?= htmlspecialchars($entrega['solicitante_nome']) ?></strong>
                                                <div class="small text-muted">
                                                    <?= htmlspecialchars($entrega['tipo_nome']) ?> •
                                                    <?= date('d/m/Y', strtotime($entrega['data_entrega'])) ?>
                                                </div>
                                            </div>
                                            <div class="text-end flex-shrink-0">
                                                <div class="valor-destaque <?= ((float)$entrega['valor_aplicado'] > 0) ? 'text-danger' : 'valor-nulo' ?>" style="font-size: 0.9rem;">
                                                    <?= ((float)$entrega['valor_aplicado'] > 0) ? formatarMoeda($entrega['valor_aplicado']) : 'Sem valor' ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <h5 class="mb-2 mb-md-0">Detalhamento de Todas as Entregas</h5>
                                <div class="d-flex gap-2">
                                    <button id="btnExcel" class="btn btn-success btn-sm">
                                        <i class="bi bi-file-earmark-excel"></i> <span>Excel</span>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-eye"></i> Exibir
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="filtrarValor('todos')">Todas as entregas</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item" href="#" onclick="filtrarValor('com_valor')">Somente com valor</a></li>
                                            <li><a class="dropdown-item" href="#" onclick="filtrarValor('sem_valor')">Somente sem valor</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (empty($entregas)): ?>
                                    <div class="text-center py-5">
                                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                                        <h5 class="mt-3">Nenhuma entrega encontrada</h5>
                                        <p class="text-muted mb-0">Não há registros de entregas com os filtros selecionados.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info mb-3 clean-info-box">
                                        <i class="bi bi-info-circle"></i>
                                        Mostrando <strong><?= $total_entregas ?></strong> entregas de um total de <strong><?= $total_geral ?></strong> no sistema.
                                        <?php if ($filtros_aplicados): ?>
                                            <a href="valoresAplicados.php" class="alert-link">Ver todas</a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="valores-toolbar">
                                        <div class="toolbar-search">
                                            <div class="dt-search-wrap">
                                                <input type="text" id="pesquisaValores" class="dt-search-input" placeholder="Buscar por nome/CPF/telefone/endereço/responsável...">
                                                <button type="button" class="dt-search-clear" id="btnClearSearchValores" title="Limpar pesquisa" aria-label="Limpar pesquisa">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="tabelaValores" class="table table-hover table-striped align-middle mb-0">
                                            <colgroup>
                                                <col style="width: 90px;">
                                                <col style="width: 250px;">
                                                <col style="width: 120px;">
                                                <col style="width: 120px;">
                                                <col style="width: 130px;">
                                                <col style="width: 60px;">
                                                <col style="width: 110px;">
                                                <col style="width: 120px;">
                                                <col style="width: 110px;">
                                                <col style="width: 150px;">
                                                <col style="width: 95px;">
                                            </colgroup>
                                            <thead>
                                                <tr>
                                                    <th class="text-nowrap">Data/Hora</th>
                                                    <th class="text-nowrap">Beneficiário</th>
                                                    <th class="text-nowrap">Telefone</th>
                                                    <th class="text-nowrap">CPF</th>
                                                    <th class="text-nowrap">Benefício</th>
                                                    <th class="text-nowrap text-center">Qtd</th>
                                                    <th class="text-nowrap">Valor Unit.</th>
                                                    <th class="text-nowrap">Valor Total</th>
                                                    <th class="text-nowrap">Bairro</th>
                                                    <th class="text-nowrap">Responsável</th>
                                                    <th class="text-nowrap text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($entregas as $entrega): ?>
                                                    <?php
                                                    $quantidade = (int)($entrega['quantidade'] ?? 0);
                                                    $valor_aplicado = (float)($entrega['valor_aplicado'] ?? 0);
                                                    $valor_unitario = $quantidade > 0 ? $valor_aplicado / $quantidade : 0;
                                                    $classe_valor = ($valor_aplicado >= 1000) ? 'valor-alto' : 'valor-cell';
                                                    $tem_valor = $valor_aplicado > 0;
                                                    $classe_linha = $tem_valor ? '' : 'valor-nulo';

                                                    $nome = $entrega['solicitante_nome'] ?? '';
                                                    $tipo_nome = $entrega['tipo_nome'] ?? '';
                                                    $dados_incompletos = empty($nome) || empty($tipo_nome);

                                                    $telefone = preg_replace('/\D+/', '', (string)($entrega['telefone'] ?? ''));
                                                    $telefone_fmt = '-';
                                                    if (strlen($telefone) === 11) {
                                                        $telefone_fmt = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
                                                    } elseif (strlen($telefone) === 10) {
                                                        $telefone_fmt = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
                                                    }
                                                    ?>
                                                    <tr class="<?= $classe_linha ?> <?= $dados_incompletos ? 'table-warning' : '' ?>"
                                                        data-tem-valor="<?= $tem_valor ? '1' : '0' ?>">
                                                        <td class="text-nowrap">
                                                            <div><?= date('d/m/Y', strtotime($entrega['data_entrega'])) ?></div>
                                                            <small class="text-muted"><?= !empty($entrega['hora_entrega']) ? substr((string)$entrega['hora_entrega'], 0, 5) : '--:--' ?></small>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($nome)): ?>
                                                                <div class="cell-ellipsis">
                                                                    <strong><?= htmlspecialchars($nome) ?></strong>
                                                                </div>
                                                            <?php else: ?>
                                                                <span class="dado-incompleto">Não identificado</span>
                                                                <?php if (!empty($entrega['pessoa_cpf'])): ?>
                                                                    <small class="text-muted d-block">CPF: <?= formatarCPF($entrega['pessoa_cpf']) ?></small>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?= $telefone_fmt !== '-' ? htmlspecialchars($telefone_fmt) : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?= !empty($entrega['pessoa_cpf']) ? formatarCPF($entrega['pessoa_cpf']) : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($tipo_nome)): ?>
                                                                <span class="clean-text-pill cell-ellipsis" title="<?= htmlspecialchars($tipo_nome) ?>">
                                                                    <?= htmlspecialchars($tipo_nome) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Tipo não identificado</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center text-nowrap"><?= $quantidade ?></td>
                                                        <td class="text-nowrap <?= $tem_valor ? 'valor-cell' : 'valor-nulo' ?>">
                                                            <?= $tem_valor ? formatarMoeda($valor_unitario) : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="<?= $tem_valor ? $classe_valor : 'valor-nulo' ?> text-nowrap">
                                                            <strong>
                                                                <?= $tem_valor ? formatarMoeda($valor_aplicado) : '<span class="text-muted">Sem valor</span>' ?>
                                                            </strong>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($entrega['bairro_nome'])): ?>
                                                                <span class="clean-text-pill cell-ellipsis" title="<?= htmlspecialchars($entrega['bairro_nome']) ?>"><?= htmlspecialchars($entrega['bairro_nome']) ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="cell-ellipsis"><?= htmlspecialchars((string)($entrega['responsavel_entrega'] ?? 'N/A')) ?></div>
                                                        </td>
                                                        <td class="text-nowrap text-center">
                                                            <span class="status-clean status-entregue">
                                                                Entregue
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="5" class="text-end text-nowrap">Total Geral:</th>
                                                    <th class="text-center text-nowrap"><?= $total_quantidade ?></th>
                                                    <th></th>
                                                    <th class="valor-alto text-nowrap">
                                                        <strong><?= formatarMoeda($total_valor) ?></strong>
                                                    </th>
                                                    <th colspan="3"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div id="customPaginationValores" class="custom-pagination-bar"></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Estatísticas por Tipo de Benefício</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-stats table-hover text-nowrap">
                                        <thead>
                                            <tr>
                                                <th>Tipo de Benefício</th>
                                                <th class="text-center">Entregas</th>
                                                <th class="text-center">Quantidade</th>
                                                <th class="text-center">Valor Total</th>
                                                <th class="text-center">Valor Médio</th>
                                                <th class="text-center">% do Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stats_tipos as $tipo): ?>
                                                <?php
                                                $percentual = $total_valor > 0 ? (((float)$tipo['total_valor'] / $total_valor) * 100) : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($tipo['nome']) ?></td>
                                                    <td class="text-center"><?= (int)$tipo['total_entregas'] ?></td>
                                                    <td class="text-center"><?= (int)$tipo['total_quantidade'] ?></td>
                                                    <td class="text-center valor-cell"><?= formatarMoeda($tipo['total_valor']) ?></td>
                                                    <td class="text-center"><?= formatarMoeda($tipo['valor_medio']) ?></td>
                                                    <td class="text-center">
                                                        <div class="progress progress-percentual" style="height: 20px;">
                                                            <div class="progress-bar"
                                                                role="progressbar"
                                                                style="width: <?= min(100, max(0, $percentual)) ?>%"
                                                                aria-valuenow="<?= $percentual ?>"
                                                                aria-valuemin="0"
                                                                aria-valuemax="100">
                                                            </div>
                                                            <span class="progress-percentual-label">
                                                                <?= number_format($percentual, 1, ',', '.') ?>%
                                                            </span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
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
        <div class="modal-dialog modal-lg">
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
                                <div class="mb-3">
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

                                <div class="mb-3">
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
                                                    echo "<option value=\"" . htmlspecialchars($valor) . "\" $selected>$nome</option>";
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

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Bairro</label>
                                    <select name="bairro" class="form-select">
                                        <option value="">Todos os bairros</option>
                                        <?php foreach ($bairros as $bairro): ?>
                                            <option value="<?= htmlspecialchars((string)$bairro['id']) ?>" <?= ($filtro_bairro == $bairro['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($bairro['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12 col-lg-6">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Tipo de Benefício</label>
                                    <select name="beneficio" class="form-select">
                                        <option value="">Todos os benefícios</option>
                                        <?php foreach ($tipos_beneficios as $tipo): ?>
                                            <option value="<?= htmlspecialchars((string)$tipo['id']) ?>" <?= ($filtro_beneficio == $tipo['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tipo['nome']) ?>
                                                <?php if (!empty($tipo['valor_padrao'])): ?>
                                                    (R$ <?= number_format((float)$tipo['valor_padrao'], 2, ',', '.') ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="todos">Todos os status</option>
                                        <option value="Sim" <?= ($filtro_status === 'Sim') ? 'selected' : '' ?>>Entregue</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Responsável</label>
                                    <select name="responsavel" class="form-select">
                                        <option value="">Todos os responsáveis</option>
                                        <?php foreach ($responsaveis as $resp): ?>
                                            <option value="<?= htmlspecialchars($resp['responsavel']) ?>" <?= ($filtro_responsavel == $resp['responsavel']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($resp['responsavel']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Ordenação</label>
                                    <select name="ordenacao" class="form-select">
                                        <option value="data_desc" <?= ($ordenacao == 'data_desc') ? 'selected' : '' ?>>Data Mais Recente</option>
                                        <option value="data_asc" <?= ($ordenacao == 'data_asc') ? 'selected' : '' ?>>Data Mais Antiga</option>
                                        <option value="valor_desc" <?= ($ordenacao == 'valor_desc') ? 'selected' : '' ?>>Maior Valor</option>
                                        <option value="valor_asc" <?= ($ordenacao == 'valor_asc') ? 'selected' : '' ?>>Menor Valor</option>
                                        <option value="nome_asc" <?= ($ordenacao == 'nome_asc') ? 'selected' : '' ?>>Nome A-Z</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle"></i>
                            <strong>Nota:</strong> Esta página exibe somente registros efetivamente entregues, pois todos os dados vêm da tabela <code>ajudas_entregas</code>. O valor aplicado pode estar preenchido ou não.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-end w-100">
                            <button type="submit" class="btn btn-primary col-12 col-md-auto">
                                <i class="bi bi-check-circle"></i> Aplicar Filtros
                            </button>
                            <a href="valoresAplicados.php" class="btn btn-outline-danger col-12 col-md-auto">
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
                        <i class="bi bi-question-circle text-primary"></i> Ajuda - Valores Aplicados
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Informações sobre esta página:</h6>
                        <p class="mb-0">Esta página exibe <strong>todos os valores aplicados em benefícios</strong> entregues pelo ANEXO, permitindo um controle financeiro completo e detalhado.</p>
                    </div>

                    <h6>Funcionalidades Disponíveis:</h6>
                    <ul>
                        <li><strong>Estatísticas Gerais:</strong> Valor total aplicado, entregas, pessoas atendidas e valor médio</li>
                        <li><strong>Gráficos de Análise:</strong> Evolução mensal e distribuição por tipo de benefício</li>
                        <li><strong>Top 10 Entregas:</strong> Lista das 10 entregas com maior valor aplicado</li>
                        <li><strong>Tabela Detalhada:</strong> Lista completa de todas as entregas com valores</li>
                        <li><strong>Estatísticas por Tipo:</strong> Análise detalhada por tipo de benefício</li>
                        <li><strong>Filtros Personalizados:</strong> Filtre por data, tipo, bairro, status e responsável</li>
                        <li><strong>Exportação:</strong> Exporte os dados para Excel</li>
                    </ul>

                    <h6>Como usar os filtros:</h6>
                    <ol>
                        <li>Clique em "Aplicar Filtros" para abrir o modal</li>
                        <li>Selecione os critérios desejados (data, tipo, bairro, etc.)</li>
                        <li>Clique em "Aplicar Filtros" para confirmar</li>
                        <li>Use "Limpar Filtros" para voltar ao estado inicial</li>
                        <li>Remova filtros individuais clicando no × nos badges</li>
                    </ol>

                    <h6>Dicas de Uso:</h6>
                    <ul>
                        <li>Use o gráfico mensal para identificar tendências sazonais</li>
                        <li>Analise a distribuição por tipo para entender onde os recursos estão sendo aplicados</li>
                        <li>Consulte o Top 10 para identificar as entregas de maior valor</li>
                        <li>Use a tabela de estatísticas por tipo para planejamento futuro</li>
                    </ul>

                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle"></i> Aviso Importante:</h6>
                        <p class="mb-0">Esta página contém informações financeiras sensíveis. Certifique-se de manter a confidencialidade dos dados e utilizar apenas para fins oficiais do ANEXO.</p>
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

    <script>
        let tabelaValores = null;
        let valorFilterModo = 'todos';

        function ajustarContrastePercentuais() {
            document.querySelectorAll('.progress-percentual').forEach(function(progress) {
                const bar = progress.querySelector('.progress-bar');
                const label = progress.querySelector('.progress-percentual-label');

                if (!bar || !label) return;

                label.classList.remove('label-in-bar');

                const larguraBarra = bar.getBoundingClientRect().width;
                const larguraTexto = label.scrollWidth + 10;

                if (larguraBarra >= larguraTexto) {
                    label.classList.add('label-in-bar');
                }
            });
        }

        function renderCustomPagination() {
            if (!tabelaValores) return;

            const info = tabelaValores.page.info();
            const currentPage = info.pages > 0 ? (info.page + 1) : 1;
            const totalPages = info.pages > 0 ? info.pages : 1;
            const currentLength = info.length;

            const html = `
                <div class="custom-pagination-left">
                    <button type="button" class="custom-page-btn" id="btnPrevPage" ${info.page <= 0 ? 'disabled' : ''}>
                        Anterior
                    </button>
                    <button type="button" class="custom-page-btn" id="btnNextPage" ${info.page >= (info.pages - 1) ? 'disabled' : ''}>
                        Próximo
                    </button>
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

            $('#customPaginationValores').html(html);
        }

        $(document).ready(function() {
            if ($('#tabelaValores').length) {
                $.fn.dataTable.ext.search.push(function(settings, data) {
                    if (settings.nTable.id !== 'tabelaValores') return true;

                    const valorTexto = (data[7] || '').toString();

                    if (valorFilterModo === 'com_valor') {
                        return valorTexto.indexOf('Sem valor') === -1 && valorTexto.indexOf('R$ 0,00') === -1;
                    }

                    if (valorFilterModo === 'sem_valor') {
                        return valorTexto.indexOf('Sem valor') !== -1 || valorTexto.indexOf('R$ 0,00') !== -1;
                    }

                    return true;
                });

                tabelaValores = $('#tabelaValores').DataTable({
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
                        filename: 'valores_aplicados_<?= date('Y-m-d') ?>',
                        exportOptions: {
                            columns: ':visible',
                            stripHtml: true,
                            format: {
                                header: function(data) {
                                    return $('<div>').html(data).text().replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();
                                },
                                body: function(data, row, column) {
                                    let texto = $('<div>').html(data).text().replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();

                                    if (column === 0) {
                                        const partes = texto.match(/^(\d{2}\/\d{2}\/\d{4})\s*(\d{2}:\d{2}|--:--)?$/);
                                        if (partes) {
                                            texto = partes[1] + (partes[2] ? ' às ' + partes[2] : '');
                                        }
                                    }

                                    return texto;
                                },
                                footer: function(data) {
                                    return $('<div>').html(data).text().replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();
                                }
                            }
                        },
                        customize: function(xlsx) {
                            const sheet = xlsx.xl.worksheets['sheet1.xml'];
                            const styles = xlsx.xl['styles.xml'];
                            const sharedStrings = xlsx.xl['sharedStrings.xml'];
                            const sheetData = $('sheetData', sheet)[0];

                            const tituloTexto = 'VALORES APLICADOS - ANEXO';
                            const resumoTexto = 'Total: <?= $total_entregas ?> entregas | Quantidade: <?= $total_quantidade ?> itens | Pessoas atendidas: <?= $total_pessoas ?> | Valor Total: <?= formatarMoeda($total_valor) ?> | Valor Médio: <?= $total_entregas > 0 ? formatarMoeda($total_valor / $total_entregas) : 'R$ 0,00' ?>';

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
                                filtros.push(<?= json_encode('Bairro: ' . ($bairro_nome ?? 'Selecionado'), JSON_UNESCAPED_UNICODE) ?>);
                            <?php endif; ?>
                            <?php if ($filtro_status !== 'todos'): ?>
                                filtros.push(<?= json_encode('Status: Entregue', JSON_UNESCAPED_UNICODE) ?>);
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
                            filtros.push(<?= json_encode('Ordenação: ' . ($ordenacao === 'data_asc' ? 'Data mais antiga' : ($ordenacao === 'valor_desc' ? 'Maior valor' : ($ordenacao === 'valor_asc' ? 'Menor valor' : ($ordenacao === 'nome_asc' ? 'Nome A-Z' : 'Data mais recente')))), JSON_UNESCAPED_UNICODE) ?>);

                            if (valorFilterModo === 'com_valor') {
                                filtros.push('Exibição: somente com valor');
                            } else if (valorFilterModo === 'sem_valor') {
                                filtros.push('Exibição: somente sem valor');
                            } else {
                                filtros.push('Exibição: todas as entregas');
                            }

                            const filtrosTexto = 'Filtros: ' + (filtros.length ? filtros.join(' | ') : 'Nenhum filtro aplicado');
                            const agora = new Date();
                            const geradoTexto = 'Gerado em: ' + agora.toLocaleDateString('pt-BR') + ' ' + agora.toLocaleTimeString('pt-BR');

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
                                        '<color rgb="FF000000"/>' +
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
                                const fontId = options.fontId || 0;
                                const fillId = options.fillId || 0;
                                const borderId = options.borderId || 0;
                                appendXml(cellXfs,
                                    '<xf numFmtId="0" fontId="' + fontId + '" fillId="' + fillId + '" borderId="' + borderId + '" xfId="0"' +
                                        (options.fontId ? ' applyFont="1"' : '') +
                                        (options.fillId ? ' applyFill="1"' : '') +
                                        (options.borderId ? ' applyBorder="1"' : '') +
                                        ' applyAlignment="1">' +
                                        '<alignment horizontal="' + (options.horizontal || 'center') + '" vertical="center" wrapText="1"/>' +
                                    '</xf>'
                                );
                                cellXfs.attr('count', $('xf', cellXfs).length);
                                return id;
                            }

                            const fontTitle = addFont({ bold: true, size: 14 });
                            const fontMeta = addFont({ bold: true, size: 11 });
                            const fontHeader = addFont({ bold: true, size: 11 });
                            const grayFill = addFill('FFF2F4F7');
                            const blackBorder = addBorder();

                            const styleTitle = addStyle({ fontId: fontTitle, fillId: grayFill, borderId: blackBorder, horizontal: 'center' });
                            const styleMeta = addStyle({ fontId: fontMeta, borderId: blackBorder, horizontal: 'left' });
                            const styleHeader = addStyle({ fontId: fontHeader, fillId: grayFill, borderId: blackBorder, horizontal: 'center' });
                            const styleCenter = addStyle({ borderId: blackBorder, horizontal: 'center' });
                            const styleLeft = addStyle({ borderId: blackBorder, horizontal: 'left' });

                            function excelColName(index) {
                                let name = '';
                                index++;
                                while (index > 0) {
                                    const rem = (index - 1) % 26;
                                    name = String.fromCharCode(65 + rem) + name;
                                    index = Math.floor((index - 1) / 26);
                                }
                                return name;
                            }

                            function excelColIndex(ref) {
                                const col = String(ref || '').replace(/[0-9]/g, '');
                                let index = 0;
                                for (let i = 0; i < col.length; i++) {
                                    index = index * 26 + (col.charCodeAt(i) - 64);
                                }
                                return index - 1;
                            }

                            function getCellText(cell) {
                                const $cell = $(cell);
                                const type = $cell.attr('t');
                                if (type === 'inlineStr') return $('is t', cell).text();
                                if (type === 's') {
                                    const sharedIndex = parseInt($('v', cell).text(), 10);
                                    if (sharedStrings && !Number.isNaN(sharedIndex)) {
                                        return $('si', sharedStrings).eq(sharedIndex).text();
                                    }
                                }
                                return $('v', cell).text() || $('t', cell).text() || '';
                            }

                            const originalRows = $('row', sheet).toArray();
                            let headerIndex = originalRows.findIndex(function(row) {
                                const text = $('c', row).toArray().map(getCellText).join('|').toLowerCase();
                                return text.includes('data/hora') && text.includes('beneficiário') && text.includes('valor total');
                            });
                            if (headerIndex < 0) headerIndex = 0;

                            let tableRows = originalRows.slice(headerIndex).map(function(row) {
                                const values = new Array(11).fill('');
                                $('c', row).each(function() {
                                    const colIndex = excelColIndex($(this).attr('r'));
                                    if (colIndex >= 0 && colIndex < 11) {
                                        values[colIndex] = getCellText(this).replace(/\u00A0/g, ' ').replace(/\s+/g, ' ').trim();
                                    }
                                });
                                return values;
                            }).filter(function(values) {
                                return values.some(function(value) { return String(value).trim() !== ''; });
                            });

                            const expectedHeader = ['Data/Hora', 'Beneficiário', 'Telefone', 'CPF', 'Benefício', 'Qtd', 'Valor Unit.', 'Valor Total', 'Bairro', 'Responsável', 'Status'];
                            if (!tableRows.length || String(tableRows[0][0]).toLowerCase() !== 'data/hora') {
                                tableRows.unshift(expectedHeader);
                            }

                            while (sheetData.firstChild) sheetData.removeChild(sheetData.firstChild);
                            $('mergeCells', sheet).remove();
                            $('autoFilter', sheet).remove();

                            function createCell(colIndex, rowNumber, value, styleId) {
                                const cell = sheet.createElement('c');
                                cell.setAttribute('r', excelColName(colIndex) + rowNumber);
                                cell.setAttribute('s', styleId);
                                cell.setAttribute('t', 'inlineStr');
                                const inlineString = sheet.createElement('is');
                                const textNode = sheet.createElement('t');
                                textNode.setAttribute('xml:space', 'preserve');
                                textNode.textContent = value || '';
                                inlineString.appendChild(textNode);
                                cell.appendChild(inlineString);
                                return cell;
                            }

                            function createRow(rowNumber, values, styleResolver, height) {
                                const row = sheet.createElement('row');
                                row.setAttribute('r', String(rowNumber));
                                row.setAttribute('ht', String(height));
                                row.setAttribute('customHeight', '1');
                                for (let i = 0; i < 11; i++) {
                                    const styleId = typeof styleResolver === 'function' ? styleResolver(i) : styleResolver;
                                    row.appendChild(createCell(i, rowNumber, values[i] || '', styleId));
                                }
                                return row;
                            }

                            sheetData.appendChild(createRow(1, [tituloTexto], styleTitle, 26));
                            sheetData.appendChild(createRow(2, [filtrosTexto], styleMeta, 34));
                            sheetData.appendChild(createRow(3, [resumoTexto], styleMeta, 30));
                            sheetData.appendChild(createRow(4, [geradoTexto], styleMeta, 22));
                            sheetData.appendChild(createRow(5, tableRows[0], styleHeader, 24));

                            for (let i = 1; i < tableRows.length; i++) {
                                sheetData.appendChild(createRow(i + 5, tableRows[i], function(colIndex) {
                                    return colIndex === 1 ? styleLeft : styleCenter;
                                }, 24));
                            }

                            const mergeCellsNode = sheet.createElement('mergeCells');
                            ['A1:K1', 'A2:K2', 'A3:K3', 'A4:K4'].forEach(function(ref) {
                                const mergeCell = sheet.createElement('mergeCell');
                                mergeCell.setAttribute('ref', ref);
                                mergeCellsNode.appendChild(mergeCell);
                            });
                            mergeCellsNode.setAttribute('count', '4');
                            sheetData.parentNode.insertBefore(mergeCellsNode, sheetData.nextSibling);

                            let cols = $('cols', sheet)[0];
                            if (!cols) {
                                cols = sheet.createElement('cols');
                                sheetData.parentNode.insertBefore(cols, sheetData);
                            }
                            while (cols.firstChild) cols.removeChild(cols.firstChild);

                            const widths = [22, 38, 18, 18, 28, 10, 17, 18, 22, 30, 14];
                            widths.forEach(function(width, index) {
                                const col = sheet.createElement('col');
                                col.setAttribute('min', String(index + 1));
                                col.setAttribute('max', String(index + 1));
                                col.setAttribute('width', String(width));
                                col.setAttribute('customWidth', '1');
                                cols.appendChild(col);
                            });

                            const totalRows = tableRows.length + 4;
                            const dimension = $('dimension', sheet);
                            if (dimension.length) dimension.attr('ref', 'A1:K' + totalRows);
                        }
                    }],
                    order: [],
                    paging: true,
                    searching: true,
                    info: true,
                    autoWidth: false,
                    responsive: false,
                    scrollX: false,
                    columnDefs: [{
                            targets: [6, 7],
                            render: function(data, type) {
                                if (type === 'export') {
                                    let clean = String(data).replace(/<[^>]*>/g, '').trim();
                                    let match = clean.match(/[\d.,]+/);
                                    if (match) {
                                        return match[0].replace(/\./g, '').replace(',', '.');
                                    }
                                    return '0';
                                }
                                return data;
                            }
                        },
                        {
                            targets: '_all',
                            render: function(data, type) {
                                if (type === 'export') {
                                    return String(data).replace(/<[^>]*>/g, '').trim();
                                }
                                return data;
                            }
                        }
                    ],
                    initComplete: function() {
                        renderCustomPagination();
                        this.api().columns.adjust();
                    },
                    drawCallback: function() {
                        renderCustomPagination();
                    }
                });

                tabelaValores.columns.adjust();

                $('#btnExcel').on('click', function() {
                    tabelaValores.button('.buttons-excel').trigger();
                });

                $('#pesquisaValores').on('input', function() {
                    if (tabelaValores) {
                        tabelaValores.search(this.value).draw();
                    }
                });

                $('#btnClearSearchValores').on('click', function() {
                    $('#pesquisaValores').val('');
                    if (tabelaValores) {
                        tabelaValores.search('').draw();
                    }
                    $('#pesquisaValores').trigger('focus');
                });

                $(document).on('click', '#btnPrevPage', function() {
                    if (tabelaValores) {
                        tabelaValores.page('previous').draw('page');
                    }
                });

                $(document).on('click', '#btnNextPage', function() {
                    if (tabelaValores) {
                        tabelaValores.page('next').draw('page');
                    }
                });

                $(document).on('change', '#customPageLength', function() {
                    const novoLimite = parseInt($(this).val(), 10) || 10;
                    if (tabelaValores) {
                        tabelaValores.page.len(novoLimite).draw();
                    }
                });

                $(window).on('resize', function() {
                    if (tabelaValores) {
                        tabelaValores.columns.adjust();
                    }
                });
            }

            ajustarContrastePercentuais();

            $(window).on('resize', function() {
                ajustarContrastePercentuais();
            });

            <?php if ($filtros_aplicados): ?>
                $('button[data-bs-target="#modalFiltros"]').addClass('btn-filtro-applied');
            <?php endif; ?>

            <?php if (isset($_GET['aplicar_filtros'])): ?>
                $('#modalFiltros').modal('hide');
            <?php endif; ?>
        });

        function filtrarValor(tipo) {
            valorFilterModo = tipo;

            if (tabelaValores) {
                tabelaValores.page('first').draw(false);
            }

            if (tipo === 'todos') {
                showToast('Exibindo todas as entregas', 'info');
            } else if (tipo === 'com_valor') {
                showToast('Exibindo apenas entregas com valor aplicado', 'info');
            } else if (tipo === 'sem_valor') {
                showToast('Exibindo apenas entregas sem valor aplicado', 'info');
            }
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            setTimeout(() => toast.remove(), 3000);
        }

        const ctxMensal = document.getElementById('graficoMensal');
        if (ctxMensal) {
            new Chart(ctxMensal, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_reverse($labels_mes)) ?>,
                    datasets: [{
                        label: 'Valor Total (R$)',
                        data: <?= json_encode(array_reverse($valores_mes)) ?>,
                        backgroundColor: 'rgba(13, 110, 253, 0.1)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Valor (R$)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Valor')) {
                                        return label + ': R$ ' + context.parsed.y.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2
                                        });
                                    }
                                    return label + ': ' + context.parsed.y;
                                }
                            }
                        }
                    }
                }
            });
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
                            '#6A4C93', '#F15BB5', '#FF6B6B', '#48DBFB',
                            '#20c997', '#fd7e14', '#6f42c1', '#e83e8c'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 10
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return label + ': R$ ' + value.toLocaleString('pt-BR') + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }

        function removerFiltro(filtro) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filtro);
            window.location.href = url.toString();
        }
    </script>
</body>

</html>
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
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

// Processar filtros
$filtros_aplicados = false;
$filtro_mes = $_GET['mes'] ?? 'todos';
$filtro_ano = $_GET['ano'] ?? 'todos';
$filtro_beneficio = $_GET['beneficio'] ?? '';
$filtro_bairro = $_GET['bairro'] ?? '';
$filtro_status = $_GET['status'] ?? 'todos';
$filtro_valor_min = isset($_GET['valor_min']) && $_GET['valor_min'] !== '' ? (float)$_GET['valor_min'] : 1000;
$ordenacao = $_GET['ordenacao'] ?? 'valor_desc';
$filtro_data_inicio = $_GET['data_inicio'] ?? '';
$filtro_data_fim = $_GET['data_fim'] ?? '';
$filtro_responsavel = $_GET['responsavel'] ?? '';

// Verificar se há filtros aplicados
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

// Garantir valor mínimo de 1000
if ($filtro_valor_min < 1000) {
    $filtro_valor_min = 1000;
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
        ae.entregue,
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
        atp.periodicidade as tipo_periodicidade
        
    FROM ajudas_entregas ae
    INNER JOIN solicitantes s ON ae.pessoa_id = s.id
    INNER JOIN ajudas_tipos atp ON ae.ajuda_tipo_id = atp.id
    WHERE ae.valor_aplicado >= :valor_min
    AND ae.valor_aplicado IS NOT NULL
";

// Aplicar filtros
$params = [':valor_min' => $filtro_valor_min];

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

// Aplicar ordenação
$ordenacoes = [
    'valor_desc' => 'ae.valor_aplicado DESC',
    'valor_asc' => 'ae.valor_aplicado ASC',
    'data_desc' => 'ae.data_entrega DESC, ae.hora_entrega DESC',
    'data_asc' => 'ae.data_entrega ASC, ae.hora_entrega ASC',
    'nome_asc' => 's.nome ASC'
];

$sql_base .= " ORDER BY " . ($ordenacoes[$ordenacao] ?? 'ae.valor_aplicado DESC');

// Executar query
$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$beneficios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas
$total_valor = 0;
$total_entregas = count($beneficios);
$total_pessoas = 0;
$cpfs_unicos = [];

foreach ($beneficios as $beneficio) {
    $total_valor += (float)$beneficio['valor_aplicado'];
    if (!in_array($beneficio['pessoa_cpf'], $cpfs_unicos)) {
        $cpfs_unicos[] = $beneficio['pessoa_cpf'];
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
$sql_anos = "SELECT DISTINCT YEAR(data_entrega) as ano FROM ajudas_entregas WHERE valor_aplicado >= 1000 ORDER BY ano DESC";
$stmt_anos = $pdo->query($sql_anos);
$anos = $stmt_anos->fetchAll(PDO::FETCH_ASSOC);

// Buscar lista de responsáveis
$sql_responsaveis = "SELECT DISTINCT responsavel FROM ajudas_entregas WHERE responsavel IS NOT NULL AND responsavel != '' ORDER BY responsavel";
$stmt_responsaveis = $pdo->query($sql_responsaveis);
$responsaveis = $stmt_responsaveis->fetchAll(PDO::FETCH_ASSOC);

// Buscar distribuição por mês (para gráfico)
$sql_dist_mes = "
    SELECT 
        DATE_FORMAT(data_entrega, '%Y-%m') as mes,
        COUNT(*) as quantidade,
        SUM(valor_aplicado) as total_valor
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

// Preparar dados para gráfico
$labels_mes = [];
$valores_mes = [];
$quantidades_mes = [];

foreach ($distribuicao_mes as $mes) {
    $data = DateTime::createFromFormat('Y-m', $mes['mes']);
    $labels_mes[] = $data->format('M/Y');
    $valores_mes[] = (float)$mes['total_valor'];
    $quantidades_mes[] = (int)$mes['quantidade'];
}

// Se não tiver dados, criar arrays vazios para o gráfico
if (empty($distribuicao_mes)) {
    for ($i = 11; $i >= 0; $i--) {
        $date = new DateTime();
        $date->modify("-$i months");
        $labels_mes[] = $date->format('M/Y');
        $valores_mes[] = 0;
        $quantidades_mes[] = 0;
    }
}

// Calcular distribuição por tipo para gráfico
$dist_tipos = [];
foreach ($beneficios as $beneficio) {
    $tipo_nome = $beneficio['tipo_nome'];
    if (!isset($dist_tipos[$tipo_nome])) {
        $dist_tipos[$tipo_nome] = 0;
    }
    $dist_tipos[$tipo_nome] += (float)$beneficio['valor_aplicado'];
}

arsort($dist_tipos);
$labels_tipos = array_keys($dist_tipos);
$valores_tipos = array_values($dist_tipos);

// Se não tiver dados, criar um array vazio
if (empty($dist_tipos)) {
    $labels_tipos = ['Nenhum registro'];
    $valores_tipos = [1];
}

// Preparar dados para Top 5
$top5_beneficios = [];
if (!empty($beneficios)) {
    usort($beneficios, function ($a, $b) {
        return $b['valor_aplicado'] <=> $a['valor_aplicado'];
    });
    $top5_beneficios = array_slice($beneficios, 0, 5);
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benefícios Acima de R$ 1.000 - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
    <style>
        /* Reset e configurações gerais */
        * {
            box-sizing: border-box;
        }

        body {
            font-size: 14px;
        }

        /* Cards de estatísticas responsivos */
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
            /* Verde */
        }

        .card-statistic:nth-child(2) {
            border-left-color: #007bff !important;
            /* Azul */
        }

        .card-statistic:nth-child(3) {
            border-left-color: #ffc107 !important;
            /* Amarelo */
        }

        .card-statistic:nth-child(4) {
            border-left-color: #dc3545 !important;
            /* Vermelho */
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

        /* Layout responsivo para cartões de estatística */
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.5rem;
        }

        .stats-col {
            padding: 0 0.5rem;
            flex: 0 0 100%;
            max-width: 100%;
            margin-bottom: 1rem;
        }

        @media (min-width: 576px) {
            .stats-col {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }

        @media (min-width: 768px) {
            .stats-col {
                flex: 0 0 25%;
                max-width: 25%;
            }
        }

        /* Cards de mesma altura */
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

        /* Container para Top 5 */
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

        /* Container para gráfico - ALTURA REDUZIDA */
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

        /* Gráfico mensal - ALTURA REDUZIDA */
        .chart-container {
            height: 250px;
            position: relative;
            width: 100%;
        }

        /* Tabela responsiva */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Ajustes para tabela em telas pequenas */
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

            /* Ajustes para gráficos em mobile */
            .chart-container {
                height: 200px;
            }

            .grafico-container {
                min-height: 160px;
                max-height: 180px;
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


        /* Botões de ação na tabela */
        .btn-action-group {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
        }

        /* Filtros ativos responsivos */
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

        /* Valor mínimo input */
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

        /* Badges */
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

        /* Botões */
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
        }

        /* Header responsivo */
        .page-heading .row {
            align-items: center;
        }

        @media (max-width: 768px) {
            .page-heading .text-end {
                text-align: left !important;
                margin-top: 1rem;
            }

            .page-heading .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Ajustes para telas muito pequenas */
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

        /* Ajustes para tablets */
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

        /* Ajustes para desktop grande */
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

        /* Garantir que DataTables seja responsivo */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter,
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                float: none;
                text-align: left;
                margin-bottom: 0.5rem;
            }

            .dataTables_wrapper .dataTables_info {
                float: none;
                text-align: center;
                margin-bottom: 0.5rem;
            }

            .dataTables_wrapper .dataTables_paginate {
                float: none;
                text-align: center;
            }
        }

        /* Ajuste para que os cards sejam realmente da mesma altura */
        .card-height-fixed {
            height: 300px;
            overflow: hidden;
        }

        @media (max-width: 768px) {
            .card-height-fixed {
                height: 280px;
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
                        <li class="sidebar-item">
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

            <!-- Mostrar filtros ativos -->
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
                                        Ano: <?= $filtro_ano ?>
                                        <a href="#" class="text-white ms-1" onclick="removerFiltro('ano')">×</a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($filtro_beneficio): ?>
                                    <?php
                                    $beneficio_nome = '';
                                    foreach ($tipos_beneficios as $tipo) {
                                        if ($tipo['id'] == $filtro_beneficio) {
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
                                        Status: <?= $filtro_status == 'Sim' ? 'Entregue' : 'Pendente' ?>
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

                <!-- Cartões de Estatísticas -->
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

                <!-- Gráfico de Distribuição Mensal -->
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

                <!-- Tabela de Benefícios -->
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
                                        <p class="text-muted mb-0">Não há registros de benefícios acima de R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?> com os filtros aplicados.</p>
                                    </div>
                                <?php else: ?>
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
                                                    <th class="text-center">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($beneficios as $beneficio): ?>
                                                    <?php
                                                    $valor_unitario = $beneficio['quantidade'] > 0 ? $beneficio['valor_aplicado'] / $beneficio['quantidade'] : 0;
                                                    $classe_valor = ($beneficio['valor_aplicado'] >= 5000) ? 'valor-alto' : 'valor-cell';
                                                    ?>
                                                    <tr>
                                                        <td>
                                                            <div><?= date('d/m/Y', strtotime($beneficio['data_entrega'])) ?></div>
                                                            <small class="text-muted"><?= substr($beneficio['hora_entrega'], 0, 5) ?></small>
                                                        </td>
                                                        <td>
                                                            <strong class="d-block"><?= htmlspecialchars(mb_strimwidth($beneficio['solicitante_nome'], 0, 20, '...')) ?></strong>
                                                            <?php if ($beneficio['telefone']): ?>
                                                                <small class="text-muted"><?= $beneficio['telefone'] ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?= formatarCPF($beneficio['pessoa_cpf']) ?></td>
                                                        <td>
                                                            <span class="badge bg-primary d-inline-block text-truncate" style="max-width: 120px;"><?= htmlspecialchars($beneficio['tipo_nome']) ?></span>
                                                            <div><small class="text-muted"><?= $beneficio['tipo_categoria'] ?></small></div>
                                                        </td>
                                                        <td class="text-center"><?= $beneficio['quantidade'] ?></td>
                                                        <td class="<?= $classe_valor ?>"><?= formatarMoeda($valor_unitario) ?></td>
                                                        <td class="<?= $classe_valor ?>">
                                                            <strong><?= formatarMoeda($beneficio['valor_aplicado']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($beneficio['entregue'] === 'Sim'): ?>
                                                                <span class="badge badge-entregue bg-success">Entregue</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-pendente bg-warning">Pendente</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="d-none d-md-table-cell"><?= htmlspecialchars($beneficio['responsavel_entrega'] ?? 'N/A') ?></td>
                                                        <td class="text-center">
                                                            <div class="btn-action-group">
                                                                <button class="btn btn-sm btn-outline-info"
                                                                    onclick="verDetalhes(<?= $beneficio['id'] ?>)"
                                                                    title="Ver detalhes">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                <a href="registrarEntrega.php?editar=<?= $beneficio['id'] ?>"
                                                                    class="btn btn-sm btn-outline-warning"
                                                                    title="Editar">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <th colspan="5" class="text-end d-none d-md-table-cell">Total Geral:</th>
                                                    <th colspan="4" class="text-end d-table-cell d-md-none">Total Geral:</th>
                                                    <th class="text-center"><?= count($beneficios) ?> itens</th>
                                                    <th class="<?= $classe_valor ?>">
                                                        <strong><?= formatarMoeda($total_valor) ?></strong>
                                                    </th>
                                                    <th colspan="3"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Resumo Estatístico com cards de igual altura -->
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
                                                        <strong><?= $index + 1 ?>. <?= htmlspecialchars(mb_strimwidth($item['solicitante_nome'], 0, 25, '...')) ?></strong>
                                                        <div class="small text-muted">
                                                            <?= htmlspecialchars(mb_strimwidth($item['tipo_nome'], 0, 20, '...')) ?> •
                                                            <?= date('d/m/Y', strtotime($item['data_entrega'])) ?>
                                                        </div>
                                                    </div>
                                                    <div class="text-end flex-shrink-0">
                                                        <div class="valor-destaque text-danger"><?= formatarMoeda($item['valor_aplicado']) ?></div>
                                                        <small class="text-muted"><?= $item['quantidade'] ?> un.</small>
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

    <!-- Modal de Filtros -->
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
                                            <input type="date" name="data_inicio" class="form-control" value="<?= $filtro_data_inicio ?>">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Data Fim</label>
                                            <input type="date" name="data_fim" class="form-control" value="<?= $filtro_data_fim ?>">
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
                                                    echo "<option value='$valor' $selected>$nome</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Ano</label>
                                            <select name="ano" class="form-select">
                                                <option value="todos">Todos os anos</option>
                                                <?php foreach ($anos as $ano): ?>
                                                    <option value="<?= $ano['ano'] ?>" <?= ($filtro_ano == $ano['ano']) ? 'selected' : '' ?>>
                                                        <?= $ano['ano'] ?>
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
                                            value="<?= $filtro_valor_min ?>" min="1000" step="100" required>
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
                                            <option value="<?= $tipo['id'] ?>" <?= ($filtro_beneficio == $tipo['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tipo['nome']) ?>
                                                <?php if ($tipo['valor_padrao']): ?>
                                                    (R$ <?= number_format($tipo['valor_padrao'], 2, ',', '.') ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="todos">Todos os status</option>
                                        <option value="Sim" <?= ($filtro_status == 'Sim') ? 'selected' : '' ?>>Entregue</option>
                                        <option value="Não" <?= ($filtro_status == 'Não') ? 'selected' : '' ?>>Pendente</option>
                                    </select>
                                </div>

                                <div class="filtro-item mt-3">
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

                                <div class="filtro-item mt-3">
                                    <label class="form-label fw-bold">Ordenação</label>
                                    <select name="ordenacao" class="form-select">
                                        <option value="valor_desc" <?= ($ordenacao == 'valor_desc') ? 'selected' : '' ?>>Maior Valor</option>
                                        <option value="valor_asc" <?= ($ordenacao == 'valor_asc') ? 'selected' : '' ?>>Menor Valor</option>
                                        <option value="data_desc" <?= ($ordenacao == 'data_desc') ? 'selected' : '' ?>>Data Mais Recente</option>
                                        <option value="data_asc" <?= ($ordenacao == 'data_asc') ? 'selected' : '' ?>>Data Mais Antiga</option>
                                        <option value="nome_asc" <?= ($ordenacao == 'nome_asc') ? 'selected' : '' ?>>Nome A-Z</option>
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

    <!-- Modal de Ajuda -->
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
                        <li><strong>Exportação:</strong> Exporte os dados para Excel ou PDF (funcional)</li>
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

    <!-- Scripts -->
    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>

    <!-- jQuery (necessário para DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DataTables e plugins -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <!-- Botões de exportação -->
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>

    <!-- PDFMake para exportação PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

    <script>
        // Inicializar DataTable com exportação funcional
        $(document).ready(function() {
            const table = $('#tabelaBeneficios').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
                    decimal: ',',
                    thousands: '.'
                },
                pageLength: 25,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "Todos"]
                ],
                dom: 'Bfrtip',
                buttons: [{
                        extend: 'excel',
                        text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                        className: 'btn btn-success btn-sm',
                        title: 'Beneficios_Acima_Mil_<?= date('Y-m-d') ?>',
                        filename: 'beneficios_acima_mil_<?= date('Y-m-d') ?>',
                        exportOptions: {
                            columns: ':visible',
                            format: {
                                body: function(data, row, column, node) {
                                    // Formatar dados para Excel
                                    if (column === 5 || column === 6) { // Colunas de valor
                                        // Remover R$ e converter para número
                                        return data.replace('R$ ', '').replace('.', '').replace(',', '.');
                                    }
                                    return data;
                                }
                            }
                        },
                        customize: function(xlsx) {
                            var sheet = xlsx.xl.worksheets['sheet1.xml'];
                            $('row c[r^="F"]', sheet).attr('s', '2'); // Formato moeda para coluna F
                            $('row c[r^="G"]', sheet).attr('s', '2'); // Formato moeda para coluna G
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm',
                        title: 'Benefícios Acima de R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?> - ANEXO',
                        filename: 'beneficios_acima_mil_<?= date('Y-m-d') ?>',
                        exportOptions: {
                            columns: ':visible'
                        },
                        customize: function(doc) {
                            doc.content[1].table.widths = ['10%', '15%', '12%', '12%', '8%', '10%', '12%', '8%', '13%'];
                            doc.styles.tableHeader = {
                                fillColor: '#0d6efd',
                                color: 'white',
                                bold: true
                            };
                            doc.defaultStyle = {
                                fontSize: 10
                            };
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="bi bi-printer"></i> Imprimir',
                        className: 'btn btn-primary btn-sm',
                        title: 'Benefícios Acima de R$ <?= number_format($filtro_valor_min, 2, ',', '.') ?>',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],
                order: [], // Remover ordenação inicial
                responsive: true,
                drawCallback: function(settings) {
                    // Atualizar contador
                    const api = this.api();
                    const total = api.rows().count();
                    $('.dataTables_info').html(`Mostrando ${total} registros`);
                }
            });

            // Botões personalizados
            $('#btnExcel').on('click', function() {
                $('.buttons-excel').click();
            });

            $('#btnPDF').on('click', function() {
                $('.buttons-pdf').click();
            });

            // Atualizar botão de filtros se houver filtros aplicados
            <?php if ($filtros_aplicados): ?>
                $('button[data-bs-target="#modalFiltros"]').addClass('btn-filtro-applied');
            <?php endif; ?>

            // Função para igualar altura dos cards
            function equalizeCardHeights() {
                const cards = document.querySelectorAll('.equal-height .card');
                let maxHeight = 0;

                // Resetar altura
                cards.forEach(card => {
                    card.style.height = 'auto';
                });

                // Calcular a maior altura
                cards.forEach(card => {
                    const height = card.offsetHeight;
                    if (height > maxHeight) {
                        maxHeight = height;
                    }
                });

                // Aplicar a maior altura a todos os cards
                cards.forEach(card => {
                    card.style.height = maxHeight + 'px';
                });
            }

            // Executar quando a página carregar e quando redimensionar
            window.addEventListener('load', function() {
                equalizeCardHeights();
                // Executar novamente após um pequeno delay para garantir que os gráficos carregaram
                setTimeout(equalizeCardHeights, 500);
            });

            window.addEventListener('resize', equalizeCardHeights);

            // Executar também após os gráficos serem renderizados
            let chartsLoaded = 0;
            const totalCharts = 2; // Temos 2 gráficos

            function checkChartsLoaded() {
                chartsLoaded++;
                if (chartsLoaded === totalCharts) {
                    setTimeout(equalizeCardHeights, 100);
                }
            }

            // Esta função será chamada quando cada gráfico for criado
            window.chartLoaded = checkChartsLoaded;
        });

        // Gráfico de Distribuição Mensal
        const ctxMensal = document.getElementById('graficoMensal');
        if (ctxMensal) {
            const chartMensal = new Chart(ctxMensal, {
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
                        intersect: false,
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
                                    return 'R$ ' + value.toLocaleString('pt-BR');
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
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
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

            // Notificar que o gráfico foi carregado
            if (typeof window.chartLoaded === 'function') {
                window.chartLoaded();
            }
        }

        // Gráfico de Distribuição por Tipo - Altura ajustada
        const ctxDist = document.getElementById('graficoDistribuicao');
        if (ctxDist) {
            const chartDist = new Chart(ctxDist, {
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
                    cutout: '50%', // Controla o tamanho do buraco no meio
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

            // Notificar que o gráfico foi carregado
            if (typeof window.chartLoaded === 'function') {
                window.chartLoaded();
            }
        }

        // Funções de exportação direta
        function exportarExcel() {
            $('.buttons-excel').click();
        }

        function exportarPDF() {
            $('.buttons-pdf').click();
        }

        function verDetalhes(id) {
            // Implementar modal de detalhes
            alert('Detalhes do benefício ID: ' + id + '\n\nEm desenvolvimento: Modal com informações completas.');
        }

        // Remover filtro individual
        function removerFiltro(filtro) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filtro);
            window.location.href = url.toString();
        }

        // Atualizar título com valor mínimo
        function atualizarTitulo() {
            const valorMin = document.querySelector('input[name="valor_min"]').value;
            const valorFormatado = parseFloat(valorMin).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.querySelector('h3').textContent = 'Benefícios Acima de R$ ' + valorFormatado;
        }

        // Atualizar título quando o valor mudar
        if (document.querySelector('input[name="valor_min"]')) {
            document.querySelector('input[name="valor_min"]').addEventListener('input', atualizarTitulo);
        }

        // Validar datas no modal de filtros
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

        // Abrir modal de filtros se houver parâmetros na URL
        <?php if (isset($_GET['aplicar_filtros'])): ?>
            $(document).ready(function() {
                $('#modalFiltros').modal('hide');
            });
        <?php endif; ?>
    </script>
</body>

</html>
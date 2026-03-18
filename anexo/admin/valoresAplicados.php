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

// Primeiro, vamos verificar quantas entregas existem no total
$sql_total_entregas = "SELECT COUNT(*) as total FROM ajudas_entregas";
$stmt_total = $pdo->query($sql_total_entregas);
$total_geral = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

// Verificar valor total aplicado
$sql_valor_total = "SELECT SUM(COALESCE(valor_aplicado, 0)) as total FROM ajudas_entregas";
$stmt_valor = $pdo->query($sql_valor_total);
$valor_total_geral = $stmt_valor->fetch(PDO::FETCH_ASSOC)['total'];


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

// Construir query base - usando LEFT JOIN para incluir TODAS as entregas
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
        atp.periodicidade as tipo_periodicidade,
        
        b.nome as bairro_nome
        
    FROM ajudas_entregas ae
    LEFT JOIN solicitantes s ON ae.pessoa_id = s.id
    LEFT JOIN ajudas_tipos atp ON ae.ajuda_tipo_id = atp.id
    LEFT JOIN bairros b ON s.bairro_id = b.id
    WHERE 1=1  -- Sem filtros iniciais
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
    'data_desc' => 'ae.data_entrega DESC, ae.hora_entrega DESC',
    'data_asc' => 'ae.data_entrega ASC, ae.hora_entrega ASC',
    'valor_desc' => 'ae.valor_aplicado DESC',
    'valor_asc' => 'ae.valor_aplicado ASC',
    'nome_asc' => 'COALESCE(s.nome, ae.pessoa_cpf) ASC'
];

$sql_base .= " ORDER BY " . ($ordenacoes[$ordenacao] ?? 'ae.data_entrega DESC');

// Debug: mostrar query
echo "<!-- DEBUG Query: " . htmlspecialchars($sql_base) . " -->\n";

// Executar query
$stmt = $pdo->prepare($sql_base);
$stmt->execute($params);
$entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estatísticas CORRIGIDAS
$total_valor = 0;
$total_entregas = count($entregas);
$total_pessoas = 0;
$total_quantidade = 0;
$cpfs_unicos = [];

foreach ($entregas as $entrega) {
    // Se valor_aplicado for nulo, considerar como 0
    $valor = $entrega['valor_aplicado'] ?? 0;
    $total_valor += (float)$valor;
    $total_quantidade += (int)$entrega['quantidade'];

    // Usar CPF do registro de entrega
    $cpf = $entrega['pessoa_cpf'] ?? '';
    if ($cpf && !in_array($cpf, $cpfs_unicos)) {
        $cpfs_unicos[] = $cpf;
        $total_pessoas++;
    }
}

echo "<!-- DEBUG: Total após filtros: $total_entregas entregas -->\n";
echo "<!-- DEBUG: Valor após filtros: R$ " . number_format($total_valor, 2, ',', '.') . " -->\n";

// Buscar lista de benefícios para filtro
$sql_tipos = "SELECT id, nome, valor_padrao FROM ajudas_tipos WHERE status = 'Ativa' ORDER BY nome";
$stmt_tipos = $pdo->query($sql_tipos);
$tipos_beneficios = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

// Buscar lista de bairros
$sql_bairros = "SELECT id, nome FROM bairros ORDER BY nome";
$stmt_bairros = $pdo->query($sql_bairros);
$bairros = $stmt_bairros->fetchAll(PDO::FETCH_ASSOC);

// Buscar anos disponíveis - de TODAS as entregas
$sql_anos = "SELECT DISTINCT YEAR(data_entrega) as ano FROM ajudas_entregas WHERE data_entrega IS NOT NULL ORDER BY ano DESC";
$stmt_anos = $pdo->query($sql_anos);
$anos = $stmt_anos->fetchAll(PDO::FETCH_ASSOC);

// Buscar lista de responsáveis
$sql_responsaveis = "SELECT DISTINCT responsavel FROM ajudas_entregas WHERE responsavel IS NOT NULL AND responsavel != '' ORDER BY responsavel";
$stmt_responsaveis = $pdo->query($sql_responsaveis);
$responsaveis = $stmt_responsaveis->fetchAll(PDO::FETCH_ASSOC);

// Buscar estatísticas por tipo de benefício - CORRIGIDO para incluir todas
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

// Buscar estatísticas por mês (últimos 12 meses) - CORRIGIDO para incluir todas
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
    $labels_mes[] = $data->format('M/Y');
    $valores_mes[] = (float)$mes['total_valor'];
    $quantidades_mes[] = (int)$mes['total_entregas'];
}

// Se não tiver dados, criar arrays vazios para o gráfico
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

// Buscar top 10 entregas por valor - CORRIGIDO para incluir todas
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

        /* Container para gráficos */
        .grafico-container {
            flex: 1;
            display: flex;
            min-height: 250px;
        }

        .grafico-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* Gráfico mensal */
        .chart-container {
            height: 250px;
            position: relative;
            width: 100%;
        }

        /* Distribuição por tipo - ajuste de tamanho */
        .distribuicao-container {
            height: 250px;
            position: relative;
            width: 100%;
        }


        /* Badges */
        .badge-entregue {
            background-color: #28c76f;
        }

        .badge-pendente {
            background-color: #ff9f43;
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

        /* Badges de filtro */
        .badge-filtro {
            background-color: #0d6efd;
            color: white;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
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

        /* Valor destacado */
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

        /* Lista de top 10 */
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

        /* Tabela de estatísticas por tipo */
        .table-stats {
            font-size: 0.875rem;
        }

        .table-stats th {
            font-weight: 600;
            background-color: #f8f9fa;
        }

        /* Ajustes para telas pequenas */
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

        /* Modal de detalhes */
        .modal-detalhes .modal-header {
            background-color: #0d6efd;
            color: white;
        }

        .modal-detalhes .info-item {
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-detalhes .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 150px;
        }

        .modal-detalhes .info-value {
            color: #212529;
        }

        /* Indicador de valor nulo */
        .valor-nulo {
            color: #6c757d;
            font-style: italic;
        }

        /* Estilo para dados incompletos */
        .dado-incompleto {
            color: #dc3545;
            font-style: italic;
        }

        .badge-incompleto {
            background-color: #dc3545;
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
                                <li class="submenu-item active">
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

                                <?php if ($filtro_bairro): ?>
                                    <?php
                                    $bairro_nome = '';
                                    foreach ($bairros as $b) {
                                        if ($b['id'] == $filtro_bairro) {
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

                                <a href="valoresAplicados.php" class="btn btn-sm btn-outline-danger align-self-center ms-2">
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
                        <div class="col-12 col-md-6 col-lg-6">
                            <div class="card card-statistic">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="statistic-icon me-3">
                                            <i class="bi bi-cash-stack"></i>
                                        </div>
                                        <div class="w-100">
                                            <h6 class="text-muted mb-1">Valor Total Aplicado</h6>
                                            <div class="valor-destaque"><?= formatarMoeda($total_valor) ?></div>
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

                <!-- Gráficos e Análises -->
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

                <!-- Top 10 Entregas por Valor -->
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
                                                <div class="valor-destaque <?= ($entrega['valor_aplicado'] > 0) ? 'text-danger' : 'valor-nulo' ?>" style="font-size: 0.9rem;">
                                                    <?= ($entrega['valor_aplicado'] > 0) ? formatarMoeda($entrega['valor_aplicado']) : 'Sem valor' ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tabela Detalhada de Valores Aplicados -->
                <section class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                                <h5 class="mb-2 mb-md-0">Detalhamento de Todas as Entregas</h5>
                                <div class="d-flex gap-2">
                                    <button id="btnExcel" class="btn btn-success btn-sm">
                                        <i class="bi bi-file-earmark-excel"></i> <span class="">Excel</span>
                                    </button>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
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
                                    <div class="alert alert-info mb-3">
                                        <i class="bi bi-info-circle"></i>
                                        Mostrando <strong><?= $total_entregas ?></strong> entregas de um total de <strong><?= $total_geral ?></strong> no sistema.
                                        <?php if ($filtros_aplicados): ?>
                                            <a href="valoresAplicados.php" class="alert-link">Ver todas</a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="tabelaValores" class="table table-hover table-striped text-nowrap">
                                            <thead>
                                                <tr>
                                                    <th class="text-nowrap">Data/Hora</th>
                                                    <th class="text-nowrap">Beneficiário</th>
                                                    <th class="text-nowrap">Telefone</th>
                                                    <th class="text-nowrap">CPF</th>
                                                    <th class="text-nowrap">Benefício</th>
                                                    <th class="text-nowrap">Qtd</th>
                                                    <th class="text-nowrap">Valor Unit.</th>
                                                    <th class="text-nowrap">Valor Total</th>
                                                    <th class="text-nowrap">Bairro</th>
                                                    <th class="text-nowrap">Responsável</th>
                                                    <th class="text-nowrap">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($entregas as $entrega): ?>
                                                    <?php
                                                    $valor_aplicado = $entrega['valor_aplicado'] ?? 0;
                                                    $valor_unitario = $entrega['quantidade'] > 0 ? $valor_aplicado / $entrega['quantidade'] : 0;
                                                    $classe_valor = ($valor_aplicado >= 1000) ? 'valor-alto' : 'valor-cell';
                                                    $tem_valor = !empty($valor_aplicado) && $valor_aplicado > 0;
                                                    $classe_linha = $tem_valor ? '' : 'valor-nulo';

                                                    // Verificar se dados estão completos
                                                    $nome = $entrega['solicitante_nome'] ?? '';
                                                    $tipo_nome = $entrega['tipo_nome'] ?? '';
                                                    $dados_incompletos = empty($nome) || empty($tipo_nome);
                                                    ?>
                                                    <tr class="<?= $classe_linha ?> <?= $dados_incompletos ? 'table-warning' : '' ?>"
                                                        data-tem-valor="<?= $tem_valor ? '1' : '0' ?>">
                                                        <td class="text-nowrap">
                                                            <div><?= date('d/m/Y', strtotime($entrega['data_entrega'])) ?></div>
                                                            <small class="text-muted"><?= substr($entrega['hora_entrega'], 0, 5) ?></small>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?php if (!empty($nome)): ?>
                                                                <strong><?= htmlspecialchars($nome) ?></strong>
                                                            <?php else: ?>
                                                                <span class="dado-incompleto">Não identificado</span>
                                                                <?php if (!empty($entrega['pessoa_cpf'])): ?>
                                                                    <small class="text-muted d-block">CPF: <?= formatarCPF($entrega['pessoa_cpf']) ?></small>
                                                                <?php endif; ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?php if ($entrega['telefone']): ?>
                                                                <?= preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $entrega['telefone']) ?>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?= $entrega['pessoa_cpf'] ? formatarCPF($entrega['pessoa_cpf']) : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?php if (!empty($tipo_nome)): ?>
                                                                <span class="badge bg-primary d-inline-block text-truncate" style="max-width: 120px;">
                                                                    <?= htmlspecialchars($tipo_nome) ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge badge-incompleto">Tipo não identificado</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center text-nowrap"><?= $entrega['quantidade'] ?></td>
                                                        <td class="text-nowrap <?= $tem_valor ? 'valor-cell' : 'valor-nulo' ?>">
                                                            <?= $tem_valor ? formatarMoeda($valor_unitario) : '<span class="text-muted">-</span>' ?>
                                                        </td>
                                                        <td class="<?= $tem_valor ? $classe_valor : 'valor-nulo' ?> text-nowrap">
                                                            <strong>
                                                                <?= $tem_valor ? formatarMoeda($valor_aplicado) : '<span class="text-muted">Sem valor</span>' ?>
                                                            </strong>
                                                        </td>
                                                        <td class="text-nowrap">
                                                            <?php if ($entrega['bairro_nome']): ?>
                                                                <span class="badge bg-info"><?= htmlspecialchars($entrega['bairro_nome']) ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">N/A</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-nowrap"><?= htmlspecialchars($entrega['responsavel_entrega'] ?? 'N/A') ?></td>
                                                        <td class="text-nowrap text-center">
                                                            <span class="badge <?= $entrega['entregue'] === 'Sim' ? 'bg-success' : 'bg-warning' ?>">
                                                                <?= $entrega['entregue'] === 'Sim' ? 'Entregue' : 'Pendente' ?>
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
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Estatísticas por Tipo de Benefício -->
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
                                                $percentual = $total_valor > 0 ? ($tipo['total_valor'] / $total_valor) * 100 : 0;
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($tipo['nome']) ?></td>
                                                    <td class="text-center"><?= $tipo['total_entregas'] ?></td>
                                                    <td class="text-center"><?= $tipo['total_quantidade'] ?></td>
                                                    <td class="text-center valor-cell"><?= formatarMoeda($tipo['total_valor']) ?></td>
                                                    <td class="text-center"><?= formatarMoeda($tipo['valor_medio']) ?></td>
                                                    <td class="text-center">
                                                        <div class="progress" style="height: 20px;">
                                                            <div class="progress-bar bg-success"
                                                                role="progressbar"
                                                                style="width: <?= $percentual ?>%"
                                                                aria-valuenow="<?= $percentual ?>"
                                                                aria-valuemin="0"
                                                                aria-valuemax="100">
                                                                <?= number_format($percentual, 1) ?>%
                                                            </div>
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

    <!-- Modal de Filtros -->
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
                                            <input type="date" name="data_inicio" class="form-control" value="<?= $filtro_data_inicio ?>">
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <label class="form-label small">Data Fim</label>
                                            <input type="date" name="data_fim" class="form-control" value="<?= $filtro_data_fim ?>">
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

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Bairro</label>
                                    <select name="bairro" class="form-select">
                                        <option value="">Todos os bairros</option>
                                        <?php foreach ($bairros as $bairro): ?>
                                            <option value="<?= $bairro['id'] ?>" <?= ($filtro_bairro == $bairro['id']) ? 'selected' : '' ?>>
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
                                            <option value="<?= $tipo['id'] ?>" <?= ($filtro_beneficio == $tipo['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tipo['nome']) ?>
                                                <?php if ($tipo['valor_padrao']): ?>
                                                    (R$ <?= number_format($tipo['valor_padrao'], 2, ',', '.') ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="todos">Todos os status</option>
                                        <option value="Sim" <?= ($filtro_status == 'Sim') ? 'selected' : '' ?>>Entregue</option>
                                        <option value="Não" <?= ($filtro_status == 'Não') ? 'selected' : '' ?>>Pendente</option>
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
                            <strong>Nota:</strong> Agora exibindo todas as entregas (com e sem valor aplicado). Use os filtros para resultados específicos.
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

    <!-- Modal de Ajuda -->
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

    <script>
        // Inicializar DataTable com exportação para Excel apenas
        $(document).ready(function() {
            const table = $('#tabelaValores').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/pt-BR.json',
                    decimal: ',',
                    thousands: '.'
                },
                pageLength: 10,
                lengthMenu: [
                    [10, 25, 50, 100, -1],
                    [10, 25, 50, 100, "Todos"]
                ],
                dom: 'Bfrtip',
                buttons: [{
                    extend: 'excel',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                    className: 'btn btn-success btn-sm d-none',
                    title: 'Valores Aplicados - ANEXO',
                    filename: 'valores_aplicados_<?= date('Y-m-d') ?>',
                    exportOptions: {
                        columns: ':visible',
                        stripHtml: true,
                        format: {
                            body: function(data, row, column, node) {
                                data = data.replace(/<[^>]*>/g, '');

                                if (column === 6 || column === 7) {
                                    let match = data.match(/[\d.,]+/);
                                    if (match) {
                                        let valor = match[0].replace(/\./g, '').replace(',', '.');
                                        return parseFloat(valor) || 0;
                                    }
                                    return 0;
                                }

                                return data.trim();
                            }
                        }
                    },
                    customize: function(xlsx) {
                        var sheet = xlsx.xl.worksheets['sheet1.xml'];
                        $('row c[r^="G"]', sheet).attr('s', '44');
                        $('row c[r^="H"]', sheet).attr('s', '44');
                        $('col', sheet).each(function() {
                            $(this).attr('width', '15');
                        });
                    }
                }],
                order: [],
                responsive: true,
                drawCallback: function(settings) {
                    const api = this.api();
                    const total = api.rows().count();
                    $('.dataTables_info').html(`Mostrando ${total} registros`);
                },
                columnDefs: [{
                        targets: [6, 7],
                        render: function(data, type, row) {
                            if (type === 'export') {
                                let match = data.match(/[\d.,]+/);
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
                        render: function(data, type, row) {
                            if (type === 'export') {
                                return data.replace(/<[^>]*>/g, '').trim();
                            }
                            return data;
                        }
                    }
                ]
            });

            $('#btnExcel').on('click', function() {
                table.button('.buttons-excel').trigger();
            });

            <?php if ($filtros_aplicados): ?>
                $('button[data-bs-target="#modalFiltros"]').addClass('btn-filtro-applied');
            <?php endif; ?>
        });

        function filtrarValor(tipo) {
            const table = $('#tabelaValores').DataTable();

            if (tipo === 'todos') {
                table.column(10).search('').draw();
                showToast('Exibindo todas as entregas', 'info');
            } else if (tipo === 'com_valor') {
                $.fn.dataTable.ext.search.push(
                    function(settings, data, dataIndex) {
                        const valor = data[7];
                        return valor.indexOf('Sem valor') === -1 && valor.indexOf('0,00') === -1;
                    }
                );
                table.draw();
                $.fn.dataTable.ext.search.pop();
                showToast('Exibindo apenas entregas com valor aplicado', 'info');
            } else if (tipo === 'sem_valor') {
                $.fn.dataTable.ext.search.push(
                    function(settings, data, dataIndex) {
                        const valor = data[7];
                        return valor.indexOf('Sem valor') !== -1 || valor.indexOf('0,00') !== -1;
                    }
                );
                table.draw();
                $.fn.dataTable.ext.search.pop();
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

        // Gráfico de Evolução Mensal
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

        // Gráfico de Distribuição por Tipo de Benefício
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

        <?php if (isset($_GET['aplicar_filtros'])): ?>
            $(document).ready(function() {
                $('#modalFiltros').modal('hide');
            });
        <?php endif; ?>
    </script>
</body>

</html>
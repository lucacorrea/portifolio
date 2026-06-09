<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
view_check();

$page_title = "Relatórios Inteligentes";

/* =========================
   FUNÇÕES AUXILIARES
========================= */
if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('format_money')) {
    function format_money($value)
    {
        return 'R$ ' . number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('normalize_spaces')) {
    function normalize_spaces(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return (string)$text;
    }
}

if (!function_exists('format_date_br')) {
    function format_date_br($value): string
    {
        if (empty($value)) {
            return '-';
        }

        $ts = strtotime((string)$value);
        if ($ts === false) {
            return '-';
        }

        return date('d/m/Y', $ts);
    }
}

if (!function_exists('secretaria_sigla')) {
    function secretaria_sigla(string $nome): string
    {
        $nomeUp = mb_strtoupper(normalize_spaces($nome), 'UTF-8');

        if (preg_match('/(?:-|\/)\s*([A-Z]{3,})\s*$/u', $nomeUp, $m)) {
            return trim($m[1]);
        }

        $map = [
            'SECRETARIA MUNICIPAL DA CASA CIVIL' => 'SMCC',
            'SECRETARIA MUNICIPAL DE ADMINISTRAÇÃO' => 'SEMAD',
            'SECRETARIA MUNICIPAL DE FAZENDA' => 'SEMFAZ',
            'SECRETARIA MUNICIPAL DE EDUCAÇÃO' => 'SEMED',
            'SECRETARIA MUNICIPAL DE SAÚDE' => 'SEMSA',
            'SECRETARIA MUNICIPAL DE CULTURA E TURISMO' => 'SECULT',
            'SECRETARIA MUNICIPAL DE COMUNICAÇÃO' => 'SEMCOM',
            'SECRETARIA MUNICIPAL DE PLANEJAMENTO' => 'SEMPLAN',
            'SECRETARIA MUNICIPAL DE OBRAS' => 'SEMOB',
            'SECRETARIA MUNICIPAL DE LIMPEZA PÚBLICA' => 'SEMLIP',
            'SECRETARIA MUNICIPAL DE ASSISTÊNCIA SOCIAL' => 'SEMAS',
            'SECRETARIA MUNICIPAL DE TERRAS E HABITAÇÃO' => 'SEMTH',
            'SECRETARIA MUNICIPAL DE MEIO AMBIENTE' => 'SEMMA',
            'SECRETARIA MUNICIPAL EXTRAORDINÁRIA' => 'SME',
            'PROCURADORIA GERAL DO MUNICÍPIO' => 'PGM',
            'CONTROLADORIA GERAL DO MUNICICÍPIO' => 'CGM',
            'SECRETARIA MUNICIPAL DE CIÊNCIA, TECNOLOGIA E INOVAÇÃO' => 'SMCTI',
            'SECRETARIA MUNICIPAL DE DESENVOLVIMENTO RURAL E ECONÔMICO' => 'SMDRE',
            'SECRETARIA MUNICIPAL DE SEGURANÇA PÚBLICA E DEFESA SOCIAL' => 'SMSPDS',
            'SECRETARIA MUNICIPAL DE ESPORTE' => 'SEMESP',
            'SECRETÁRIO MUNICIPAL DE RELAÇÕES INSTITUCIONAIS' => 'SMRI',
            'COORDENADORIA REGIONAL DE EDUCAÇÃO DE COARI' => 'CREC',
            'COMIÇÃO DE CONTRATAÇÃO DE COARI' => 'CCC',
            'SECRETARIA DE ESTADO DA EDUCAÇÃO E DESPORTO ESCOLAR COORDENADORIA REGIONAL DE EDUCAÇÃO DE COARI' => 'SEDUC',
        ];

        foreach ($map as $trecho => $sigla) {
            if (mb_strpos($nomeUp, $trecho) !== false) {
                return $sigla;
            }
        }

        if (preg_match('/\b([A-Z]{3,})\b/u', $nomeUp, $m)) {
            $candidato = trim($m[1]);

            $bloqueados = [
                'SECRETARIA',
                'MUNICIPAL',
                'GERAL',
                'COARI',
                'ESTADO',
                'EDUCACAO',
                'EDUCAÇÃO',
                'DEFESA',
                'SOCIAL',
                'PUBLICA',
                'PÚBLICA',
            ];

            if (!in_array($candidato, $bloqueados, true)) {
                return $candidato;
            }
        }

        $partes = preg_split('/\s+/u', preg_replace('/[^\p{L}\s\/-]+/u', '', $nomeUp));
        $ignorar = ['DE', 'DA', 'DO', 'DAS', 'DOS', 'E', 'A', 'O', 'AS', 'OS', 'MUNICIPAL', 'SECRETARIA'];

        $sigla = '';
        foreach ($partes as $parte) {
            $parte = trim($parte);
            if ($parte === '' || in_array($parte, $ignorar, true)) {
                continue;
            }
            $sigla .= mb_substr($parte, 0, 1, 'UTF-8');
        }

        return $sigla !== '' ? $sigla : $nomeUp;
    }
}

/* =========================
   FILTROS
========================= */
$sec_id          = isset($_GET['sec_id']) ? trim((string)$_GET['sec_id']) : '';
$forn_id         = isset($_GET['forn_id']) ? trim((string)$_GET['forn_id']) : '';
$produto         = isset($_GET['produto']) ? trim((string)$_GET['produto']) : '';
$periodo_inicio  = isset($_GET['inicio']) ? trim((string)$_GET['inicio']) : '';
$periodo_fim     = isset($_GET['fim']) ? trim((string)$_GET['fim']) : '';
$export          = isset($_GET['export']) ? trim((string)$_GET['export']) : '';
$tipo_agrupamento = isset($_GET['tipo_agrupamento']) ? trim((string)$_GET['tipo_agrupamento']) : 'fornecedor';
if ($export === 'pdf_fornecedor') {
    $tipo_agrupamento = 'fornecedor';
} elseif ($export === 'pdf_secretaria') {
    $tipo_agrupamento = 'secretaria';
} elseif (!in_array($tipo_agrupamento, ['fornecedor', 'secretaria', 'geral'], true)) {
    $tipo_agrupamento = 'fornecedor';
}
$page            = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$whereParts = [];
$params = [];

$whereParts[] = "1=1";

if ($sec_id !== '') {
    $whereParts[] = "o.secretaria_id = :sec_id";
    $params[':sec_id'] = (int)$sec_id;
}

if ($forn_id !== '') {
    $whereParts[] = "a.fornecedor_id = :forn_id";
    $params[':forn_id'] = (int)$forn_id;
}

if ($produto !== '') {
    $whereParts[] = "ia.produto LIKE :produto";
    $params[':produto'] = '%' . $produto . '%';
}

if ($periodo_inicio !== '') {
    $whereParts[] = "a.criado_em >= :inicio";
    $params[':inicio'] = $periodo_inicio . ' 00:00:00';
}

if ($periodo_fim !== '') {
    $whereParts[] = "a.criado_em <= :fim";
    $params[':fim'] = $periodo_fim . ' 23:59:59';
}

$where = implode(' AND ', $whereParts);

/* =========================
   LISTAS DOS FILTROS
========================= */
$secretarias = $pdo->query("SELECT * FROM secretarias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   RELATÓRIO PRINCIPAL
========================= */
$sql_secretarias = "
    SELECT
        s.id AS secretaria_id,
        s.nome AS secretaria_nome,
        MAX(a.criado_em) AS data_referencia,
        COALESCE(SUM(ia.quantidade), 0) AS total_qtd,
        COALESCE(SUM(ia.quantidade * ia.valor_unitario), 0) AS total_valor
    FROM itens_aquisicao ia
    INNER JOIN aquisicoes a ON ia.aquisicao_id = a.id
    INNER JOIN oficios o ON a.oficio_id = o.id
    INNER JOIN secretarias s ON o.secretaria_id = s.id
    WHERE $where
    GROUP BY s.id, s.nome
    ORDER BY data_referencia ASC, s.nome ASC
";

$stmt_secretarias = $pdo->prepare($sql_secretarias);
$stmt_secretarias->execute($params);
$relatorio_secretarias = $stmt_secretarias->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   TOTAIS GERAIS
========================= */
$total_geral = 0;
$total_qtd_geral = 0;

foreach ($relatorio_secretarias as $row) {
    $total_geral += (float)$row['total_valor'];
    $total_qtd_geral += (float)$row['total_qtd'];
}

/* =========================
   PAGINAÇÃO
========================= */
$per_page = 6;
$total_registros = count($relatorio_secretarias);
$total_pages = max(1, (int)ceil($total_registros / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;
$relatorio_secretarias_paginado = array_slice($relatorio_secretarias, $offset, $per_page);

/* =========================
   DADOS DO GRÁFICO
========================= */
$chart_labels = [];
$chart_labels_full = [];
$chart_values = [];

foreach ($relatorio_secretarias as $row) {
    $sigla = secretaria_sigla((string)$row['secretaria_nome']);
    $chart_labels[] = $sigla;
    $chart_labels_full[] = (string)$row['secretaria_nome'];
    $chart_values[] = (float)$row['total_valor'];
}

/* =========================
   TEXTO DOS FILTROS
========================= */
$nome_secretaria_filtro = 'Todas';
if ($sec_id !== '') {
    foreach ($secretarias as $s) {
        if ((string)$s['id'] === (string)$sec_id) {
            $nome_secretaria_filtro = $s['nome'];
            break;
        }
    }
}

$nome_fornecedor_filtro = 'Todos';
if ($forn_id !== '') {
    foreach ($fornecedores as $f) {
        if ((string)$f['id'] === (string)$forn_id) {
            $nome_fornecedor_filtro = $f['nome'];
            break;
        }
    }
}

$periodo_texto = 'Todos';
if ($periodo_inicio !== '' || $periodo_fim !== '') {
    $inicioTxt = $periodo_inicio !== '' ? date('d/m/Y', strtotime($periodo_inicio)) : '...';
    $fimTxt    = $periodo_fim !== '' ? date('d/m/Y', strtotime($periodo_fim)) : '...';
    $periodo_texto = $inicioTxt . ' até ' . $fimTxt;
}

function relatorio_cor_secretaria($cor): string
{
    $cor = strtoupper(trim((string)$cor));

    if (preg_match('/^#[0-9A-F]{6}$/', $cor)) {
        return $cor;
    }

    return '#D9E3F4';
}

function relatorio_texto_caixa_alta($texto): string
{
    $texto = normalize_spaces(strip_tags((string)$texto));

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($texto, 'UTF-8');
    }

    return strtoupper($texto);
}

function relatorio_resumir_descricao($descricao, int $limite = 180): string
{
    $texto = relatorio_texto_caixa_alta($descricao);

    if ($texto === '') {
        return 'DESCRIÇÃO NÃO INFORMADA';
    }

    if ($limite <= 0) {
        return $texto;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($texto, 'UTF-8') <= $limite) {
            return $texto;
        }

        return rtrim(mb_substr($texto, 0, $limite - 3, 'UTF-8')) . '...';
    }

    if (strlen($texto) <= $limite) {
        return $texto;
    }

    return rtrim(substr($texto, 0, $limite - 3)) . '...';
}

function relatorio_nome($valor, string $fallback): string
{
    $nome = relatorio_texto_caixa_alta($valor);

    return $nome !== '' ? $nome : $fallback;
}

function get_relatorio_geral_aquisicoes(PDO $pdo, string $where, array $params): array
{
    $sql = "
        SELECT
            a.id,
            a.numero_aq,
            a.valor_total,
            a.status,
            a.criado_em,
            o.numero AS oficio_num,
            o.resumo_itens,
            s.nome AS secretaria,
            s.cor_relatorio AS secretaria_cor,
            f.nome AS fornecedor,
            COALESCE(NULLIF(TRIM(o.resumo_itens), ''), GROUP_CONCAT(ia.produto ORDER BY ia.id ASC SEPARATOR '; '), '') AS descricao_relatorio
        FROM itens_aquisicao ia
        INNER JOIN aquisicoes a ON ia.aquisicao_id = a.id
        INNER JOIN oficios o ON a.oficio_id = o.id
        INNER JOIN secretarias s ON o.secretaria_id = s.id
        INNER JOIN fornecedores f ON a.fornecedor_id = f.id
        WHERE $where
        GROUP BY
            a.id,
            a.numero_aq,
            a.valor_total,
            a.status,
            a.criado_em,
            o.numero,
            o.resumo_itens,
            s.nome,
            s.cor_relatorio,
            f.nome
        ORDER BY
            f.nome ASC,
            s.nome ASC,
            a.criado_em ASC,
            a.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function agrupar_relatorio_geral_por_fornecedor(array $aquisicoes): array
{
    $grupos = [];
    $total = 0.0;

    foreach ($aquisicoes as $aquisicao) {
        $fornecedor = relatorio_nome($aquisicao['fornecedor'] ?? '', 'FORNECEDOR NÃO INFORMADO');
        $secretaria = relatorio_nome($aquisicao['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA');
        $cor = relatorio_cor_secretaria($aquisicao['secretaria_cor'] ?? '');
        $valor = (float)($aquisicao['valor_total'] ?? 0);

        if (!isset($grupos[$fornecedor])) {
            $grupos[$fornecedor] = [
                'nome' => $fornecedor,
                'quantidade' => 0,
                'total' => 0.0,
                'secretarias' => [],
            ];
        }

        if (!isset($grupos[$fornecedor]['secretarias'][$secretaria])) {
            $grupos[$fornecedor]['secretarias'][$secretaria] = [
                'nome' => $secretaria,
                'cor' => $cor,
                'quantidade' => 0,
                'total' => 0.0,
                'items' => [],
            ];
        }

        $grupos[$fornecedor]['quantidade']++;
        $grupos[$fornecedor]['total'] += $valor;
        $grupos[$fornecedor]['secretarias'][$secretaria]['quantidade']++;
        $grupos[$fornecedor]['secretarias'][$secretaria]['total'] += $valor;
        $grupos[$fornecedor]['secretarias'][$secretaria]['items'][] = $aquisicao;
        $total += $valor;
    }

    uasort($grupos, static function ($a, $b) {
        return strcmp((string)$a['nome'], (string)$b['nome']);
    });

    foreach ($grupos as &$grupo) {
        uasort($grupo['secretarias'], static function ($a, $b) {
            return strcmp((string)$a['nome'], (string)$b['nome']);
        });
    }
    unset($grupo);

    return [
        'grupos' => $grupos,
        'quantidade' => count($aquisicoes),
        'total' => $total,
    ];
}

function agrupar_relatorio_geral_por_secretaria(array $aquisicoes): array
{
    $grupos = [];
    $total = 0.0;

    foreach ($aquisicoes as $aquisicao) {
        $secretaria = relatorio_nome($aquisicao['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA');
        $fornecedor = relatorio_nome($aquisicao['fornecedor'] ?? '', 'FORNECEDOR NÃO INFORMADO');
        $cor = relatorio_cor_secretaria($aquisicao['secretaria_cor'] ?? '');
        $valor = (float)($aquisicao['valor_total'] ?? 0);

        if (!isset($grupos[$secretaria])) {
            $grupos[$secretaria] = [
                'nome' => $secretaria,
                'cor' => $cor,
                'quantidade' => 0,
                'total' => 0.0,
                'fornecedores' => [],
            ];
        }

        if (!isset($grupos[$secretaria]['fornecedores'][$fornecedor])) {
            $grupos[$secretaria]['fornecedores'][$fornecedor] = [
                'nome' => $fornecedor,
                'quantidade' => 0,
                'total' => 0.0,
                'items' => [],
            ];
        }

        $grupos[$secretaria]['quantidade']++;
        $grupos[$secretaria]['total'] += $valor;
        $grupos[$secretaria]['fornecedores'][$fornecedor]['quantidade']++;
        $grupos[$secretaria]['fornecedores'][$fornecedor]['total'] += $valor;
        $grupos[$secretaria]['fornecedores'][$fornecedor]['items'][] = $aquisicao;
        $total += $valor;
    }

    uasort($grupos, static function ($a, $b) {
        return strcmp((string)$a['nome'], (string)$b['nome']);
    });

    foreach ($grupos as &$grupo) {
        uasort($grupo['fornecedores'], static function ($a, $b) {
            return strcmp((string)$a['nome'], (string)$b['nome']);
        });
    }
    unset($grupo);

    return [
        'grupos' => $grupos,
        'quantidade' => count($aquisicoes),
        'total' => $total,
    ];
}

/* =========================
   EXPORTAÇÃO GERAL PDF / EXCEL
========================= */
if (in_array($export, ['excel', 'pdf', 'pdf_fornecedor', 'pdf_secretaria'], true)) {
    $is_pdf_export = in_array($export, ['pdf', 'pdf_fornecedor', 'pdf_secretaria'], true);
    $aquisicoes_export = get_relatorio_geral_aquisicoes($pdo, $where, $params);
    $agrupamento_geral = agrupar_relatorio_geral_por_fornecedor($aquisicoes_export);
    $agrupamento_secretarias = agrupar_relatorio_geral_por_secretaria($aquisicoes_export);
    $dados_por_fornecedor = $agrupamento_geral['grupos'];
    $dados_por_secretaria = $agrupamento_secretarias['grupos'];
    $quantidade_export = (int)$agrupamento_geral['quantidade'];
    $total_export = (float)$agrupamento_geral['total'];
    $descricao_limite = 180;
    $filename = 'relatorio_geral_aquisicoes_' . date('Ymd_His');
    $back_query = [
        'sec_id' => $sec_id,
        'forn_id' => $forn_id,
        'produto' => $produto,
        'inicio' => $periodo_inicio,
        'fim' => $periodo_fim,
    ];
    $back_url = 'relatorios.php?' . http_build_query($back_query);

    if ($is_pdf_export) {
        header('Content-Type: text/html; charset=UTF-8');
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
    }

    if ($is_pdf_export) {
        $tipo_agrupamento_label = [
            'fornecedor' => 'Por Fornecedor',
            'secretaria' => 'Por Secretaria',
            'geral' => 'Geral',
        ][$tipo_agrupamento] ?? 'Por Fornecedor';
        $descricao_limite_pdf = 220;
?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório Geral de Aquisições</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            @page {
                size: A4 landscape;
                margin: 6mm;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                background: #eef2f7;
                color: #1f2937;
                font-family: Arial, DejaVu Sans, sans-serif;
                font-size: 8px;
            }

            .print-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                padding: 14px 18px;
                background: #ffffff;
                border-bottom: 1px solid #dbe2ea;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .toolbar-title {
                color: #0f172a;
                font-size: 14px;
                font-weight: 800;
            }

            .toolbar-actions {
                display: flex;
                flex-wrap: wrap;
                gap: .6rem;
            }

            .pdf-btn {
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                border: 1px solid #dbe2ea;
                border-radius: 8px;
                padding: .62rem .9rem;
                background: #ffffff;
                color: #334155;
                text-decoration: none;
                font-weight: 800;
                cursor: pointer;
            }

            .pdf-btn-primary {
                background: #1f4e78;
                border-color: #1f4e78;
                color: #ffffff;
            }

            .pdf-wrap {
                padding: 10px;
                overflow-x: auto;
            }

            .pdf-page {
                width: 287mm;
                max-width: none;
                background: #ffffff;
                margin: 0 auto;
                padding: 5mm;
            }

            .titulo-relatorio {
                background: #1f4e78;
                border: 1px solid #1f4e78;
                color: #ffffff;
                text-align: center;
                font-size: 14px;
                font-weight: bold;
                padding: 6px;
                text-transform: uppercase;
            }

            .bloco-filtros,
            .resumo,
            .tabela-aquisicoes {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            .bloco-filtros {
                margin-top: 6px;
                margin-bottom: 8px;
            }

            .bloco-filtros td {
                border: 1px solid #aab2bd;
                padding: 4px;
                font-size: 8px;
                vertical-align: top;
            }

            .resumo {
                margin-bottom: 8px;
            }

            .resumo th {
                background: #e5e7eb;
                border: 1px solid #aab2bd;
                padding: 5px;
                text-align: center;
                font-size: 8px;
            }

            .resumo td {
                border: 1px solid #aab2bd;
                padding: 5px;
                text-align: center;
                font-weight: bold;
                font-size: 8px;
            }

            .grupo-fornecedor,
            .grupo-secretaria {
                page-break-after: always;
                margin-bottom: 12px;
            }

            .grupo-fornecedor:last-child,
            .grupo-secretaria:last-child {
                page-break-after: auto;
            }

            .cabecalho-fornecedor {
                background: #d9eaf7;
                border: 1px solid #7fa6c9;
                padding: 6px;
                font-size: 10px;
                font-weight: bold;
                color: #1f2937;
                margin-top: 8px;
                page-break-after: avoid;
            }

            .cabecalho-secretaria {
                background: #eaf4ea;
                border: 1px solid #a7c7a7;
                padding: 5px;
                font-size: 9px;
                font-weight: bold;
                color: #1f2937;
                margin-top: 6px;
                page-break-after: avoid;
            }

            .tabela-aquisicoes {
                margin-top: 4px;
                page-break-inside: auto;
            }

            .tabela-aquisicoes th {
                background: #2f5597;
                color: #ffffff;
                border: 1px solid #7f8fa6;
                padding: 4px;
                font-size: 7.5px;
                text-align: center;
            }

            .tabela-aquisicoes td {
                border: 1px solid #b6c2d1;
                padding: 4px;
                font-size: 7.5px;
                vertical-align: top;
                word-wrap: break-word;
                overflow-wrap: break-word;
            }

            .tabela-aquisicoes tr:nth-child(even) td {
                background: #f7f9fc;
            }

            .col-numero { width: 12%; }
            .col-oficio { width: 14%; }
            .col-fornecedor { width: 20%; }
            .col-secretaria { width: 20%; }
            .col-descricao { width: 42%; }
            .col-data { width: 12%; text-align: center; }
            .col-valor { width: 12%; text-align: right; }

            .subtotal td,
            .subtotal {
                background: #e5e7eb !important;
                font-weight: bold;
            }

            .total-grupo {
                background: #d9e2f3;
                border: 1px solid #7f8fa6;
                font-weight: bold;
                padding: 6px;
                text-align: right;
                margin-top: 5px;
            }

            .total-geral {
                background: #1f4e78;
                color: #ffffff;
                font-weight: bold;
                padding: 7px;
                text-align: right;
                margin-top: 8px;
            }

            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .descricao-cell { line-height: 1.25; }

            tr {
                page-break-inside: avoid;
            }

            @media print {
                body {
                    background: #ffffff !important;
                }

                .no-print {
                    display: none !important;
                }

                .pdf-wrap {
                    padding: 0 !important;
                }

                .pdf-page {
                    margin: 0 !important;
                    width: 100% !important;
                    padding: 0 !important;
                }
            }
        </style>
    </head>

    <body>
        <div class="print-toolbar no-print">
            <div class="toolbar-title">Relatório Geral - <?php echo h($tipo_agrupamento_label); ?></div>
            <div class="toolbar-actions">
                <a href="<?php echo h($back_url); ?>" class="pdf-btn">
                    <i class="fas fa-arrow-left"></i> Voltar
                </a>
                <button type="button" class="pdf-btn pdf-btn-primary" onclick="window.print()">
                    <i class="fas fa-file-pdf"></i> Imprimir / Salvar PDF
                </button>
            </div>
        </div>

        <div class="pdf-wrap">
            <div class="pdf-page">
                <div class="titulo-relatorio">RELATÓRIO DE AQUISIÇÕES</div>

                <table class="bloco-filtros">
                    <tr>
                        <td><strong>Agrupamento:</strong> <?php echo h($tipo_agrupamento_label); ?></td>
                        <td><strong>Produto:</strong> <?php echo $produto !== '' ? h($produto) : 'Todos'; ?></td>
                        <td><strong>Emitido em:</strong> <?php echo date('d/m/Y H:i:s'); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Secretaria:</strong> <?php echo h($nome_secretaria_filtro); ?></td>
                        <td><strong>Fornecedor:</strong> <?php echo h($nome_fornecedor_filtro); ?></td>
                        <td><strong>Período:</strong> <?php echo h($periodo_texto); ?></td>
                    </tr>
                </table>

                <table class="resumo">
                    <tr>
                        <th>Total de Aquisições</th>
                        <th>Valor Total Geral</th>
                        <th>Fornecedores</th>
                        <th>Secretarias</th>
                    </tr>
                    <tr>
                        <td><?php echo $quantidade_export; ?></td>
                        <td><?php echo format_money($total_export); ?></td>
                        <td><?php echo count($dados_por_fornecedor); ?></td>
                        <td><?php echo count($dados_por_secretaria); ?></td>
                    </tr>
                </table>

                <?php if (empty($aquisicoes_export)): ?>
                    <table class="tabela-aquisicoes">
                        <tr>
                            <td class="text-center">Nenhuma aquisição encontrada para os filtros selecionados.</td>
                        </tr>
                    </table>
                <?php elseif ($tipo_agrupamento === 'secretaria'): ?>
                    <?php foreach ($dados_por_secretaria as $secretaria): ?>
                        <div class="grupo-secretaria">
                            <?php $cor_secretaria = h(relatorio_cor_secretaria($secretaria['cor'] ?? '')); ?>
                            <div class="cabecalho-secretaria" bgcolor="<?php echo $cor_secretaria; ?>" style="background-color: <?php echo $cor_secretaria; ?>;">SECRETARIA: <?php echo h($secretaria['nome']); ?></div>
                            <table class="resumo">
                                <tr>
                                    <th>Total de Aquisições</th>
                                    <th>Valor Total da Secretaria</th>
                                    <th>Fornecedores Envolvidos</th>
                                    <th>Período</th>
                                </tr>
                                <tr>
                                    <td><?php echo (int)$secretaria['quantidade']; ?></td>
                                    <td><?php echo format_money($secretaria['total']); ?></td>
                                    <td><?php echo count($secretaria['fornecedores']); ?></td>
                                    <td><?php echo h($periodo_texto); ?></td>
                                </tr>
                            </table>

                            <?php foreach ($secretaria['fornecedores'] as $fornecedor): ?>
                                <div class="cabecalho-fornecedor">FORNECEDOR: <?php echo h($fornecedor['nome']); ?></div>
                                <table class="tabela-aquisicoes">
                                    <colgroup>
                                        <col class="col-numero">
                                        <col class="col-oficio">
                                        <col class="col-descricao">
                                        <col class="col-data">
                                        <col class="col-valor">
                                    </colgroup>
                                    <tr>
                                        <th>Nº Aquisição</th>
                                        <th>Nº Ofício</th>
                                        <th>Descrição</th>
                                        <th>Data</th>
                                        <th>Valor</th>
                                    </tr>
                                    <?php foreach ($fornecedor['items'] as $aq): ?>
                                        <?php $descricao = relatorio_resumir_descricao($aq['descricao_relatorio'] ?? '', $descricao_limite_pdf); ?>
                                        <tr>
                                            <td class="text-center"><?php echo h($aq['numero_aq']); ?></td>
                                            <td class="text-center"><?php echo h($aq['oficio_num']); ?></td>
                                            <td class="descricao-cell"><?php echo h($descricao); ?></td>
                                            <td class="text-center"><?php echo h(format_date_br($aq['criado_em'])); ?></td>
                                            <td class="text-right"><?php echo format_money($aq['valor_total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal">
                                        <td colspan="3" class="text-right">Subtotal do fornecedor</td>
                                        <td class="text-center"><?php echo (int)$fornecedor['quantidade']; ?> AQ</td>
                                        <td class="text-right"><?php echo format_money($fornecedor['total']); ?></td>
                                    </tr>
                                </table>
                            <?php endforeach; ?>

                            <div class="total-grupo">
                                TOTAL DA SECRETARIA: <?php echo format_money($secretaria['total']); ?>
                                &nbsp;|&nbsp; AQUISIÇÕES: <?php echo (int)$secretaria['quantidade']; ?>
                                &nbsp;|&nbsp; FORNECEDORES: <?php echo count($secretaria['fornecedores']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($tipo_agrupamento === 'geral'): ?>
                    <table class="tabela-aquisicoes">
                        <colgroup>
                            <col class="col-numero">
                            <col class="col-oficio">
                            <col class="col-fornecedor">
                            <col class="col-secretaria">
                            <col class="col-descricao">
                            <col class="col-data">
                            <col class="col-valor">
                        </colgroup>
                        <tr>
                            <th>Nº Aquisição</th>
                            <th>Nº Ofício</th>
                            <th>Fornecedor</th>
                            <th>Secretaria</th>
                            <th>Descrição</th>
                            <th>Data</th>
                            <th>Valor</th>
                        </tr>
                        <?php foreach ($aquisicoes_export as $aq): ?>
                            <?php $descricao = relatorio_resumir_descricao($aq['descricao_relatorio'] ?? '', $descricao_limite_pdf); ?>
                            <tr>
                                <td class="text-center"><?php echo h($aq['numero_aq']); ?></td>
                                <td class="text-center"><?php echo h($aq['oficio_num']); ?></td>
                                <td><?php echo h(relatorio_nome($aq['fornecedor'] ?? '', 'FORNECEDOR NÃO INFORMADO')); ?></td>
                                <td><?php echo h(relatorio_nome($aq['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA')); ?></td>
                                <td class="descricao-cell"><?php echo h($descricao); ?></td>
                                <td class="text-center"><?php echo h(format_date_br($aq['criado_em'])); ?></td>
                                <td class="text-right"><?php echo format_money($aq['valor_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <div class="total-geral">TOTAL GERAL: <?php echo format_money($total_export); ?> | AQUISIÇÕES: <?php echo $quantidade_export; ?></div>
                <?php else: ?>
                    <?php foreach ($dados_por_fornecedor as $fornecedor): ?>
                        <div class="grupo-fornecedor">
                            <div class="cabecalho-fornecedor">FORNECEDOR: <?php echo h($fornecedor['nome']); ?></div>
                            <table class="resumo">
                                <tr>
                                    <th>Total de Aquisições</th>
                                    <th>Valor Total Fornecido</th>
                                    <th>Secretarias Atendidas</th>
                                    <th>Período</th>
                                </tr>
                                <tr>
                                    <td><?php echo (int)$fornecedor['quantidade']; ?></td>
                                    <td><?php echo format_money($fornecedor['total']); ?></td>
                                    <td><?php echo count($fornecedor['secretarias']); ?></td>
                                    <td><?php echo h($periodo_texto); ?></td>
                                </tr>
                            </table>

                            <?php foreach ($fornecedor['secretarias'] as $secretaria): ?>
                                <?php $cor_secretaria = h(relatorio_cor_secretaria($secretaria['cor'] ?? '')); ?>
                                <div class="cabecalho-secretaria" bgcolor="<?php echo $cor_secretaria; ?>" style="background-color: <?php echo $cor_secretaria; ?>;">SECRETARIA: <?php echo h($secretaria['nome']); ?></div>
                                <table class="tabela-aquisicoes">
                                    <colgroup>
                                        <col class="col-numero">
                                        <col class="col-oficio">
                                        <col class="col-descricao">
                                        <col class="col-data">
                                        <col class="col-valor">
                                    </colgroup>
                                    <tr>
                                        <th>Nº Aquisição</th>
                                        <th>Nº Ofício</th>
                                        <th>Descrição</th>
                                        <th>Data</th>
                                        <th>Valor</th>
                                    </tr>
                                    <?php foreach ($secretaria['items'] as $aq): ?>
                                        <?php $descricao = relatorio_resumir_descricao($aq['descricao_relatorio'] ?? '', $descricao_limite_pdf); ?>
                                        <tr>
                                            <td class="text-center"><?php echo h($aq['numero_aq']); ?></td>
                                            <td class="text-center"><?php echo h($aq['oficio_num']); ?></td>
                                            <td class="descricao-cell"><?php echo h($descricao); ?></td>
                                            <td class="text-center"><?php echo h(format_date_br($aq['criado_em'])); ?></td>
                                            <td class="text-right"><?php echo format_money($aq['valor_total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal">
                                        <td colspan="3" class="text-right">Subtotal da secretaria</td>
                                        <td class="text-center"><?php echo (int)$secretaria['quantidade']; ?> AQ</td>
                                        <td class="text-right"><?php echo format_money($secretaria['total']); ?></td>
                                    </tr>
                                </table>
                            <?php endforeach; ?>

                            <div class="total-grupo">
                                TOTAL DO FORNECEDOR: <?php echo format_money($fornecedor['total']); ?>
                                &nbsp;|&nbsp; AQUISIÇÕES: <?php echo (int)$fornecedor['quantidade']; ?>
                                &nbsp;|&nbsp; SECRETARIAS ATENDIDAS: <?php echo count($fornecedor['secretarias']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($aquisicoes_export) && $tipo_agrupamento !== 'geral'): ?>
                    <div class="total-geral">TOTAL GERAL: <?php echo format_money($total_export); ?> | AQUISIÇÕES: <?php echo $quantidade_export; ?></div>
                <?php endif; ?>
            </div>
        </div>
    </body>

    </html>
<?php
        exit;
    }
?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório Geral de Aquisições</title>
        <?php if ($is_pdf_export): ?>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <?php endif; ?>
        <style>
            <?php if ($is_pdf_export): ?>
            @page {
                size: A4 landscape;
                margin: 6mm;
            }
            <?php endif; ?>

            * {
                box-sizing: border-box;
            }

            body {
                margin: <?php echo $is_pdf_export ? '0' : '18px'; ?>;
                background: <?php echo $is_pdf_export ? '#eef2f7' : '#ffffff'; ?>;
                color: #1f2937;
                font-family: Arial, sans-serif;
                font-size: <?php echo $is_pdf_export ? '9px' : '12px'; ?>;
            }

            table {
                border-collapse: collapse;
                width: 100%;
                table-layout: fixed;
            }

            .sheet td,
            .sheet th {
                border: 1px solid #7c8aa5;
                padding: <?php echo $is_pdf_export ? '3px 5px' : '7px 8px'; ?>;
                vertical-align: middle;
                word-wrap: break-word;
                overflow-wrap: anywhere;
                line-height: 1.15;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .title-main {
                background: #dbeafe;
                color: #0f172a;
                font-size: <?php echo $is_pdf_export ? '13px' : '18px'; ?>;
                font-weight: bold;
                text-align: center;
                padding: <?php echo $is_pdf_export ? '5px' : '12px'; ?>;
            }

            .sub-info {
                background: #f8fafc;
                font-size: <?php echo $is_pdf_export ? '9px' : '11px'; ?>;
            }

            .summary-label {
                background: #f8fafc;
                font-weight: bold;
                text-align: center;
            }

            .summary-value {
                text-align: center;
                font-weight: bold;
                font-size: <?php echo $is_pdf_export ? '10px' : '14px'; ?>;
                background: #ffffff;
            }

            .section-title {
                background: #1d4ed8;
                color: #ffffff;
                font-weight: bold;
                text-transform: uppercase;
                text-align: center;
            }

            .supplier-section td {
                background: #eef2ff;
                color: #0f172a;
                font-weight: bold;
                text-transform: uppercase;
            }

            .secretaria-card-title td {
                color: #0f172a;
                font-weight: bold;
                text-transform: uppercase;
                border-top: 2px solid #475569;
                border-bottom: 1px solid #7c8aa5;
            }

            .secretaria-card-head th {
                background: #f3f4f6;
                color: #111827;
                font-weight: bold;
                text-align: center;
            }

            .group-total-row td {
                background: #d9dde4;
                font-weight: bold;
                border-top: 2px solid #64748b;
                border-bottom: 2px solid #64748b;
            }

            .supplier-total-row td {
                background: #dbeafe;
                font-weight: bold;
                border-top: 2px solid #3b82f6;
            }

            .total-row td {
                background: #eef2ff;
                font-weight: bold;
                border-top: 2px solid #334155;
            }

            .spacer td {
                border: none !important;
                height: <?php echo $is_pdf_export ? '5px' : '8px'; ?>;
                padding: 0;
                background: transparent;
            }

            .left {
                text-align: left;
            }

            .center {
                text-align: center;
            }

            .right {
                text-align: right;
            }

            .text-cell {
                mso-number-format: "\@";
            }

            .desc-cell {
                font-weight: 600;
                text-transform: uppercase;
            }

            .money-cell {
                white-space: nowrap;
            }

            .print-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                padding: 14px 18px;
                background: #ffffff;
                border-bottom: 1px solid #dbe2ea;
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .toolbar-title {
                color: #0f172a;
                font-size: 14px;
                font-weight: 800;
            }

            .toolbar-actions {
                display: flex;
                flex-wrap: wrap;
                gap: .6rem;
            }

            .pdf-btn {
                display: inline-flex;
                align-items: center;
                gap: .45rem;
                border: 1px solid #dbe2ea;
                border-radius: 8px;
                padding: .62rem .9rem;
                background: #ffffff;
                color: #334155;
                text-decoration: none;
                font-weight: 800;
                cursor: pointer;
            }

            .pdf-btn-primary {
                background: #1d4ed8;
                border-color: #1d4ed8;
                color: #ffffff;
            }

            .pdf-wrap {
                padding: 10px;
                overflow-x: auto;
            }

            .pdf-page {
                width: 287mm;
                max-width: none;
                background: #ffffff;
                margin: 0 auto;
                padding: 5mm;
            }

            .sheet tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            @media print {
                body {
                    background: #ffffff !important;
                }

                .no-print {
                    display: none !important;
                }

                .pdf-wrap {
                    padding: 0 !important;
                }

                .pdf-page {
                    margin: 0 !important;
                    width: 100% !important;
                    padding: 0 !important;
                }
            }
        </style>
    </head>

    <body>
        <?php if ($is_pdf_export): ?>
            <div class="print-toolbar no-print">
                <div class="toolbar-title">Relatório Geral - PDF em paisagem</div>
                <div class="toolbar-actions">
                    <a href="<?php echo h($back_url); ?>" class="pdf-btn">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="button" class="pdf-btn pdf-btn-primary" onclick="window.print()">
                        <i class="fas fa-file-pdf"></i> Imprimir / Salvar PDF
                    </button>
                </div>
            </div>
            <div class="pdf-wrap">
                <div class="pdf-page">
        <?php endif; ?>

        <table class="sheet">
            <colgroup>
                <col style="width: 13%;">
                <col style="width: 16%;">
                <col style="width: 25%;">
                <col style="width: 28%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
            </colgroup>

            <tr>
                <td colspan="6" class="title-main">RELATÓRIO GERAL DE AQUISIÇÕES</td>
            </tr>
            <tr>
                <td colspan="6" class="sub-info left"><strong>Gerado em:</strong> <?php echo date('d/m/Y H:i:s'); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Secretaria:</strong> <?php echo h($nome_secretaria_filtro); ?></td>
                <td colspan="3" class="sub-info left"><strong>Fornecedor:</strong> <?php echo h($nome_fornecedor_filtro); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Produto:</strong> <?php echo $produto !== '' ? h($produto) : 'Todos'; ?></td>
                <td colspan="3" class="sub-info left"><strong>Período:</strong> <?php echo h($periodo_texto); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="summary-label">TOTAL DE AQUISIÇÕES</td>
                <td colspan="3" class="summary-label">VALOR TOTAL</td>
            </tr>
            <tr>
                <td colspan="3" class="summary-value"><?php echo $quantidade_export; ?></td>
                <td colspan="3" class="summary-value"><?php echo format_money($total_export); ?></td>
            </tr>

            <tr class="spacer">
                <td colspan="6"></td>
            </tr>

            <tr>
                <td colspan="6" class="section-title">AQUISIÇÕES INDIVIDUAIS POR FORNECEDOR E SECRETARIA</td>
            </tr>

            <?php if (empty($dados_por_fornecedor)): ?>
                <tr>
                    <td colspan="6" class="center">Nenhuma aquisição encontrada para os filtros selecionados.</td>
                </tr>
            <?php else: ?>
                <?php $fornecedor_index = 0; ?>
                <?php foreach ($dados_por_fornecedor as $fornecedor): ?>
                    <?php if ($fornecedor_index > 0): ?>
                        <tr class="spacer">
                            <td colspan="6"></td>
                        </tr>
                    <?php endif; ?>
                    <?php $fornecedor_index++; ?>
                    <tr class="supplier-section">
                        <td colspan="4" class="left">FORNECEDOR: <?php echo h($fornecedor['nome']); ?></td>
                        <td class="center"><?php echo (int)$fornecedor['quantidade']; ?> AQ</td>
                        <td class="right money-cell"><?php echo format_money($fornecedor['total']); ?></td>
                    </tr>

                    <?php foreach ($fornecedor['secretarias'] as $secretaria): ?>
                        <?php
                        $cor_secretaria = relatorio_cor_secretaria($secretaria['cor'] ?? '');
                        $cor_attr = h($cor_secretaria);
                        ?>
                        <tr class="spacer">
                            <td colspan="6"></td>
                        </tr>
                        <tr class="secretaria-card-title">
                            <td colspan="4" class="left" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;">SECRETARIA: <?php echo h($secretaria['nome']); ?></td>
                            <td class="center" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo (int)$secretaria['quantidade']; ?> AQ</td>
                            <td class="right money-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo format_money($secretaria['total']); ?></td>
                        </tr>
                        <tr class="secretaria-card-head">
                            <th>Nº Aquisição</th>
                            <th>Nº Ofício</th>
                            <th>Secretaria</th>
                            <th>Descrição</th>
                            <th>Data</th>
                            <th>Valor</th>
                        </tr>
                        <?php foreach ($secretaria['items'] as $aq): ?>
                            <?php $descricao = relatorio_resumir_descricao($aq['descricao_relatorio'] ?? '', $descricao_limite); ?>
                            <tr>
                                <td class="center text-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo h($aq['numero_aq']); ?></td>
                                <td class="center text-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo h($aq['oficio_num']); ?></td>
                                <td class="left text-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo h(relatorio_nome($aq['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA')); ?></td>
                                <td class="left text-cell desc-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo h($descricao); ?></td>
                                <td class="center" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo h(format_date_br($aq['criado_em'])); ?></td>
                                <td class="right money-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo format_money($aq['valor_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="group-total-row">
                            <td colspan="4" class="right">TOTAL DA SECRETARIA</td>
                            <td class="center"><?php echo (int)$secretaria['quantidade']; ?> AQ</td>
                            <td class="right money-cell"><?php echo format_money($secretaria['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="supplier-total-row">
                        <td colspan="4" class="right">TOTAL DO FORNECEDOR</td>
                        <td class="center"><?php echo (int)$fornecedor['quantidade']; ?> AQ</td>
                        <td class="right money-cell"><?php echo format_money($fornecedor['total']); ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="spacer">
                    <td colspan="6"></td>
                </tr>
                <tr class="total-row">
                    <td colspan="5" class="right">TOTAL GERAL</td>
                    <td class="right money-cell"><?php echo format_money($total_export); ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <?php if ($is_pdf_export): ?>
                </div>
            </div>
        <?php endif; ?>
    </body>

    </html>
<?php
    exit;
}

$query_base = [
    'sec_id'  => $sec_id,
    'forn_id' => $forn_id,
    'produto' => $produto,
    'inicio'  => $periodo_inicio,
    'fim'     => $periodo_fim,
];

include 'views/layout/header.php';
?>

<style>
    .relatorio-wrapper .card {
        border: 1px solid #e9edf5;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        overflow: hidden;
        background: #fff;
    }

    .relatorio-wrapper .card+.card,
    .relatorio-wrapper .row+.card,
    .relatorio-wrapper .card+.row {
        margin-top: 1.25rem;
    }

    .report-header-title {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin-bottom: 1.25rem;
    }

    .report-header-title .icon-box {
        width: 42px;
        height: 42px;
        min-width: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        background: rgba(32, 107, 196, 0.12);
        color: #206bc4;
        font-size: 1rem;
    }

    .report-header-title h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 800;
        color: #0f172a;
    }

    .report-header-title p {
        margin: .15rem 0 0;
        font-size: .88rem;
        color: #64748b;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        gap: 1rem;
    }

    .form-group label,
    .form-label {
        display: block;
        font-weight: 700;
        color: #334155;
        margin-bottom: .45rem;
        font-size: .88rem;
    }

    .form-control {
        width: 100%;
        min-height: 44px;
        border-radius: 12px;
        border: 1px solid #dbe2ea;
        padding: .7rem .9rem;
        transition: .2s ease;
        background: #fff;
    }

    .form-control:focus {
        outline: none;
        border-color: #206bc4;
        box-shadow: 0 0 0 4px rgba(32, 107, 196, 0.10);
    }

    .filter-actions {
        display: flex;
        justify-content: flex-end;
        gap: .75rem;
        margin-top: 1.25rem;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        border-radius: 12px;
        padding: .7rem 1rem;
        border: 1px solid transparent;
        text-decoration: none;
        cursor: pointer;
        font-weight: 700;
        transition: .2s ease;
    }

    .btn-sm {
        padding: .62rem .95rem;
        font-size: .85rem;
    }

    .btn-primary {
        background: #206bc4;
        border-color: #206bc4;
        color: #fff;
    }

    .btn-primary:hover {
        background: #1a5aa8;
        border-color: #1a5aa8;
        color: #fff;
    }

    .btn-outline {
        background: #fff;
        border-color: #dbe2ea;
        color: #334155;
    }

    .btn-outline:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .btn-success-custom {
        background: #198754;
        border-color: #198754;
        color: #fff;
    }

    .btn-success-custom:hover {
        background: #157347;
        border-color: #146c43;
        color: #fff;
    }

    .btn-pagination {
        min-width: 70px;
        height: 34px;
        padding: .4rem .85rem;
        border-radius: 8px;
        border: 1px solid #dbe2ea;
        background: #fff;
        color: #475569;
        font-size: .8rem;
        font-weight: 600;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        transition: .2s ease;
    }

    .btn-pagination:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #0f172a;
    }

    .btn-pagination.disabled,
    .btn-pagination[aria-disabled="true"] {
        pointer-events: none;
        opacity: .5;
        background: #f8fafc;
    }

    .summary-card {
        height: 100%;
        position: relative;
    }

    .summary-card .card-body {
        padding: 1.5rem;
    }

    .summary-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .85rem;
    }

    .summary-top .mini-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(32, 107, 196, 0.10);
        color: #206bc4;
    }

    .summary-label {
        color: #64748b;
        font-size: .82rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .35px;
        margin: 0;
    }

    .summary-number {
        font-size: 2rem;
        line-height: 1.1;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        word-break: break-word;
    }

    .summary-helper {
        margin-top: .6rem;
        color: #64748b;
        font-size: .9rem;
    }

    .chart-card-body {
        padding: 1.5rem;
    }

    .chart-wrap {
        position: relative;
        width: 100%;
        height: 320px;
    }

    .table-header-box {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }

    .report-actions {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .modern-table-wrap {
        border: 1px solid #e9edf5;
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
    }

    .table-scroll-x {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }

    .table-scroll-x::-webkit-scrollbar {
        height: 10px;
    }

    .table-scroll-x::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .table-scroll-x::-webkit-scrollbar-track {
        background: #f8fafc;
    }

    .modern-table {
        width: 100%;
        min-width: 760px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .modern-table thead th {
        background: #f8fafc;
        color: #334155;
        font-size: .86rem;
        font-weight: 800;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem .9rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .modern-table tbody td {
        padding: .95rem .9rem;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
        color: #0f172a;
        background: #fff;
        white-space: nowrap;
    }

    .modern-table tbody tr:hover>td {
        background: #fcfdff;
    }

    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }

    .td-secretaria {
        font-weight: 800;
        color: #206bc4 !important;
    }

    .td-right {
        text-align: right;
    }

    .td-center {
        text-align: center;
    }

    .text-nowrap {
        white-space: nowrap !important;
    }

    .badge-total {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: .42rem .75rem;
        border-radius: 999px;
        background: rgba(25, 135, 84, 0.10);
        color: #157347;
        font-weight: 800;
        font-size: .82rem;
        white-space: nowrap;
    }

    .total-row-main td {
        background: #f8fafc !important;
        font-weight: 800;
        color: #0f172a;
    }

    .empty-state {
        text-align: center;
        padding: 2.4rem 1rem !important;
        color: #64748b !important;
    }

    .pagination-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .65rem;
        flex-wrap: wrap;
        padding-top: 1.15rem;
    }

    .pagination-info {
        font-size: .86rem;
        color: #0f172a;
        font-weight: 700;
    }

    @media (max-width: 992px) {
        .summary-number {
            font-size: 1.6rem;
        }

        .chart-wrap {
            height: 280px;
        }
    }

    @media (max-width: 768px) {

        .filter-actions,
        .report-actions,
        .table-header-box {
            width: 100%;
        }

        .filter-actions .btn,
        .report-actions .btn {
            flex: 1 1 100%;
        }

        .chart-wrap {
            height: 250px;
        }

        .modern-table {
            min-width: 720px;
        }

        .modern-table thead th,
        .modern-table tbody td {
            padding: .75rem .7rem;
            font-size: .86rem;
        }

        .pagination-wrap {
            flex-direction: column;
        }
    }

    @media (max-width: 480px) {
        .modern-table {
            min-width: 680px;
        }
    }

    @media print {

        .no-print,
        .btn,
        button,
        .report-actions,
        .filter-actions,
        .pagination-wrap {
            display: none !important;
        }

        .relatorio-wrapper .card {
            box-shadow: none !important;
            border: 1px solid #d1d5db !important;
        }

        .table-scroll-x {
            overflow: visible !important;
        }

        .modern-table {
            min-width: 0 !important;
            width: 100% !important;
        }

        .modern-table th,
        .modern-table td {
            white-space: normal !important;
        }
    }
</style>

<div class="relatorio-wrapper">

    <div class="card no-print">
        <div class="card-body" style="padding: 1.5rem;">
            <div class="report-header-title">
                <div class="icon-box">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <h3>Filtros do Relatório</h3>
                    <p>Refine os resultados por secretaria, fornecedor, produto e período.</p>
                </div>
            </div>

            <form action="" method="GET">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Secretaria</label>
                        <select name="sec_id" class="form-control">
                            <option value="">Todas</option>
                            <?php foreach ($secretarias as $s): ?>
                                <option value="<?php echo h($s['id']); ?>" <?php echo $sec_id == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($s['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fornecedor</label>
                        <select name="forn_id" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($fornecedores as $f): ?>
                                <option value="<?php echo h($f['id']); ?>" <?php echo $forn_id == $f['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($f['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nome do Produto</label>
                        <input type="text" name="produto" class="form-control" placeholder="Buscar produto..." value="<?php echo h($produto); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Período de Início</label>
                        <input type="date" name="inicio" class="form-control" value="<?php echo h($periodo_inicio); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Período de Fim</label>
                        <input type="date" name="fim" class="form-control" value="<?php echo h($periodo_fim); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <a href="relatorios.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-eraser"></i> Limpar
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="fas fa-sync-alt"></i> Gerar Relatório
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row" style="margin-top: 1.25rem;">
        <div class="col-lg-8 col-12" style="margin-bottom: 1.25rem;">
            <div class="card summary-card">
                <div class="card-body chart-card-body">
                    <div class="report-header-title" style="margin-bottom: 1rem;">
                        <div class="icon-box">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div>
                            <h3>Distribuição de Gastos por Secretaria</h3>
                            <p>Visualização do valor total adquirido por secretaria.</p>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="chartSecretaria"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-12" style="margin-bottom: 1.25rem;">
            <div class="card summary-card">
                <div class="card-body">
                    <div class="summary-top">
                        <div>
                            <p class="summary-label">Total financeiro geral</p>
                            <h3 class="summary-number"><?php echo format_money($total_geral); ?></h3>
                        </div>
                        <div class="mini-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="summary-helper">
                        Soma de todos os valores encontrados conforme os filtros selecionados.
                    </div>
                </div>
            </div>

            <div class="card summary-card" style="margin-top: 1rem;">
                <div class="card-body">
                    <div class="summary-top">
                        <div>
                            <p class="summary-label">Quantidade total</p>
                            <h3 class="summary-number"><?php echo number_format((int)$total_qtd_geral, 0, ',', '.'); ?></h3>
                        </div>
                        <div class="mini-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                    <div class="summary-helper">
                        Quantidade acumulada dos itens de aquisições no período filtrado.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 1.5rem;">
            <div class="table-header-box">
                <div class="report-header-title" style="margin-bottom: 0;">
                    <div class="icon-box">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h3>Detalhamento por Secretaria</h3>
                        <p>Clique em detalhes para listar todas as aquisições da secretaria.</p>
                    </div>
                </div>

                <div class="report-actions no-print">
                    <a
                        href="?<?php echo h(http_build_query(array_merge($query_base, ['export' => 'excel']))); ?>"
                        class="btn btn-success-custom btn-sm">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </a>

                    <a
                        href="relatorios_pdf_fornecedores.php?<?php echo h(http_build_query($query_base)); ?>"
                        target="_blank"
                        class="btn btn-outline btn-sm">
                        <i class="fas fa-file-pdf"></i> PDF Fornecedores
                    </a>

                    <a
                        href="relatorios_pdf_secretarias.php?<?php echo h(http_build_query($query_base)); ?>"
                        target="_blank"
                        class="btn btn-outline btn-sm">
                        <i class="fas fa-file-pdf"></i> PDF Secretarias
                    </a>

                    <a
                        href="?<?php echo h(http_build_query(array_merge($query_base, ['export' => 'pdf', 'tipo_agrupamento' => 'geral']))); ?>"
                        target="_blank"
                        class="btn btn-outline btn-sm">
                        <i class="fas fa-file-pdf"></i> PDF Geral
                    </a>
                </div>
            </div>

            <div class="modern-table-wrap">
                <div class="table-scroll-x">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Secretaria</th>
                                <th class="td-right text-nowrap">Qtd Itens</th>
                                <th class="td-right text-nowrap">Valor Total</th>
                                <th class="no-print td-center text-nowrap" style="width: 150px;">Detalhes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($relatorio_secretarias_paginado)): ?>
                                <?php foreach ($relatorio_secretarias_paginado as $row): ?>
                                    <tr>
                                        <td class="td-secretaria text-nowrap"><?php echo h($row['secretaria_nome']); ?></td>
                                        <td class="td-right text-nowrap"><?php echo number_format((int)$row['total_qtd'], 0, ',', '.'); ?></td>
                                        <td class="td-right text-nowrap">
                                            <span class="badge-total"><?php echo format_money($row['total_valor']); ?></span>
                                        </td>
                                        <td class="no-print td-center text-nowrap">
                                            <a
                                                class="btn btn-primary btn-sm"
                                                href="relatorios_oficios_secretaria.php?<?php
                                                                                        echo h(http_build_query([
                                                                                            'sec_id'  => $row['secretaria_id'],
                                                                                            'forn_id' => $forn_id,
                                                                                            'produto' => $produto,
                                                                                            'inicio'  => $periodo_inicio,
                                                                                            'fim'     => $periodo_fim,
                                                                                        ]));
                                                                                        ?>">
                                                <i class="fas fa-search"></i> Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <tr class="total-row-main">
                                    <td class="text-nowrap">TOTAL GERAL</td>
                                    <td class="td-right text-nowrap"><?php echo number_format((int)$total_qtd_geral, 0, ',', '.'); ?></td>
                                    <td class="td-right text-nowrap"><?php echo format_money($total_geral); ?></td>
                                    <td class="no-print"></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        Nenhum dado encontrado para os filtros selecionados.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($total_registros > 0 && $total_pages > 1): ?>
                <div class="pagination-wrap no-print">
                    <?php
                    $prev_link = '?' . http_build_query(array_merge($query_base, ['page' => max(1, $page - 1)]));
                    $next_link = '?' . http_build_query(array_merge($query_base, ['page' => min($total_pages, $page + 1)]));
                    ?>

                    <?php if ($page > 1): ?>
                        <a href="<?php echo h($prev_link); ?>" class="btn-pagination">
                            <i class="fas fa-angle-left"></i> Anterior
                        </a>
                    <?php else: ?>
                        <span class="btn-pagination disabled" aria-disabled="true">
                            <i class="fas fa-angle-left"></i> Anterior
                        </span>
                    <?php endif; ?>

                    <div class="pagination-info">Página <?php echo $page; ?> de <?php echo $total_pages; ?></div>

                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo h($next_link); ?>" class="btn-pagination">
                            Próxima <i class="fas fa-angle-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="btn-pagination disabled" aria-disabled="true">
                            Próxima <i class="fas fa-angle-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartElement = document.getElementById('chartSecretaria');

        if (!chartElement) {
            return;
        }

        const labels = <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>;
        const fullLabels = <?php echo json_encode($chart_labels_full, JSON_UNESCAPED_UNICODE); ?>;
        const values = <?php echo json_encode($chart_values, JSON_UNESCAPED_UNICODE); ?>;

        if (!Array.isArray(labels) || labels.length === 0) {
            return;
        }

        const ctx = chartElement.getContext('2d');

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: [
                        '#206bc4',
                        '#2fb344',
                        '#f59e0b',
                        '#d63939',
                        '#6f42c1',
                        '#0ea5e9',
                        '#14b8a6',
                        '#f97316',
                        '#64748b',
                        '#8b5cf6',
                        '#ec4899',
                        '#22c55e',
                        '#eab308',
                        '#3b82f6',
                        '#ef4444'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '58%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 16,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const index = context[0]?.dataIndex ?? 0;
                                return fullLabels[index] || '';
                            },
                            label: function(context) {
                                const index = context.dataIndex ?? 0;
                                const sigla = labels[index] || '';
                                const valor = Number(context.raw || 0);

                                return sigla + ': ' + new Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL'
                                }).format(valor);
                            }
                        }
                    }
                }
            }
        });
    });
</script>

<?php include 'views/layout/footer.php'; ?>

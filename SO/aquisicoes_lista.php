<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = "Lista de Aquisições";

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function format_date_excel($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime((string)$date);
    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

function formatarMoedaBR($valor): string
{
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function formatarDataBR($data): string
{
    return format_date_excel($data);
}

function formatarTextoCaixaAlta($texto): string
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$texto));

    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($text, 'UTF-8');
    }

    return strtoupper($text);
}

function limparDescricaoItens($descricao): string
{
    $texto = strip_tags((string)$descricao);
    $texto = str_replace(["\r", "\n", "\t"], ' ', $texto);
    $texto = preg_replace('/\s+/u', ' ', $texto);

    return trim((string)$texto);
}

function resumirItensDescricao($descricao, int $limite = 180): string
{
    $texto = limparDescricaoItens($descricao);

    if ($texto === '') {
        return '-';
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

function formatarDescricaoRelatorio($descricao, int $limite = 180): string
{
    return formatarTextoCaixaAlta(resumirItensDescricao($descricao, $limite));
}

function corRelatorioSecretaria($cor): string
{
    $cor = strtoupper(trim((string)$cor));

    if (preg_match('/^#[0-9A-F]{6}$/', $cor)) {
        return $cor;
    }

    return '#D9E3F4';
}

function corRelatorioFornecedor(int $indice): string
{
    $cores = [
        '#FDE68A',
        '#BBF7D0',
        '#BFDBFE',
        '#FBCFE8',
        '#DDD6FE',
        '#FED7AA',
        '#CFFAFE',
        '#E9D5FF',
        '#D9F99D',
        '#FECACA',
    ];

    return $cores[$indice % count($cores)];
}

function nomeRelatorio($valor, string $fallback): string
{
    $nome = formatarTextoCaixaAlta($valor);

    return $nome !== '' ? $nome : $fallback;
}

function agruparAquisicoesPorFornecedor(array $aquisicoes): array
{
    $grupos = [];
    $totalGeral = 0.0;

    foreach ($aquisicoes as $aq) {
        $fornecedor = nomeRelatorio($aq['fornecedor'] ?? '', 'FORNECEDOR NÃO INFORMADO');
        $secretaria = nomeRelatorio($aq['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA');
        $secretariaCor = corRelatorioSecretaria($aq['secretaria_cor'] ?? '');
        $valor = (float)($aq['valor_total'] ?? 0);

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
                'cor' => $secretariaCor,
                'quantidade' => 0,
                'total' => 0.0,
                'items' => [],
            ];
        }

        $grupos[$fornecedor]['quantidade']++;
        $grupos[$fornecedor]['total'] += $valor;
        $grupos[$fornecedor]['secretarias'][$secretaria]['quantidade']++;
        $grupos[$fornecedor]['secretarias'][$secretaria]['total'] += $valor;
        $grupos[$fornecedor]['secretarias'][$secretaria]['items'][] = $aq;
        $totalGeral += $valor;
    }

    uasort($grupos, static function ($a, $b) {
        $byName = strcmp((string)$a['nome'], (string)$b['nome']);
        return $byName !== 0 ? $byName : 0;
    });

    foreach ($grupos as &$grupo) {
        uasort($grupo['secretarias'], static function ($a, $b) {
            $byName = strcmp((string)$a['nome'], (string)$b['nome']);
            return $byName !== 0 ? $byName : 0;
        });
    }
    unset($grupo);

    return [
        'grupos' => $grupos,
        'total' => $totalGeral,
        'quantidade' => count($aquisicoes),
    ];
}

function gerarResumoSecretarias(array $aquisicoes): array
{
    $resumo = [];

    foreach ($aquisicoes as $aq) {
        $secretaria = nomeRelatorio($aq['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA');
        if (!isset($resumo[$secretaria])) {
            $resumo[$secretaria] = [
                'nome' => $secretaria,
                'cor' => corRelatorioSecretaria($aq['secretaria_cor'] ?? ''),
                'quantidade' => 0,
                'total' => 0.0,
            ];
        }

        $resumo[$secretaria]['quantidade']++;
        $resumo[$secretaria]['total'] += (float)($aq['valor_total'] ?? 0);
    }

    uasort($resumo, static function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    return array_values($resumo);
}

function renderizarCabecalhoRelatorioAquisicoes(
    string $geradoEm,
    string $busca,
    string $status,
    string $periodoTexto,
    string $secretaria,
    string $fornecedor,
    int $quantidade,
    float $total,
    bool $quebrarPagina = false
): void {
    $classeLinhaTitulo = $quebrarPagina ? ' class="supplier-page-break"' : '';
    ?>
            <tr<?php echo $classeLinhaTitulo; ?>>
                <td colspan="6" class="title-main">RELATÓRIO DE AQUISIÇÕES</td>
            </tr>
            <tr>
                <td colspan="6" class="sub-info left"><strong>Gerado em:</strong> <?php echo h($geradoEm); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Busca:</strong> <?php echo $busca !== '' ? h($busca) : 'Todos'; ?></td>
                <td colspan="3" class="sub-info left"><strong>Status:</strong> <?php echo $status !== '' ? h($status) : 'Todos'; ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Período:</strong> <?php echo h($periodoTexto); ?></td>
                <td colspan="3" class="sub-info left"><strong>Secretaria:</strong> <?php echo h($secretaria); ?></td>
            </tr>
            <tr>
                <td colspan="3" class="sub-info left"><strong>Fornecedor:</strong> <?php echo h($fornecedor); ?></td>
                <td colspan="3" class="sub-info left"><strong>Registros:</strong> <?php echo $quantidade; ?></td>
            </tr>

            <tr class="spacer"><td colspan="6"></td></tr>

            <tr>
                <td colspan="3" class="summary-label">TOTAL DE AQUISIÇÕES</td>
                <td colspan="3" class="summary-label">VALOR TOTAL</td>
            </tr>
            <tr>
                <td colspan="3" class="summary-value"><?php echo $quantidade; ?></td>
                <td colspan="3" class="summary-value"><?php echo formatarMoedaBR($total); ?></td>
            </tr>

            <tr class="spacer"><td colspan="6"></td></tr>

            <tr>
                <td colspan="6" class="section-title">AQUISIÇÕES INDIVIDUAIS</td>
            </tr>
    <?php
}

$busca = trim((string)($_GET['busca'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$secretaria_id = trim((string)($_GET['secretaria_id'] ?? ''));
$fornecedor_id = trim((string)($_GET['fornecedor_id'] ?? ''));
$data_inicio = trim((string)($_GET['data_inicio'] ?? ''));
$data_fim = trim((string)($_GET['data_fim'] ?? ''));
$export = trim((string)($_GET['export'] ?? ''));
$tipo_relatorio = trim((string)($_GET['tipo_relatorio'] ?? 'sintetico'));
if (!in_array($tipo_relatorio, ['sintetico', 'analitico'], true)) {
    $tipo_relatorio = 'sintetico';
}
$data_inicio_valida = $data_inicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio);
$data_fim_valida = $data_fim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim);

$where_parts = ["1=1"];
$params = [];
$status_options = ['AGUARDANDO ENTREGA', 'FINALIZADO'];

if ($status !== '' && in_array($status, $status_options, true)) {
    $where_parts[] = "a.status = :status";
    $params[':status'] = $status;
}

if ($busca !== '') {
    $busca_valor = preg_replace('/[^\d,.-]/', '', $busca);
    $busca_valor = str_replace('.', '', (string)$busca_valor);
    $busca_valor = str_replace(',', '.', $busca_valor);
    $busca_tem_valor = $busca_valor !== '' && is_numeric($busca_valor);
    $busca_valor_sql = $busca_tem_valor
        ? "OR CAST(a.valor_total AS CHAR) LIKE :busca_valor_texto OR a.valor_total = :busca_valor_exato"
        : "";

    $where_parts[] = "(
        a.numero_aq LIKE :busca_aq
        OR o.numero LIKE :busca_oficio
        OR o.resumo_itens LIKE :busca_resumo
        OR s.nome LIKE :busca_secretaria
        OR f.nome LIKE :busca_fornecedor
        $busca_valor_sql
    )";
    $busca_like = '%' . $busca . '%';
    $params[':busca_aq'] = $busca_like;
    $params[':busca_oficio'] = $busca_like;
    $params[':busca_resumo'] = $busca_like;
    $params[':busca_secretaria'] = $busca_like;
    $params[':busca_fornecedor'] = $busca_like;

    if ($busca_tem_valor) {
        $params[':busca_valor_texto'] = '%' . str_replace(',', '.', $busca_valor) . '%';
        $params[':busca_valor_exato'] = (float)$busca_valor;
    }
}

if ($secretaria_id !== '') {
    $where_parts[] = "o.secretaria_id = :secretaria_id";
    $params[':secretaria_id'] = (int)$secretaria_id;
}

if ($fornecedor_id !== '') {
    $where_parts[] = "a.fornecedor_id = :fornecedor_id";
    $params[':fornecedor_id'] = (int)$fornecedor_id;
}

if ($data_inicio_valida) {
    $where_parts[] = "a.criado_em >= :data_inicio";
    $params[':data_inicio'] = $data_inicio . ' 00:00:00';
}

if ($data_fim_valida) {
    $where_parts[] = "a.criado_em <= :data_fim";
    $params[':data_fim'] = $data_fim . ' 23:59:59';
}

$where = implode(' AND ', $where_parts);

$secretarias_list = $pdo->query("SELECT id, nome FROM secretarias ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$fornecedores_list = $pdo->query("SELECT id, nome, cnpj FROM fornecedores ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

$nome_secretaria_filtro = 'Todas';
if ($secretaria_id !== '') {
    foreach ($secretarias_list as $sec) {
        if ((string)$sec['id'] === (string)$secretaria_id) {
            $nome_secretaria_filtro = $sec['nome'];
            break;
        }
    }
}

$nome_fornecedor_filtro = 'Todos';
if ($fornecedor_id !== '') {
    foreach ($fornecedores_list as $fornecedor) {
        if ((string)$fornecedor['id'] === (string)$fornecedor_id) {
            $nome_fornecedor_filtro = $fornecedor['nome'];
            break;
        }
    }
}

$sql_select = "
    SELECT
        a.id,
        a.numero_aq,
        a.valor_total,
        a.status,
        a.criado_em,
        o.numero as oficio_num,
        s.nome as secretaria,
        s.cor_relatorio as secretaria_cor,
        f.nome as fornecedor
    FROM aquisicoes a
    LEFT JOIN oficios o ON a.oficio_id = o.id
    LEFT JOIN secretarias s ON o.secretaria_id = s.id
    LEFT JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
";

$sql_select_export = "
    SELECT
        a.id,
        a.numero_aq,
        a.valor_total,
        a.status,
        a.criado_em,
        o.numero as oficio_num,
        o.resumo_itens,
        s.nome as secretaria,
        s.cor_relatorio as secretaria_cor,
        f.nome as fornecedor,
        COALESCE(NULLIF(TRIM(o.resumo_itens), ''), itens_relatorio.descricao, '') as descricao_relatorio
    FROM aquisicoes a
    LEFT JOIN oficios o ON a.oficio_id = o.id
    LEFT JOIN secretarias s ON o.secretaria_id = s.id
    LEFT JOIN fornecedores f ON a.fornecedor_id = f.id
    LEFT JOIN (
        SELECT
            aquisicao_id,
            GROUP_CONCAT(produto ORDER BY id ASC SEPARATOR '; ') as descricao
        FROM itens_aquisicao
        GROUP BY aquisicao_id
    ) itens_relatorio ON itens_relatorio.aquisicao_id = a.id
    WHERE $where
";

$sql_order = "
    ORDER BY
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', 1) AS UNSIGNED) ASC,
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', -1) AS UNSIGNED) ASC,
        a.id ASC
";

$sql_export_order = "
    ORDER BY
        COALESCE(f.nome, 'FORNECEDOR NÃO INFORMADO') ASC,
        COALESCE(s.nome, 'SECRETARIA NÃO INFORMADA') ASC,
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', 1) AS UNSIGNED) ASC,
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', -1) AS UNSIGNED) ASC,
        a.id ASC
";

if (in_array($export, ['excel', 'pdf'], true)) {
    $stmt_export = $pdo->prepare($sql_select_export . $sql_export_order);
    $stmt_export->execute($params);
    $aquisicoes_export = $stmt_export->fetchAll(PDO::FETCH_ASSOC);
    $is_pdf_export = $export === 'pdf';

    $agrupamento_fornecedores = agruparAquisicoesPorFornecedor($aquisicoes_export);
    $dadosPorFornecedor = $agrupamento_fornecedores['grupos'];
    $total_export = (float)$agrupamento_fornecedores['total'];
    $quantidade_export = (int)$agrupamento_fornecedores['quantidade'];
    $resumoSecretarias = gerarResumoSecretarias($aquisicoes_export);
    $secretaria_color_map = [];
    foreach ($resumoSecretarias as $secretariaResumo) {
        $secretaria_color_map[$secretariaResumo['nome']] = corRelatorioSecretaria($secretariaResumo['cor'] ?? '');
    }
    $usar_cores_fornecedor = count($resumoSecretarias) === 1 && count($dadosPorFornecedor) > 1;
    $fornecedor_color_map = [];
    if ($usar_cores_fornecedor) {
        $fornecedor_cor_index = 0;
        foreach (array_keys($dadosPorFornecedor) as $nomeFornecedor) {
            $fornecedor_color_map[$nomeFornecedor] = corRelatorioFornecedor($fornecedor_cor_index);
            $fornecedor_cor_index++;
        }
    }

    $periodo_texto = 'Todos';
    if ($data_inicio_valida || $data_fim_valida) {
        $inicio_txt = $data_inicio_valida ? date('d/m/Y', strtotime($data_inicio)) : '...';
        $fim_txt = $data_fim_valida ? date('d/m/Y', strtotime($data_fim)) : '...';
        $periodo_texto = $inicio_txt . ' até ' . $fim_txt;
    }
    $descricao_limite = $tipo_relatorio === 'analitico' ? 420 : 180;

    $filename = 'relatorio_aquisicoes_' . date('Ymd_His');
    $back_query = $_GET;
    unset($back_query['export'], $back_query['page']);
    $back_url = 'aquisicoes_lista.php';
    if (!empty($back_query)) {
        $back_url .= '?' . http_build_query($back_query);
    }

    if ($is_pdf_export) {
        header('Content-Type: text/html; charset=UTF-8');
    } else {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
    }
?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório de Aquisições</title>
        <?php if ($is_pdf_export): ?>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <?php endif; ?>
        <style>
            <?php if ($is_pdf_export): ?>
            @page {
                size: A4 landscape;
                margin: 0;
            }
            <?php endif; ?>

            * { box-sizing: border-box; }
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                color: #1f2937;
                margin: <?php echo $is_pdf_export ? '0' : '18px'; ?>;
                background: <?php echo $is_pdf_export ? '#eef2f7' : '#ffffff'; ?>;
            }
            table { border-collapse: collapse; width: 100%; table-layout: fixed; }
            .sheet td, .sheet th { border: 1px solid #7c8aa5; padding: 7px 8px; vertical-align: middle; word-wrap: break-word; }
            .title-main { background: #dbeafe; color: #0f172a; font-size: 18px; font-weight: bold; text-align: center; padding: 12px; margin-top: 30px !important;}
            .sub-info { background: #f8fafc; font-size: 11px; }
            .thead { background: #e5e7eb; font-weight: bold; text-align: center; }
            .thead th { background: #e5e7eb; }
            .summary-label { background: #f8fafc; font-weight: bold; text-align: center; }
            .summary-value { text-align: center; font-weight: bold; font-size: 14px; background: #ffffff; }
            .section-title { background: #1d4ed8; color: #fff; font-weight: bold; text-transform: uppercase; text-align: center; }
            .left { text-align: left; }
            .center { text-align: center; }
            .right { text-align: right; }
            .text-cell { mso-number-format: "\@"; }
            .desc-cell { font-weight: 600; text-transform: uppercase; }
            .money-cell { white-space: nowrap; }
            .excel-report td,
            .excel-report th {
                overflow-wrap: anywhere;
                word-wrap: break-word;
            }
            .secretaria-card-title td {
                color: #0f172a;
                font-weight: bold;
                text-transform: uppercase;
                border-top: 2px solid #475569;
                border-bottom: 1px solid #7c8aa5;
                padding-top: 9px;
                padding-bottom: 9px;
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
            .supplier-section td {
                background: #eef2ff;
                color: #0f172a;
                font-weight: bold;
                text-transform: uppercase;
            }
            .supplier-total-row td {
                background: #dbeafe;
                font-weight: bold;
                border-top: 2px solid #3b82f6;
            }
            .total-row td { background: #eef2ff; font-weight: bold; border-top: 2px solid #334155; }
            .spacer td { border: none !important; height: 8px; padding: 0; background: transparent; }

            <?php if ($is_pdf_export): ?>
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

            .pdf-btn[disabled] {
                cursor: not-allowed;
                opacity: .75;
            }

            .pdf-btn-primary {
                background: #1d4ed8;
                border-color: #1d4ed8;
                color: #ffffff;
            }

            body.pdf-export {
                background: #ffffff;
                color: #001228;
                font-size: 8.5px;
            }

            .pdf-wrap {
                padding: 0;
                overflow-x: auto;
            }

            .pdf-page {
                width: 297mm;
                min-height: 210mm;
                max-width: none;
                background: #ffffff;
                margin: 0 auto;
                padding: 5mm;
            }

            body.pdf-export .sheet {
                width: 100%;
                table-layout: fixed;
                border-collapse: collapse;
            }

            .sheet td,
            .sheet th {
                line-height: 1.15;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            body.pdf-export .sheet td,
            body.pdf-export .sheet th {
                border-color: #7f8daa;
                color: #001228;
                padding: 1px 5px;
                vertical-align: middle;
            }

            body.pdf-export .title-main {
                background: #dbeafe;
                border: 1px solid #7c8aa5;
                color: #001228;
                font-size: 12px;
                line-height: 1.1;
                padding: 4px 5px;
                text-transform: uppercase;
            }

            body.pdf-export .sub-info {
                background: #ffffff;
                font-size: 9.2px;
                line-height: 1.08;
                padding-top: 1px;
                padding-bottom: 1px;
            }

            body.pdf-export .summary-label {
                background: #ffffff;
                font-size: 9.5px;
                line-height: 1.1;
                padding: 2px 5px;
            }

            body.pdf-export .summary-value {
                background: #ffffff;
                font-size: 8.5px;
                line-height: 1.1;
                padding: 2px 5px;
            }

            body.pdf-export .section-title {
                background: #1d4ed8;
                color: #ffffff;
                font-size: 10px;
                line-height: 1.1;
                padding: 2px 5px;
            }

            body.pdf-export .thead th {
                background: #e5e7eb;
                color: #001228;
                font-size: 9.5px;
                line-height: 1.1;
                padding: 2px 5px;
            }

            body.pdf-export .desc-cell {
                font-size: 8.2px;
                line-height: 1.12;
            }

            body.pdf-export .group-total-row td {
                background: #d9dde4;
                font-size: 9.5px;
                padding: 2px 5px;
            }

            body.pdf-export .supplier-section td,
            body.pdf-export .supplier-total-row td {
                font-size: 9px;
                padding: 2px 5px;
            }

            body.pdf-export .supplier-page-break {
                break-before: page;
                page-break-before: always;
            }

            body.pdf-export .secretaria-card-title td {
                font-size: 9.5px;
                line-height: 1.12;
                padding: 4px 5px;
            }

            body.pdf-export .secretaria-card-head th {
                font-size: 8.8px;
                padding: 2px 5px;
            }

            body.pdf-export .total-row td {
                background: #eef2ff;
                font-size: 9.5px;
                padding: 1px 5px;
            }

            body.pdf-export .spacer td {
                height: 5px;
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
                    width: 297mm !important;
                    min-height: 210mm !important;
                }
            }
            <?php endif; ?>
        </style>
    </head>
    <body class="<?php echo $is_pdf_export ? 'pdf-export' : ''; ?>">
        <?php if ($is_pdf_export): ?>
            <div class="print-toolbar no-print">
                <div class="toolbar-title">Relatório de Aquisições - PDF em paisagem</div>
                <div class="toolbar-actions">
                    <a href="<?php echo h($back_url); ?>" class="pdf-btn">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                    <button type="button" id="download-pdf-btn" class="pdf-btn pdf-btn-primary" onclick="downloadAquisicoesPdf()">
                        <i class="fas fa-file-pdf"></i> Baixar PDF
                    </button>
                    <button type="button" class="pdf-btn" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
            </div>
            <div class="pdf-wrap">
                <div class="pdf-page" id="pdf-report-page">
        <?php endif; ?>

        <table class="sheet excel-report">
            <colgroup>
                <col style="width: 13%;">
                <col style="width: 22%;">
                <col style="width: 27%;">
                <col style="width: 22%;">
                <col style="width: 8%;">
                <col style="width: 8%;">
            </colgroup>

            <?php if (empty($dadosPorFornecedor)): ?>
                <?php
                renderizarCabecalhoRelatorioAquisicoes(
                    date('d/m/Y H:i:s'),
                    $busca,
                    $status,
                    $periodo_texto,
                    $nome_secretaria_filtro,
                    $nome_fornecedor_filtro,
                    $quantidade_export,
                    $total_export
                );
                ?>
                <tr>
                    <td colspan="6" class="center">Nenhuma aquisição encontrada para os filtros selecionados.</td>
                </tr>
            <?php else: ?>
                <?php $gerado_em_relatorio = date('d/m/Y H:i:s'); ?>
                <?php $fornecedor_index = 0; ?>
                <?php foreach ($dadosPorFornecedor as $fornecedor): ?>
                    <?php
                    $fornecedor_cor = $usar_cores_fornecedor
                        ? h($fornecedor_color_map[$fornecedor['nome']] ?? corRelatorioFornecedor($fornecedor_index))
                        : '';
                    $fornecedor_bg_attr = $fornecedor_cor !== ''
                        ? ' bgcolor="' . $fornecedor_cor . '" style="background-color: ' . $fornecedor_cor . ';"'
                        : '';
                    $fornecedor_quebra_pagina = $fornecedor_index > 0;
                    $fornecedor_index++;
                    ?>
                    <?php
                    renderizarCabecalhoRelatorioAquisicoes(
                        $gerado_em_relatorio,
                        $busca,
                        $status,
                        $periodo_texto,
                        $nome_secretaria_filtro,
                        (string)$fornecedor['nome'],
                        (int)$fornecedor['quantidade'],
                        (float)$fornecedor['total'],
                        $fornecedor_quebra_pagina
                    );
                    ?>
                    <tr class="supplier-section">
                        <td colspan="4" class="left"<?php echo $fornecedor_bg_attr; ?>>FORNECEDOR: <?php echo h($fornecedor['nome']); ?></td>
                        <td class="center"<?php echo $fornecedor_bg_attr; ?>><?php echo (int)$fornecedor['quantidade']; ?> AQ</td>
                        <td class="right money-cell"<?php echo $fornecedor_bg_attr; ?>><?php echo formatarMoedaBR($fornecedor['total']); ?></td>
                    </tr>

                    <?php foreach ($fornecedor['secretarias'] as $secretaria): ?>
                        <?php
                        $cor_secretaria = corRelatorioSecretaria($secretaria_color_map[$secretaria['nome']] ?? ($secretaria['cor'] ?? ''));
                        $cor_attr = h($cor_secretaria);
                        $cor_item_attr = $fornecedor_cor !== '' ? $fornecedor_cor : $cor_attr;
                        ?>
                        <tr class="spacer"><td colspan="6"></td></tr>
                        <tr class="secretaria-card-title">
                            <td colspan="4" class="left" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;">SECRETARIA: <?php echo h($secretaria['nome']); ?></td>
                            <td class="center" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo (int)$secretaria['quantidade']; ?> AQ</td>
                            <td class="right money-cell" bgcolor="<?php echo $cor_attr; ?>" style="background-color: <?php echo $cor_attr; ?>;"><?php echo formatarMoedaBR($secretaria['total']); ?></td>
                        </tr>
                        <tr class="secretaria-card-head">
                            <th>Nº Aquisição</th>
                            <th>Nº Ofício</th>
                            <th>Secretaria</th>
                            <th>DESCRIÇÃO</th>
                            <th>Data</th>
                            <th>Valor</th>
                        </tr>
                        <?php foreach ($secretaria['items'] as $aq): ?>
                            <?php
                            $descricaoCompleta = limparDescricaoItens($aq['descricao_relatorio'] ?? '');
                            $descricaoRelatorio = $tipo_relatorio === 'analitico'
                                ? formatarTextoCaixaAlta($descricaoCompleta !== '' ? $descricaoCompleta : '-')
                                : formatarDescricaoRelatorio($descricaoCompleta, $descricao_limite);
                            ?>
                            <tr class="report-row">
                                <td class="center text-cell" bgcolor="<?php echo $cor_item_attr; ?>" style="background-color: <?php echo $cor_item_attr; ?>;"><?php echo h($aq['numero_aq']); ?></td>
                                <td class="center text-cell" bgcolor="<?php echo $cor_item_attr; ?>" style="background-color: <?php echo $cor_item_attr; ?>;"><?php echo h($aq['oficio_num']); ?></td>
                                <td class="left text-cell" bgcolor="<?php echo $cor_item_attr; ?>" style="background-color: <?php echo $cor_item_attr; ?>;"><?php echo h(nomeRelatorio($aq['secretaria'] ?? '', 'SECRETARIA NÃO INFORMADA')); ?></td>
                                <td class="left text-cell desc-cell" bgcolor="<?php echo $cor_item_attr; ?>" style="background-color: <?php echo $cor_item_attr; ?>;"><?php echo h($descricaoRelatorio); ?></td>
                                <td class="center" bgcolor="<?php echo $cor_item_attr; ?>" style="background-color: <?php echo $cor_item_attr; ?>;"><?php echo h(formatarDataBR($aq['criado_em'])); ?></td>
                                <td class="right money-cell" bgcolor="<?php echo $cor_item_attr; ?>" style="background-color: <?php echo $cor_item_attr; ?>;"><?php echo formatarMoedaBR($aq['valor_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="group-total-row">
                            <td colspan="4" class="right">TOTAL DA SECRETARIA</td>
                            <td class="center"><?php echo (int)$secretaria['quantidade']; ?> AQ</td>
                            <td class="right money-cell"><?php echo formatarMoedaBR($secretaria['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="supplier-total-row">
                        <td colspan="4" class="right"<?php echo $fornecedor_bg_attr; ?>>TOTAL DO FORNECEDOR</td>
                        <td class="center"<?php echo $fornecedor_bg_attr; ?>><?php echo (int)$fornecedor['quantidade']; ?> AQ</td>
                        <td class="right money-cell"<?php echo $fornecedor_bg_attr; ?>><?php echo formatarMoedaBR($fornecedor['total']); ?></td>
                    </tr>
                <?php endforeach; ?>

                <tr class="spacer"><td colspan="6"></td></tr>
                <tr class="total-row">
                    <td colspan="5" class="right">TOTAL GERAL</td>
                    <td class="right money-cell"><?php echo formatarMoedaBR($total_export); ?></td>
                </tr>
            <?php endif; ?>
        </table>
        <?php if ($is_pdf_export): ?>
                </div>
            </div>

            <script>
                async function downloadAquisicoesPdf() {
                    const report = document.getElementById('pdf-report-page');
                    const button = document.getElementById('download-pdf-btn');
                    const originalHtml = button.innerHTML;

                    if (!window.html2pdf) {
                        window.print();
                        return;
                    }

                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';

                    try {
                        const worker = window.html2pdf().set({
                            margin: [0, 0, 0, 0],
                            filename: <?php echo json_encode($filename . '.pdf'); ?>,
                            image: { type: 'jpeg', quality: 0.98 },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                backgroundColor: '#ffffff',
                                windowWidth: report.scrollWidth
                            },
                            jsPDF: {
                                unit: 'mm',
                                format: 'a4',
                                orientation: 'landscape'
                            },
                            pagebreak: {
                                mode: ['css', 'legacy'],
                                before: ['.supplier-page-break'],
                                avoid: ['tr']
                            }
                        }).from(report).toPdf();

                        await worker.get('pdf').then((pdf) => {
                            const totalPages = pdf.internal.getNumberOfPages();
                            const pageWidth = pdf.internal.pageSize.getWidth();
                            const pageHeight = pdf.internal.pageSize.getHeight();
                            const emittedAt = <?php echo json_encode('Emitido em ' . date('d/m/Y H:i:s')); ?>;

                            pdf.setFontSize(7);
                            pdf.setTextColor(100);

                            for (let page = 1; page <= totalPages; page++) {
                                pdf.setPage(page);
                                pdf.text(`${emittedAt} | Página ${page} de ${totalPages}`, pageWidth - 6, pageHeight - 4, {
                                    align: 'right'
                                });
                            }
                        });

                        await worker.save();
                    } catch (error) {
                        console.error('Falha ao baixar PDF:', error);
                        window.print();
                    } finally {
                        button.disabled = false;
                        button.innerHTML = originalHtml;
                    }
                }
            </script>
        <?php endif; ?>
    </body>
    </html>
<?php
    exit;
}

// Configurações de Paginação
$itens_por_pagina = 6;
$pagina_atual = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$pagina_atual = max(1, $pagina_atual);
$offset = ($pagina_atual - 1) * $itens_por_pagina;

// Contagem total para paginação
$stmt_count = $pdo->prepare("
    SELECT COUNT(*)
    FROM aquisicoes a
    LEFT JOIN oficios o ON a.oficio_id = o.id
    LEFT JOIN secretarias s ON o.secretaria_id = s.id
    LEFT JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
");
$stmt_count->execute($params);
$total_registros = (int)$stmt_count->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $itens_por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
    $offset = ($pagina_atual - 1) * $itens_por_pagina;
}

// Query principal com LIMIT
$stmt = $pdo->prepare($sql_select . $sql_order . "
    LIMIT $itens_por_pagina OFFSET $offset
");
$stmt->execute($params);
$aquisicoes = $stmt->fetchAll();

// Função auxiliar para manter parâmetros na URL da paginação
function get_pagination_url($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

include 'views/layout/header.php';
?>

<style>
    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filtro-busca {
        grid-column: span 3;
    }

    .filtro-status {
        grid-column: span 2;
    }

    .filtro-secretaria {
        grid-column: span 3;
    }

    .filtro-fornecedor {
        grid-column: span 4;
    }

    .filtro-data {
        grid-column: span 2;
    }

    .filtro-tipo {
        grid-column: span 2;
    }

    .filtros-acoes {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: nowrap;
        grid-column: span 6;
    }

    .filtros-acoes .btn {
        min-height: 40px;
        justify-content: center;
        white-space: nowrap;
    }

    @media (max-width: 1200px) {
        .filtro-busca,
        .filtro-fornecedor {
            grid-column: span 6;
        }

        .filtro-status,
        .filtro-secretaria,
        .filtro-data,
        .filtro-tipo {
            grid-column: span 3;
        }

        .filtros-acoes {
            grid-column: span 6;
        }
    }

    .lista-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .lista-table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table {
        min-width: 1220px;
    }

    .lista-table,
    .lista-table th,
    .lista-table td,
    .lista-table span,
    .lista-table a,
    .lista-table .badge {
        white-space: nowrap !important;
    }

    .acoes-wrap {
        display: flex;
        gap: 8px;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-end;
    }

    .paginacao-box {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: .75rem;
        margin-top: 2rem;
        border-top: 1px solid var(--border-color);
        padding-top: 1.5rem;
        flex-wrap: wrap;
    }

    .paginacao-info {
        font-weight: 600;
        color: var(--text-dark);
    }

    .btn-limpar {
        width: 100%;
    }

    @media (max-width: 768px) {
        .filtros-grid {
            grid-template-columns: 1fr;
        }

        .filtro-busca,
        .filtro-status,
        .filtro-secretaria,
        .filtro-fornecedor,
        .filtro-data,
        .filtro-tipo,
        .filtros-acoes {
            grid-column: span 1;
        }

        .lista-header {
            flex-direction: column;
            align-items: stretch;
        }

        .lista-header .btn {
            width: 100%;
            justify-content: center;
        }

        .filtros-acoes {
            display: grid;
            grid-template-columns: 1fr;
            gap: .75rem;
        }

        .filtros-acoes .btn {
            width: 100%;
        }

        .paginacao-box {
            flex-direction: column;
        }

        .paginacao-box .btn {
            width: 100%;
            max-width: 260px;
            justify-content: center;
        }
    }

    /* Dropdown Actions Styles */
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-menu {
        position: absolute;
        right: 0;
        top: 100%;
        z-index: 9999;
        display: none !important;
        min-width: 200px;
        padding: 0.5rem 0;
        margin-top: 0.25rem;
        background-color: #fff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .dropdown-menu.show {
        display: block !important;
        animation: dropdownFadeIn 0.2s ease-out;
    }

    /* Versão Dropup */
    .dropdown-menu.dropup {
        top: auto !important;
        bottom: 100% !important;
        margin-top: 0 !important;
        margin-bottom: 0.25rem !important;
        animation: dropdownFadeUp 0.2s ease-out !important;
    }

    @keyframes dropdownFadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @keyframes dropdownFadeUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        gap: 10px;
        width: 100%;
        padding: 0.75rem 1rem;
        color: var(--text-dark) !important;
        text-decoration: none !important;
        font-weight: 500;
        font-size: 0.825rem;
        transition: all 0.2s;
        border: 0;
        background: transparent;
        cursor: pointer;
        padding-right: 2rem;
    }

    .dropdown-item:hover {
        background-color: var(--primary-light);
        color: var(--primary) !important;
    }

    .dropdown-item i {
        width: 16px;
        text-align: center;
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .dropdown-item:hover i {
        color: var(--primary);
    }

    .btn-three-dots {
        background: #fff;
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        padding: 0;
        cursor: pointer;
    }

    .btn-three-dots:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
    }

    /* Fix table clipping */
    .lista-table-wrap {
        overflow-x: auto !important;
        padding-bottom: 0 !important;
        margin-bottom: 0 !important;
        -webkit-overflow-scrolling: touch;
    }

    .lista-table td {
        position: relative;
    }
</style>

<div class="card no-print">
    <div class="card-body">
        <h3 class="card-title" style="margin-bottom: 1rem; font-weight: 700; font-size: 1rem;">
            <i class="fas fa-filter" style="margin-right: 5px; color: var(--primary);"></i> Filtros de Busca
        </h3>

        <form action="" method="GET" class="filtros-grid">
            <div class="form-group filtro-busca" style="margin-bottom: 0;">
                <label class="form-label">Termo de busca</label>
                <input
                    type="text"
                    name="busca"
                    class="form-control"
                    placeholder="Nº aquisição, ofício, secretaria, fornecedor ou valor..."
                    value="<?php echo htmlspecialchars($_GET['busca'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="form-group filtro-status" style="margin-bottom: 0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">Todos Status</option>
                    <option value="AGUARDANDO ENTREGA" <?php echo $status === 'AGUARDANDO ENTREGA' ? 'selected' : ''; ?>>AGUARDANDO ENTREGA</option>
                    <option value="FINALIZADO" <?php echo $status === 'FINALIZADO' ? 'selected' : ''; ?>>FINALIZADO</option>
                </select>
            </div>

            <div class="form-group filtro-secretaria" style="margin-bottom: 0;">
                <label class="form-label">Secretaria</label>
                <select name="secretaria_id" class="form-control">
                    <option value="">Todas as Secretarias</option>
                    <?php foreach ($secretarias_list as $sec): ?>
                        <option value="<?php echo (int)$sec['id']; ?>" <?php echo $secretaria_id == $sec['id'] ? 'selected' : ''; ?>>
                            <?php echo h($sec['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtro-fornecedor" style="margin-bottom: 0;">
                <label class="form-label">Fornecedor</label>
                <select name="fornecedor_id" class="form-control">
                    <option value="">Todos os Fornecedores</option>
                    <?php foreach ($fornecedores_list as $fornecedor): ?>
                        <option value="<?php echo (int)$fornecedor['id']; ?>" <?php echo $fornecedor_id == $fornecedor['id'] ? 'selected' : ''; ?>>
                            <?php echo h($fornecedor['nome']); ?>
                            <?php if (!empty($fornecedor['cnpj'])): ?>
                                (<?php echo h($fornecedor['cnpj']); ?>)
                            <?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group filtro-data" style="margin-bottom: 0;">
                <label class="form-label">Data inicial</label>
                <input
                    type="date"
                    name="data_inicio"
                    class="form-control"
                    value="<?php echo h($data_inicio); ?>">
            </div>

            <div class="form-group filtro-data" style="margin-bottom: 0;">
                <label class="form-label">Data final</label>
                <input
                    type="date"
                    name="data_fim"
                    class="form-control"
                    value="<?php echo h($data_fim); ?>">
            </div>

            <div class="form-group filtro-tipo" style="margin-bottom: 0;">
                <label class="form-label">Tipo relatório</label>
                <select name="tipo_relatorio" class="form-control">
                    <option value="sintetico" <?php echo $tipo_relatorio === 'sintetico' ? 'selected' : ''; ?>>Sintético</option>
                    <option value="analitico" <?php echo $tipo_relatorio === 'analitico' ? 'selected' : ''; ?>>Analítico</option>
                </select>
            </div>

            <div class="form-group filtros-acoes" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-outline btn-sm" title="Filtrar">
                    <i class="fas fa-search"></i> Filtrar
                </button>

                <button
                    type="submit"
                    name="export"
                    value="excel"
                    class="btn btn-primary btn-sm"
                    title="Exportar Excel">
                    <i class="fas fa-file-excel"></i> Excel
                </button>

                <button
                    type="submit"
                    name="export"
                    value="pdf"
                    formtarget="_blank"
                    class="btn btn-outline btn-sm"
                    title="Baixar PDF">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>

                <button
                    type="submit"
                    formaction="aquisicoes_imprimir_lote.php"
                    formtarget="_blank"
                    class="btn btn-outline btn-sm"
                    title="Imprimir aquisições filtradas">
                    <i class="fas fa-print"></i> Imprimir
                </button>

                <a href="aquisicoes_lista.php" class="btn btn-outline btn-sm" title="Limpar Filtros">
                    <i class="fas fa-eraser"></i> Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="lista-header">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1.25rem; margin: 0;">
                <i class="fas fa-file-invoice-dollar" style="margin-right: 10px; color: var(--primary);"></i> Aquisições Geradas
            </h3>
        </div>

        <?php display_flash(); ?>

        <div class="table-responsive lista-table-wrap">
            <table class="table-vcenter text-nowrap lista-table">
                <thead>
                    <tr>
                        <th>Nº Aquisição</th>
                        <th>Ref. Ofício</th>
                        <th>Secretaria</th>
                        <th>Fornecedor</th>
                        <th>Data</th>
                        <th style="text-align: right;">Valor Total</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aquisicoes as $aq): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-dark);">
                                    <?php echo htmlspecialchars($aq['numero_aq'], ENT_QUOTES, 'UTF-8'); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($aq['oficio_num'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-weight: 600;">
                                    <?php echo htmlspecialchars($aq['secretaria'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($aq['fornecedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo format_date($aq['criado_em']); ?></td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary);">
                                <?php echo format_money($aq['valor_total']); ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-<?php echo strtolower($aq['status'] === 'AGUARDANDO ENTREGA' ? 'pending' : 'finalized'); ?>" style="padding: 0.4rem 1rem;">
                                    <?php echo htmlspecialchars($aq['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="acoes-wrap">
                                    <div class="dropdown">
                                        <button class="btn-three-dots" data-dropdown-toggle title="Ações">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="aquisicoes_visualizar.php?id=<?php echo (int)$aq['id']; ?>">
                                                <i class="fas fa-eye"></i> Visualizar
                                            </a>

                                            <?php
                                                $nivel_user = strtoupper($_SESSION['nivel'] ?? '');
                                                if ($aq['status'] !== 'FINALIZADO' && ($nivel_user === 'ADMIN' || $nivel_user === 'SUPORTE')):
                                            ?>
                                                <a class="dropdown-item" href="editar_aquisicoes.php?id=<?php echo (int)$aq['id']; ?>">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                            <?php endif; ?>

                                            <?php
                                                if ($aq['status'] === 'AGUARDANDO ENTREGA' && ($nivel_user === 'ADMIN' || $nivel_user === 'SUPORTE')):
                                            ?>
                                                <a class="dropdown-item" href="aquisicao_finalizar.php?id=<?php echo (int)$aq['id']; ?>" style="color: var(--status-finalized) !important;" onclick="return confirm('Confirmar o recebimento desta aquisição?')">
                                                    <i class="fas fa-check-circle"></i> Marcar como Recebido
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($aquisicoes)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding: 3rem; color: var(--text-muted);">
                                Nenhuma aquisição gerada até o momento.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_paginas > 1): ?>
            <div class="paginacao-box">
                <a href="<?php echo $pagina_atual > 1 ? get_pagination_url($pagina_atual - 1) : '#'; ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual <= 1 ? 'disabled' : ''; ?>">
                    <i class="fas fa-angle-left"></i> Anterior
                </a>

                <span class="paginacao-info">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?>
                </span>

                <a href="<?php echo $pagina_atual < $total_paginas ? get_pagination_url($pagina_atual + 1) : '#'; ?>"
                    class="btn btn-outline btn-sm <?php echo $pagina_atual >= $total_paginas ? 'disabled' : ''; ?>">
                    Próxima <i class="fas fa-angle-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>

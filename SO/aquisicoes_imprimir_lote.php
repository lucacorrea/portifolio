<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = 'Imprimir Aquisições em Lote';
$is_support = strtoupper((string)($_SESSION['nivel'] ?? '')) === 'SUPORTE';

$busca = trim((string)($_GET['busca'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$secretaria_id = trim((string)($_GET['secretaria_id'] ?? ''));
$fornecedor_id = trim((string)($_GET['fornecedor_id'] ?? ''));
$data_inicio = trim((string)($_GET['data_inicio'] ?? ''));
$data_fim = trim((string)($_GET['data_fim'] ?? ''));
$via = trim((string)($_GET['via'] ?? 'administrativa'));

$vias_permitidas = ['administrativa', 'fornecedor'];
if (!in_array($via, $vias_permitidas, true)) {
    $via = 'administrativa';
}

$data_inicio_valida = $data_inicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio);
$data_fim_valida = $data_fim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim);
$status_options = ['AGUARDANDO ENTREGA', 'FINALIZADO'];

$where_parts = [reportable_aquisicoes_condition()];
$params = [];

if ($status !== '' && !in_array($status, $status_options, true)) {
    $status = '';
}
if ($status !== '') {
    $where_parts[] = 'a.status = :status';
    $params[':status'] = $status;
}
if ($busca !== '') {
    $where_parts[] = "(
        a.numero_aq LIKE :busca_aq
        OR o.numero LIKE :busca_oficio
        OR s.nome LIKE :busca_secretaria
        OR f.nome LIKE :busca_fornecedor
    )";
    $busca_like = '%' . $busca . '%';
    $params[':busca_aq'] = $busca_like;
    $params[':busca_oficio'] = $busca_like;
    $params[':busca_secretaria'] = $busca_like;
    $params[':busca_fornecedor'] = $busca_like;
}
if ($secretaria_id !== '' && ctype_digit($secretaria_id) && (int)$secretaria_id > 0) {
    $where_parts[] = 'o.secretaria_id = :secretaria_id';
    $params[':secretaria_id'] = (int)$secretaria_id;
} else {
    $secretaria_id = '';
}
if ($fornecedor_id !== '' && ctype_digit($fornecedor_id) && (int)$fornecedor_id > 0) {
    $where_parts[] = 'a.fornecedor_id = :fornecedor_id';
    $params[':fornecedor_id'] = (int)$fornecedor_id;
} else {
    $fornecedor_id = '';
}
if ($data_inicio_valida) {
    $where_parts[] = 'a.criado_em >= :data_inicio';
    $params[':data_inicio'] = $data_inicio . ' 00:00:00';
} else {
    $data_inicio = '';
}
if ($data_fim_valida) {
    $where_parts[] = 'a.criado_em <= :data_fim';
    $params[':data_fim'] = $data_fim . ' 23:59:59';
} else {
    $data_fim = '';
}

$where = implode(' AND ', $where_parts);
$sql_order = "
    ORDER BY
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', 1) AS UNSIGNED) ASC,
        CAST(SUBSTRING_INDEX(REPLACE(REPLACE(UPPER(TRIM(a.numero_aq)), 'AQ-', ''), 'AQ', ''), '-', -1) AS UNSIGNED) ASC,
        a.id ASC
";

$stmt = $pdo->prepare("
    SELECT
        a.*,
        o.numero AS oficio_num,
        o.local AS oficio_local,
        s.nome AS secretaria,
        s.responsavel AS sec_responsavel,
        f.nome AS fornecedor,
        f.cnpj AS fornecedor_cnpj,
        f.contato AS fornecedor_contato
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE $where
    $sql_order
");
$stmt->execute($params);
$aquisicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$itens_por_aquisicao = [];
if (!empty($aquisicoes)) {
    $ids = array_map(static function (array $aq): int {
        return (int)$aq['id'];
    }, $aquisicoes);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt_items = $pdo->prepare("
        SELECT
            ia.*,
            a_it.oficio_id,
            COALESCE(
                (
                    SELECT io.unidade
                    FROM itens_oficio io
                    WHERE io.oficio_id = a_it.oficio_id
                      AND (
                          io.id = ia.oficio_item_id
                          OR (
                              ia.oficio_item_id IS NULL
                              AND TRIM(UPPER(io.produto)) = TRIM(UPPER(ia.produto))
                          )
                      )
                    ORDER BY io.id ASC
                    LIMIT 1
                ),
                'UN'
            ) AS unidade
        FROM itens_aquisicao ia
        INNER JOIN aquisicoes a_it ON a_it.id = ia.aquisicao_id
        WHERE ia.aquisicao_id IN ($placeholders)
        ORDER BY ia.aquisicao_id ASC, ia.id ASC
    ");
    $stmt_items->execute($ids);
    foreach ($stmt_items->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $aq_id = (int)$item['aquisicao_id'];
        $itens_por_aquisicao[$aq_id][] = $item;
    }
}

$assinatura_disponivel = false;
if ($is_support) {
    try {
        $stmt_assinatura = $pdo->prepare("
            SELECT id
            FROM assinaturas_sistema
            WHERE finalidade = ?
              AND ativo = 1
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt_assinatura->execute(['AUTORIZACAO_FORNECEDOR']);
        $assinatura_disponivel = (bool)$stmt_assinatura->fetchColumn();
    } catch (Throwable $e) {
        $assinatura_disponivel = false;
    }
}

$filtros_lista = array_filter([
    'busca' => $busca,
    'status' => $status,
    'secretaria_id' => $secretaria_id,
    'fornecedor_id' => $fornecedor_id,
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim,
], static function ($value): bool {
    return $value !== '';
});

$voltar_url = 'aquisicoes_lista.php';
if (!empty($filtros_lista)) {
    $voltar_url .= '?' . http_build_query($filtros_lista);
}

function h_lote($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function money_br_lote($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function render_aquisicao_lote_header(array $aq, string $titulo, string $viaLabel): void
{
    $data_emissao = strtotime((string)($aq['criado_em'] ?? '')) ?: time();
?>
    <div class="ordem-header">
        <div class="ordem-logo">
            <img src="assets/img/prefeitura.jpg" alt="Logo Prefeitura">
        </div>
        <div class="ordem-center">
            <h1>PREFEITURA MUNICIPAL DE COARI</h1>
            <h2><?= h_lote($titulo) ?></h2>
            <div class="ordem-cnpj">COARI - AM | CNPJ: 04.262.432/0001-21</div>
        </div>
        <div class="ordem-right">
            <div class="via-label"><?= h_lote($viaLabel) ?></div>
            <div class="ordem-right-box">
                <div class="ordem-label">Ordem Nº</div>
                <div class="ordem-numero"><?= h_lote(str_replace('AQ-', '', (string)$aq['numero_aq'])) ?></div>
            </div>
            <div class="ordem-data">DATA: <?= date('d/m/Y', $data_emissao) ?> | <?= date('H:i', $data_emissao) ?></div>
        </div>
    </div>
<?php
}

function render_aquisicao_lote_signature_footer(string $assinaturaLabel, bool $isSupport, bool $assinaturaDisponivel): void
{
?>
    <div class="rodape-documento print-signature-footer">
        <div class="assinaturas-grid">
            <div class="assinatura-fornecedor-col">
                <div class="assinatura-linha assinatura-fornecedor-linha">
                    <div class="assinatura-campo assinatura-campo-fornecedor">
                        <?php if ($isSupport && $assinaturaDisponivel): ?>
                            <img
                                src="assinatura_suporte.php"
                                alt="Assinatura autorizada"
                                class="support-signature-image"
                                loading="eager">
                        <?php endif; ?>
                    </div>
                    <div class="assinatura-titulo">AUTORIZAÇÃO DE FORNECEDOR</div>
                    <div class="assinatura-subtitulo"><?= h_lote($assinaturaLabel) ?></div>
                </div>
            </div>
            <div>
                <div class="assinatura-linha">
                    <div class="assinatura-campo"></div>
                    <div class="assinatura-titulo">CONFIRMAÇÃO DE RECEBIMENTO</div>
                    <div class="assinatura-subtitulo">Assinatura e Carimbo</div>
                </div>
            </div>
        </div>
    </div>
<?php
}

function render_itens_aquisicao_lote(array $items, float $valorTotal): void
{
?>
    <div class="ordem-items-wrap">
        <table class="ordem-items-table">
            <thead>
                <tr>
                    <th class="c-item">Item</th>
                    <th class="c-unid">Unid.</th>
                    <th class="c-qtd">Qtd</th>
                    <th>Especificação Completa</th>
                    <th class="c-preco">Preço Unitário</th>
                    <th class="c-total">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="6" class="center strong">Nenhum item cadastrado.</td></tr>
                <?php else: ?>
                    <?php $i = 1; ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $quantidade = (float)($item['quantidade'] ?? 0);
                        $valorUnitario = (float)($item['valor_unitario'] ?? 0);
                        $valorItem = $quantidade * $valorUnitario;
                        $unidade = trim((string)($item['unidade'] ?? 'UN')) ?: 'UN';
                        ?>
                        <tr>
                            <td class="center strong"><?= str_pad((string)$i++, 2, '0', STR_PAD_LEFT) ?></td>
                            <td class="center"><?= h_lote(strtoupper($unidade)) ?></td>
                            <td class="center strong"><?= number_format($quantidade, 0, ',', '.') ?></td>
                            <td class="strong"><?= h_lote(strtoupper((string)($item['produto'] ?? ''))) ?></td>
                            <td class="right valor-monetario"><?= money_br_lote($valorUnitario) ?></td>
                            <td class="right strong valor-monetario"><?= money_br_lote($valorItem) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="ordem-total-row">
                        <td colspan="5" class="right strong total-label">Valor Total R$</td>
                        <td class="right strong total-value"><?= money_br_lote($valorTotal) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

function render_aquisicao_lote_page(
    array $aq,
    array $items,
    string $via,
    bool $isSupport,
    bool $assinaturaDisponivel,
    int $pagina
): void {
    $is_fornecedor = $via === 'fornecedor';
    $titulo = $is_fornecedor ? 'Ordem de Fornecimento' : 'Ordem de Aquisição e Suprimentos';
    $label_via = $is_fornecedor ? 'Via Fornecedor' : 'Via Administrativa';
    $assinatura_label = $is_fornecedor ? 'Autorização de Saída' : 'Autorização de Recebimento';
    $data_emissao = strtotime((string)($aq['criado_em'] ?? '')) ?: time();
?>
    <div class="card printable-page" data-page="<?= $pagina ?>" data-aquisicao="<?= (int)$aq['id'] ?>">
        <?php if ($isSupport && $assinaturaDisponivel): ?>
            <label class="signature-page-control no-print" title="Assinar somente esta aquisição">
                <input type="checkbox" class="signature-page-checkbox">
                <span>Página <?= $pagina ?> • <?= h_lote($aq['numero_aq']) ?></span>
            </label>
        <?php endif; ?>

        <div class="card-body">
            <table class="print-repeat-table">
                <thead>
                    <tr><td><?php render_aquisicao_lote_header($aq, $titulo, $label_via); ?></td></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="ordem-info-wrap">
                                <table class="ordem-info-table">
                                    <colgroup>
                                        <col class="info-col-label-a">
                                        <col class="info-col-value-a">
                                        <col class="info-col-label-b">
                                        <col class="info-col-value-b">
                                    </colgroup>
                                    <tr>
                                        <td class="ordem-info-label">Fornecedor:</td>
                                        <td class="strong"><?= h_lote(strtoupper((string)$aq['fornecedor'])) ?></td>
                                        <td class="ordem-info-label">Local e Data de Emissão:</td>
                                        <td class="strong data-emissao">COARI-AM - <?= date('d/m/Y', $data_emissao) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="ordem-info-label">Para:</td>
                                        <td class="strong"><?= h_lote(strtoupper((string)$aq['secretaria'])) ?></td>
                                        <td class="ordem-info-label">Referência:</td>
                                        <td class="referencia"><?= h_lote($aq['oficio_num']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="ordem-info-label">Local:</td>
                                        <td colspan="3" class="strong uppercase"><?= !empty($aq['oficio_local']) ? h_lote($aq['oficio_local']) : '---' ?></td>
                                    </tr>
                                </table>
                            </div>

                            <h3 class="ordem-section-title">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>
                            <?php render_itens_aquisicao_lote($items, (float)$aq['valor_total']); ?>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td><?php render_aquisicao_lote_signature_footer($assinatura_label, $isSupport, $assinaturaDisponivel); ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php
}

include 'views/layout/header.php';
?>

<style>
.center{text-align:center}.right{text-align:right}.strong{font-weight:700}.uppercase{text-transform:uppercase}
.print-topbar{margin-bottom:2rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}.print-topbar .spacer{flex-grow:1}.print-count{color:var(--text-muted);font-weight:700;font-size:.875rem}
.print-options{display:flex;gap:.75rem;align-items:end;flex-wrap:wrap}.print-options .form-group{margin-bottom:0;min-width:220px}
.support-signature-panel{display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;padding:.75rem .9rem;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff}.support-signature-panel .form-group{min-width:220px;margin:0}.support-signature-panel .form-label{color:#1e3a8a}.signature-status{font-size:.76rem;font-weight:700;color:#1e3a8a;min-width:170px;padding-bottom:.68rem}
.signature-warning{display:flex;align-items:center;gap:.6rem;padding:.75rem .9rem;border:1px solid #fed7aa;border-radius:10px;background:#fff7ed;color:#9a3412;font-size:.8rem;font-weight:700}

.print-doc{max-width:1120px;margin:0 auto}.printable-page{position:relative;margin-bottom:2rem;border-radius:12px;overflow:visible;background:#fff}.printable-page .card-body{padding:2rem}
.signature-page-control{display:none;position:absolute;top:10px;left:10px;z-index:8;align-items:center;gap:.45rem;padding:.45rem .65rem;background:#fff7ed;border:1px solid #fdba74;border-radius:8px;color:#9a3412;font-size:.76rem;font-weight:800;box-shadow:0 4px 12px rgba(15,23,42,.08);cursor:pointer}.signature-select-mode .signature-page-control{display:flex}.signature-page-control input{width:16px;height:16px}

.ordem-header{display:grid;grid-template-columns:205px minmax(0,1fr) 205px;align-items:center;border-bottom:2px solid #000;padding:0 0 1rem;margin-bottom:1.2rem;gap:1rem;min-height:96px}
.ordem-logo{display:flex;align-items:center;justify-content:flex-start;min-width:0}.ordem-logo img{display:block;max-height:76px;max-width:185px;width:auto;height:auto;object-fit:contain}
.ordem-center{text-align:center;min-width:0;padding:0 .35rem}.ordem-center h1{font-size:1.35rem;line-height:1.12;font-weight:900;margin:0;color:#000;text-transform:uppercase;white-space:nowrap}.ordem-center h2{font-size:.84rem;line-height:1.25;font-weight:800;margin:5px 0 0;color:#222;text-transform:uppercase}.ordem-cnpj{font-size:.71rem;line-height:1.25;margin-top:5px;color:#555;font-weight:600}
.ordem-right{display:flex;flex-direction:column;align-items:stretch;justify-content:center;text-align:center;width:100%;min-width:0}.via-label{font-weight:800;color:#666;font-size:.66rem;text-transform:uppercase;margin-bottom:5px;letter-spacing:.08em;text-align:center}.ordem-right-box{border:1.5px solid #000;padding:.42rem .55rem;width:100%;box-sizing:border-box;text-align:center}.ordem-label{font-size:.61rem;font-weight:800;color:#000;text-transform:uppercase}.ordem-numero{font-size:1.34rem;font-weight:900;color:#000;line-height:1.05;white-space:nowrap}.ordem-data{font-size:.68rem;color:#333;margin-top:5px;font-weight:700;text-transform:uppercase;text-align:center;white-space:nowrap}

.ordem-info-table,.ordem-items-table,.print-repeat-table{width:100%;border-collapse:collapse}.print-repeat-table>thead>tr>td,.print-repeat-table>tfoot>tr>td,.print-repeat-table>tbody>tr>td{padding:0;border:0}.ordem-info-wrap,.ordem-items-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}.ordem-info-wrap{margin-bottom:.9rem}.ordem-info-table{margin-bottom:0;font-size:.84rem;table-layout:fixed}.ordem-items-table{font-size:.82rem;border:1px solid #000;table-layout:fixed}
.info-col-label-a{width:14%}.info-col-value-a{width:40%}.info-col-label-b{width:23%}.info-col-value-b{width:23%}
.ordem-info-table td,.ordem-items-table th,.ordem-items-table td{border:1px solid #000;padding:8px 9px;vertical-align:middle;line-height:1.3}.ordem-items-table thead tr,.ordem-total-row,.ordem-info-label{background:#f1f1f1}.ordem-items-table th{text-align:center;font-size:.73rem;font-weight:800;text-transform:uppercase}.ordem-items-table td{font-size:.82rem}.ordem-info-label{font-weight:800;font-size:.69rem;text-transform:uppercase;white-space:normal}.data-emissao{white-space:nowrap}.referencia{font-family:Arial,Helvetica,sans-serif;font-size:.78rem;font-weight:800;letter-spacing:0;word-break:normal;overflow-wrap:break-word;line-height:1.25}.c-item{width:7%}.c-unid{width:8%}.c-qtd{width:9%}.c-preco{width:15%}.c-total{width:16%}.valor-monetario{white-space:nowrap;font-variant-numeric:tabular-nums}.ordem-total-row td{border-bottom:1px solid #000!important}.total-label{font-size:.86rem;text-transform:uppercase;padding-right:10px!important}.total-value{font-size:.94rem;white-space:nowrap;font-variant-numeric:tabular-nums}.ordem-section-title{font-size:.78rem;font-weight:800;color:#222;text-transform:uppercase;margin:1.05rem 0 .45rem}

.rodape-documento{margin-top:1.8rem}.assinaturas-grid{display:grid;grid-template-columns:1fr 1fr;gap:3.5rem;text-align:center;margin-top:2.2rem}.assinatura-linha{min-height:100px;box-sizing:border-box}.assinatura-campo{height:62px;border-bottom:1.5px solid #000;display:flex;align-items:flex-end;justify-content:center;overflow:hidden;box-sizing:border-box}.assinatura-titulo{font-weight:800;color:#000;font-size:.84rem;line-height:1.2;margin-top:8px}.assinatura-subtitulo{font-size:.63rem;color:#555;font-weight:700;text-transform:uppercase;margin-top:3px}
.support-signature-image{display:none;width:100%;height:100%;object-fit:contain;object-position:center bottom}.printable-page.signature-enabled .assinatura-campo-fornecedor{border-bottom-color:transparent}.printable-page.signature-enabled .support-signature-image{display:block}

@media(max-width:768px){.print-topbar{flex-direction:column;align-items:stretch}.print-topbar .btn,.print-options .btn,.print-options .form-group,.support-signature-panel,.support-signature-panel .form-group,.signature-warning{width:100%;justify-content:center;text-align:center}.print-options{width:100%}.printable-page .card-body{padding:1rem}.ordem-header{grid-template-columns:1fr;text-align:center}.ordem-right,.ordem-logo{justify-self:center;text-align:center;align-items:center;justify-content:center;width:100%;max-width:260px;margin:0 auto}.ordem-center h1{white-space:normal}.assinaturas-grid{grid-template-columns:1fr;gap:2rem}.ordem-info-table,.ordem-items-table{min-width:760px}.signature-page-control{position:static;margin:10px}}

@media print{
    @page{size:A4 portrait;margin:8mm 8mm 8mm 8mm}
    html,body{background:#fff!important;margin:0!important;padding:0!important;width:100%!important;color:#000!important;font-family:Arial,Helvetica,sans-serif!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
    body *{visibility:hidden}.printable-page,.printable-page *{visibility:visible}.no-print,header,footer,.navbar,.page-header,.signature-page-control{display:none!important}
    .page-body,.container-xl,.print-doc{width:100%!important;max-width:100%!important;margin:0!important;padding:0!important}.printable-page{display:block!important;width:100%!important;max-width:100%!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important;background:#fff!important;page-break-after:always;break-after:page;border-radius:0!important;overflow:visible!important}.print-doc .printable-page:last-child{page-break-after:auto;break-after:auto}.printable-page .card-body{padding:0!important;margin:0!important}

    .ordem-header{display:grid!important;grid-template-columns:40mm minmax(0,1fr) 40mm!important;align-items:center!important;gap:4mm!important;min-height:25mm!important;margin:0 0 4.5mm!important;padding:0 0 3.5mm!important;border-bottom:1.6px solid #000!important}.ordem-logo{display:flex!important;align-items:center!important;justify-content:flex-start!important;margin:0!important;padding:0!important;width:40mm!important}.ordem-logo img{display:block!important;width:auto!important;height:auto!important;max-width:38mm!important;max-height:19mm!important;margin:0!important}.ordem-center{padding:0!important;margin:0!important;text-align:center!important;min-width:0!important}.ordem-center h1{font-size:17px!important;line-height:1.08!important;white-space:nowrap!important;margin:0!important}.ordem-center h2{font-size:9.5px!important;line-height:1.2!important;margin:1.8mm 0 0!important}.ordem-cnpj{font-size:7.8px!important;line-height:1.2!important;margin-top:1mm!important}.ordem-right{display:flex!important;flex-direction:column!important;align-items:stretch!important;justify-content:center!important;width:40mm!important;margin:0!important;padding:0!important;text-align:center!important}.via-label{font-size:7px!important;margin:0 0 1mm!important;text-align:center!important}.ordem-right-box{width:100%!important;box-sizing:border-box!important;padding:1.4mm 1mm!important;border:1.3px solid #000!important}.ordem-label{font-size:6.7px!important}.ordem-numero{font-size:16px!important;line-height:1!important}.ordem-data{font-size:6.8px!important;line-height:1.1!important;margin-top:1.2mm!important;text-align:center!important;white-space:nowrap!important}

    .ordem-info-wrap,.ordem-items-wrap{overflow:visible!important;width:100%!important}.ordem-info-wrap{margin:0 0 3mm!important}.ordem-info-table,.ordem-items-table,.print-repeat-table{width:100%!important;min-width:0!important;border-collapse:collapse!important;table-layout:fixed!important}.print-repeat-table>thead{display:table-header-group!important}.print-repeat-table>tbody{display:table-row-group!important}.print-repeat-table>tfoot{display:table-footer-group!important}.info-col-label-a{width:14%!important}.info-col-value-a{width:40%!important}.info-col-label-b{width:23%!important}.info-col-value-b{width:23%!important}
    .ordem-info-table{font-size:10px!important}.ordem-items-table{font-size:9.5px!important;border:1px solid #000!important}.ordem-items-table thead{display:table-header-group!important}.ordem-info-table td,.ordem-items-table th,.ordem-items-table td{padding:2.4mm 1.9mm!important;border:1px solid #000!important;vertical-align:middle!important;line-height:1.22!important}.ordem-info-label{font-size:7.8px!important;line-height:1.15!important}.ordem-items-table th{font-size:7.8px!important;line-height:1.15!important}.ordem-items-table td{font-size:9.5px!important}.data-emissao{white-space:nowrap!important}.referencia{font-size:8.6px!important;line-height:1.2!important;word-break:normal!important;overflow-wrap:break-word!important}.c-item{width:7%!important}.c-unid{width:8%!important}.c-qtd{width:9%!important}.c-preco{width:15%!important}.c-total{width:16%!important}.valor-monetario,.total-value{white-space:nowrap!important;font-variant-numeric:tabular-nums!important}.ordem-section-title{margin:3.8mm 0 1.6mm!important;font-size:9px!important;line-height:1.2!important}.ordem-total-row,.ordem-total-row td,.rodape-documento{page-break-inside:avoid!important;break-inside:avoid!important}.total-label{font-size:9.3px!important}.total-value{font-size:10.3px!important}

    .rodape-documento{margin-top:10mm!important}.assinaturas-grid{display:grid!important;grid-template-columns:1fr 1fr!important;gap:18mm!important;text-align:center!important;margin-top:5mm!important;page-break-inside:avoid!important;break-inside:avoid!important}.assinatura-linha{min-height:29mm!important;box-sizing:border-box!important}.assinatura-campo{height:18mm!important;border-bottom:1px solid #000!important;display:flex!important;align-items:flex-end!important;justify-content:center!important;overflow:hidden!important;box-sizing:border-box!important}.assinatura-titulo{font-size:9.3px!important;line-height:1.15!important;margin-top:1.5mm!important}.assinatura-subtitulo{font-size:7px!important;line-height:1.15!important;margin-top:.8mm!important}.support-signature-image{display:none!important;width:100%!important;height:100%!important;max-width:none!important;max-height:none!important;margin:0!important;object-fit:contain!important;object-position:center bottom!important}.printable-page.signature-enabled .assinatura-campo-fornecedor{border-bottom-color:transparent!important}.printable-page.signature-enabled .support-signature-image{display:block!important}
    .assinatura-fornecedor-col{margin-top:8px!important};
}
</style>

<div class="no-print print-topbar">
    <a href="<?= h_lote($voltar_url) ?>" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
    <span class="print-count"><?= count($aquisicoes) ?> aquisição(ões) encontrada(s)</span>
    <div class="spacer"></div>

    <?php if ($is_support && !empty($aquisicoes)): ?>
        <?php if ($assinatura_disponivel): ?>
            <div class="support-signature-panel" id="support-signature-panel">
                <div class="form-group">
                    <label class="form-label" for="signature-mode"><i class="fas fa-signature"></i> Assinatura</label>
                    <select id="signature-mode" class="form-control">
                        <option value="none" selected>Sem assinatura</option>
                        <option value="all">Assinar todas</option>
                        <option value="select">Selecionar páginas</option>
                    </select>
                </div>
                <div class="signature-status" id="signature-status">Nenhuma página será assinada.</div>
            </div>
        <?php else: ?>
            <div class="signature-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Nenhuma assinatura ativa.</span>
                <a href="configuracoes_assinatura.php" class="btn btn-outline btn-sm">Configurar</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form action="aquisicoes_imprimir_lote.php" method="GET" class="print-options">
        <input type="hidden" name="busca" value="<?= h_lote($busca) ?>">
        <input type="hidden" name="status" value="<?= h_lote($status) ?>">
        <input type="hidden" name="secretaria_id" value="<?= h_lote($secretaria_id) ?>">
        <input type="hidden" name="fornecedor_id" value="<?= h_lote($fornecedor_id) ?>">
        <input type="hidden" name="data_inicio" value="<?= h_lote($data_inicio) ?>">
        <input type="hidden" name="data_fim" value="<?= h_lote($data_fim) ?>">
        <div class="form-group">
            <label class="form-label">Via para impressão</label>
            <select name="via" class="form-control" onchange="this.form.submit()">
                <option value="administrativa" <?= $via === 'administrativa' ? 'selected' : '' ?>>Via Administrativa</option>
                <option value="fornecedor" <?= $via === 'fornecedor' ? 'selected' : '' ?>>Via Fornecedor</option>
            </select>
        </div>
        <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-sync-alt"></i> Aplicar</button>
        <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" <?= empty($aquisicoes) ? 'disabled' : '' ?>><i class="fas fa-print"></i> Imprimir</button>
    </form>
</div>

<?php display_flash(); ?>

<?php if (empty($aquisicoes)): ?>
    <div class="card no-print"><div class="card-body" style="text-align:center;padding:3rem;color:var(--text-muted);">Nenhuma aquisição encontrada para os filtros selecionados.</div></div>
<?php else: ?>
    <div class="print-doc" id="print-doc">
        <?php $pagina = 1; ?>
        <?php foreach ($aquisicoes as $aq): ?>
            <?php
            $aq_id = (int)$aq['id'];
            render_aquisicao_lote_page(
                $aq,
                $itens_por_aquisicao[$aq_id] ?? [],
                $via,
                $is_support,
                $assinatura_disponivel,
                $pagina++
            );
            ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($is_support && $assinatura_disponivel && !empty($aquisicoes)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var mode = document.getElementById('signature-mode');
    var status = document.getElementById('signature-status');
    var doc = document.getElementById('print-doc');
    var pages = Array.prototype.slice.call(document.querySelectorAll('.printable-page'));

    if (!mode || !doc || pages.length === 0) return;

    function checkboxFor(page) {
        return page.querySelector('.signature-page-checkbox');
    }

    function syncPage(page) {
        var checkbox = checkboxFor(page);
        page.classList.toggle('signature-enabled', !!checkbox && checkbox.checked);
    }

    function updateStatus() {
        var signed = pages.filter(function (page) {
            return page.classList.contains('signature-enabled');
        }).length;

        if (signed === 0) {
            status.textContent = 'Nenhuma página será assinada.';
        } else if (signed === pages.length) {
            status.textContent = 'Todas as ' + pages.length + ' página(s) serão assinadas.';
        } else {
            status.textContent = signed + ' de ' + pages.length + ' página(s) selecionada(s).';
        }
    }

    function applyMode() {
        var value = mode.value;
        doc.classList.toggle('signature-select-mode', value === 'select');

        pages.forEach(function (page) {
            var checkbox = checkboxFor(page);
            if (!checkbox) return;

            if (value === 'all') {
                checkbox.checked = true;
                checkbox.disabled = true;
            } else if (value === 'none') {
                checkbox.checked = false;
                checkbox.disabled = true;
            } else {
                checkbox.disabled = false;
            }
            syncPage(page);
        });
        updateStatus();
    }

    pages.forEach(function (page) {
        var checkbox = checkboxFor(page);
        if (!checkbox) return;
        checkbox.addEventListener('change', function () {
            syncPage(page);
            updateStatus();
        });
    });

    mode.addEventListener('change', applyMode);
    window.addEventListener('beforeprint', function () {
        pages.forEach(syncPage);
    });

    applyMode();
});
</script>
<?php endif; ?>

<?php include 'views/layout/footer.php'; ?>

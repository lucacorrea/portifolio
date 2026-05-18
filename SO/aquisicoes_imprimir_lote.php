<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$page_title = 'Imprimir Aquisições em Lote';

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

$where_parts = ['1=1'];
$params = [];

if ($status !== '' && in_array($status, $status_options, true)) {
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

if ($secretaria_id !== '') {
    $where_parts[] = 'o.secretaria_id = :secretaria_id';
    $params[':secretaria_id'] = (int)$secretaria_id;
}

if ($fornecedor_id !== '') {
    $where_parts[] = 'a.fornecedor_id = :fornecedor_id';
    $params[':fornecedor_id'] = (int)$fornecedor_id;
}

if ($data_inicio_valida) {
    $where_parts[] = 'a.criado_em >= :data_inicio';
    $params[':data_inicio'] = $data_inicio . ' 00:00:00';
}

if ($data_fim_valida) {
    $where_parts[] = 'a.criado_em <= :data_fim';
    $params[':data_fim'] = $data_fim . ' 23:59:59';
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

$stmt_items = $pdo->prepare("
    SELECT
        ia.*,
        COALESCE(
            (
                SELECT io.unidade
                FROM itens_oficio io
                WHERE io.oficio_id = :oficio_id
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
    WHERE ia.aquisicao_id = :aquisicao_id
    ORDER BY ia.id ASC
");

$itens_por_aquisicao = [];
foreach ($aquisicoes as $aq) {
    $stmt_items->execute([
        ':oficio_id' => (int)$aq['oficio_id'],
        ':aquisicao_id' => (int)$aq['id'],
    ]);
    $itens_por_aquisicao[(int)$aq['id']] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
}

$filtros_lista = [
    'busca' => $busca,
    'status' => $status,
    'secretaria_id' => $secretaria_id,
    'fornecedor_id' => $fornecedor_id,
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim,
];

$filtros_lista = array_filter($filtros_lista, static function ($value) {
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

function render_itens_aquisicao_lote(array $items, float $valorTotal): void
{
?>
    <div class="ordem-items-wrap">
        <table class="ordem-items-table">
            <thead>
                <tr>
                    <th style="text-align: center; width: 40px;">Item</th>
                    <th style="text-align: center; width: 70px;">Unid.</th>
                    <th style="text-align: center; width: 70px;">Qtd</th>
                    <th style="text-align: center;">Especificação Completa</th>
                    <th style="text-align: center; width: 110px;">Preço Unitário</th>
                    <th style="text-align: center; width: 110px;">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; font-weight: 700;">Nenhum item cadastrado.</td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $quantidade = (float)($item['quantidade'] ?? 0);
                        $valorUnitario = (float)($item['valor_unitario'] ?? 0);
                        $valorItem = $quantidade * $valorUnitario;
                        $unidade = trim((string)($item['unidade'] ?? 'UN'));

                        if ($unidade === '') {
                            $unidade = 'UN';
                        }
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: #333;">
                                <?= str_pad((string)$i++, 2, '0', STR_PAD_LEFT) ?>
                            </td>

                            <td style="text-align: center; font-weight: 600; color: #555;">
                                <?= h_lote(strtoupper($unidade)) ?>
                            </td>

                            <td style="text-align: center; font-weight: 700;">
                                <?= number_format($quantidade, 0, ',', '.') ?>
                            </td>

                            <td style="font-weight: 600;">
                                <?= h_lote(strtoupper((string)($item['produto'] ?? ''))) ?>
                            </td>

                            <td style="text-align: center;">
                                <?= money_br_lote($valorUnitario) ?>
                            </td>

                            <td style="text-align: center; font-weight: 700;">
                                <?= money_br_lote($valorItem) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="ordem-total-row">
                        <td colspan="5" style="text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">
                            Valor Total R$
                        </td>
                        <td style="text-align: right; font-weight: 900; font-size: 0.9375rem;">
                            <?= money_br_lote($valorTotal) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}

function render_aquisicao_lote_page(array $aq, array $items, string $via): void
{
    $is_fornecedor = $via === 'fornecedor';
    $titulo = $is_fornecedor ? 'Ordem de Fornecimento' : 'Ordem de Aquisição e Suprimentos';
    $label_via = $is_fornecedor ? 'Via Fornecedor' : 'Via Administrativa';
    $assinatura_label = $is_fornecedor ? 'Autorização de Saída' : 'Autorização de Recebimento';
    $data_emissao = strtotime((string)($aq['criado_em'] ?? '')) ?: time();
?>
    <div class="card printable-page">
        <div class="card-body">
            <div class="ordem-header">
                <div class="ordem-logo">
                    <img src="assets/img/prefeitura.jpg" alt="Logo Prefeitura">
                </div>

                <div class="ordem-center">
                    <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">
                        PREFEITURA MUNICIPAL DE COARI
                    </h1>
                    <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">
                        <?= h_lote($titulo) ?>
                    </h2>
                    <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">
                        COARI - AM | CNPJ: 04.262.432/0001-21
                    </div>
                </div>

                <div class="ordem-right">
                    <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">
                        <?= h_lote($label_via) ?>
                    </div>
                    <div class="ordem-right-box">
                        <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                        <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;">
                            <?= h_lote(str_replace('AQ-', '', (string)$aq['numero_aq'])) ?>
                        </div>
                    </div>
                    <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                        DATA: <?= date('d/m/Y', $data_emissao) ?> | <?= date('H:i', $data_emissao) ?>
                    </div>
                </div>
            </div>

            <div class="ordem-info-wrap">
                <table class="ordem-info-table">
                    <tr>
                        <td class="ordem-info-label" style="width: 15%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Fornecedor:</td>
                        <td style="font-weight: 700;"><?= h_lote(strtoupper((string)$aq['fornecedor'])) ?></td>
                        <td class="ordem-info-label" style="width: 30%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Local e Data de Emissão:</td>
                        <td style="width: 20%; font-weight: 700;">COARI-AM - <?= date('d/m/Y', $data_emissao) ?></td>
                    </tr>
                    <tr>
                        <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Para:</td>
                        <td style="font-weight: 700;"><?= h_lote(strtoupper((string)$aq['secretaria'])) ?></td>
                        <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Referência:</td>
                        <td style="font-family: monospace; font-weight: 900; letter-spacing: 1px;"><?= h_lote($aq['oficio_num']) ?></td>
                    </tr>
                    <tr>
                        <td class="ordem-info-label" style="width: 15%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Local:</td>
                        <td colspan="3" style="font-weight: 700; text-transform: uppercase;">
                            <?= !empty($aq['oficio_local']) ? h_lote($aq['oficio_local']) : '---' ?>
                        </td>
                    </tr>
                </table>
            </div>

            <h3 class="ordem-section-title">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>

            <?php render_itens_aquisicao_lote($items, (float)$aq['valor_total']); ?>

            <div class="rodape-documento">
                <div class="assinaturas-grid">
                    <div>
                        <div class="assinatura-linha">
                            <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                            <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                <?= h_lote($assinatura_label) ?>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="assinatura-linha">
                            <div style="font-weight: 800; color: #000; font-size: 0.875rem;">CONFIRMAÇÃO DE RECEBIMENTO</div>
                            <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                Assinatura e Carimbo
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
}

include 'views/layout/header.php';
?>

<style>
    .print-topbar {
        margin-bottom: 2rem;
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .print-topbar .spacer {
        flex-grow: 1;
    }

    .print-options {
        display: flex;
        gap: .75rem;
        align-items: end;
        flex-wrap: wrap;
    }

    .print-options .form-group {
        margin-bottom: 0;
        min-width: 220px;
    }

    .print-count {
        color: var(--text-muted);
        font-weight: 700;
        font-size: .875rem;
    }

    .print-doc {
        max-width: 1120px;
        margin: 0 auto;
    }

    .printable-page {
        margin-bottom: 2rem;
        border-radius: 12px;
        overflow: visible;
        background: #fff;
    }

    .printable-page .card-body {
        padding: 2rem;
    }

    .ordem-header {
        display: grid;
        grid-template-columns: 1fr auto 1fr;
        align-items: center;
        border-bottom: 2px solid #000;
        padding-bottom: 1.25rem;
        margin-bottom: 2rem;
        gap: 1rem;
    }

    .ordem-logo {
        text-align: left;
    }

    .ordem-logo img {
        max-height: 80px;
        max-width: 200px;
        object-fit: contain;
        width: 100%;
    }

    .ordem-center {
        text-align: center;
    }

    .ordem-right {
        text-align: right;
        justify-self: end;
        width: 100%;
        padding-right: 0;
        margin-right: -10px;
    }

    .ordem-right-box {
        border: 1.5px solid #000;
        padding: 0.4rem 1rem;
        display: inline-block;
        text-align: center;
    }

    .ordem-info-table,
    .ordem-items-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ordem-info-wrap,
    .ordem-items-wrap {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .ordem-info-wrap {
        margin-bottom: 1.35rem;
    }

    .ordem-info-table {
        margin-bottom: 0;
        font-size: 0.8125rem;
    }

    .ordem-items-table {
        font-size: 0.8125rem;
        border: 1px solid #000;
    }

    .ordem-info-table td,
    .ordem-items-table th,
    .ordem-items-table td {
        border: 1px solid #000;
        padding: 6px 8px;
    }

    .ordem-items-table thead tr,
    .ordem-total-row,
    .ordem-info-label {
        background: #f0f0f0;
    }

    .ordem-total-row td {
        border-bottom: 1px solid #000 !important;
    }

    .ordem-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #333;
        text-transform: uppercase;
        margin: 1.85rem 0 0.5rem;
    }

    .rodape-documento {
        margin-top: 1.25rem;
    }

    .assinaturas-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 3rem;
        text-align: center;
        margin-top: 1.5rem;
    }

    .assinatura-linha {
        border-top: 1.5px solid #000;
        padding-top: 0.75rem;
    }

    @media (max-width: 768px) {
        .print-topbar {
            flex-direction: column;
            align-items: stretch;
        }

        .print-topbar .btn,
        .print-options .btn,
        .print-options .form-group {
            width: 100%;
            justify-content: center;
            text-align: center;
        }

        .print-options {
            width: 100%;
        }

        .printable-page .card-body {
            padding: 1rem;
        }

        .ordem-header {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .ordem-right,
        .ordem-logo,
        .ordem-header>div:first-child {
            text-align: center;
            justify-self: center;
            margin-right: 0;
        }

        .ordem-right-box {
            display: inline-block;
        }

        .assinaturas-grid {
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-top: 1.25rem;
        }

        .ordem-info-table,
        .ordem-items-table {
            min-width: 760px;
        }

        .ordem-info-wrap {
            margin-bottom: 1.2rem;
        }

        .ordem-section-title {
            margin-top: 1.55rem;
        }
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 6mm 6mm 7mm 6mm;
        }

        html,
        body {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100%;
        }

        body * {
            visibility: hidden;
        }

        .printable-page,
        .printable-page * {
            visibility: visible;
        }

        .no-print,
        header,
        footer,
        .navbar,
        .page-header {
            display: none !important;
        }

        .page-body,
        .container-xl,
        .print-doc {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .printable-page {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 0 4mm 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            background: #fff !important;
            page-break-after: always;
            break-after: page;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .print-doc .printable-page:last-child {
            page-break-after: auto;
            break-after: auto;
        }

        .printable-page .card-body {
            padding: 4mm 5mm !important;
        }

        .ordem-header {
            gap: 8px !important;
            margin-bottom: 10px !important;
            padding-bottom: 8px !important;
            grid-template-columns: 1fr auto 1fr !important;
        }

        .ordem-logo {
            margin-left: -70px !important;
        }

        .ordem-logo img {
            max-height: 70px !important;
            max-width: 180px !important;
        }

        .ordem-right {
            text-align: right !important;
            justify-self: end !important;
            width: 100% !important;
            margin-right: -14px !important;
            padding-right: 0 !important;
        }

        .ordem-info-wrap,
        .ordem-items-wrap {
            overflow: visible !important;
        }

        .ordem-info-wrap {
            margin-bottom: 10px !important;
        }

        .ordem-info-table,
        .ordem-items-table {
            width: 100% !important;
            min-width: 0 !important;
            font-size: 10px !important;
        }

        .ordem-items-table {
            border: 1px solid #000 !important;
        }

        .ordem-items-table thead {
            display: table-header-group !important;
        }

        .ordem-info-table td,
        .ordem-items-table th,
        .ordem-items-table td {
            padding: 4px 6px !important;
            border: 1px solid #000 !important;
        }

        .ordem-section-title {
            margin: 12px 0 4px !important;
            font-size: 10px !important;
        }

        .ordem-total-row,
        .ordem-total-row td {
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .rodape-documento {
            margin-top: 8px !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .assinaturas-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 2rem !important;
            text-align: center !important;
            margin-top: 70px !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .assinatura-linha {
            border-top: 1.5px solid #000 !important;
            padding-top: 6px !important;
        }
    }
</style>

<div class="no-print print-topbar">
    <a href="<?= h_lote($voltar_url) ?>" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>

    <span class="print-count">
        <?= count($aquisicoes) ?> aquisição(ões) encontrada(s)
    </span>

    <div class="spacer"></div>

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

        <button type="submit" class="btn btn-outline btn-sm">
            <i class="fas fa-sync-alt"></i> Aplicar
        </button>

        <button type="button" onclick="window.print()" class="btn btn-primary btn-sm" <?= empty($aquisicoes) ? 'disabled' : '' ?>>
            <i class="fas fa-print"></i> Imprimir
        </button>
    </form>
</div>

<?php display_flash(); ?>

<?php if (empty($aquisicoes)): ?>
    <div class="card no-print">
        <div class="card-body" style="text-align: center; padding: 3rem; color: var(--text-muted);">
            Nenhuma aquisição encontrada para os filtros selecionados.
        </div>
    </div>
<?php else: ?>
    <div class="print-doc">
        <?php foreach ($aquisicoes as $aq): ?>
            <?php
            $aq_id = (int)$aq['id'];
            render_aquisicao_lote_page($aq, $itens_por_aquisicao[$aq_id] ?? [], $via);
            ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'views/layout/footer.php'; ?>

<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT a.*, o.numero as oficio_num, s.nome as secretaria, s.responsavel as sec_responsavel, 
           f.nome as fornecedor, f.cnpj as fornecedor_cnpj, f.contato as fornecedor_contato
    FROM aquisicoes a
    JOIN oficios o ON a.oficio_id = o.id
    JOIN secretarias s ON o.secretaria_id = s.id
    JOIN fornecedores f ON a.fornecedor_id = f.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$aq = $stmt->fetch();

if (!$aq) {
    die("Aquisição não encontrada.");
}

$stmt_items = $pdo->prepare("SELECT * FROM itens_aquisicao WHERE aquisicao_id = ?");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();

$page_title = "Aquisição: " . $aq['numero_aq'];

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

    .print-doc {
        max-width: 1120px;
        margin: 0 auto;
    }

    .printable-page {
        margin-bottom: 2rem;
        border-radius: 12px;
        overflow: visible;
        /* corrigido para não cortar borda inferior */
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
        text-align: center;
    }

    .ordem-logo img {
        max-height: 80px;
        max-width: 200px;
        object-fit: contain;
        width: 100%;
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
        /* afastou mais do título abaixo */
    }

    .ordem-info-table {
        margin-bottom: 0;
        font-size: 0.8125rem;
    }

    .ordem-items-table {
        font-size: 0.8125rem;
        border: 1px solid #000;
        /* reforça a borda completa */
    }

    .ordem-info-table td,
    .ordem-items-table th,
    .ordem-items-table td {
        border: 1px solid #000;
        padding: 6px 8px;
    }

    .ordem-items-table thead tr,
    .ordem-items-table tfoot tr,
    .ordem-info-label {
        background: #f0f0f0;
    }

    .ordem-items-table tbody tr:last-child td,
    .ordem-items-table tfoot td {
        border-bottom: 1px solid #000 !important;
        /* garante borda inferior visível */
    }

    .ordem-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: #333;
        text-transform: uppercase;
        margin: 1.85rem 0 0.5rem;
        /* desce mais o título */
    }

    .assinaturas-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 4rem;
        text-align: center;
        margin-top: 5rem;
    }

    .assinatura-linha {
        border-top: 1.5px solid #000;
        padding-top: 0.75rem;
    }

    .texto-entrega {
        font-size: 0.75rem;
        color: #555;
        margin-top: 1.5rem;
        margin-bottom: 4rem;
        line-height: 1.5;
    }

    @media (max-width: 768px) {
        .print-topbar {
            flex-direction: column;
            align-items: stretch;
        }

        .print-topbar .btn {
            width: 100%;
            justify-content: center;
            text-align: center;
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
            margin-top: 3rem;
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

            /* borda completa na impressão */
            box-shadow: none !important;
            background: #fff !important;
            page-break-after: always;
            break-after: page;
            border-radius: 0 !important;
            overflow: visible !important;
        }

        .printable-page:last-of-type {
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
            margin-left: 50px !important;
        }

        .ordem-logo img {
            max-height: 60px !important;
            max-width: 150px !important;
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
            margin-bottom: 12px !important;
            /* mais espaço antes do título */
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

        .ordem-info-table td,
        .ordem-items-table th,
        .ordem-items-table td {
            padding: 4px 6px !important;
            border: 1px solid #000 !important;
        }

        .ordem-items-table tbody tr:last-child td,
        .ordem-items-table tfoot td {
            border-bottom: 1px solid #000 !important;
        }

        .ordem-section-title {
            margin: 14px 0 4px !important;
            font-size: 10px !important;
        }

        /* mantém as assinaturas do jeito que estavam */
        .assinaturas-grid {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 4rem !important;
            text-align: center !important;
            margin-top: 5rem !important;
        }

        .assinatura-linha {
            border-top: 1.5px solid #000 !important;
            padding-top: 0.75rem !important;
        }

        .texto-entrega {
            margin-top: 1.5rem !important;
            margin-bottom: 4rem !important;
            font-size: 0.75rem !important;
            line-height: 1.5 !important;
        }
    }
</style>

<div class="no-print print-topbar">
    <a href="aquisicoes_lista.php" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Voltar
    </a>
    <div class="spacer"></div>
    <button onclick="window.print()" class="btn btn-primary btn-sm">
        <i class="fas fa-print"></i> Imprimir Ordem (2 Vias)
    </button>
</div>

<?php display_flash(); ?>

<div class="print-doc">

    <!-- VIA PREFEITURA -->
    <div class="card printable-page" id="via-prefeitura-aq">
        <div class="card-body">

            <div class="ordem-header">
                <div>
                    <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">PREFEITURA MUNICIPAL</h1>
                    <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">Ordem de Aquisição e Suprimentos</h2>
                    <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">COARI - AM | CNPJ: 00.000.000/0001-00</div>
                </div>

                <div class="ordem-logo">
                    <img src="assets/img/prefeitura.jpg" alt="Logo Prefeitura">
                </div>

                <div class="ordem-right">
                    <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">Via Administrativa</div>
                    <div class="ordem-right-box">
                        <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                        <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;"><?php echo str_replace('AQ-', '', $aq['numero_aq']); ?></div>
                    </div>
                    <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                        DATA: <?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?> | <?php echo date('H:i', strtotime($aq['criado_em'])); ?>
                    </div>
                </div>
            </div>

            <div class="ordem-info-wrap">
                <table class="ordem-info-table">
                    <tr>
                        <td class="ordem-info-label" style="width: 15%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Fornecedor:</td>
                        <td style="font-weight: 700;"><?php echo strtoupper($aq['fornecedor']); ?></td>
                        <td class="ordem-info-label" style="width: 30%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Local e Data de Emissão:</td>
                        <td style="width: 20%; font-weight: 700;"><?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?></td>
                    </tr>
                    <tr>
                        <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Para:</td>
                        <td style="font-weight: 700;"><?php echo strtoupper($aq['secretaria']); ?></td>
                        <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Referência:</td>
                        <td style="font-family: monospace; font-weight: 900; letter-spacing: 1px;"><?php echo $aq['oficio_num']; ?></td>
                    </tr>
                </table>
            </div>

            <h3 class="ordem-section-title">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>

            <div class="ordem-items-wrap">
                <table class="ordem-items-table">
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 40px;">Item</th>
                            <th style="text-align: center; width: 50px;">Unid.</th>
                            <th style="text-align: center; width: 60px;">Qtd</th>
                            <th style="text-align: left;">Especificação Completa</th>
                            <th style="text-align: right; width: 110px;">Preço Unitário</th>
                            <th style="text-align: right; width: 110px;">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1;
                        foreach ($items as $item): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: #333;"><?php echo str_pad($i++, 2, '0', STR_PAD_LEFT); ?></td>
                                <td style="text-align: center; font-weight: 600; color: #555;">UN</td>
                                <td style="text-align: center; font-weight: 700;"><?php echo number_format($item['quantidade'], 0, ',', '.'); ?></td>
                                <td style="font-weight: 600;"><?php echo strtoupper($item['produto']); ?></td>
                                <td style="text-align: right;">R$ <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                                <td style="text-align: right; font-weight: 700;">R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">Valor Total R$</td>
                            <td style="text-align: right; font-weight: 900; font-size: 0.9375rem;">R$ <?php echo number_format($aq['valor_total'], 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="assinaturas-grid">
                <div>
                    <div class="assinatura-linha">
                        <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                        <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Autorização de Saída</div>
                    </div>
                </div>
                <div>
                    <div class="assinatura-linha">
                        <div style="font-weight: 800; color: #000; font-size: 0.875rem;">CONFIRMAÇÃO DE RECEBIMENTO</div>
                        <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Assinatura e Carimbo</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- VIA FORNECEDOR -->
    <div class="card printable-page" id="via-fornecedor-aq">
        <div class="card-body">

            <div class="ordem-header">
                <div>
                    <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">PREFEITURA MUNICIPAL</h1>
                    <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">Ordem de Fornecimento</h2>
                    <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">COARI - AM | CNPJ: 00.000.000/0001-00</div>
                </div>

                <div class="ordem-logo">
                    <img src="assets/img/prefeitura.jpg" alt="Logo Prefeitura">
                </div>

                <div class="ordem-right">
                    <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">Via Fornecedor</div>
                    <div class="ordem-right-box">
                        <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                        <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;"><?php echo str_replace('AQ-', '', $aq['numero_aq']); ?></div>
                    </div>
                    <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                        DATA: <?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?> | <?php echo date('H:i', strtotime($aq['criado_em'])); ?>
                    </div>
                </div>
            </div>

            <div class="ordem-info-wrap">
                <table class="ordem-info-table">
                    <tr>
                        <td class="ordem-info-label" style="width: 15%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Fornecedor:</td>
                        <td style="font-weight: 700;"><?php echo strtoupper($aq['fornecedor']); ?></td>
                        <td class="ordem-info-label" style="width: 30%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Local e Data de Emissão:</td>
                        <td style="width: 20%; font-weight: 700;"><?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?></td>
                    </tr>
                    <tr>
                        <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Para:</td>
                        <td style="font-weight: 700;"><?php echo strtoupper($aq['secretaria']); ?></td>
                        <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Referência:</td>
                        <td style="font-family: monospace; font-weight: 900; letter-spacing: 1px;"><?php echo $aq['oficio_num']; ?></td>
                    </tr>
                </table>
            </div>

            <h3 class="ordem-section-title">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>

            <div class="ordem-items-wrap">
                <table class="ordem-items-table">
                    <thead>
                        <tr>
                            <th style="text-align: center; width: 40px;">Item</th>
                            <th style="text-align: center; width: 50px;">Unid.</th>
                            <th style="text-align: center; width: 60px;">Qtd</th>
                            <th style="text-align: left;">Especificação Completa</th>
                            <th style="text-align: right; width: 110px;">Preço Unitário</th>
                            <th style="text-align: right; width: 110px;">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $j = 1;
                        foreach ($items as $item): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 700; color: #333;"><?php echo str_pad($j++, 2, '0', STR_PAD_LEFT); ?></td>
                                <td style="text-align: center; font-weight: 600; color: #555;">UN</td>
                                <td style="text-align: center; font-weight: 700;"><?php echo number_format($item['quantidade'], 0, ',', '.'); ?></td>
                                <td style="font-weight: 600;"><?php echo strtoupper($item['produto']); ?></td>
                                <td style="text-align: right;">R$ <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                                <td style="text-align: right; font-weight: 700;">R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">Valor Total R$</td>
                            <td style="text-align: right; font-weight: 900; font-size: 0.9375rem;">R$ <?php echo number_format($aq['valor_total'], 2, ',', '.'); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="texto-entrega">
                No ato da entrega, esta via deverá ser carimbada e assinada pelo responsável.
                Para fins de pagamento, o fornecedor deve apresentar esta ordem devidamente assinada no setor administrativo/financeiro.
            </p>

            <div class="assinaturas-grid" style="margin-top: 4rem;">
                <div>
                    <div class="assinatura-linha">
                        <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                        <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Autorização de Saída</div>
                    </div>
                </div>
                <div>
                    <div class="assinatura-linha">
                        <div style="font-weight: 800; color: #000; font-size: 0.875rem;">CONFIRMAÇÃO DE RECEBIMENTO</div>
                        <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Assinatura e Carimbo</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<?php include 'views/layout/footer.php'; ?>
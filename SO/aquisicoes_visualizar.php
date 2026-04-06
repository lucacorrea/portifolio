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
<div class="no-print" style="margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center;">
    <a href="aquisicoes_lista.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
    <div style="flex-grow: 1;"></div>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Imprimir Ordem (2 Vias)</button>
</div>

<?php display_flash(); ?>

<!-- VIA PREFEITURA -->
<div class="card printable-page" id="via-prefeitura-aq">
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 2px solid #000; padding-bottom: 1.25rem; margin-bottom: 2rem; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">PREFEITURA MUNICIPAL</h1>
                <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">Ordem de Aquisição e Suprimentos</h2>
                <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">COARI - AM | CNPJ: 00.000.000/0001-00</div>
            </div>
            <div style="text-align: center;">
                <img src="assets/img/prefeitura.png" alt="Logo Prefeitura" style="max-height: 80px; max-width: 200px; object-fit: contain;">
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">Via Administrativa</div>
                <div style="border: 1.5px solid #000; padding: 0.4rem 1rem; display: inline-block; text-align: center;">
                    <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;"><?php echo str_replace('AQ-', '', $aq['numero_aq']); ?></div>
                </div>
                <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                    DATA: <?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?> | <?php echo date('H:i', strtotime($aq['criado_em'])); ?>
                </div>
            </div>
        </div>

        <!-- Cabeçalho informativo da Ordem - estilo AF -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 0.8125rem;" border="1">
            <tr>
                <td style="padding: 6px 10px; width: 15%; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Fornecedor:</td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 700;"><?php echo strtoupper($aq['fornecedor']); ?></td>
                <td style="padding: 6px 10px; width: 30%; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Local e Data de Emissão:</td>
                <td style="padding: 6px 10px; width: 20%; border: 1px solid #000; font-weight: 700;"><?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Para:</td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 700;"><?php echo strtoupper($aq['secretaria']); ?></td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Referência:</td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-family: monospace; font-weight: 900; letter-spacing: 1px;"><?php echo $aq['oficio_num']; ?></td>
            </tr>
        </table>

        <h3 style="font-size: 0.75rem; font-weight: 800; color: #333; text-transform: uppercase; margin: 1.5rem 0 0.5rem;">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>

        <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 40px;">Item</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 50px;">Unid.</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 60px;">Qtd</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: left;">Especificação Completa</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: right; width: 110px;">Preço Unitário</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: right; width: 110px;">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach($items as $item): ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: center; font-weight: 700; color: #333;"><?php echo str_pad($i++, 2, '0', STR_PAD_LEFT); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: center; font-weight: 600; color: #555;">UN</td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: center; font-weight: 700;"><?php echo number_format($item['quantidade'], 0, ',', '.'); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; font-weight: 600;"><?php echo strtoupper($item['produto']); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: right;">R$ <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: right; font-weight: 700;">R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f0f0f0;">
                    <td colspan="5" style="border: 1px solid #000; padding: 8px 10px; text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">Valor Total R$</td>
                    <td style="border: 1px solid #000; padding: 8px 10px; text-align: right; font-weight: 900; font-size: 0.9375rem;">R$ <?php echo number_format($aq['valor_total'], 2, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; text-align: center; margin-top: 5rem;">
            <div>
                <div style="border-top: 1.5px solid #000; padding-top: 0.75rem;">
                    <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                    <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Autorização de Saída</div>
                </div>
            </div>
            <div>
                <div style="border-top: 1.5px solid #000; padding-top: 0.75rem;">
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
        <div style="display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 2px solid #000; padding-bottom: 1.25rem; margin-bottom: 2rem; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">PREFEITURA MUNICIPAL</h1>
                <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">Ordem de Fornecimento</h2>
                <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">COARI - AM | CNPJ: 00.000.000/0001-00</div>
            </div>
            <div style="text-align: center;">
                <img src="assets/img/prefeitura.png" alt="Logo Prefeitura" style="max-height: 80px; max-width: 200px; object-fit: contain;">
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">Via Fornecedor</div>
                <div style="border: 1.5px solid #000; padding: 0.4rem 1rem; display: inline-block; text-align: center;">
                    <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                    <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;"><?php echo str_replace('AQ-', '', $aq['numero_aq']); ?></div>
                </div>
                <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                    DATA: <?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?> | <?php echo date('H:i', strtotime($aq['criado_em'])); ?>
                </div>
            </div>
        </div>

        <!-- Cabeçalho informativo da Ordem - estilo AF -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 0.8125rem;" border="1">
            <tr>
                <td style="padding: 6px 10px; width: 15%; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Fornecedor:</td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 700;"><?php echo strtoupper($aq['fornecedor']); ?></td>
                <td style="padding: 6px 10px; width: 30%; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Local e Data de Emissão:</td>
                <td style="padding: 6px 10px; width: 20%; border: 1px solid #000; font-weight: 700;"><?php echo date('d/m/Y', strtotime($aq['criado_em'])); ?></td>
            </tr>
            <tr>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Para:</td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 700;"><?php echo strtoupper($aq['secretaria']); ?></td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; background: #f0f0f0;">Referência:</td>
                <td style="padding: 6px 10px; border: 1px solid #000; font-family: monospace; font-weight: 900; letter-spacing: 1px;"><?php echo $aq['oficio_num']; ?></td>
            </tr>
        </table>

        <h3 style="font-size: 0.75rem; font-weight: 800; color: #333; text-transform: uppercase; margin: 1.5rem 0 0.5rem;">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>

        <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 40px;">Item</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 50px;">Unid.</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: center; width: 60px;">Qtd</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: left;">Especificação Completa</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: right; width: 110px;">Preço Unitário</th>
                    <th style="border: 1px solid #000; padding: 6px 8px; text-align: right; width: 110px;">Valor Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $j = 1; foreach($items as $item): ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: center; font-weight: 700; color: #333;"><?php echo str_pad($j++, 2, '0', STR_PAD_LEFT); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: center; font-weight: 600; color: #555;">UN</td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: center; font-weight: 700;"><?php echo number_format($item['quantidade'], 0, ',', '.'); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; font-weight: 600;"><?php echo strtoupper($item['produto']); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: right;">R$ <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                    <td style="border: 1px solid #000; padding: 5px 8px; text-align: right; font-weight: 700;">R$ <?php echo number_format($item['quantidade'] * $item['valor_unitario'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f0f0f0;">
                    <td colspan="5" style="border: 1px solid #000; padding: 8px 10px; text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">Valor Total R$</td>
                    <td style="border: 1px solid #000; padding: 8px 10px; text-align: right; font-weight: 900; font-size: 0.9375rem;">R$ <?php echo number_format($aq['valor_total'], 2, ',', '.'); ?></td>
                </tr>
            </tfoot>
        </table>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; text-align: center; margin-top: 5rem;">
            <div>
                <div style="border-top: 1.5px solid #000; padding-top: 0.75rem;">
                    <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                    <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Autorização de Saída</div>
                </div>
            </div>
            <div>
                <div style="border-top: 1.5px solid #000; padding-top: 0.75rem;">
                    <div style="font-weight: 800; color: #000; font-size: 0.875rem;">CONFIRMAÇÃO DE RECEBIMENTO</div>
                    <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Assinatura e Carimbo</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>

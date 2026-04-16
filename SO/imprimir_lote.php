<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();

// Verifica se quer imprimir
$is_print = isset($_GET['print']) && $_GET['print'] == 1;

if ($is_print) {
    // Buscar todas as aquisições e seus ofícios
    $stmt_aq = $pdo->query("
        SELECT
            a.*,
            o.numero AS oficio_num,
            o.justificativa,
            o.valor_orcamento,
            o.criado_em as oficio_criado_em,
            o.status as oficio_status,
            s.nome AS secretaria,
            s.responsavel AS sec_responsavel,
            f.nome AS fornecedor,
            f.cnpj AS fornecedor_cnpj,
            f.contato AS fornecedor_contato,
            u.nome AS oficio_usuario
        FROM aquisicoes a
        JOIN oficios o ON a.oficio_id = o.id
        JOIN secretarias s ON o.secretaria_id = s.id
        JOIN fornecedores f ON a.fornecedor_id = f.id
        JOIN usuarios u ON o.usuario_id = u.id
        ORDER BY a.criado_em ASC, a.id ASC
    ");
    $aquisicoes = $stmt_aq->fetchAll();

    function h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    function money_br($value) {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <title>Imprimir Lote: Aquisições e Ofícios</title>
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            .print-topbar { margin-bottom: 2rem; display: flex; gap: 1rem; align-items: center; background: #f8f9fa; padding: 15px; border-radius: 8px; }
            .print-topbar .spacer { flex-grow: 1; }
            .print-doc { max-width: 1120px; margin: 0 auto; }
            
            .printable-page { margin-bottom: 2rem; border-radius: 12px; overflow: visible; background: #fff; border: 1px solid #ddd; padding: 2rem; }
            
            .ordem-header { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 2px solid #000; padding-bottom: 1.25rem; margin-bottom: 2rem; gap: 1rem; }
            .ordem-logo img { max-height: 80px; max-width: 200px; object-fit: contain; width: 100%; }
            .ordem-center { text-align: center; }
            .ordem-right { text-align: right; justify-self: end; width: 100%; }
            .ordem-right-box { border: 1.5px solid #000; padding: 0.4rem 1rem; display: inline-block; text-align: center; }
            
            .ordem-info-table, .ordem-items-table { width: 100%; border-collapse: collapse; }
            .ordem-info-wrap, .ordem-items-wrap { width: 100%; margin-bottom: 1.35rem; }
            .ordem-info-table td, .ordem-items-table th, .ordem-items-table td { border: 1px solid #000; padding: 6px 8px; font-size: 0.8125rem; }
            .ordem-items-table th { background: #f0f0f0; }
            .ordem-info-label { background: #f0f0f0; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; }
            
            .ordem-section-title { font-size: 0.75rem; font-weight: 800; color: #333; text-transform: uppercase; margin: 1.85rem 0 0.5rem; }
            
            .rodape-documento { margin-top: 1.25rem; }
            .assinaturas-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; text-align: center; margin-top: 1.5rem; }
            .assinatura-linha { border-top: 1.5px solid #000; padding-top: 0.75rem; }

            /* Estilos Oficio Específico */
            .oficio-detalhes-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
            .oficio-box { border: 1px solid #000; padding: 10px; border-radius: 4px; }
            .oficio-box label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #555; display: block; margin-bottom: 5px; }
            .oficio-box div { font-weight: 700; font-size: 0.9rem; }
            
            .oficio-justificativa { border: 1px solid #000; padding: 10px; border-radius: 4px; margin-bottom: 1.5rem; }
            .oficio-justificativa label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; color: #555; display: block; margin-bottom: 5px;}
            .oficio-justificativa p { font-size: 0.85rem; margin: 0; line-height: 1.5; color: #000; }

            /* Modo Impressao */
            @media print {
                @page { size: A4 portrait; margin: 6mm 6mm 7mm 6mm; }
                html, body { background: #fff !important; margin: 0 !important; padding: 0 !important; }
                .no-print { display: none !important; }
                .printable-page { border: none !important; margin: 0 0 4mm 0 !important; padding: 0 !important; page-break-after: always; break-after: page; box-shadow: none !important; border-radius: 0 !important;}
                .printable-page:last-of-type { page-break-after: auto; break-after: auto; }
            }
        </style>
    </head>
    <body onload="setTimeout(window.print, 1000);">
        <div class="no-print print-topbar">
            <strong>Impressão em Lote (<?php echo count($aquisicoes); ?> registros)</strong>
            <div class="spacer"></div>
            <a href="imprimir_lote.php" class="btn btn-outline btn-sm">Voltar</a>
            <button onclick="window.print()" class="btn btn-primary btn-sm">Imprimir Novamente</button>
        </div>

        <div class="print-doc">
            <?php foreach ($aquisicoes as $aq): ?>
                
                <?php
                // Buscar itens da Aquisicao
                $stmt_items_aq = $pdo->prepare("SELECT * FROM itens_aquisicao WHERE aquisicao_id = ? ORDER BY id ASC");
                $stmt_items_aq->execute([$aq['id']]);
                $items_aq = $stmt_items_aq->fetchAll();

                // Buscar itens do Oficio
                $stmt_items_of = $pdo->prepare("SELECT * FROM itens_oficio WHERE oficio_id = ? ORDER BY id ASC");
                $stmt_items_of->execute([$aq['oficio_id']]);
                $items_of = $stmt_items_of->fetchAll();
                ?>

                <!-- VIA AQUISIÇÃO -->
                <div class="printable-page">
                    <div class="ordem-header">
                        <div class="ordem-logo">
                            <img src="assets/img/prefeitura.jpg" alt="Logo Prefeitura">
                        </div>
                        <div class="ordem-center">
                            <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">
                                PREFEITURA MUNICIPAL DE COARI
                            </h1>
                            <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">
                                Ordem de Aquisição e Suprimentos
                            </h2>
                            <div style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">
                                COARI - AM | CNPJ: 04.262.432/0001-21
                            </div>
                        </div>
                        <div class="ordem-right">
                            <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">
                                Via Administrativa
                            </div>
                            <div class="ordem-right-box">
                                <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                                <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;">
                                    <?= h(str_replace('AQ-', '', $aq['numero_aq'])) ?>
                                </div>
                            </div>
                            <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                                DATA: <?= date('d/m/Y', strtotime($aq['criado_em'])) ?>
                            </div>
                        </div>
                    </div>

                    <div class="ordem-info-wrap">
                        <table class="ordem-info-table">
                            <tr>
                                <td class="ordem-info-label" style="width: 15%;">Fornecedor:</td>
                                <td style="font-weight: 700;"><?= h(strtoupper($aq['fornecedor'])) ?></td>
                                <td class="ordem-info-label" style="width: 30%;">Local e Data de Emissão:</td>
                                <td style="width: 20%; font-weight: 700;">COARI-AM - <?= date('d/m/Y', strtotime($aq['criado_em'])) ?></td>
                            </tr>
                            <tr>
                                <td class="ordem-info-label">Para:</td>
                                <td style="font-weight: 700;"><?= h(strtoupper($aq['secretaria'])) ?></td>
                                <td class="ordem-info-label">Referência:</td>
                                <td style="font-family: monospace; font-weight: 900; letter-spacing: 1px;"><?= h($aq['oficio_num']) ?></td>
                            </tr>
                        </table>
                    </div>

                    <h3 class="ordem-section-title">AUTORIZAÇÃO DE FORNECIMENTO - AF</h3>

                    <div class="ordem-items-wrap">
                        <table class="ordem-items-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Item</th>
                                    <th style="width: 50px;">Unid.</th>
                                    <th style="width: 60px;">Qtd</th>
                                    <th>Especificação Completa</th>
                                    <th style="width: 110px;">Preço Unitário</th>
                                    <th style="width: 110px;">Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items_aq)): ?>
                                    <tr><td colspan="6" style="text-align: center; font-weight: 700;">Nenhum item.</td></tr>
                                <?php else: ?>
                                    <?php $i = 1; $valorTotalAquisicao = 0; ?>
                                    <?php foreach ($items_aq as $item): ?>
                                        <?php
                                        $quantidade = (float) ($item['quantidade'] ?? 0);
                                        $valorUnitario = (float) ($item['valor_unitario'] ?? 0);
                                        $valorItem = $quantidade * $valorUnitario;
                                        $valorTotalAquisicao += $valorItem;
                                        ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 700;"><?= str_pad($i++, 2, '0', STR_PAD_LEFT) ?></td>
                                            <td style="text-align: center; font-weight: 600;">UN</td>
                                            <td style="text-align: center; font-weight: 700;"><?= number_format($quantidade, 0, ',', '.') ?></td>
                                            <td style="font-weight: 600;"><?= h(strtoupper($item['produto'])) ?></td>
                                            <td style="text-align: center;"><?= money_br($valorUnitario) ?></td>
                                            <td style="text-align: center; font-weight: 700;"><?= money_br($valorItem) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr style="background: #f0f0f0;">
                                        <td colspan="5" style="text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">Valor Total R$</td>
                                        <td style="text-align: right; font-weight: 900; font-size: 0.9375rem;"><?= money_br($valorTotalAquisicao) ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="rodape-documento">
                        <div class="assinaturas-grid">
                            <div>
                                <div class="assinatura-linha">
                                    <div style="font-weight: 800; font-size: 0.875rem;">RECEBEDOR</div>
                                    <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Autorização de Recebimento</div>
                                </div>
                            </div>
                            <div>
                                <div class="assinatura-linha">
                                    <div style="font-weight: 800; font-size: 0.875rem;">CONFIRMAÇÃO DE RECEBIMENTO</div>
                                    <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Assinatura e Carimbo</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIA OFÍCIO -->
                <div class="printable-page">
                    <div class="ordem-header" style="grid-template-columns: 1fr auto 1fr;">
                        <div class="ordem-logo">
                             <img src="assets/img/prefeitura.jpg" alt="Logo Prefeitura">
                        </div>
                        <div class="ordem-center">
                             <h1 style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">
                                 PREFEITURA MUNICIPAL DE COARI
                             </h1>
                             <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">
                                 Solicitação Interna (Ofício)
                             </h2>
                        </div>
                        <div class="ordem-right">
                             <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">
                                Via Solicitação
                            </div>
                             <div class="ordem-right-box">
                                 <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Referência</div>
                                 <div style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;">
                                     <?= h($aq['oficio_num']) ?>
                                 </div>
                             </div>
                             <div style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                                 DATA: <?= date('d/m/Y H:i', strtotime($aq['oficio_criado_em'])) ?>
                             </div>
                        </div>
                    </div>

                    <div class="oficio-detalhes-grid">
                        <div class="oficio-box">
                            <label>Secretaria Solicitante</label>
                            <div><?= h($aq['secretaria']) ?></div>
                            <div style="font-size: 0.8rem; font-weight:normal; margin-top:5px; color:#444;">Resp: <?= h($aq['sec_responsavel']) ?></div>
                        </div>
                        <div class="oficio-box">
                            <label>Cadastrado Por</label>
                            <div><?= h($aq['oficio_usuario']) ?></div>
                        </div>
                        <div class="oficio-box">
                            <label>Orçamento Previsto</label>
                            <div style="font-weight: 900; color: #206bc4;"><?= !empty($aq['valor_orcamento']) ? money_br($aq['valor_orcamento']) : '---'; ?></div>
                        </div>
                    </div>

                    <h3 class="ordem-section-title">ITENS SOLICITADOS NO OFÍCIO</h3>
                    <div class="ordem-items-wrap">
                        <table class="ordem-items-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">Item</th>
                                    <th>Produto / Serviço</th>
                                    <th style="width: 60px;">Qtd</th>
                                    <th style="width: 50px;">Unid.</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($items_of)): ?>
                                    <tr><td colspan="4" style="text-align: center; font-weight: 700;">Nenhum item no ofício.</td></tr>
                                <?php else: ?>
                                    <?php $j = 1; ?>
                                    <?php foreach ($items_of as $itOf): ?>
                                        <tr>
                                            <td style="text-align: center; font-weight: 700;"><?= str_pad($j++, 2, '0', STR_PAD_LEFT) ?></td>
                                            <td style="font-weight: 600;"><?= h($itOf['produto']) ?></td>
                                            <td style="text-align: center; font-weight: 700;"><?= number_format($itOf['quantidade'], 2, ',', '.') ?></td>
                                            <td style="text-align: center; font-weight: 600;"><?= h($itOf['unidade']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="oficio-justificativa">
                        <label>Justificativa e Finalidade</label>
                        <p><?= nl2br(h($aq['justificativa'])) ?></p>
                    </div>

                    <div class="rodape-documento">
                        <div class="assinaturas-grid" style="grid-template-columns: 1fr;">
                            <div style="margin: 0 auto; width: 60%;">
                                <div class="assinatura-linha" style="text-align:center;">
                                    <div style="font-weight: 800; font-size: 0.875rem;"><?= h($aq['sec_responsavel']) ?></div>
                                    <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">Assinatura do Responsável</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// VIEW NORMAL PARA CLICAR "IMPRIMIR LOTE"
$page_title = "Impressão em Lote";
include 'views/layout/header.php';
?>

<div class="card">
    <div class="card-body" style="text-align: center; padding: 4rem 2rem;">
        <div style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;">
            <i class="fas fa-file-pdf"></i>
        </div>
        <h2 style="margin-bottom: 1rem; color: var(--text-dark);">Gerar Documento Unificado (PDF)</h2>
        <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto 2rem auto; font-size: 1.1rem; line-height: 1.6;">
            Esta ferramenta gera um único arquivo pronto para impressão contendo <strong>todas as Aquisições</strong> e seus respectivos <strong>Ofícios de Solicitação</strong>, organizados na sequência de cadastro.
            <br><br>
            A impressão será na seguinte ordem:
            <br>
            <strong>Aquisição 1 &rarr; Ofício 1 &rarr; Aquisição 2 &rarr; Ofício 2 ...</strong>
        </p>

        <a href="imprimir_lote.php?print=1" target="_blank" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.15rem; border-radius: 50px; font-weight: 800;">
            <i class="fas fa-print" style="margin-right: 8px;"></i> Gerar e Imprimir Lote Completo
        </a>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>

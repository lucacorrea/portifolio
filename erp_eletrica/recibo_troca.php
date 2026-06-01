<?php
// recibo_troca.php — Comprovante de Troca Não Fiscal (estilo bobina 80mm)
// Uso: recibo_troca.php?id=<exchange_id>
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (empty($_SESSION['usuario_id'])) { http_response_code(403); exit('Acesso negado.'); }

require_once __DIR__ . '/config.php';

$exchangeId = (int)($_GET['id'] ?? 0);
if (!$exchangeId) { exit('ID inválido.'); }

$db = \App\Config\Database::getInstance()->getConnection();

// Fetch exchange details
$stmt = $db->prepare("
    SELECT t.*, 
           v.data_venda as venda_data,
           v.forma_pagamento as venda_pagamento,
           COALESCE(c.nome, v.nome_cliente_avulso, 'Consumidor Final') as cliente_nome,
           c.cpf_cnpj as cliente_cpf,
           u.nome as vendedor_nome,
           f.nome as filial_nome,
           f.cnpj as filial_cnpj,
           CONCAT_WS(', ', f.logradouro, f.numero, f.bairro) as filial_endereco,
           f.municipio as filial_cidade,
           f.uf as filial_uf,
           f.telefone as filial_telefone,
           po.nome as produto_original_nome,
           po.codigo as produto_original_codigo,
           po.unidade as produto_original_unidade,
           pn.nome as produto_novo_nome,
           pn.codigo as produto_novo_codigo,
           pn.unidade as produto_novo_unidade
    FROM trocas t
    JOIN vendas v ON t.venda_id = v.id
    LEFT JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN usuarios u ON t.usuario_id = u.id
    LEFT JOIN filiais  f ON v.filial_id = f.id
    JOIN produtos po ON t.produto_original_id = po.id
    JOIN produtos pn ON t.produto_novo_id = pn.id
    WHERE t.id = ?
");
$stmt->execute([$exchangeId]);
$troca = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$troca) { exit('Registro de troca não encontrado.'); }

$dataTroca = date('d/m/Y H:i', strtotime($troca['created_at']));
$dataVendaOriginal = date('d/m/Y H:i', strtotime($troca['venda_data']));

$totalDevolvido = $troca['quantidade_original'] * $troca['preco_original'];
$totalNovo = $troca['quantidade_nova'] * $troca['preco_novo'];
$diferenca = (float)$troca['diferenca_valor'];
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>Comprovante de Troca #<?= $exchangeId ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <style>
        :root {
            --ticket-max: 384px;
            --pad: 12px;
            --accent: #1a73e8;
            --ink: #111;
            --paper: #fff;
            --bg: #f5f7fb
        }

        * {
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--ink);
            -webkit-text-size-adjust: 100%
        }

        body {
            font: 13px/1.45 monospace
        }

        .wrapper {
            width: 100%;
            max-width: var(--ticket-max);
            margin: 10px auto 92px;
            background: var(--paper);
            border-radius: 12px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, .08);
            padding: var(--pad)
        }

        header h2 {
            font-size: 14px;
            margin: 4px 0 2px;
            text-transform: uppercase
        }

        .small {
            font-size: 11px;
            color: #111
        }

        .hr {
            border-top: 1px dashed #000;
            margin: 8px 0
        }

        .tbl {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed
        }

        .tbl thead th {
            border-bottom: 1px dashed #000;
            font-weight: 700;
            padding: 4px 0
        }

        .tbl td {
            padding: 3px 0;
            vertical-align: top
        }

        .left {
            text-align: left
        }

        .right {
            text-align: right
        }

        .center {
            text-align: center
        }

        .badge-nf { 
            display:inline-block; background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid #fca5a5; font-weight: bold;
        }

        .badge-success-label {
            display:inline-block; background:#dcfce7; color:#166534; padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid #bbf7d0; font-weight: bold;
        }

        .actions {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 50;
            padding: 10px env(safe-area-inset-right) calc(10px + env(safe-area-inset-bottom)) env(safe-area-inset-left);
            background: #fff;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
            justify-content: center
        }

        .btn {
            appearance: none;
            border: 0;
            border-radius: 10px;
            padding: 11px 16px;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
            white-space: nowrap
        }

        .btn:focus {
            outline: 3px solid rgba(26, 115, 232, .25);
            outline-offset: 2px
        }

        .btn-primary {
            background: var(--accent);
            color: #fff
        }

        .btn-primary:hover {
            filter: brightness(.95)
        }

        .btn-secondary {
            background: #6b7280;
            color: #fff
        }

        .btn-secondary:hover {
            filter: brightness(.95)
        }

        @media (max-width:420px) {
            body {
                font-size: 12px
            }

            .wrapper {
                margin: 6px auto 88px;
                border-radius: 10px
            }

            .tbl thead th,
            .tbl td {
                padding: 2px 0
            }
        }

        @media (max-width:340px) {
            .wrapper {
                border-radius: 0;
                box-shadow: none;
                margin: 0 auto 88px
            }
        }

        @page {
            size: 72mm auto;
            margin: 0;
        }

        @media print {
            html,
            body {
                background: #fff;
                width: 72mm;
                margin: 0;
                padding: 0;
            }

            .wrapper {
                box-shadow: none;
                border-radius: 0;
                margin: 0 auto !important;
                max-width: unset;
                width: 72mm;
                zoom: 113% !important;
                padding: 4mm 0;
                font-size: 14px;
            }

            .actions {
                display: none
            }
        }
    </style>
</head>

<body>
    <div class="wrapper" role="document" aria-label="Comprovante de Troca">
        <header class="center">
            <h2>CENTRO DO ELETRICISTA</h2>
            <div class="small" style="text-transform: uppercase; margin-top: -2px; margin-bottom: 4px;">
                <?= htmlspecialchars($troca['filial_nome'] ?? '') ?>
            </div>
            <div class="small">
                <?php if (!empty($troca['filial_cnpj'])): ?>CNPJ: <?= htmlspecialchars($troca['filial_cnpj']) ?><br><?php endif; ?>
                <?php if (!empty($troca['filial_endereco'])): ?>
                    <?= htmlspecialchars($troca['filial_endereco']) ?>
                    <?php if(!empty($troca['filial_cidade'])): ?> - <?= htmlspecialchars($troca['filial_cidade']) ?>/<?= htmlspecialchars($troca['filial_uf'] ?? '') ?><?php endif; ?>
                    <br>
                <?php endif; ?>
                <?php if (!empty($troca['filial_telefone'])): ?>Tel: <?= htmlspecialchars($troca['filial_telefone']) ?><?php endif; ?>
            </div>
            <div class="hr"></div>
            <div class="center"><span class="badge-nf">COMPROVANTE DE TROCA</span></div>
            <div class="hr"></div>
        </header>

        <div class="small"><b>Troca Nº:</b> <?= $exchangeId ?> &nbsp;&nbsp; <b>Data:</b> <?= $dataTroca ?></div>
        <div class="small"><b>Venda Ref:</b> #<?= $troca['venda_id'] ?> (<?= $dataVendaOriginal ?>)</div>
        <div class="small"><b>Operador:</b> <?= htmlspecialchars($troca['vendedor_nome'] ?? '—') ?></div>

        <div class="hr"></div>

        <div class="small" style="font-weight: bold; text-transform: uppercase; color: #991b1b; margin-bottom: 5px;">▼ Item Devolvido (Retorno)</div>
        <table class="tbl small" aria-label="Item Devolvido">
            <colgroup>
                <col style="width:20%">
                <col style="width:40%">
                <col style="width:10%">
                <col style="width:15%">
                <col style="width:15%">
            </colgroup>
            <thead>
                <tr>
                    <th class="left">Cód</th>
                    <th class="left">Produto</th>
                    <th class="right">Qtd</th>
                    <th class="right">Unit</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="left small"><?= htmlspecialchars($troca['produto_original_codigo']) ?></td>
                    <td class="left"><?= htmlspecialchars(mb_strimwidth($troca['produto_original_nome'], 0, 20, '..')) ?></td>
                    <td class="right"><?= formatarQuantidade($troca['quantidade_original']) ?></td>
                    <td class="right"><?= number_format($troca['preco_original'],2,',','.') ?></td>
                    <td class="right"><?= number_format($totalDevolvido,2,',','.') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="hr" style="border-top-style: dotted;"></div>

        <div class="small" style="font-weight: bold; text-transform: uppercase; color: #166534; margin-bottom: 5px;">▲ Item Novo (Saída)</div>
        <table class="tbl small" aria-label="Item Novo">
            <colgroup>
                <col style="width:20%">
                <col style="width:40%">
                <col style="width:10%">
                <col style="width:15%">
                <col style="width:15%">
            </colgroup>
            <thead>
                <tr>
                    <th class="left">Cód</th>
                    <th class="left">Produto</th>
                    <th class="right">Qtd</th>
                    <th class="right">Unit</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="left small"><?= htmlspecialchars($troca['produto_novo_codigo']) ?></td>
                    <td class="left"><?= htmlspecialchars(mb_strimwidth($troca['produto_novo_nome'], 0, 20, '..')) ?></td>
                    <td class="right"><?= formatarQuantidade($troca['quantidade_nova']) ?></td>
                    <td class="right"><?= number_format($troca['preco_novo'],2,',','.') ?></td>
                    <td class="right"><?= number_format($totalNovo,2,',','.') ?></td>
                </tr>
            </tbody>
        </table>

        <div class="hr"></div>

        <table class="tbl small" aria-label="Resumo Financeiro">
            <tbody>
                <tr>
                    <td class="left">TOTAL DEVOLVIDO</td>
                    <td class="right">R$ <?= number_format($totalDevolvido,2,',','.') ?></td>
                </tr>
                <tr>
                    <td class="left">TOTAL LEVADO</td>
                    <td class="right">R$ <?= number_format($totalNovo,2,',','.') ?></td>
                </tr>
                <tr style="border-top:1px dashed #000">
                    <td class="left" style="font-size:14px;padding-top:4px"><b>DIFERENÇA</b></td>
                    <td class="right" style="font-size:14px;padding-top:4px">
                        <b>R$ <?= number_format(abs($diferenca),2,',','.') ?></b>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="center small" style="color:#111; font-weight: bold; margin-bottom: 5px;">
            "Dai graças ao Senhor sempre, Amém"
        </div>

        <div class="center small" style="color:#888;">
            Este comprovante não tem validade fiscal.<br>Obrigado pela preferência!
        </div>
    </div>

    <!-- Barra de ações (oculta se for iframe para impressão silenciosa) -->
    <div class="actions" aria-label="Ações" id="print-actions">
        <button class="btn btn-secondary" onclick="window.close()">← Fechar</button>
        <button id="btn-print" class="btn btn-primary" type="button">🖨️ Imprimir</button>
    </div>

    <script>
        (function() {
            const isIframe = window.self !== window.top;
            const btnPrint = document.getElementById('btn-print');
            const actions = document.getElementById('print-actions');
            
            if (isIframe && actions) {
                actions.style.display = 'none';
            }

            if (btnPrint) {
                btnPrint.addEventListener('click', function() {
                    window.print();
                });
            }
            
            function triggerPrint() {
                window.focus();
                window.print();
                
                if (!isIframe && (window.opener || window.name === 'print_popup')) {
                    setTimeout(() => window.close(), 500);
                }
            }

            if (document.readyState === 'complete') {
                triggerPrint();
            } else {
                window.addEventListener('load', triggerPrint);
            }
        })();
    </script>
</body>

</html>

<?php
// recibo_venda.php — Recibo Não Fiscal (estilo bobina 80mm)
// Uso: recibo_venda.php?id=<venda_id>
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (empty($_SESSION['usuario_id'])) { http_response_code(403); exit('Acesso negado.'); }

require_once __DIR__ . '/config.php';

$vendaId = (int)($_GET['id'] ?? 0);
if (!$vendaId) { exit('ID inválido.'); }

$db = \App\Config\Database::getInstance()->getConnection();

// Fetch sale — column names fixed: logradouro, municipio, numero, bairro
$stmt = $db->prepare("
    SELECT v.*, 
           COALESCE(c.nome, v.nome_cliente_avulso, 'Consumidor Final') as cliente_nome,
           c.cpf_cnpj,
           u.nome as vendedor_nome,
           f.nome as filial_nome,
           f.cnpj as filial_cnpj,
           CONCAT_WS(', ', f.logradouro, f.numero, f.bairro) as filial_endereco,
           f.municipio as filial_cidade,
           f.uf as filial_uf,
           f.telefone as filial_telefone,
           f.razao_social as filial_razao
    FROM vendas v
    LEFT JOIN clientes c ON v.cliente_id = c.id
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    LEFT JOIN filiais  f ON v.filial_id = f.id
    WHERE v.id = ?
");
$stmt->execute([$vendaId]);
$venda = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$venda) { exit('Venda não encontrada.'); }

// Fetch items
$stmtI = $db->prepare("
    SELECT vi.quantidade, vi.preco_unitario, p.nome, p.codigo, p.unidade
    FROM vendas_itens vi
    JOIN produtos p ON vi.produto_id = p.id
    WHERE vi.venda_id = ?
");
$stmtI->execute([$vendaId]);
$itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

$paymentMap = [
    'dinheiro'       => 'Dinheiro',
    'pix'            => 'PIX',
    'cartao_credito' => 'Cartão de Crédito',
    'cartao_debito'  => 'Cartão de Débito',
    'boleto'         => 'Boleto',
    'fiado'          => 'A Prazo (Fiado)',
    'credito'        => 'Crédito',
    'debito'         => 'Débito',
];
$formaPag = $paymentMap[$venda['forma_pagamento']] ?? strtoupper($venda['forma_pagamento']);
$dataVenda = date('d/m/Y H:i', strtotime($venda['data_venda'] ?? $venda['created_at'] ?? 'now'));

// Compute troco: if payment is dinheiro and valor_recebido > valor_total
$valorTotal   = (float)($venda['valor_total'] ?? 0);
$valorRecebido = isset($venda['valor_recebido']) ? (float)$venda['valor_recebido'] : null;
$troco         = ($valorRecebido !== null && $venda['forma_pagamento'] === 'dinheiro' && $valorRecebido > $valorTotal)
                 ? ($valorRecebido - $valorTotal) : 0;
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>Recibo #<?= $vendaId ?> - Não Fiscal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <style>
        :root {
            --ticket-max: 384px;
            --pad: 12px;
            --qr: 210px;
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

        .key {
            letter-spacing: 1px;
            word-spacing: 4px
        }

        .logo {
            max-height: 28px
        }

        .qr {
            display: block;
            margin: 8px auto;
            width: min(var(--qr), calc(100% - 2*var(--pad)));
            height: auto;
            aspect-ratio: 1/1
        }

        .badge {
            display: inline-block;
            background: #eef2ff;
            color: #1f2937;
            padding: 3px 6px;
            border-radius: 6px;
            font-size: 10px
        }
        
        .badge-nf { 
            display:inline-block; background:#fee2e2; color:#991b1b; padding:4px 8px; border-radius:6px; font-size:12px; border:1px solid #fca5a5; font-weight: bold;
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

            :root {
                --qr: 180px
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
            size: 80mm auto;
            margin: 3mm
        }

        @media print {

            html,
            body {
                background: #fff
            }

            .wrapper {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: unset;
                width: 75mm;
                padding: 0
            }

            .actions {
                display: none
            }

            .qr {
                width: 210px;
                height: 210px
            }
        }
    </style>
</head>

<body>
    <div class="wrapper" role="document" aria-label="Recibo Não Fiscal">
        <header class="center">
            <h2><?= htmlspecialchars($venda['filial_nome'] ?? 'ERP Elétrica') ?></h2>
            <div class="small">
                <?php if (!empty($venda['filial_cnpj'])): ?>CNPJ: <?= htmlspecialchars($venda['filial_cnpj']) ?><br><?php endif; ?>
                <?php if (!empty($venda['filial_endereco'])): ?>
                    <?= htmlspecialchars($venda['filial_endereco']) ?>
                    <?php if(!empty($venda['filial_cidade'])): ?> - <?= htmlspecialchars($venda['filial_cidade']) ?>/<?= htmlspecialchars($venda['filial_uf'] ?? '') ?><?php endif; ?>
                    <br>
                <?php endif; ?>
                <?php if (!empty($venda['filial_telefone'])): ?>Tel: <?= htmlspecialchars($venda['filial_telefone']) ?><?php endif; ?>
            </div>
            <div class="hr"></div>
            <div class="center"><span class="badge-nf">DOCUMENTO NÃO FISCAL</span></div>
            <div class="hr"></div>
        </header>

        <div class="small"><b>Recibo Nº:</b> <?= $vendaId ?> &nbsp;&nbsp; <b>Data:</b> <?= $dataVenda ?></div>
        <div class="small"><b>Vendedor:</b> <?= htmlspecialchars($venda['vendedor_nome'] ?? '—') ?></div>

        <div class="hr"></div>

        <table class="tbl small" aria-label="Itens">
            <colgroup>
                <col style="width:40%">
                <col style="width:10%">
                <col style="width:10%">
                <col style="width:18%">
                <col style="width:22%">
            </colgroup>
            <thead>
                <tr>
                    <th class="left">Produto</th>
                    <th class="right">Qtd</th>
                    <th class="left">Un</th>
                    <th class="right">V.Unit</th>
                    <th class="right">V.Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $it): 
                    $subtotal = $it['quantidade'] * $it['preco_unitario'];
                ?>
                    <tr>
                        <td class="left"><?= htmlspecialchars(mb_strimwidth($it['nome'], 0, 22, '..')) ?></td>
                        <td class="right"><?= number_format($it['quantidade'],2,',','.') ?></td>
                        <td class="left"><?= htmlspecialchars($it['unidade'] ?? 'UN') ?></td>
                        <td class="right"><?= number_format($it['preco_unitario'],2,',','.') ?></td>
                        <td class="right"><?= number_format($subtotal,2,',','.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="hr"></div>

        <table class="tbl small" aria-label="Totais">
            <tbody>
                <tr>
                    <td class="left"><b>QTDE DE ITENS</b></td>
                    <td class="right"><?= count($itens) ?></td>
                </tr>
                <?php if ($venda['desconto_total'] > 0): ?>
                <tr>
                    <td class="left">DESCONTO</td>
                    <td class="right">- R$ <?= number_format($venda['desconto_total'],2,',','.') ?></td>
                </tr>
                <?php endif; ?>
                <tr style="border-top:1px dashed #000">
                    <td class="left" style="font-size:14px;padding-top:4px"><b>TOTAL R$</b></td>
                    <td class="right" style="font-size:14px;padding-top:4px"><b><?= number_format($valorTotal,2,',','.') ?></b></td>
                </tr>
                <tr>
                    <td class="left"><b>FORMA DE PAGAMENTO</b></td>
                    <td class="right"><?= htmlspecialchars($formaPag) ?></td>
                </tr>
                <?php if ($valorRecebido !== null && $venda['forma_pagamento'] === 'dinheiro'): ?>
                <tr>
                    <td class="left">VL. RECEBIDO R$</td>
                    <td class="right"><?= number_format($valorRecebido,2,',','.') ?></td>
                </tr>
                <tr>
                    <td class="left"><b>TROCO R$</b></td>
                    <td class="right"><b><?= number_format($troco,2,',','.') ?></b></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="hr"></div>

        <div class="small"><b>CONSUMIDOR</b></div>
        <div class="small">
            <?= htmlspecialchars($venda['cliente_nome']) ?><br>
            <?php if (!empty($venda['cpf_cnpj'])): ?>CPF/CNPJ: <?= htmlspecialchars($venda['cpf_cnpj']) ?><?php endif; ?>
        </div>

        <div class="hr"></div>

        <div class="center small" style="color:#888;">
            Este documento não tem validade fiscal.<br>Obrigado pela preferência!
        </div>
    </div>

    <!-- Barra de ações -->
    <div class="actions" aria-label="Ações">
        <button class="btn btn-secondary" onclick="window.close()">← Fechar</button>
        <button id="btn-print" class="btn btn-primary" type="button">🖨️ Imprimir</button>
    </div>

    <script>
        (function() {
            document.getElementById('btn-print').addEventListener('click', function() {
                window.print();
            });
            window.addEventListener('load', function() {
                setTimeout(() => window.print(), 600);
            });
        })();
    </script>
</body>

</html>

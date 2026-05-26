<?php
// orcamento_imprimir.php — Impressão de Orçamento (estilo bobina 72mm/80mm)
// Uso: orcamento_imprimir.php?code=<codigo_orcamento>
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (empty($_SESSION['usuario_id'])) { http_response_code(403); exit('Acesso negado.'); }

require_once __DIR__ . '/config.php';

$code = $_GET['code'] ?? '';
if (!$code) { exit('Código inválido.'); }

$db = \App\Config\Database::getInstance()->getConnection();

// Auto-migrate missing columns for A4 budget feature to prevent breaking production
$columns = [
    'razao_social VARCHAR(255) NULL',
    'cep VARCHAR(20) NULL',
    'banco_agencia VARCHAR(50) NULL',
    'banco_cc VARCHAR(50) NULL'
];
foreach ($columns as $col) {
    try {
        $db->exec("ALTER TABLE clientes ADD COLUMN $col");
    } catch (\Exception $e) {
        // Ignore error if column already exists
    }
}

// Fetch Pre-sale / Budget details
$stmt = $db->prepare("
    SELECT pv.*, 
           COALESCE(c.nome, pv.nome_cliente_avulso, 'Consumidor Final') as cliente_nome,
           COALESCE(c.cpf_cnpj, pv.cpf_cliente) as cliente_doc,
           c.razao_social as cliente_razao_social,
           c.endereco as cliente_endereco,
           c.cep as cliente_cep,
           c.email as cliente_email,
           c.telefone as cliente_telefone,
           c.banco_agencia as cliente_banco_agencia,
           c.banco_cc as cliente_banco_cc,
           u.nome as vendedor_nome,
           f.nome as filial_nome,
           f.cnpj as filial_cnpj,
           CONCAT_WS(', ', f.logradouro, f.numero, f.bairro) as filial_endereco,
           f.municipio as filial_cidade,
           f.uf as filial_uf,
           f.telefone as filial_telefone,
           f.razao_social as filial_razao_social,
           f.inscricao_estadual as filial_inscricao_estadual,
           f.cep as filial_cep,
           f.dados_bancarios as filial_dados_bancarios,
           f.chave_pix as filial_chave_pix,
           f.titular_conta as filial_titular_conta,
           f.complemento as filial_complemento
    FROM pre_vendas pv
    LEFT JOIN clientes c ON pv.cliente_id = c.id
    LEFT JOIN usuarios u ON pv.usuario_id = u.id
    LEFT JOIN filiais  f ON pv.filial_id = f.id
    WHERE pv.codigo = ?
");
$stmt->execute([$code]);
$pv = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pv) { exit('Orçamento não encontrado.'); }

// Fetch items
$stmtI = $db->prepare("
    SELECT pvi.quantidade, pvi.preco_unitario, p.nome, p.codigo, p.unidade
    FROM pre_venda_itens pvi
    JOIN produtos p ON pvi.produto_id = p.id
    WHERE pvi.pre_venda_id = ?
");
$stmtI->execute([$pv['id']]);
$itens = $stmtI->fetchAll(PDO::FETCH_ASSOC);

$dataOrcamento = date('d/m/Y H:i', strtotime($pv['created_at']));
$validadeOrcamento = date('d/m/Y H:i', strtotime($pv['created_at'] . ' + 24 hours'));
$valorTotal = (float)($pv['valor_total'] ?? 0);

// Check if expired
$createdAt = strtotime($pv['created_at']);
$now = time();
$diffHours = ($now - $createdAt) / 3600;
$isExpired = $diffHours >= 24;

$type = $_GET['type'] ?? 'cupom';
if ($type === 'A4') {
    require __DIR__ . '/orcamento_imprimir_a4.php';
    exit;
}
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>Orçamento <?= htmlspecialchars($pv['codigo']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <style>
        :root {
            --ticket-max: 384px;
            --pad: 12px;
            --accent: #10b981;
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
            display: inline-block;
            background: #000 !important;
            color: #fff !important;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: 2px;
            border: 2px solid #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            margin: 4px 0;
        }

        .badge-expired {
            display: inline-block;
            background: #fee2e2 !important;
            color: #991b1b !important;
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 900;
            letter-spacing: 1px;
            border: 2px solid #991b1b;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            margin: 4px 0;
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
            outline: 3px solid rgba(16, 185, 129, .25);
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
    <div class="wrapper" role="document" aria-label="Orçamento">
        <header class="center">
            <h2>CENTRO DO ELETRICISTA</h2>
            <div class="small" style="text-transform: uppercase; margin-top: -2px; margin-bottom: 4px;">
                <?= htmlspecialchars($pv['filial_nome'] ?? '') ?>
            </div>
            <div class="small">
                <?php if (!empty($pv['filial_cnpj'])): ?>CNPJ: <?= htmlspecialchars($pv['filial_cnpj']) ?><br><?php endif; ?>
                <?php if (!empty($pv['filial_endereco'])): ?>
                    <?= htmlspecialchars($pv['filial_endereco']) ?>
                    <?php if(!empty($pv['filial_cidade'])): ?> - <?= htmlspecialchars($pv['filial_cidade']) ?>/<?= htmlspecialchars($pv['filial_uf'] ?? '') ?><?php endif; ?>
                    <br>
                <?php endif; ?>
                <?php if (!empty($pv['filial_telefone'])): ?>Tel: <?= htmlspecialchars($pv['filial_telefone']) ?><?php endif; ?>
            </div>
            <div class="hr"></div>
            <div class="center">
                <div style="margin-bottom: 6px;"><span class="badge-nf">ORÇAMENTO</span></div>
            </div>
            <div class="hr"></div>
        </header>

        <div class="small"><b>Orçamento n. <?= htmlspecialchars($pv['codigo']) ?></b> &nbsp;&nbsp; <b>Data:</b> <?= $dataOrcamento ?></div>
        <div class="small"><b>Vendedor:</b> <?= htmlspecialchars($pv['vendedor_nome'] ?? '—') ?></div>

        <div class="hr"></div>

        <table class="tbl small" aria-label="Itens">
            <colgroup>
                <col style="width:7%">
                <col style="width:18%">
                <col style="width:25%">
                <col style="width:10%">
                <col style="width:8%">
                <col style="width:16%">
                <col style="width:16%">
            </colgroup>
            <thead>
                <tr>
                    <th class="left">#</th>
                    <th class="left">Cód</th>
                    <th class="left">Produto</th>
                    <th class="right">Qtd</th>
                    <th class="left">Un</th>
                    <th class="right">V.Unit</th>
                    <th class="right">V.Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $idx = 1; foreach ($itens as $it): 
                    $subtotal = $it['quantidade'] * $it['preco_unitario'];
                ?>
                    <tr>
                        <td class="left"><?= $idx++ ?></td>
                        <td class="left small"><?= htmlspecialchars($it['codigo']) ?></td>
                        <td class="left"><?= htmlspecialchars(mb_strimwidth($it['nome'], 0, 20, '..')) ?></td>
                        <td class="right"><?= formatarQuantidade($it['quantidade']) ?></td>
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
                <tr style="border-top:1px dashed #000">
                    <td class="left" style="font-size:14px;padding-top:4px"><b>TOTAL R$</b></td>
                    <td class="right" style="font-size:14px;padding-top:4px"><b><?= number_format($valorTotal,2,',','.') ?></b></td>
                </tr>
            </tbody>
        </table>

        <div class="hr"></div>

        <!-- Rodapé Customizado: Nesse campo embaixo onde estar escrito Consumidor põe a Validade -->
        <div style="border: 2px dashed #000; padding: 6px 10px; margin: 8px 0; text-align: center;">
            <b style="font-size: 14px; display: block; letter-spacing: 1px; margin: 0;">*** VÁLIDO POR 24H ***</b>
        </div>
        <div class="small" style="margin-top: 6px;">
            <b>Cliente:</b> <?= htmlspecialchars($pv['cliente_nome']) ?><br>
            <?php if (!empty($pv['cliente_doc'])): ?>CPF/CNPJ: <?= htmlspecialchars($pv['cliente_doc']) ?><?php endif; ?>
        </div>

        <div class="hr"></div>

        <div class="center small" style="color:#111; font-weight: bold; margin-bottom: 5px;">
            "Dai graças ao Senhor sempre, Amém"
        </div>

        <div class="center" style="color:#111; font-weight: bold; font-size: 11px; border-top: 1px dashed #000; padding-top: 4px; margin-top: 4px;">
            Este documento é um orçamento e não tem validade fiscal.
        </div>
    </div>

    <!-- Barra de ações -->
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

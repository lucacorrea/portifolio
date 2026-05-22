<?php
// Included by orcamento_imprimir.php
// Available variables: $pv, $itens, $dataOrcamento, $valorTotal

$docRazaoSocial = !empty($pv['cliente_razao_social']) ? $pv['cliente_razao_social'] : (!empty($pv['cliente_nome']) ? $pv['cliente_nome'] : '');
$docCnpjCpf = !empty($pv['cliente_doc']) ? $pv['cliente_doc'] : '';
$docEndereco = !empty($pv['cliente_endereco']) ? $pv['cliente_endereco'] : '';
$docCep = !empty($pv['cliente_cep']) ? $pv['cliente_cep'] : '';
$docEmail = !empty($pv['cliente_email']) ? $pv['cliente_email'] : '';
$docTelefone = !empty($pv['cliente_telefone']) ? $pv['cliente_telefone'] : '';
$docAgencia = !empty($pv['cliente_banco_agencia']) ? $pv['cliente_banco_agencia'] : '';
$docCc = !empty($pv['cliente_banco_cc']) ? $pv['cliente_banco_cc'] : '';

// Helper for formatting data
function formatDocLine($label, $value, $minSpaces = 30) {
    if (empty($value)) {
        return "<b>{$label}</b> " . str_repeat('_', $minSpaces);
    }
    return "<b>{$label}</b> " . htmlspecialchars($value);
}

// Current date formatted
$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$currentDate = date('d') . ' de ' . $meses[(int)date('m')] . ' de ' . date('Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento A4 - <?= htmlspecialchars($pv['codigo']) ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 14px;
            line-height: 1.3;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        .header-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
        }
        .logo-img {
            max-width: 250px;
            height: auto;
        }
        .company-info {
            font-size: 13px;
            line-height: 1.4;
        }
        .company-title {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .highlight-blue {
            background-color: #00ffff;
            font-weight: bold;
        }
        .title-orcamento {
            color: red;
            font-size: 24px;
            font-weight: bold;
            text-align: right;
            padding-top: 20px;
        }
        .section-title {
            background-color: #d9d9d9;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            border: 1px solid #000;
            padding: 4px;
            margin-top: 15px;
        }
        .bordered-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .bordered-table td, .bordered-table th {
            border: 1px solid #000;
            padding: 4px 6px;
            vertical-align: middle;
        }
        .bordered-table th {
            text-align: center;
            font-weight: bold;
        }
        .table-center { text-align: center; }
        .table-right { text-align: right; }
        .table-left { text-align: left; }
        
        .client-info-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .client-info-table td {
            padding: 4px 6px;
            border: 1px solid #000;
        }
        
        .items-table th {
            background-color: #fff;
        }
        
        .total-row {
            background-color: #d9d9d9;
            font-weight: bold;
        }
        
        .info-relevantes {
            margin-top: 20px;
            font-size: 13px;
        }
        .info-relevantes p { margin: 2px 0; }
        
        .footer-signature {
            text-align: center;
            margin-top: 50px;
            font-size: 14px;
        }
        
        .no-print-actions {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #fff;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .no-print-actions button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            background: #0d6efd;
            color: #fff;
            border: none;
            border-radius: 4px;
        }
        
        @media print {
            .no-print-actions { display: none !important; }
            body { background: transparent; }
        }
    </style>
</head>
<body>

<div class="container">
    <table class="header-table">
        <tr>
            <td style="width: 70%;">
                <img src="logo_sistema_erp_eletrica.PNG" alt="Centro do Eletricista" class="logo-img">
                <div class="company-info">
                    <div class="company-title">CENTRO DO ELETRICISTA - COMPROVANTES</div>
                    Rua Vasco Martins Nº 200 B, Chagas Aguiar - Coari/AM - CEP: 69460-000<br>
                    Rua Brito Inglez S/N Centro - Codajás/AM - CEP: 69460-000 (Prox. do Banco Bradesco)<br>
                    CNPJ: 35.621.921/0001-45 Inscrição Estadual: 05.415.271-2<br>
                    <span class="highlight-blue">Dados Bancários: Banco Cooperativo Sicoob - Cod: 756 - Agência: 0002 C/C: 91103-7 Chave Pix: 9298115-4226</span><br>
                    <span class="highlight-blue">Empresa: DAB COMERCIO VAREJISTA DE MATERIAL ELÉTRICO LTDA</span><br>
                    <span class="highlight-blue">Titular: RAYLSON DE ARAUJO BEZERRA</span>
                </div>
            </td>
            <td style="width: 30%; text-align: right; vertical-align: top;">
                <div class="title-orcamento">ORÇAMENTO</div>
                <div style="font-size: 12px; margin-top: 10px;">Nº <?= htmlspecialchars($pv['codigo']) ?></div>
            </td>
        </tr>
    </table>

    <div class="section-title">1. IDENTIFICAÇÃO DO CLIENTE</div>
    <table class="client-info-table">
        <tr>
            <td style="width: 60%;"><?= formatDocLine('RAZÃO SOCIAL:', $docRazaoSocial, 50) ?></td>
            <td style="width: 40%;"><?= formatDocLine('CNPJ/CPF:', $docCnpjCpf, 20) ?></td>
        </tr>
        <tr>
            <td><?= formatDocLine('ENDEREÇO:', $docEndereco, 50) ?></td>
            <td><?= formatDocLine('CEP:', $docCep, 20) ?></td>
        </tr>
        <tr>
            <td><?= formatDocLine('E-MAIL:', $docEmail, 50) ?></td>
            <td><?= formatDocLine('TELEFONE:', $docTelefone, 20) ?></td>
        </tr>
        <tr>
            <td colspan="2">
                <b>DADOS BANCÁRIOS:</b> AGÊNCIA <?= empty($docAgencia) ? '___________' : htmlspecialchars($docAgencia) ?> 
                C/C <?= empty($docCc) ? '___________' : htmlspecialchars($docCc) ?>
            </td>
        </tr>
    </table>

    <div class="section-title">2. DADOS DO MATERIAL/SERVIÇO</div>
    <table class="bordered-table items-table">
        <thead>
            <tr>
                <th style="width: 8%;">ITEM</th>
                <th style="width: 45%;">DESCRIÇÃO</th>
                <th style="width: 10%;">UND</th>
                <th style="width: 10%;">QTDE</th>
                <th style="width: 12%;">VALOR<br>UNITÁRIO</th>
                <th style="width: 15%;">VALOR<br>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php $itemNum = 1; foreach ($itens as $it): 
                $subtotal = $it['quantidade'] * $it['preco_unitario'];
            ?>
            <tr>
                <td class="table-center" style="color: red;"><?= $itemNum++ ?></td>
                <td class="table-left"><?= htmlspecialchars($it['nome']) ?></td>
                <td class="table-center"><?= htmlspecialchars($it['unidade'] ?? 'UN') ?></td>
                <td class="table-center"><?= formatarQuantidade($it['quantidade']) ?></td>
                <td class="table-center"><?= number_format($it['preco_unitario'], 2, ',', '.') ?></td>
                <td class="table-center"><?= number_format($subtotal, 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="5" class="table-right">TOTAL</td>
                <td class="table-center"><?= number_format($valorTotal, 2, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <div class="info-relevantes">
        Informações relevantes:<br>
        1. O presente orçamento tem validade de 02 (dois) dias;<br>
        <span class="highlight-blue">2. A entrega deste material é combinar;</span><br>
        3. O preço proposto acima contempla todas as despesas necessárias ao pleno fornecimento, tais como os encargos<br>
        &nbsp;&nbsp;&nbsp;&nbsp; (obrigações sociais, impostos, taxas, etc.) e frete, se for o caso.<br>
        4. O Cliente fica responsável de fazer a RETIRADA dos produtos na LOJA.
    </div>

    <div class="footer-signature">
        Coari/Codajás - AM, <?= $currentDate ?><br><br><br>
        CENTRO DO ELETRICISTA<br>
        CNPJ 35.621.921/0001-45
    </div>
</div>

<div class="no-print-actions">
    <button onclick="window.print()">🖨️ Imprimir A4</button>
</div>

<script>
    // Auto print if requested, but wait a bit for styles
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 300);
    };
</script>

</body>
</html>

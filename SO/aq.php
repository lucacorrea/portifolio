<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
$dataAtualIso = date('Y-m-d');
$dataAtualBr  = date('d / m / Y');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem de Aquisição Manual</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .container {
            max-width: 1250px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary {
            background: #1d4ed8;
            color: #fff;
        }

        .btn-success {
            background: #15803d;
            color: #fff;
        }

        .btn-danger {
            background: #b91c1c;
            color: #fff;
        }

        .btn-secondary {
            background: #374151;
            color: #fff;
        }

        .form-card,
        .printable-page {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.07);
            border: 1px solid #e5e7eb;
        }

        .form-card {
            padding: 20px;
            margin-bottom: 24px;
        }

        .form-title {
            margin: 0 0 18px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            padding: 11px 12px;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            font-size: 14px;
            outline: none;
        }

        .field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .span-2 { grid-column: span 2; }
        .span-3 { grid-column: span 3; }
        .span-4 { grid-column: span 4; }

        .items-box {
            margin-top: 18px;
            border-top: 1px solid #e5e7eb;
            padding-top: 18px;
        }

        .items-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 800;
        }

        .item-row {
            display: grid;
            grid-template-columns: 80px 100px 1fr 140px 140px 70px;
            gap: 10px;
            margin-bottom: 10px;
            align-items: end;
        }

        .print-doc {
            max-width: 1120px;
            margin: 0 auto;
        }

        .printable-page {
            margin-bottom: 2rem;
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

        .ordem-logo-box {
            width: 180px;
            height: 78px;
            border: 1.5px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            text-align: center;
            padding: 8px;
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
            vertical-align: top;
        }

        .ordem-items-table thead tr,
        .ordem-items-table tfoot tr,
        .ordem-info-label {
            background: #f0f0f0;
        }

        .ordem-items-table tbody tr:last-child td,
        .ordem-items-table tfoot td {
            border-bottom: 1px solid #000 !important;
        }

        .ordem-section-title {
            font-size: 0.75rem;
            font-weight: 800;
            color: #333;
            text-transform: uppercase;
            margin: 1.85rem 0 0.5rem;
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

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .empty-note {
            text-align: center;
            color: #666;
            font-weight: 700;
            padding: 12px;
        }

        @media (max-width: 992px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .span-2,
            .span-3,
            .span-4 {
                grid-column: span 2;
            }

            .item-row {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 14px;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .span-2,
            .span-3,
            .span-4 {
                grid-column: span 1;
            }

            .print-topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .print-topbar .btn,
            .topbar .btn {
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
            .ordem-header > div:first-child {
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
                margin-bottom: 12px !important;
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
</head>
<body>
<div class="container">

    <div class="topbar no-print">
        <button class="btn btn-primary" onclick="adicionarItem()">+ Adicionar item</button>
        <button class="btn btn-success" onclick="window.print()">Imprimir / Salvar em PDF</button>
        <button class="btn btn-secondary" onclick="limparFormulario()">Limpar tudo</button>
    </div>

    <div class="form-card no-print">
        <h2 class="form-title">Preencher Ordem de Aquisição</h2>

        <div class="grid">
            <div class="field">
                <label>Número da ordem</label>
                <input type="text" id="numero_aq" placeholder="Ex: AQ-001/2026" oninput="atualizarPreview()">
            </div>

            <div class="field">
                <label>Referência / Ofício</label>
                <input type="text" id="oficio_num" placeholder="Ex: OF-023/2026" oninput="atualizarPreview()">
            </div>

            <div class="field span-2">
                <label>Fornecedor</label>
                <input type="text" id="fornecedor" placeholder="Nome do fornecedor" oninput="atualizarPreview()">
            </div>

            <div class="field">
                <label>CNPJ do fornecedor</label>
                <input type="text" id="fornecedor_cnpj" placeholder="00.000.000/0001-00" oninput="atualizarPreview()">
            </div>

            <div class="field">
                <label>Contato do fornecedor</label>
                <input type="text" id="fornecedor_contato" placeholder="Telefone / responsável" oninput="atualizarPreview()">
            </div>

            <div class="field span-2">
                <label>Secretaria / Destino</label>
                <input type="text" id="secretaria" placeholder="Ex: Secretaria Municipal de Saúde" oninput="atualizarPreview()">
            </div>

            <div class="field span-2">
                <label>Responsável</label>
                <input type="text" id="sec_responsavel" placeholder="Nome do responsável" oninput="atualizarPreview()">
            </div>

            <div class="field span-4">
                <label>Texto da observação da via do fornecedor</label>
                <textarea id="texto_entrega" oninput="atualizarPreview()">No ato da entrega, esta via deverá ser carimbada e assinada pelo responsável. Para fins de pagamento, o fornecedor deve apresentar esta ordem devidamente assinada no setor administrativo/financeiro.</textarea>
            </div>
        </div>

        <div class="items-box">
            <h3 class="items-title">Itens da aquisição</h3>
            <div id="itens-formulario"></div>
        </div>
    </div>

    <div class="print-doc">

        <div class="printable-page">
            <div class="card-body">
                <div class="ordem-header">
                    <div class="ordem-logo">
                        <div class="ordem-logo-box">LOGO / BRASÃO</div>
                    </div>

                    <div class="ordem-center">
                        <h1 id="pv_orgao_nome_1" style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">
                            PREFEITURA MUNICIPAL DE COARI
                        </h1>
                        <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">
                            ORDEM DE AQUISIÇÃO E SUPRIMENTOS
                        </h2>
                        <div id="pv_orgao_meta_1" style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">
                            COARI - AM | CNPJ: 04.262.432/0001-21
                        </div>
                    </div>

                    <div class="ordem-right">
                        <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">
                            Via Administrativa
                        </div>
                        <div class="ordem-right-box">
                            <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                            <div id="pv_numero_1" style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;">000</div>
                        </div>
                        <div id="pv_datahora_1" style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                            DATA: <?php echo $dataAtualBr; ?>
                        </div>
                        <div style="margin-top:8px; font-size:0.72rem; line-height:1.4;">
                            <strong>CNPJ Fornecedor:</strong> <span id="pv_cnpj_fornecedor_1">-</span><br>
                            <strong>Contato:</strong> <span id="pv_contato_fornecedor_1">-</span>
                        </div>
                    </div>
                </div>

                <div class="ordem-info-wrap">
                    <table class="ordem-info-table">
                        <tr>
                            <td class="ordem-info-label" style="width: 15%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Fornecedor:</td>
                            <td id="pv_fornecedor_1" style="font-weight: 700;">-</td>
                            <td class="ordem-info-label" style="width: 30%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Local e Data de Emissão:</td>
                            <td id="pv_data_1" style="width: 20%; font-weight: 700;"><?php echo $dataAtualBr; ?></td>
                        </tr>
                        <tr>
                            <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Para:</td>
                            <td id="pv_secretaria_1" style="font-weight: 700;">-</td>
                            <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Referência:</td>
                            <td id="pv_oficio_1" style="font-family: monospace; font-weight: 900; letter-spacing: 1px;">-</td>
                        </tr>
                        <tr>
                            <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Responsável:</td>
                            <td id="pv_responsavel_1" style="font-weight: 700;">-</td>
                            <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Número da Aquisição:</td>
                            <td id="pv_numero_full_1" style="font-family: monospace; font-weight: 900; letter-spacing: 1px;">-</td>
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
                        <tbody id="preview_itens_1">
                            <tr><td colspan="6" class="empty-note">Nenhum item adicionado.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">
                                    Valor Total R$
                                </td>
                                <td id="preview_total_1" style="text-align: right; font-weight: 900; font-size: 0.9375rem;">
                                    R$ 0,00
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="assinaturas-grid">
                    <div>
                        <div class="assinatura-linha">
                            <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                            <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                Autorização de Saída
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

        <div class="printable-page">
            <div class="card-body">
                <div class="ordem-header">
                    <div class="ordem-logo">
                        <div class="ordem-logo-box">LOGO / BRASÃO</div>
                    </div>

                    <div class="ordem-center">
                        <h1 id="pv_orgao_nome_2" style="font-size: 1.25rem; font-weight: 800; margin: 0; color: #000; text-transform: uppercase;">
                            PREFEITURA MUNICIPAL DE COARI
                        </h1>
                        <h2 style="font-size: 0.8rem; font-weight: 700; margin: 2px 0 0; color: #333; text-transform: uppercase;">
                            ORDEM DE AQUISIÇÃO E SUPRIMENTOS
                        </h2>
                        <div id="pv_orgao_meta_2" style="font-size: 0.7rem; margin-top: 4px; color: #666; font-weight: 600;">
                            COARI - AM | CNPJ: 04.262.432/0001-21
                        </div>
                    </div>

                    <div class="ordem-right">
                        <div style="font-weight: 800; color: #999; font-size: 0.65rem; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.1em;">
                            Via Fornecedor
                        </div>
                        <div class="ordem-right-box">
                            <div style="font-size: 0.6rem; font-weight: 800; color: #000; text-transform: uppercase;">Ordem Nº</div>
                            <div id="pv_numero_2" style="font-size: 1.25rem; font-weight: 900; color: #000; line-height: 1.1;">000</div>
                        </div>
                        <div id="pv_datahora_2" style="font-size: 0.7rem; color: #666; margin-top: 8px; font-weight: 600; text-transform: uppercase;">
                            DATA: <?php echo $dataAtualBr; ?>
                        </div>
                    </div>
                </div>

                <div class="ordem-info-wrap">
                    <table class="ordem-info-table">
                        <tr>
                            <td class="ordem-info-label" style="width: 15%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Fornecedor:</td>
                            <td id="pv_fornecedor_2" style="font-weight: 700;">-</td>
                            <td class="ordem-info-label" style="width: 30%; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Local e Data de Emissão:</td>
                            <td id="pv_data_2" style="width: 20%; font-weight: 700;"><?php echo $dataAtualBr; ?></td>
                        </tr>
                        <tr>
                            <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Para:</td>
                            <td id="pv_secretaria_2" style="font-weight: 700;">-</td>
                            <td class="ordem-info-label" style="font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">Referência:</td>
                            <td id="pv_oficio_2" style="font-family: monospace; font-weight: 900; letter-spacing: 1px;">-</td>
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
                        <tbody id="preview_itens_2">
                            <tr><td colspan="6" class="empty-note">Nenhum item adicionado.</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" style="text-align: right; font-weight: 800; font-size: 0.875rem; text-transform: uppercase;">
                                    Valor Total R$
                                </td>
                                <td id="preview_total_2" style="text-align: right; font-weight: 900; font-size: 0.9375rem;">
                                    R$ 0,00
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p id="pv_texto_entrega" class="texto-entrega">
                    No ato da entrega, esta via deverá ser carimbada e assinada pelo responsável.
                    Para fins de pagamento, o fornecedor deve apresentar esta ordem devidamente assinada
                    no setor administrativo/financeiro.
                </p>

                <div class="assinaturas-grid" style="margin-top: 4rem;">
                    <div>
                        <div class="assinatura-linha">
                            <div style="font-weight: 800; color: #000; font-size: 0.875rem;">RECEBEDOR</div>
                            <div style="font-size: 0.65rem; color: #555; font-weight: 700; text-transform: uppercase; margin-top: 3px;">
                                Autorização de Saída
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
</div>

<script>
    const DATA_ATUAL_BR = <?php echo json_encode($dataAtualBr); ?>;

    let contadorItens = 0;

    function moedaBR(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function maiusculo(texto) {
        return (texto || '-').toUpperCase();
    }

    function valor(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function adicionarItem(item = {}) {
        contadorItens++;

        const container = document.getElementById('itens-formulario');
        const row = document.createElement('div');
        row.className = 'item-row';
        row.dataset.item = contadorItens;

        row.innerHTML = `
            <div class="field">
                <label>Unid.</label>
                <input type="text" value="${item.unidade || 'UN'}" oninput="atualizarPreview()">
            </div>
            <div class="field">
                <label>Qtd</label>
                <input type="number" min="0" step="1" value="${item.quantidade || 1}" oninput="atualizarPreview()">
            </div>
            <div class="field">
                <label>Descrição</label>
                <input type="text" value="${item.produto || ''}" placeholder="Descrição do item" oninput="atualizarPreview()">
            </div>
            <div class="field">
                <label>Vlr Unit.</label>
                <input type="number" min="0" step="0.01" value="${item.valor_unitario || 0}" oninput="atualizarPreview()">
            </div>
            <div class="field">
                <label>Total</label>
                <input type="text" value="R$ 0,00" readonly>
            </div>
            <div class="field">
                <button type="button" class="btn btn-danger" onclick="removerItem(this)">X</button>
            </div>
        `;

        container.appendChild(row);
        atualizarPreview();
    }

    function removerItem(botao) {
        const row = botao.closest('.item-row');
        row.remove();
        atualizarPreview();
    }

    function coletarItens() {
        const rows = document.querySelectorAll('.item-row');
        const itens = [];

        rows.forEach((row) => {
            const inputs = row.querySelectorAll('input');
            const unidade = (inputs[0].value || 'UN').trim();
            const quantidade = parseFloat(inputs[1].value || 0);
            const produto = (inputs[2].value || '').trim();
            const valorUnitario = parseFloat(inputs[3].value || 0);
            const total = quantidade * valorUnitario;

            inputs[4].value = moedaBR(total);

            if (produto !== '' || quantidade > 0 || valorUnitario > 0) {
                itens.push({
                    unidade,
                    quantidade,
                    produto,
                    valorUnitario,
                    total
                });
            }
        });

        return itens;
    }

    function renderItensPreview(tbodyId, totalId) {
        const tbody = document.getElementById(tbodyId);
        const totalEl = document.getElementById(totalId);
        const itens = coletarItens();

        if (!itens.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="empty-note">Nenhum item adicionado.</td></tr>`;
            totalEl.textContent = 'R$ 0,00';
            return;
        }

        let html = '';
        let soma = 0;

        itens.forEach((item, index) => {
            soma += item.total;
            html += `
                <tr>
                    <td class="text-center" style="font-weight:700;">${String(index + 1).padStart(2, '0')}</td>
                    <td class="text-center" style="font-weight:600;">${item.unidade || 'UN'}</td>
                    <td class="text-center" style="font-weight:700;">${Number(item.quantidade).toLocaleString('pt-BR')}</td>
                    <td style="font-weight:600;">${(item.produto || '-').toUpperCase()}</td>
                    <td class="text-right">${moedaBR(item.valorUnitario)}</td>
                    <td class="text-right" style="font-weight:700;">${moedaBR(item.total)}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        totalEl.textContent = moedaBR(soma);
    }

    function atualizarPreview() {
        const numeroAq = valor('numero_aq') || '000';
        const numeroCurto = numeroAq.replace('AQ-', '').replace('aq-', '');
        const fornecedor = maiusculo(valor('fornecedor'));
        const secretaria = maiusculo(valor('secretaria'));
        const oficio = valor('oficio_num') || '-';
        const responsavel = maiusculo(valor('sec_responsavel'));
        const fornecedorCnpj = valor('fornecedor_cnpj') || '-';
        const fornecedorContato = valor('fornecedor_contato') || '-';
        const textoEntrega = valor('texto_entrega') || '-';

        document.getElementById('pv_numero_1').textContent = numeroCurto;
        document.getElementById('pv_numero_2').textContent = numeroCurto;

        document.getElementById('pv_numero_full_1').textContent = numeroAq;

        document.getElementById('pv_datahora_1').textContent = `DATA: ${DATA_ATUAL_BR}`;
        document.getElementById('pv_datahora_2').textContent = `DATA: ${DATA_ATUAL_BR}`;

        document.getElementById('pv_data_1').textContent = DATA_ATUAL_BR;
        document.getElementById('pv_data_2').textContent = DATA_ATUAL_BR;

        document.getElementById('pv_fornecedor_1').textContent = fornecedor;
        document.getElementById('pv_fornecedor_2').textContent = fornecedor;

        document.getElementById('pv_secretaria_1').textContent = secretaria;
        document.getElementById('pv_secretaria_2').textContent = secretaria;

        document.getElementById('pv_oficio_1').textContent = oficio;
        document.getElementById('pv_oficio_2').textContent = oficio;

        document.getElementById('pv_responsavel_1').textContent = responsavel;

        document.getElementById('pv_cnpj_fornecedor_1').textContent = fornecedorCnpj;
        document.getElementById('pv_contato_fornecedor_1').textContent = fornecedorContato;

        document.getElementById('pv_texto_entrega').textContent = textoEntrega;

        renderItensPreview('preview_itens_1', 'preview_total_1');
        renderItensPreview('preview_itens_2', 'preview_total_2');
    }

    function limparFormulario() {
        document.querySelectorAll('input, textarea').forEach((el) => {
            if (el.type === 'button' || el.type === 'submit' || el.readOnly) return;
            el.value = '';
        });

        document.getElementById('texto_entrega').value = 'No ato da entrega, esta via deverá ser carimbada e assinada pelo responsável. Para fins de pagamento, o fornecedor deve apresentar esta ordem devidamente assinada no setor administrativo/financeiro.';

        document.getElementById('itens-formulario').innerHTML = '';
        contadorItens = 0;
        adicionarItem();
        atualizarPreview();
    }

    adicionarItem();
    atualizarPreview();
</script>
</body>
</html>
<?php
require_once 'config.php';
checkAuth();

$id = $_GET['id'] ?? null;
if (!$id) die("ID da OS não fornecido.");

// Buscar OS detalhada
$stmt = $pdo->prepare("
    SELECT os.*, clientes.nome as cliente_nome, clientes.cpf_cnpj, clientes.telefone, clientes.whatsapp, clientes.endereco,
           usuarios.nome as tecnico_nome
    FROM os 
    JOIN clientes ON os.cliente_id = clientes.id 
    LEFT JOIN usuarios ON os.tecnico_id = usuarios.id 
    WHERE os.id = ?
");
$stmt->execute([$id]);
$os = $stmt->fetch();

if (!$os) die("OS não encontrada.");

// Buscar itens
$stmt = $pdo->prepare("SELECT i.*, p.nome as produto_nome, p.unidade FROM itens_os i LEFT JOIN produtos p ON i.produto_id = p.id WHERE i.os_id = ?");
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$checklist = json_decode($os['checklist_tecnico'] ?? '[]', true);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório Técnico OS #<?php echo $os['numero_os']; ?></title>
    <style>
        body { font-family: 'Inter', sans-serif; font-size: 11pt; color: #333; margin: 0; padding: 20px; line-height: 1.4; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #0056b3; padding-bottom: 10px; margin-bottom: 20px; }
        .company-info h1 { margin: 0; font-size: 20pt; color: #0056b3; }
        .os-number { text-align: right; }
        .os-number h2 { margin: 0; color: #2c3e50; font-family: 'Roboto Mono', monospace; }
        .section { margin-bottom: 20px; }
        .section-title { background: #f0f4f8; padding: 5px 10px; font-weight: bold; border-left: 4px solid #0056b3; margin-bottom: 10px; text-transform: uppercase; font-size: 9pt; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table th { background: #f8f9fa; text-align: left; padding: 8px; border-bottom: 1px solid #ddd; font-size: 9pt; }
        table td { padding: 8px; border-bottom: 1px solid #eee; font-size: 10pt; }
        .total-row { font-weight: bold; background: #f8f9fa; }
        .checklist { display: grid; grid-template-columns: 1fr 1fr; gap: 5px; }
        .check-item { display: flex; align-items: center; gap: 10px; font-size: 9pt; }
        .check-box { width: 12px; height: 12px; border: 1px solid #333; display: inline-block; }
        .check-box.checked { background: #333; }
        .footer { margin-top: 50px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px; text-align: center; }
        .signature { border-top: 1px solid #333; padding-top: 5px; font-size: 9pt; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="background: #f8f9fa; padding: 10px; border-bottom: 1px solid #ddd; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <span>Visualização de Impressão (A4)</span>
        <button onclick="window.print()" style="padding: 10px 20px; background: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer;">
            <i class="fas fa-print"></i> Imprimir Documento
        </button>
    </div>

    <div class="header">
        <div class="company-info">
            <h1><?php echo APP_NAME; ?></h1>
            <div style="font-size: 9pt; color: #666;">
                CNPJ: 00.000.000/0001-00 | Tel: (11) 99999-9999<br>
                E-mail: contato@erpeletrica.com.br
            </div>
        </div>
        <div class="os-number">
            <h2 style="font-size: 24pt;">#<?php echo $os['numero_os']; ?></h2>
            <div style="font-weight: bold; color: #e67e22;"><?php echo strtoupper(str_replace('_', ' ', $os['status'])); ?></div>
        </div>
    </div>

    <div class="grid">
        <div class="section">
            <div class="section-title">Dados do Cliente</div>
            <b><?php echo $os['cliente_nome']; ?></b><br>
            <?php echo $os['cpf_cnpj'] ? "Doc: ".$os['cpf_cnpj']."<br>" : ""; ?>
            End: <?php echo $os['endereco']; ?><br>
            Tel: <?php echo $os['telefone']; ?> <?php echo $os['whatsapp'] ? " / ".$os['whatsapp'] : ""; ?>
        </div>
        <div class="section">
            <div class="section-title">Dados da Ordem</div>
            Abertura: <?php echo formatarData($os['data_abertura']); ?><br>
            Previsão: <?php echo formatarData($os['data_previsao']); ?><br>
            Técnico Resp: <b><?php echo $os['tecnico_nome'] ?: '---'; ?></b>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Descrição Técnica dos Serviços</div>
        <div style="background: #fff; border: 1px solid #eee; padding: 10px; min-height: 80px;">
            <?php echo nl2br($os['descricao']); ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Materiais e Insumos</div>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descrição do Item</th>
                    <th>Qtd</th>
                    <th>UN</th>
                    <th>V. Unit</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                <tr>
                    <td style="font-family: 'Roboto Mono'; font-size: 8pt;">ITM-<?php echo str_pad($item['produto_id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo $item['produto_nome']; ?></td>
                    <td><?php echo $item['quantidade']; ?></td>
                    <td><?php echo $item['unidade']; ?></td>
                    <td><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                    <td><?php echo formatarMoeda($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="5" style="text-align: right;">VALOR TOTAL DA ORDEM:</td>
                    <td><?php echo formatarMoeda($os['valor_total']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Checklist de Conformidade Técnica</div>
        <div class="checklist">
            <?php 
            $checks_available = ['Verificação de Aterramento', 'Teste de Tensão de Entrada', 'Verificação de Torque em Parafusos', 'Limpeza de Componentes', 'Identificação de Cabos', 'Teste de Carga / Funcionalidade'];
            foreach ($checks_available as $chk):
                $is_checked = in_array($chk, $checklist);
            ?>
            <div class="check-item">
                <div class="check-box <?php echo $is_checked ? 'checked' : ''; ?>"></div>
                <span><?php echo $chk; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Observações de Campo</div>
        <div style="border: 1px solid #eee; height: 60px;"></div>
    </div>

    <div class="footer">
        <div class="signature">
            <b><?php echo $os['tecnico_nome'] ?: 'Responsável Técnico'; ?></b><br>
            Assinatura do Técnico
        </div>
        <div class="signature">
            <b><?php echo $os['cliente_nome']; ?></b><br>
            Assinatura do Cliente
        </div>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 8pt; color: #999;">
        Documento gerado eletronicamente por <?php echo APP_NAME; ?> em <?php echo date('d/m/Y H:i'); ?>
    </div>

    <script>
        // Auto print if requested via URL
        if (window.location.search.includes('autoprint=true')) {
            window.print();
        }
    </script>
</body>
</html>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$acao = $_GET['acao'] ?? '';

try {
    switch ($acao) {
        case 'membro':
            gerarRelatoriMembro();
            break;
            
        case 'todos':
            gerarRelatorioTodos();
            break;
            
        case 'estatisticas':
            gerarRelatorioEstatisticas();
            break;
            
        default:
            die('Ação não encontrada');
    }
    
} catch (Exception $e) {
    die('Erro: ' . $e->getMessage());
}

function gerarRelatoriMembro() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        die('ID do membro não fornecido');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
    $stmt->execute([$id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membro) {
        die('Membro não encontrado');
    }
    
    $idade = calcularIdade($membro['data_nascimento']);
    
    // Gerar HTML para PDF
    $html = gerarHTMLRelatoriMembro($membro, $idade);
    
    // Converter HTML para PDF usando WeasyPrint ou similar
    salvarComoPDF($html, 'relatorio_membro_' . $id . '.pdf');
}

function gerarRelatorioTodos() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM membros ORDER BY nome_completo");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHTMLRelatorioTodos($membros);
    salvarComoPDF($html, 'relatorio_todos_membros.pdf');
}

function gerarRelatorioEstatisticas() {
    global $pdo;
    
    // Obter estatísticas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM membros");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $pdo->query("
        SELECT tipo_integracao, COUNT(*) as quantidade 
        FROM membros 
        GROUP BY tipo_integracao
    ");
    $porTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT sexo, COUNT(*) as quantidade 
        FROM membros 
        GROUP BY sexo
    ");
    $porSexo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("
        SELECT estado_civil, COUNT(*) as quantidade 
        FROM membros 
        GROUP BY estado_civil
    ");
    $porEstadoCivil = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = gerarHTMLRelatorioEstatisticas($total, $porTipo, $porSexo, $porEstadoCivil);
    salvarComoPDF($html, 'relatorio_estatisticas.pdf');
}

function gerarHTMLRelatoriMembro($membro, $idade) {
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório - ' . htmlspecialchars($membro['nome_completo']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #1a2e4a; color: white; padding: 20px; text-align: center; }
        .section { margin: 20px 0; border: 1px solid #ddd; padding: 15px; }
        .section-title { background: #1a2e4a; color: white; padding: 10px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .label { font-weight: bold; width: 30%; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE MEMBRO</h1>
        <p>Igreja de Deus Nascer de Novo</p>
    </div>
    
    <div class="section">
        <div class="section-title">Dados Pessoais</div>
        <table>
            <tr><td class="label">Nome:</td><td>' . htmlspecialchars($membro['nome_completo']) . '</td></tr>
            <tr><td class="label">Data de Nascimento:</td><td>' . formatarData($membro['data_nascimento']) . ' (' . ($idade ? $idade . ' anos' : '') . ')</td></tr>
            <tr><td class="label">Sexo:</td><td>' . ($membro['sexo'] === 'M' ? 'Masculino' : 'Feminino') . '</td></tr>
            <tr><td class="label">CPF:</td><td>' . formatarCPF($membro['cpf']) . '</td></tr>
            <tr><td class="label">RG:</td><td>' . htmlspecialchars($membro['rg'] ?? '') . '</td></tr>
            <tr><td class="label">Profissão:</td><td>' . htmlspecialchars($membro['profissao'] ?? '') . '</td></tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Endereço</div>
        <table>
            <tr><td class="label">Rua:</td><td>' . htmlspecialchars($membro['endereco_rua'] ?? '') . ', ' . htmlspecialchars($membro['endereco_numero'] ?? '') . '</td></tr>
            <tr><td class="label">Bairro:</td><td>' . htmlspecialchars($membro['endereco_bairro'] ?? '') . '</td></tr>
            <tr><td class="label">Cidade:</td><td>' . htmlspecialchars($membro['endereco_cidade'] ?? '') . ' - ' . htmlspecialchars($membro['endereco_uf'] ?? '') . '</td></tr>
            <tr><td class="label">CEP:</td><td>' . formatarCEP($membro['endereco_cep'] ?? '') . '</td></tr>
            <tr><td class="label">Telefone:</td><td>' . formatarTelefone($membro['telefone'] ?? '') . '</td></tr>
        </table>
    </div>
    
    <div class="section">
        <div class="section-title">Dados Eclesiásticos</div>
        <table>
            <tr><td class="label">Tipo de Integração:</td><td>' . htmlspecialchars($membro['tipo_integracao'] ?? '') . '</td></tr>
            <tr><td class="label">Data de Integração:</td><td>' . formatarData($membro['data_integracao'] ?? '') . '</td></tr>
            <tr><td class="label">Congregação:</td><td>' . htmlspecialchars($membro['congregacao'] ?? '') . '</td></tr>
            <tr><td class="label">Área:</td><td>' . htmlspecialchars($membro['area'] ?? '') . '</td></tr>
            <tr><td class="label">Núcleo:</td><td>' . htmlspecialchars($membro['nucleo'] ?? '') . '</td></tr>
        </table>
    </div>
    
    <p style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
        Relatório gerado em ' . date('d/m/Y H:i:s') . '
    </p>
</body>
</html>';
    
    return $html;
}

function gerarHTMLRelatorioTodos($membros) {
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Membros</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #1a2e4a; color: white; padding: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #1a2e4a; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:nth-child(even) { background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LISTA DE MEMBROS</h1>
        <p>Igreja de Deus Nascer de Novo</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>Telefone</th>
                <th>Tipo de Integração</th>
                <th>Data de Integração</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($membros as $membro) {
        $html .= '<tr>
            <td>' . htmlspecialchars($membro['nome_completo']) . '</td>
            <td>' . formatarCPF($membro['cpf']) . '</td>
            <td>' . formatarTelefone($membro['telefone'] ?? '') . '</td>
            <td>' . htmlspecialchars($membro['tipo_integracao'] ?? '') . '</td>
            <td>' . formatarData($membro['data_integracao'] ?? '') . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <p style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
        Total de membros: ' . count($membros) . ' | Relatório gerado em ' . date('d/m/Y H:i:s') . '
    </p>
</body>
</html>';
    
    return $html;
}

function gerarHTMLRelatorioEstatisticas($total, $porTipo, $porSexo, $porEstadoCivil) {
    $html = '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Estatísticas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #1a2e4a; color: white; padding: 20px; text-align: center; }
        .stat-box { background: #f0f0f0; padding: 15px; margin: 15px 0; border-left: 4px solid #1a2e4a; }
        .stat-title { font-weight: bold; font-size: 16px; color: #1a2e4a; }
        .stat-value { font-size: 24px; font-weight: bold; color: #c9a84c; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #1a2e4a; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class="header">
        <h1>RELATÓRIO DE ESTATÍSTICAS</h1>
        <p>Igreja de Deus Nascer de Novo</p>
    </div>
    
    <div class="stat-box">
        <div class="stat-title">Total de Membros</div>
        <div class="stat-value">' . $total . '</div>
    </div>
    
    <h2>Por Tipo de Integração</h2>
    <table>
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Quantidade</th>
                <th>Percentual</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($porTipo as $item) {
        $percentual = $total > 0 ? round(($item['quantidade'] / $total) * 100, 1) : 0;
        $html .= '<tr>
            <td>' . htmlspecialchars($item['tipo_integracao']) . '</td>
            <td>' . $item['quantidade'] . '</td>
            <td>' . $percentual . '%</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <h2>Por Sexo</h2>
    <table>
        <thead>
            <tr>
                <th>Sexo</th>
                <th>Quantidade</th>
                <th>Percentual</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($porSexo as $item) {
        $percentual = $total > 0 ? round(($item['quantidade'] / $total) * 100, 1) : 0;
        $sexo = $item['sexo'] === 'M' ? 'Masculino' : 'Feminino';
        $html .= '<tr>
            <td>' . $sexo . '</td>
            <td>' . $item['quantidade'] . '</td>
            <td>' . $percentual . '%</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <h2>Por Estado Civil</h2>
    <table>
        <thead>
            <tr>
                <th>Estado Civil</th>
                <th>Quantidade</th>
                <th>Percentual</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($porEstadoCivil as $item) {
        $percentual = $total > 0 ? round(($item['quantidade'] / $total) * 100, 1) : 0;
        $html .= '<tr>
            <td>' . htmlspecialchars($item['estado_civil']) . '</td>
            <td>' . $item['quantidade'] . '</td>
            <td>' . $percentual . '%</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
    
    <p style="text-align: center; margin-top: 40px; color: #666; font-size: 12px;">
        Relatório gerado em ' . date('d/m/Y H:i:s') . '
    </p>
</body>
</html>';
    
    return $html;
}

function salvarComoPDF($html, $filename) {
    // Salvar como HTML e permitir download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    echo $html;
    exit;
}
?>

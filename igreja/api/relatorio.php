<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Usar FPDF para gerar PDF
require_once __DIR__ . '/../vendor/autoload.php';

use FPDF;

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
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Cabeçalho
    $pdf->SetTextColor(26, 46, 74); // Azul marinho
    $pdf->Cell(0, 10, 'IGREJA DE DEUS NASCER DE NOVO', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Ficha de Membro', 0, 1, 'C');
    $pdf->Ln(5);
    
    // Linha separadora
    $pdf->SetDrawColor(201, 168, 76); // Dourado
    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
    $pdf->Ln(5);
    
    // Dados pessoais
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(26, 46, 74);
    $pdf->Cell(0, 7, 'DADOS PESSOAIS', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $dados = [
        'Nome Completo' => $membro['nome_completo'],
        'CPF' => formatarCPF($membro['cpf'] ?? ''),
        'Data de Nascimento' => formatarData($membro['data_nascimento'] ?? ''),
        'Sexo' => $membro['sexo'] ?? '',
        'Nacionalidade' => $membro['nacionalidade'] ?? '',
        'Naturalidade' => $membro['naturalidade'] ?? '',
        'Estado' => $membro['estado_uf'] ?? '',
        'Escolaridade' => $membro['escolaridade'] ?? '',
        'Profissão' => $membro['profissao'] ?? ''
    ];
    
    foreach ($dados as $label => $valor) {
        $pdf->Cell(50, 6, $label . ':', 0, 0);
        $pdf->Cell(0, 6, $valor, 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Endereço
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(26, 46, 74);
    $pdf->Cell(0, 7, 'ENDEREÇO RESIDENCIAL', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $endereco = [
        'Rua' => $membro['endereco_rua'] ?? '',
        'Número' => $membro['endereco_numero'] ?? '',
        'Bairro' => $membro['endereco_bairro'] ?? '',
        'CEP' => formatarCEP($membro['endereco_cep'] ?? ''),
        'Cidade' => $membro['endereco_cidade'] ?? '',
        'Estado' => $membro['endereco_uf'] ?? '',
        'Telefone' => formatarTelefone($membro['telefone'] ?? '')
    ];
    
    foreach ($endereco as $label => $valor) {
        $pdf->Cell(50, 6, $label . ':', 0, 0);
        $pdf->Cell(0, 6, $valor, 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Dados eclesiásticos
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetTextColor(26, 46, 74);
    $pdf->Cell(0, 7, 'DADOS ECLESIÁSTICOS', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $eclesiasticos = [
        'Tipo de Integração' => $membro['tipo_integracao'] ?? '',
        'Data de Integração' => formatarData($membro['data_integracao'] ?? ''),
        'Batismo em Águas' => $membro['batismo_aguas'] ?? '',
        'Batismo no Espírito Santo' => $membro['batismo_espirito_santo'] ?? '',
        'Procedência' => $membro['procedencia'] ?? '',
        'Congregação' => $membro['congregacao'] ?? '',
        'Área' => $membro['area'] ?? '',
        'Núcleo' => $membro['nucleo'] ?? ''
    ];
    
    foreach ($eclesiasticos as $label => $valor) {
        $pdf->Cell(50, 6, $label . ':', 0, 0);
        $pdf->Cell(0, 6, $valor, 0, 1);
    }
    
    $pdf->Ln(10);
    
    // Rodapé
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Documento gerado em ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    
    $pdf->Output('D', 'ficha_membro_' . $membro['id'] . '.pdf');
}

function gerarRelatorioTodos() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM membros ORDER BY nome_completo");
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pdf = new FPDF();
    $pdf->AddPage('L'); // Landscape
    $pdf->SetFont('Arial', 'B', 14);
    
    // Cabeçalho
    $pdf->SetTextColor(26, 46, 74);
    $pdf->Cell(0, 10, 'LISTA DE MEMBROS - IGREJA DE DEUS NASCER DE NOVO', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Total de membros: ' . count($membros), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Cabeçalho da tabela
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(26, 46, 74);
    $pdf->SetTextColor(255, 255, 255);
    
    $pdf->Cell(10, 7, '#', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Nome', 1, 0, 'L', true);
    $pdf->Cell(40, 7, 'CPF', 1, 0, 'C', true);
    $pdf->Cell(40, 7, 'Telefone', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Integração', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Data', 1, 1, 'C', true);
    
    // Dados
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $numero = 1;
    foreach ($membros as $membro) {
        $pdf->Cell(10, 6, $numero++, 1, 0, 'C');
        $pdf->Cell(50, 6, substr($membro['nome_completo'], 0, 30), 1, 0, 'L');
        $pdf->Cell(40, 6, formatarCPF($membro['cpf'] ?? ''), 1, 0, 'C');
        $pdf->Cell(40, 6, formatarTelefone($membro['telefone'] ?? ''), 1, 0, 'C');
        $pdf->Cell(35, 6, $membro['tipo_integracao'] ?? '', 1, 0, 'C');
        $pdf->Cell(30, 6, formatarData($membro['data_integracao'] ?? ''), 1, 1, 'C');
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Documento gerado em ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    
    $pdf->Output('D', 'lista_membros_' . date('Y-m-d') . '.pdf');
}

function gerarRelatorioEstatisticas() {
    global $pdo;
    
    // Obter estatísticas
    $stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM membros");
    $total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmtTipo = $pdo->query("
        SELECT tipo_integracao, COUNT(*) as quantidade 
        FROM membros 
        WHERE tipo_integracao IS NOT NULL
        GROUP BY tipo_integracao
    ");
    $porTipo = $stmtTipo->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtSexo = $pdo->query("
        SELECT sexo, COUNT(*) as quantidade 
        FROM membros 
        WHERE sexo IS NOT NULL
        GROUP BY sexo
    ");
    $porSexo = $stmtSexo->fetchAll(PDO::FETCH_ASSOC);
    
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    // Cabeçalho
    $pdf->SetTextColor(26, 46, 74);
    $pdf->Cell(0, 10, 'RELATÓRIO DE ESTATÍSTICAS', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, 'Igreja de Deus Nascer de Novo', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Total de membros
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(26, 46, 74);
    $pdf->Cell(0, 7, 'Total de Membros: ' . $total, 0, 1);
    $pdf->Ln(5);
    
    // Por tipo de integração
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'Membros por Tipo de Integração', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    foreach ($porTipo as $tipo) {
        $percentual = ($tipo['quantidade'] / $total) * 100;
        $pdf->Cell(50, 6, $tipo['tipo_integracao'] . ':', 0, 0);
        $pdf->Cell(0, 6, $tipo['quantidade'] . ' (' . number_format($percentual, 1) . '%)', 0, 1);
    }
    
    $pdf->Ln(5);
    
    // Por sexo
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, 'Membros por Sexo', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    foreach ($porSexo as $sexo) {
        $percentual = ($sexo['quantidade'] / $total) * 100;
        $sexoLabel = ($sexo['sexo'] === 'M') ? 'Masculino' : 'Feminino';
        $pdf->Cell(50, 6, $sexoLabel . ':', 0, 0);
        $pdf->Cell(0, 6, $sexo['quantidade'] . ' (' . number_format($percentual, 1) . '%)', 0, 1);
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Documento gerado em ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    
    $pdf->Output('D', 'relatorio_estatisticas_' . date('Y-m-d') . '.pdf');
}

?>

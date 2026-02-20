<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_GET['acao'] ?? '';

try {
    switch ($acao) {
        case 'listar':
            listarMembros();
            break;
            
        case 'obter':
            obterMembro();
            break;
            
        case 'criar':
            if ($metodo === 'POST') {
                criarMembro();
            }
            break;
            
        case 'atualizar':
            if ($metodo === 'POST') {
                atualizarMembro();
            }
            break;
            
        case 'deletar':
            if ($metodo === 'POST') {
                deletarMembro();
            }
            break;
            
        case 'estatisticas':
            obterEstatisticas();
            break;
            
        case 'buscar':
            buscarMembros();
            break;
            
        default:
            responderJSON('erro', 'Ação não encontrada');
    }
    
} catch (Exception $e) {
    responderJSON('erro', $e->getMessage());
}

function listarMembros() {
    global $pdo;
    
    $pagina = $_GET['pagina'] ?? 1;
    $limite = 10;
    $offset = ($pagina - 1) * $limite;
    
    $stmt = $pdo->prepare("
        SELECT * FROM membros 
        ORDER BY data_cadastro DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limite, $offset]);
    $membros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtTotal = $pdo->query("SELECT COUNT(*) FROM membros");
    $total = $stmtTotal->fetchColumn();
    
    responderJSON('sucesso', 'Membros listados com sucesso', [
        'membros' => $membros,
        'total' => $total,
        'paginas' => ceil($total / $limite),
        'paginaAtual' => $pagina
    ]);
}

function obterMembro() {
    global $pdo;
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        responderJSON('erro', 'ID do membro não fornecido');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM membros WHERE id = ?");
    $stmt->execute([$id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membro) {
        responderJSON('erro', 'Membro não encontrado');
    }
    
    responderJSON('sucesso', 'Membro obtido com sucesso', $membro);
}

function criarMembro() {
    global $pdo;
    
    $dados = sanitizar($_POST);
    
    // Validações
    if (empty($dados['nome_completo'])) {
        responderJSON('erro', 'Nome completo é obrigatório');
    }
    
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        responderJSON('erro', 'CPF inválido');
    }
    
    // Processar foto se enviada
    $fotoPath = null;
    if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        try {
            $fotoPath = processarUploadFoto($_FILES['foto']);
        } catch (Exception $e) {
            responderJSON('erro', $e->getMessage());
        }
    }
    
    // Converter datas
    $dataNascimento = !empty($dados['data_nascimento']) ? converterDataParaBanco($dados['data_nascimento']) : null;
    $dataIntegracao = !empty($dados['data_integracao']) ? converterDataParaBanco($dados['data_integracao']) : null;
    
    $sql = "
        INSERT INTO membros (
            nome_completo, data_nascimento, nacionalidade, naturalidade, estado_uf, sexo, 
            tipo_sanguineo, escolaridade, profissao, rg, cpf, titulo_eleitor, ctp, cdi,
            filiacao_pai, filiacao_mae, estado_civil, conjuge, filhos,
            endereco_rua, endereco_numero, endereco_bairro, endereco_cep, endereco_cidade, endereco_uf,
            telefone, tipo_integracao, data_integracao, batismo_aguas, batismo_espirito_santo,
            procedencia, congregacao, area, nucleo, foto_path
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";
    
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            $dados['nome_completo'],
            $dataNascimento,
            $dados['nacionalidade'] ?? null,
            $dados['naturalidade'] ?? null,
            $dados['estado_uf'] ?? null,
            $dados['sexo'] ?? null,
            $dados['tipo_sanguineo'] ?? null,
            $dados['escolaridade'] ?? null,
            $dados['profissao'] ?? null,
            $dados['rg'] ?? null,
            $dados['cpf'] ?? null,
            $dados['titulo_eleitor'] ?? null,
            $dados['ctp'] ?? null,
            $dados['cdi'] ?? null,
            $dados['filiacao_pai'] ?? null,
            $dados['filiacao_mae'] ?? null,
            $dados['estado_civil'] ?? null,
            $dados['conjuge'] ?? null,
            $dados['filhos'] ?? 0,
            $dados['endereco_rua'] ?? null,
            $dados['endereco_numero'] ?? null,
            $dados['endereco_bairro'] ?? null,
            $dados['endereco_cep'] ?? null,
            $dados['endereco_cidade'] ?? null,
            $dados['endereco_uf'] ?? null,
            $dados['telefone'] ?? null,
            $dados['tipo_integracao'] ?? null,
            $dataIntegracao,
            $dados['batismo_aguas'] ?? null,
            $dados['batismo_espirito_santo'] ?? null,
            $dados['procedencia'] ?? null,
            $dados['congregacao'] ?? null,
            $dados['area'] ?? null,
            $dados['nucleo'] ?? null,
            $fotoPath
        ]);
        
        $id = $pdo->lastInsertId();
        responderJSON('sucesso', 'Membro cadastrado com sucesso', ['id' => $id]);
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            responderJSON('erro', 'CPF já cadastrado');
        }
        throw $e;
    }
}

function atualizarMembro() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        responderJSON('erro', 'ID do membro não fornecido');
    }
    
    $dados = sanitizar($_POST);
    
    // Validações
    if (empty($dados['nome_completo'])) {
        responderJSON('erro', 'Nome completo é obrigatório');
    }
    
    if (!empty($dados['cpf']) && !validarCPF($dados['cpf'])) {
        responderJSON('erro', 'CPF inválido');
    }
    
    // Obter membro atual
    $stmt = $pdo->prepare("SELECT foto_path FROM membros WHERE id = ?");
    $stmt->execute([$id]);
    $membroAtual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membroAtual) {
        responderJSON('erro', 'Membro não encontrado');
    }
    
    $fotoPath = $membroAtual['foto_path'];
    
    // Processar nova foto se enviada
    if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        try {
            // Deletar foto antiga
            deletarFoto($membroAtual['foto_path']);
            $fotoPath = processarUploadFoto($_FILES['foto']);
        } catch (Exception $e) {
            responderJSON('erro', $e->getMessage());
        }
    }
    
    // Converter datas
    $dataNascimento = !empty($dados['data_nascimento']) ? converterDataParaBanco($dados['data_nascimento']) : null;
    $dataIntegracao = !empty($dados['data_integracao']) ? converterDataParaBanco($dados['data_integracao']) : null;
    
    $sql = "
        UPDATE membros SET
            nome_completo = ?, data_nascimento = ?, nacionalidade = ?, naturalidade = ?, estado_uf = ?, sexo = ?,
            tipo_sanguineo = ?, escolaridade = ?, profissao = ?, rg = ?, cpf = ?, titulo_eleitor = ?, ctp = ?, cdi = ?,
            filiacao_pai = ?, filiacao_mae = ?, estado_civil = ?, conjuge = ?, filhos = ?,
            endereco_rua = ?, endereco_numero = ?, endereco_bairro = ?, endereco_cep = ?, endereco_cidade = ?, endereco_uf = ?,
            telefone = ?, tipo_integracao = ?, data_integracao = ?, batismo_aguas = ?, batismo_espirito_santo = ?,
            procedencia = ?, congregacao = ?, area = ?, nucleo = ?, foto_path = ?, data_atualizacao = CURRENT_TIMESTAMP
        WHERE id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            $dados['nome_completo'],
            $dataNascimento,
            $dados['nacionalidade'] ?? null,
            $dados['naturalidade'] ?? null,
            $dados['estado_uf'] ?? null,
            $dados['sexo'] ?? null,
            $dados['tipo_sanguineo'] ?? null,
            $dados['escolaridade'] ?? null,
            $dados['profissao'] ?? null,
            $dados['rg'] ?? null,
            $dados['cpf'] ?? null,
            $dados['titulo_eleitor'] ?? null,
            $dados['ctp'] ?? null,
            $dados['cdi'] ?? null,
            $dados['filiacao_pai'] ?? null,
            $dados['filiacao_mae'] ?? null,
            $dados['estado_civil'] ?? null,
            $dados['conjuge'] ?? null,
            $dados['filhos'] ?? 0,
            $dados['endereco_rua'] ?? null,
            $dados['endereco_numero'] ?? null,
            $dados['endereco_bairro'] ?? null,
            $dados['endereco_cep'] ?? null,
            $dados['endereco_cidade'] ?? null,
            $dados['endereco_uf'] ?? null,
            $dados['telefone'] ?? null,
            $dados['tipo_integracao'] ?? null,
            $dataIntegracao,
            $dados['batismo_aguas'] ?? null,
            $dados['batismo_espirito_santo'] ?? null,
            $dados['procedencia'] ?? null,
            $dados['congregacao'] ?? null,
            $dados['area'] ?? null,
            $dados['nucleo'] ?? null,
            $fotoPath,
            $id
        ]);
        
        responderJSON('sucesso', 'Membro atualizado com sucesso');
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
            responderJSON('erro', 'CPF já cadastrado');
        }
        throw $e;
    }
}

function deletarMembro() {
    global $pdo;
    
    $id = $_POST['id'] ?? null;
    
    if (!$id) {
        responderJSON('erro', 'ID do membro não fornecido');
    }
    
    // Obter foto para deletar
    $stmt = $pdo->prepare("SELECT foto_path FROM membros WHERE id = ?");
    $stmt->execute([$id]);
    $membro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$membro) {
        responderJSON('erro', 'Membro não encontrado');
    }
    
    // Deletar foto
    deletarFoto($membro['foto_path']);
    
    // Deletar membro
    $stmt = $pdo->prepare("DELETE FROM membros WHERE id = ?");
    $stmt->execute([$id]);
    
    responderJSON('sucesso', 'Membro deletado com sucesso');
}

function obterEstatisticas() {
    global $pdo;
    
    // Total de membros
    $stmtTotal = $pdo->query("SELECT COUNT(*) as total FROM membros");
    $total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Membros por tipo de integração
    $stmtTipo = $pdo->query("
        SELECT tipo_integracao, COUNT(*) as quantidade 
        FROM membros 
        WHERE tipo_integracao IS NOT NULL
        GROUP BY tipo_integracao
    ");
    $porTipo = $stmtTipo->fetchAll(PDO::FETCH_ASSOC);
    
    // Membros por sexo
    $stmtSexo = $pdo->query("
        SELECT sexo, COUNT(*) as quantidade 
        FROM membros 
        WHERE sexo IS NOT NULL
        GROUP BY sexo
    ");
    $porSexo = $stmtSexo->fetchAll(PDO::FETCH_ASSOC);
    
    // Membros por estado civil
    $stmtEstadoCivil = $pdo->query("
        SELECT estado_civil, COUNT(*) as quantidade 
        FROM membros 
        WHERE estado_civil IS NOT NULL
        GROUP BY estado_civil
    ");
    $porEstadoCivil = $stmtEstadoCivil->fetchAll(PDO::FETCH_ASSOC);
    
    // Distribuição por escolaridade
    $stmtEscolaridade = $pdo->query("
        SELECT escolaridade, COUNT(*) as quantidade 
        FROM membros 
        WHERE escolaridade IS NOT NULL
        GROUP BY escolaridade
    ");
    $porEscolaridade = $stmtEscolaridade->fetchAll(PDO::FETCH_ASSOC);
    
    // Faixa etária
    $stmtIdade = $pdo->query("
        SELECT 
            CASE 
                WHEN (julianday('now') - julianday(data_nascimento)) / 365.25 < 18 THEN 'Menor de 18'
                WHEN (julianday('now') - julianday(data_nascimento)) / 365.25 < 30 THEN '18-29'
                WHEN (julianday('now') - julianday(data_nascimento)) / 365.25 < 40 THEN '30-39'
                WHEN (julianday('now') - julianday(data_nascimento)) / 365.25 < 50 THEN '40-49'
                WHEN (julianday('now') - julianday(data_nascimento)) / 365.25 < 60 THEN '50-59'
                ELSE '60+'
            END as faixa_etaria,
            COUNT(*) as quantidade
        FROM membros
        WHERE data_nascimento IS NOT NULL
        GROUP BY faixa_etaria
    ");
    $porFaixaEtaria = $stmtIdade->fetchAll(PDO::FETCH_ASSOC);
    
    responderJSON('sucesso', 'Estatísticas obtidas com sucesso', [
        'total' => $total,
        'porTipo' => $porTipo,
        'porSexo' => $porSexo,
        'porEstadoCivil' => $porEstadoCivil,
        'porEscolaridade' => $porEscolaridade,
        'porFaixaEtaria' => $porFaixaEtaria
    ]);
}

function buscarMembros() {
    global $pdo;
    
    $termo = $_GET['termo'] ?? '';
    $filtro = $_GET['filtro'] ?? 'nome';
    
    if (strlen($termo) < 2) {
        responderJSON('erro', 'Termo de busca deve ter pelo menos 2 caracteres');
    }
    
    $termo = '%' . $termo . '%';
    
    $camposBusca = [
        'nome' => 'nome_completo',
        'cpf' => 'cpf',
        'telefone' => 'telefone',
        'email' => 'email'
    ];
    
    $campo = $camposBusca[$filtro] ?? 'nome_completo';
    
    $stmt = $pdo->prepare("
        SELECT id, nome_completo, cpf, telefone, tipo_integracao, data_integracao, foto_path 
        FROM membros 
        WHERE $campo LIKE ?
        ORDER BY nome_completo
        LIMIT 20
    ");
    $stmt->execute([$termo]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    responderJSON('sucesso', 'Busca realizada com sucesso', $resultados);
}

?>

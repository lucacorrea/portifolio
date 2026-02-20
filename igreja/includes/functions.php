<?php

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($i = 0; $i < $t; $i++) {
            $d += $cpf[$i] * (($t + 1) - $i);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$t] != $d) {
            return false;
        }
    }
    
    return true;
}

// Função para formatar CPF
function formatarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

// Função para formatar telefone
function formatarTelefone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    if (strlen($telefone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
    } elseif (strlen($telefone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
    }
    
    return $telefone;
}

// Função para formatar CEP
function formatarCEP($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);
    return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
}

// Função para formatar data
function formatarData($data) {
    if (empty($data)) return '';
    $timestamp = strtotime($data);
    return date('d/m/Y', $timestamp);
}

// Função para converter data de exibição para banco
function converterDataParaBanco($data) {
    if (empty($data)) return null;
    $partes = explode('/', $data);
    if (count($partes) == 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}

// Função para calcular idade
function calcularIdade($dataNascimento) {
    if (empty($dataNascimento)) return null;
    $data = new DateTime($dataNascimento);
    $hoje = new DateTime();
    $idade = $hoje->diff($data);
    return $idade->y;
}

// Função para gerar iniciais para avatar
function gerarIniciaisAvatar($nome) {
    $partes = explode(' ', trim($nome));
    $iniciais = '';
    
    if (count($partes) >= 2) {
        $iniciais = strtoupper($partes[0][0] . $partes[count($partes) - 1][0]);
    } else {
        $iniciais = strtoupper(substr($nome, 0, 2));
    }
    
    return $iniciais;
}

// Função para gerar cor baseada no nome
function gerarCorAvatar($nome) {
    $cores = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];
    $hash = crc32($nome);
    $indice = abs($hash) % count($cores);
    return $cores[$indice];
}

// Função para processar upload de foto
function processarUploadFoto($file) {
    $uploadDir = __DIR__ . '/../public/uploads/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (!in_array($file['type'], $tiposPermitidos)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPG, PNG ou GIF.');
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Arquivo muito grande. Máximo 5MB.');
    }
    
    $nomeArquivo = uniqid('foto_') . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    if (!move_uploaded_file($file['tmp_name'], $caminhoCompleto)) {
        throw new Exception('Erro ao fazer upload do arquivo.');
    }
    
    return '/uploads/' . $nomeArquivo;
}

// Função para deletar foto
function deletarFoto($caminhoFoto) {
    if (!empty($caminhoFoto)) {
        $arquivo = __DIR__ . '/../public' . $caminhoFoto;
        if (file_exists($arquivo)) {
            unlink($arquivo);
        }
    }
}

// Função para sanitizar entrada
function sanitizar($dados) {
    if (is_array($dados)) {
        return array_map('sanitizar', $dados);
    }
    return htmlspecialchars(strip_tags(trim($dados)), ENT_QUOTES, 'UTF-8');
}

// Função para responder em JSON
function responderJSON($status, $mensagem, $dados = null) {
    header('Content-Type: application/json');
    $resposta = [
        'status' => $status,
        'mensagem' => $mensagem
    ];
    
    if ($dados !== null) {
        $resposta['dados'] = $dados;
    }
    
    echo json_encode($resposta);
    exit;
}


// ============================================
// FUNÇÕES DE BANCO DE DADOS
// ============================================

// Função para obter conexão com banco de dados
function obterConexao() {
    global $pdo;
    
    if (!isset($pdo)) {
        try {
            $dbPath = __DIR__ . '/../data/membros.db';
            
            if (!file_exists($dbPath)) {
                // Criar banco de dados se não existir
                $pdo = new PDO('sqlite:' . $dbPath);
                criarTabelasMembros($pdo);
            } else {
                $pdo = new PDO('sqlite:' . $dbPath);
            }
            
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('Erro ao conectar ao banco de dados: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Função para criar tabelas
function criarTabelasMembros($pdo) {
    $sql = "
    CREATE TABLE IF NOT EXISTS membros (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nome_completo TEXT NOT NULL,
        data_nascimento DATE,
        sexo TEXT,
        tipo_sanguineo TEXT,
        nacionalidade TEXT,
        naturalidade TEXT,
        estado_uf TEXT,
        cpf TEXT UNIQUE,
        rg TEXT,
        titulo_eleitor TEXT,
        ctp TEXT,
        cdi TEXT,
        escolaridade TEXT,
        profissao TEXT,
        pai TEXT,
        mae TEXT,
        estado_civil TEXT,
        conjuge TEXT,
        filhos INTEGER,
        rua TEXT,
        numero TEXT,
        bairro TEXT,
        cep TEXT,
        cidade TEXT,
        telefone TEXT,
        tipo_integracao TEXT,
        data_integracao DATE,
        batismo_aguas DATE,
        batismo_espirito DATE,
        procedencia TEXT,
        congregacao TEXT,
        area TEXT,
        nucleo TEXT,
        foto TEXT,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ";
    
    $pdo->exec($sql);
}

// Função para obter todos os membros com paginação
function obterMembros($pagina = 1, $porPagina = 10) {
    try {
        $pdo = obterConexao();
        
        $offset = ($pagina - 1) * $porPagina;
        
        $sql = "SELECT * FROM membros ORDER BY data_cadastro DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Erro ao obter membros: ' . $e->getMessage());
        return array();
    }
}

// Função para obter membro por ID
function obterMembroPorId($id) {
    try {
        $pdo = obterConexao();
        
        $sql = "SELECT * FROM membros WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Erro ao obter membro: ' . $e->getMessage());
        return null;
    }
}

// Função para contar total de membros
function contarMembros() {
    try {
        $pdo = obterConexao();
        
        $sql = "SELECT COUNT(*) as total FROM membros";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado['total'] ?? 0;
    } catch (Exception $e) {
        error_log('Erro ao contar membros: ' . $e->getMessage());
        return 0;
    }
}

// Função para obter estatísticas
function obterEstatisticas() {
    try {
        $pdo = obterConexao();
        
        $stats = array(
            'total' => 0,
            'mes_atual' => 0,
            'batismo' => 0,
            'mudanca' => 0,
            'aclamacao' => 0
        );
        
        // Total de membros
        $sql = "SELECT COUNT(*) as total FROM membros";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total'] = $resultado['total'] ?? 0;
        
        // Membros cadastrados este mês
        $sql = "SELECT COUNT(*) as total FROM membros WHERE strftime('%m', data_cadastro) = strftime('%m', 'now') AND strftime('%Y', data_cadastro) = strftime('%Y', 'now')";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['mes_atual'] = $resultado['total'] ?? 0;
        
        // Batismos
        $sql = "SELECT COUNT(*) as total FROM membros WHERE tipo_integracao = 'Batismo'";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['batismo'] = $resultado['total'] ?? 0;
        
        // Mudanças
        $sql = "SELECT COUNT(*) as total FROM membros WHERE tipo_integracao = 'Mudança'";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['mudanca'] = $resultado['total'] ?? 0;
        
        // Aclamação
        $sql = "SELECT COUNT(*) as total FROM membros WHERE tipo_integracao = 'Aclamação'";
        $stmt = $pdo->query($sql);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['aclamacao'] = $resultado['total'] ?? 0;
        
        return $stats;
    } catch (Exception $e) {
        error_log('Erro ao obter estatísticas: ' . $e->getMessage());
        return array(
            'total' => 0,
            'mes_atual' => 0,
            'batismo' => 0,
            'mudanca' => 0,
            'aclamacao' => 0
        );
    }
}

// Função para buscar membros
function buscarMembros($termo) {
    try {
        $pdo = obterConexao();
        
        $termo = '%' . $termo . '%';
        
        $sql = "SELECT * FROM membros WHERE 
                nome_completo LIKE :termo OR 
                cpf LIKE :termo OR 
                telefone LIKE :termo 
                ORDER BY data_cadastro DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':termo', $termo);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Erro ao buscar membros: ' . $e->getMessage());
        return array();
    }
}

// Função para inserir membro
function inserirMembro($dados) {
    try {
        $pdo = obterConexao();
        
        $sql = "INSERT INTO membros (
            nome_completo, data_nascimento, sexo, tipo_sanguineo, nacionalidade, 
            naturalidade, estado_uf, cpf, rg, titulo_eleitor, ctp, cdi, 
            escolaridade, profissao, pai, mae, estado_civil, conjuge, filhos, 
            rua, numero, bairro, cep, cidade, telefone, tipo_integracao, 
            data_integracao, batismo_aguas, batismo_espirito, procedencia, 
            congregacao, area, nucleo, foto
        ) VALUES (
            :nome_completo, :data_nascimento, :sexo, :tipo_sanguineo, :nacionalidade, 
            :naturalidade, :estado_uf, :cpf, :rg, :titulo_eleitor, :ctp, :cdi, 
            :escolaridade, :profissao, :pai, :mae, :estado_civil, :conjuge, :filhos, 
            :rua, :numero, :bairro, :cep, :cidade, :telefone, :tipo_integracao, 
            :data_integracao, :batismo_aguas, :batismo_espirito, :procedencia, 
            :congregacao, :area, :nucleo, :foto
        )";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind values
        foreach ($dados as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log('Erro ao inserir membro: ' . $e->getMessage());
        throw $e;
    }
}

// Função para atualizar membro
function atualizarMembro($id, $dados) {
    try {
        $pdo = obterConexao();
        
        $campos = array();
        foreach ($dados as $key => $value) {
            $campos[] = $key . ' = :' . $key;
        }
        
        $sql = "UPDATE membros SET " . implode(', ', $campos) . ", data_atualizacao = CURRENT_TIMESTAMP WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        foreach ($dados as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log('Erro ao atualizar membro: ' . $e->getMessage());
        throw $e;
    }
}

// Função para deletar membro
function deletarMembro($id) {
    try {
        $pdo = obterConexao();
        
        // Obter foto antes de deletar
        $membro = obterMembroPorId($id);
        if ($membro && !empty($membro['foto'])) {
            deletarFoto($membro['foto']);
        }
        
        $sql = "DELETE FROM membros WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log('Erro ao deletar membro: ' . $e->getMessage());
        throw $e;
    }
}

// Variável global para conexão
$pdo = null;
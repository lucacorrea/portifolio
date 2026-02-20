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

?>

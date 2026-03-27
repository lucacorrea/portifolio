<?php
ob_start();
session_start();
// api.php - Backend com MySQL (Hostinger)
header('Content-Type: application/json');

$host = 'localhost'; 
$dbname = 'u784961086_procuradoria';
$username = 'u784961086_procuradoria';
$password = '@XeFGMa8';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['status' => 'erro', 'message' => 'Erro de conexão: ' . $e->getMessage()]);
    exit;
}

// Criação automática da tabela se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS processos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(255),
    tipo_ato VARCHAR(255),
    natureza VARCHAR(255),
    tipo_manifestacao VARCHAR(255),
    revelia VARCHAR(50),
    data_envio VARCHAR(50),
    data_ciencia VARCHAR(50),
    tipo_contagem VARCHAR(50),
    final_prazo VARCHAR(50),
    prazo_critico VARCHAR(50),
    analisador VARCHAR(255),
    peticionador VARCHAR(255),
    quantidade_dias INT,
    status VARCHAR(100),
    data_protocolo VARCHAR(50),
    observacoes TEXT,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tabela de Usuários
$pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255),
    login VARCHAR(100) UNIQUE,
    senha VARCHAR(255),
    senha_plana VARCHAR(255),
    perfil VARCHAR(50) DEFAULT 'ANALISADOR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Inserir usuário padrão se não existir (admin / admin123)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = 'admin'");
$stmt->execute();
if ($stmt->fetchColumn() == 0) {
    $senhaHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO usuarios (nome, login, senha, perfil) VALUES ('Administrador', 'admin', ?, 'ADMIN')")->execute([$senhaHash]);
}

// Tabela de Configurações
$pdo->exec("CREATE TABLE IF NOT EXISTS configuracoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(100) UNIQUE,
    valor TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Tabela de Auditoria
$pdo->exec("CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    usuario_nome VARCHAR(255),
    acao VARCHAR(100),
    tabela VARCHAR(100),
    registro_id INT,
    dados_anteriores TEXT,
    dados_novos TEXT,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Verificar se a coluna senha_plana existe na tabela usuários
$cols_u = $pdo->query("SHOW COLUMNS FROM usuarios")->fetchAll();
$hasSenhaPlana = false;
foreach ($cols_u as $col) {
    if ($col['Field'] === 'senha_plana') {
        $hasSenhaPlana = true;
        break;
    }
}
if (!$hasSenhaPlana) {
    $pdo->exec("ALTER TABLE usuarios ADD COLUMN senha_plana VARCHAR(255)");
    $pdo->exec("UPDATE usuarios SET senha_plana = 'admin123' WHERE login = 'admin'");
}

// Verificar se a coluna peticionador existe na tabela processos
$columns = $pdo->query("SHOW COLUMNS FROM processos")->fetchAll();
$hasPeticionador = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'peticionador') {
        $hasPeticionador = true;
        break;
    }
}
if (!$hasPeticionador) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN peticionador VARCHAR(255)");
}

// Verificar se a coluna quantidade_dias existe
$hasQtdDias = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'quantidade_dias') {
        $hasQtdDias = true;
        break;
    }
}
if (!$hasQtdDias) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN quantidade_dias INT");
}

// Verificar se a coluna observacoes existe
$hasObs = false;
foreach ($columns as $col) {
    if ($col['Field'] === 'observacoes') {
        $hasObs = true;
        break;
    }
}
if (!$hasObs) {
    $pdo->exec("ALTER TABLE processos ADD COLUMN observacoes TEXT");
}


function registrarAuditoria($pdo, $acao, $tabela, $registro_id, $dados_anteriores = null, $dados_novos = null) {
    $usuario_id = $_SESSION['usuario_id'] ?? 0;
    $usuario_nome = $_SESSION['usuario_nome'] ?? 'Sistema';
    $stmt = $pdo->prepare("INSERT INTO auditoria (usuario_id, usuario_nome, acao, tabela, registro_id, dados_anteriores, dados_novos) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $usuario_id, 
        $usuario_nome, 
        $acao, 
        $tabela, 
        $registro_id, 
        $dados_anteriores ? json_encode($dados_anteriores) : null, 
        $dados_novos ? json_encode($dados_novos) : null
    ]);
}

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = $_GET['acao'] ?? '';

try {
    switch ($metodo) {
        case 'GET':
            if ($acao === 'listar') {
                $stmt = $pdo->query("SELECT * FROM processos ORDER BY data_criacao DESC");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } elseif ($acao === 'login_status') {
                echo json_encode([
                    'logado' => isset($_SESSION['usuario_id']),
                    'usuario' => $_SESSION['usuario_nome'] ?? '',
                    'perfil' => $_SESSION['usuario_perfil'] ?? ''
                ]);
            } elseif ($acao === 'listar_auditoria') {
                if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
                    throw new Exception("Acesso negado");
                }
                $stmt = $pdo->query("SELECT * FROM auditoria ORDER BY data_hora DESC LIMIT 100");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } elseif ($acao === 'listar_usuarios') {
                if (!isset($_SESSION['usuario_id'])) {
                    throw new Exception("Acesso negado");
                }
                $stmt = $pdo->query("SELECT id, nome, login, perfil, senha_plana FROM usuarios ORDER BY nome ASC");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } elseif ($acao === 'logout') {
                session_destroy();
                header('Location: login.php');
                exit;
            }
            break;
        case 'POST':
            if ($acao === 'login') {
                $p = json_decode(file_get_contents('php://input'), true);
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE login = ?");
                $stmt->execute([$p['login']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($p['senha'], $user['senha'])) {
                    $_SESSION['usuario_id'] = $user['id'];
                    $_SESSION['usuario_nome'] = $user['nome'];
                    $_SESSION['usuario_perfil'] = $user['perfil'];
                    echo json_encode(['status' => 'sucesso', 'usuario' => $user['nome']]);
                } else {
                    echo json_encode(['status' => 'erro', 'message' => 'Login ou senha inválidos']);
                }
            } elseif ($acao === 'salvar_usuario') {
                if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
                    throw new Exception("Acesso negado");
                }
                $p = json_decode(file_get_contents('php://input'), true);
                
                if (isset($p['id']) && $p['id'] !== '') {
                    // Update
                    if (!empty($p['senha'])) {
                        $senhaHash = password_hash($p['senha'], PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, senha = ?, senha_plana = ?, perfil = ? WHERE id = ?");
                        $stmt->execute([$p['nome'], $p['login'], $senhaHash, $p['senha'], $p['perfil'], $p['id']]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, login = ?, perfil = ? WHERE id = ?");
                        $stmt->execute([$p['nome'], $p['login'], $p['perfil'], $p['id']]);
                    }
                } else {
                    // Novo
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ?");
                    $stmt_check->execute([$p['login']]);
                    if ($stmt_check->fetchColumn() > 0) {
                        throw new Exception("O login '" . $p['login'] . "' já está em uso.");
                    }

                    $senhaHash = password_hash($p['senha'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, senha_plana, perfil) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$p['nome'], $p['login'], $senhaHash, $p['senha'], $p['perfil']]);
                }
                echo json_encode(['status' => 'sucesso']);

            } elseif ($acao === 'salvar') {
                $p = json_decode(file_get_contents('php://input'), true);
                
                if (isset($p['id']) && $p['id'] !== '') {
                    // Buscar dados anteriores para auditoria
                    $stmt_ant = $pdo->prepare("SELECT * FROM processos WHERE id = ?");
                    $stmt_ant->execute([$p['id']]);
                    $dados_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);

                    // Editar
                    $stmt = $pdo->prepare("UPDATE processos SET 
                        numero = ?, tipo_ato = ?, natureza = ?, tipo_manifestacao = ?, 
                        revelia = ?, data_envio = ?, data_ciencia = ?, tipo_contagem = ?, 
                        final_prazo = ?, prazo_critico = ?, analisador = ?, peticionador = ?, 
                        quantidade_dias = ?, status = ?, 
                        data_protocolo = ?, observacoes = ? WHERE id = ?");
                    $stmt->execute([
                        $p['numero'], $p['tipo_ato'], $p['natureza'], $p['tipo_manifestacao'],
                        $p['revelia'], $p['data_envio'], $p['data_ciencia'], $p['tipo_contagem'],
                        $p['final_prazo'], $p['prazo_critico'], $p['analisador'], $p['peticionador'],
                        $p['quantidade_dias'], $p['status'],
                        $p['data_protocolo'], $p['observacoes'], $p['id']
                    ]);

                    registrarAuditoria($pdo, 'UPDATE', 'processos', $p['id'], $dados_anteriores, $p);
                } else {
                    // Novo
                    $stmt = $pdo->prepare("INSERT INTO processos (
                        numero, tipo_ato, natureza, tipo_manifestacao, 
                        revelia, data_envio, data_ciencia, tipo_contagem, 
                        final_prazo, prazo_critico, analisador, peticionador, 
                        quantidade_dias, status, data_protocolo, observacoes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $p['numero'], $p['tipo_ato'], $p['natureza'], $p['tipo_manifestacao'],
                        $p['revelia'], $p['data_envio'], $p['data_ciencia'], $p['tipo_contagem'],
                        $p['final_prazo'], $p['prazo_critico'], $p['analisador'], $p['peticionador'],
                        $p['quantidade_dias'], $p['status'],
                        $p['data_protocolo'], $p['observacoes']
                    ]);
                    $novo_id = $pdo->lastInsertId();
                    registrarAuditoria($pdo, 'INSERT', 'processos', $novo_id, null, $p);
                }
                echo json_encode(['status' => 'sucesso']);
            } elseif ($acao === 'importar_csv') {
                if (!isset($_FILES['arquivo'])) {
                    throw new Exception("Nenhum arquivo enviado.");
                }

                $file = $_FILES['arquivo']['tmp_name'];
                $handle = fopen($file, "r");
                $cabecalho = fgetcsv($handle, 1000, ",");

                if (count($cabecalho) < 5) {
                    rewind($handle);
                    $cabecalho = fgetcsv($handle, 1000, ";");
                    $delimiter = ";";
                } else {
                    $delimiter = ",";
                }

                $mapeamento = [];
                foreach ($cabecalho as $index => $col) {
                    $col = trim(strtoupper($col));
                    if (strpos($col, 'Nº DO PROCESSO') !== false || strpos($col, 'PROCESSO') !== false) $mapeamento['numero'] = $index;
                    if (strpos($col, 'TIPO DE ATO') !== false) $mapeamento['tipo_ato'] = $index;
                    if (strpos($col, 'NATUREZA') !== false) $mapeamento['natureza'] = $index;
                    if (strpos($col, 'MANIFESTAÇÃO') !== false && strpos($col, 'TIPO') !== false) $mapeamento['tipo_manifestacao'] = $index;
                    if (strpos($col, 'REVEL') !== false) $mapeamento['revelia'] = $index;
                    if (strpos($col, 'ENVIO') !== false) $mapeamento['data_envio'] = $index;
                    if (strpos($col, 'CIÊNCIA') !== false) $mapeamento['data_ciencia'] = $index;
                    if (strpos($col, 'CONTAGEM') !== false) $mapeamento['tipo_contagem'] = $index;
                    if (strpos($col, 'FINAL DO PRAZO') !== false) $mapeamento['final_prazo'] = $index;
                    if (strpos($col, 'CRÍTICO') !== false) $mapeamento['prazo_critico'] = $index;
                    if (strpos($col, 'ANALISADOR') !== false) $mapeamento['analisador'] = $index;
                    if (strpos($col, 'STATUS') !== false) $mapeamento['status'] = $index;
                    if (strpos($col, 'PROTOCOLO') !== false) $mapeamento['data_protocolo'] = $index;
                    if (strpos($col, 'OBSERVAÇÕES') !== false) $mapeamento['observacoes'] = $index;
                }

                $inseridos = 0;
                $pula = 0;

                while (($dados = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    if (empty($dados[$mapeamento['numero'] ?? -1])) continue;

                    $numero = trim($dados[$mapeamento['numero']]);
                    $data_ciencia = trim($dados[$mapeamento['data_ciencia'] ?? -1] ?? '');

                    $formatarData = function($d) {
                        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', trim($d))) {
                            $parts = explode('/', trim($d));
                            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                        }
                        return trim($d);
                    };

                    $data_ciencia_formatada = $formatarData($data_ciencia);
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM processos WHERE numero = ? AND data_ciencia = ?");
                    $stmt_check->execute([$numero, $data_ciencia_formatada]);
                    if ($stmt_check->fetchColumn() > 0) {
                        $pula++;
                        continue;
                    }

                    $stmt = $pdo->prepare("INSERT INTO processos (
                        numero, tipo_ato, natureza, tipo_manifestacao, 
                        revelia, data_envio, data_ciencia, tipo_contagem, 
                        final_prazo, prazo_critico, analisador, status, 
                        data_protocolo, observacoes, peticionador, quantidade_dias
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->execute([
                        $numero,
                        $dados[$mapeamento['tipo_ato'] ?? -1] ?? '',
                        $dados[$mapeamento['natureza'] ?? -1] ?? '',
                        $dados[$mapeamento['tipo_manifestacao'] ?? -1] ?? '',
                        $dados[$mapeamento['revelia'] ?? -1] ?? 'NÃO',
                        $formatarData($dados[$mapeamento['data_envio'] ?? -1] ?? ''),
                        $data_ciencia_formatada,
                        $dados[$mapeamento['tipo_contagem'] ?? -1] ?? 'ÚTEIS',
                        $formatarData($dados[$mapeamento['final_prazo'] ?? -1] ?? ''),
                        $dados[$mapeamento['prazo_critico'] ?? -1] ?? 'NÃO',
                        $dados[$mapeamento['analisador'] ?? -1] ?? $_SESSION['usuario_nome'],
                        $dados[$mapeamento['status'] ?? -1] ?? 'PENDENTE',
                        $formatarData($dados[$mapeamento['data_protocolo'] ?? -1] ?? ''),
                        $dados[$mapeamento['observacoes'] ?? -1] ?? '',
                        $_SESSION['usuario_nome'],
                        15
                    ]);
                    $inseridos++;
                }
                fclose($handle);
                registrarAuditoria($pdo, 'IMPORT', 'processos', 0, null, ['inseridos' => $inseridos, 'pula' => $pula]);
                echo json_encode(['status' => 'sucesso', 'inseridos' => $inseridos, 'pula' => $pula]);
            } elseif ($acao === 'importar_dados') {
                $p = json_decode(file_get_contents('php://input'), true);
                if (!is_array($p)) {
                    throw new Exception("Dados inválidos.");
                }

                $inseridos = 0;
                $pula = 0;

                foreach ($p as $item) {
                    if (empty($item['numero'])) continue;

                    $numero = trim($item['numero']);
                    $data_ciencia = trim($item['data_ciencia'] ?? '');
                    $analisador_nome = trim($item['analisador'] ?? '');

                    // Cadastro automático de analisador se não existir
                    if ($analisador_nome !== '' && $analisador_nome !== 'Sistema') {
                        $stmt_u = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE nome = ?");
                        $stmt_u->execute([$analisador_nome]);
                        if ($stmt_u->fetchColumn() == 0) {
                            // Gerar um login simples (primeiro nome + last id ou similar para garantir UNIQUE se necessário)
                            // Aqui usaremos o nome higienizado como login inicial
                            $login_gerado = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $analisador_nome));
                            
                            // Verificar se o login já existe, se sim, anexar algo
                            $check_l = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE login = ?");
                            $check_l->execute([$login_gerado]);
                            if ($check_l->fetchColumn() > 0) {
                                $login_gerado .= rand(10, 99);
                            }

                            $senhaPadrao = 'admin123';
                            $senhaHash = password_hash($senhaPadrao, PASSWORD_DEFAULT);
                            $stmt_new_u = $pdo->prepare("INSERT INTO usuarios (nome, login, senha, senha_plana, perfil) VALUES (?, ?, ?, ?, ?)");
                            $stmt_new_u->execute([$analisador_nome, $login_gerado, $senhaHash, $senhaPadrao, 'ANALISADOR']);
                            
                            registrarAuditoria($pdo, 'AUTO_INSERT_USER', 'usuarios', $pdo->lastInsertId(), null, ['nome' => $analisador_nome, 'login' => $login_gerado]);
                        }
                    }

                    // Verificar duplicata (Processo + Data Ciência)
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM processos WHERE numero = ? AND data_ciencia = ?");
                    $stmt_check->execute([$numero, $data_ciencia]);
                    if ($stmt_check->fetchColumn() > 0) {
                        $pula++;
                        continue;
                    }

                    $stmt = $pdo->prepare("INSERT INTO processos (
                        numero, tipo_ato, natureza, tipo_manifestacao, 
                        revelia, data_envio, data_ciencia, tipo_contagem, 
                        final_prazo, prazo_critico, analisador, status, 
                        data_protocolo, observacoes, peticionador, quantidade_dias
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->execute([
                        $numero,
                        $item['tipo_ato'] ?? '',
                        $item['natureza'] ?? '',
                        $item['tipo_manifestacao'] ?? '',
                        $item['revelia'] ?? 'NÃO',
                        $item['data_envio'] ?? '',
                        $data_ciencia,
                        $item['tipo_contagem'] ?? 'ÚTEIS',
                        $item['final_prazo'] ?? '',
                        $item['prazo_critico'] ?? 'NÃO',
                        $item['analisador'] ?? $_SESSION['usuario_nome'],
                        $item['status'] ?? 'PENDENTE',
                        $item['data_protocolo'] ?? '',
                        $item['observacoes'] ?? '',
                        $_SESSION['usuario_nome'],
                        15
                    ]);
                    $inseridos++;
                }

                registrarAuditoria($pdo, 'IMPORT_JSON', 'processos', 0, null, ['inseridos' => $inseridos, 'pula' => $pula]);
                echo json_encode(['status' => 'sucesso', 'inseridos' => $inseridos, 'pula' => $pula]);
            }
            break;
            
        case 'DELETE':
            $id = $_GET['id'] ?? '';

            if ($acao === 'excluir_usuario') {
                if ($_SESSION['usuario_perfil'] !== 'ADMIN') {
                    throw new Exception("Acesso negado");
                }
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['status' => 'sucesso']);
            } else { // 'excluir' processo
                // Buscar para auditoria
                $stmt_ant = $pdo->prepare("SELECT * FROM processos WHERE id = ?");
                $stmt_ant->execute([$id]);
                $dados_anteriores = $stmt_ant->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM processos WHERE id = ?");
                $stmt->execute([$id]);

                registrarAuditoria($pdo, 'DELETE', 'processos', $id, $dados_anteriores, null);
                echo json_encode(['status' => 'sucesso']);
            }
            break;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'message' => $e->getMessage()]);
}
?>

<?php
// transferencias.php - Motor Core de Logística B2B do ERP Elétrica
require_once 'config.php';
checkAuth();

$usuario_nivel = $_SESSION['usuario_nivel'] ?? '';
$filial_logada = $_SESSION['filial_id'] ?? 1;

// Identificando se é Matriz (Filial 1 e Admin/Master) ou Filial Externa
$isMatriz = ($filial_logada == 1 && in_array($usuario_nivel, ['admin', 'master', 'gerente']));

$action = $_GET['action'] ?? '';
$aba = $_GET['aba'] ?? ($isMatriz ? 'recebidas' : 'nova_solicitacao');
$msg = $_GET['msg'] ?? '';
$erro = $_GET['erro'] ?? '';

// =======================================================
// AUTO-INSTALADOR (Garante que tudo funcionará em qualquer servidor)
// =======================================================
try {
    $pdo->query("SELECT data_solicitacao FROM erp_transferencias LIMIT 1");
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'doesn\'t exist') !== false || strpos($e->getMessage(), '42S02') !== false) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS erp_transferencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo_transferencia VARCHAR(20) NOT NULL UNIQUE,
                tipo VARCHAR(50) NOT NULL DEFAULT 'transferencia',
                origem_filial_id INT NOT NULL,
                destino_filial_id INT NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pendente',
                valor_total_custo DECIMAL(10,2) DEFAULT 0,
                observacoes TEXT,
                usuario_id INT NOT NULL,
                data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_aprovacao TIMESTAMP NULL,
                data_envio TIMESTAMP NULL,
                data_recebimento TIMESTAMP NULL
            );
            CREATE TABLE IF NOT EXISTS erp_transferencias_itens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transferencia_id INT NOT NULL,
                produto_id INT NOT NULL,
                quantidade_solicitada DECIMAL(10,3) NOT NULL,
                quantidade_enviada DECIMAL(10,3) DEFAULT 0,
                quantidade_recebida DECIMAL(10,3) DEFAULT 0,
                valor_custo_unitario DECIMAL(10,2) DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS estoque_filiais (
                id INT AUTO_INCREMENT PRIMARY KEY,
                produto_id INT NOT NULL,
                filial_id INT NOT NULL,
                quantidade DECIMAL(10,3) DEFAULT 0,
                estoque_minimo DECIMAL(10,3) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_produto_filial (produto_id, filial_id)
            );
            INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
            SELECT id, 1, quantidade, estoque_minimo FROM produtos;
        ");
    }
}

// =======================================================
// AÇÕES POST - LÓGICA DE TRANSFERÊNCIA DE ATIVOS
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- Nova Solicitação de Material (Filial -> Matriz) ---
    if ($action == 'nova_solicitacao' && !$isMatriz) {
        $itens = $_POST['itens'] ?? [];
        $observacoes = $_POST['observacoes'] ?? '';
        
        $itensValidos = array_filter($itens, function($item) {
            return !empty($item['selecionado']) && $item['quantidade'] > 0;
        });
        
        if (count($itensValidos) == 0) {
            header("Location: transferencias.php?aba=nova_solicitacao&erro=Selecione ao menos um produto com quantidade válida.");
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            // Cria o Malote "Transferência de Estoque"
            $codigoTransf = 'REQ-' . date('YmdHis') . '-' . rand(100, 999);
            $stmt = $pdo->prepare("INSERT INTO erp_transferencias (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $codigoTransf,
                'solicitacao',
                1, // De onde o estoque vai sair (A matriz manda)
                $filial_logada, // O destino final do pedido (A filial que pede)
                'pendente',
                $observacoes,
                $_SESSION['usuario_id'] ?? 0
            ]);
            
            $transf_id = $pdo->lastInsertId();
            
            $stmtItem = $pdo->prepare("INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, valor_custo_unitario) VALUES (?, ?, ?, ?)");
            
            foreach ($itensValidos as $item) {
                // Recuperar o custo unitário atual para auditoria
                $pd = $pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                $pd->execute([$item['produto_id']]);
                $custo = $pd->fetchColumn() ?: 0;
                
                $stmtItem->execute([
                    $transf_id,
                    $item['produto_id'],
                    $item['quantidade'],
                    $custo
                ]);
            }
            
            $pdo->commit();
            header("Location: transferencias.php?aba=pendentes&msg=Solicitação enviada para a Matriz com sucesso!");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: transferencias.php?aba=nova_solicitacao&erro=Erro ao processar: " . urlencode($e->getMessage()));
            exit;
        }
    }
    
    // --- Transferência Direta Remessa (Matriz -> Filial) ---
    if ($action == 'nova_transferencia' && $isMatriz) {
        $itens = $_POST['itens'] ?? [];
        $destino_id = $_POST['destino_filial_id'] ?? 0;
        $observacoes = $_POST['observacoes'] ?? '';
        
        $itensValidos = array_filter($itens, function($item) {
            return !empty($item['selecionado']) && $item['quantidade'] > 0;
        });
        
        if (count($itensValidos) == 0 || $destino_id == 0) {
            header("Location: transferencias.php?aba=nova_transferencia&erro=Selecione a filial destino e os produtos.");
            exit;
        }

        try {
            $pdo->beginTransaction();
            
            $codigoTransf = 'ENV-' . date('YmdHis') . '-' . rand(100, 999);
            $stmt = $pdo->prepare("INSERT INTO erp_transferencias (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, data_envio) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $codigoTransf,
                'transferencia',
                1, 
                $destino_id, 
                'em_transito', // Como é a matriz forçando envio, já sai em trânsito
                $observacoes,
                $_SESSION['usuario_id'] ?? 0
            ]);
            
            $transf_id = $pdo->lastInsertId();
            
            $stmtItem = $pdo->prepare("INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, quantidade_enviada, valor_custo_unitario) VALUES (?, ?, ?, ?, ?)");
            $stmtDecrement = $pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = 1");
            // Se as filiais ainda operarem em estoque global, descontamos do global temporariamente caso a migração do estoque_filiais ainda não tenha rodado
            $stmtGlobalDec = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

            foreach ($itensValidos as $item) {
                $pd = $pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                $pd->execute([$item['produto_id']]);
                $custo = $pd->fetchColumn() ?: 0;
                
                $stmtItem->execute([
                    $transf_id,
                    $item['produto_id'],
                    $item['quantidade'], // Considera-se solicitada para histórico
                    $item['quantidade'], // Considera-se enviada fisicamente
                    $custo
                ]);
                
                // Debita FISICAMENTE o estoque da Matriz (pois já está em trânsito)
                try {
                    $stmtDecrement->execute([$item['quantidade'], $item['produto_id']]);
                } catch (\Exception $ex) {
                    $stmtGlobalDec->execute([$item['quantidade'], $item['produto_id']]);
                }
            }
            
            $pdo->commit();
            header("Location: transferencias.php?aba=historico_envios&msg=Transferência despachada com sucesso!");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: transferencias.php?aba=nova_transferencia&erro=Erro: " . urlencode($e->getMessage()));
            exit;
        }
    }

    // --- Aprovar Solicitação (Ação da Matriz) ---
    if ($action == 'aprovar_solicitacao' && $isMatriz) {
        $transf_id = $_POST['transferencia_id'];
        $itens_enviados = $_POST['qtd_enviada'] ?? []; // Quantidades reais confirmadas
        
        try {
            $pdo->beginTransaction();
            
            // Atualiza status do malote
            $stmt = $pdo->prepare("UPDATE erp_transferencias SET status = 'em_transito', data_envio = NOW(), data_aprovacao = NOW() WHERE id = ? AND origem_filial_id = 1");
            $stmt->execute([$transf_id]);
            
            $stmtItem = $pdo->prepare("UPDATE erp_transferencias_itens SET quantidade_enviada = ? WHERE transferencia_id = ? AND produto_id = ?");
            $stmtDecrement = $pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = 1");
            $stmtGlobalDec = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

            foreach ($itens_enviados as $produto_id => $qtd) {
                if ($qtd > 0) {
                    $stmtItem->execute([$qtd, $transf_id, $produto_id]);
                    
                    try {
                        $stmtDecrement->execute([$qtd, $produto_id]);
                    } catch (\Exception $ex) {
                         $stmtGlobalDec->execute([$qtd, $produto_id]);
                    }
                }
            }
            
            $pdo->commit();
            header("Location: transferencias.php?aba=recebidas&msg=Solicitação Aprovada e Despachada!");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: transferencias.php?aba=recebidas&erro=Erro: " . urlencode($e->getMessage()));
            exit;
        }
    }

    // --- Confirmar Recebimento (Ação da Filial Destino) ---
    if ($action == 'confirmar_recebimento' && !$isMatriz) {
        $transf_id = $_POST['transferencia_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Garantir que a transferência seja desta filial
            $check = $pdo->prepare("SELECT * FROM erp_transferencias WHERE id = ? AND destino_filial_id = ? AND status = 'em_transito'");
            $check->execute([$transf_id, $filial_logada]);
            if (!$check->fetch()) throw new Exception("Requisição inválida.");

            $stmt = $pdo->prepare("UPDATE erp_transferencias SET status = 'concluida', data_recebimento = NOW() WHERE id = ?");
            $stmt->execute([$transf_id]);
            
            // Puxa os itens enviados
            $itens = $pdo->prepare("SELECT produto_id, quantidade_enviada FROM erp_transferencias_itens WHERE transferencia_id = ?");
            $itens->execute([$transf_id]);
            
            $stmtIncrement = $pdo->prepare("INSERT INTO estoque_filiais (produto_id, filial_id, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + ?");

            foreach ($itens->fetchAll() as $item) {
                $qtd = $item['quantidade_enviada'];
                if ($qtd > 0) {
                    // Update transferencias_itens
                    $upd = $pdo->prepare("UPDATE erp_transferencias_itens SET quantidade_recebida = ? WHERE transferencia_id = ? AND produto_id = ?");
                    $upd->execute([$qtd, $transf_id, $item['produto_id']]);

                    // Adiciona Fisicamente ao Estoque da FILIAL
                    try {
                         $stmtIncrement->execute([$item['produto_id'], $filial_logada, $qtd, $qtd]);
                    } catch (\Exception $ex) {
                         // Fallback não faz nada no global senão bagunça
                    }
                }
            }
            
            $pdo->commit();
            header("Location: transferencias.php?aba=historico_recebimentos&msg=Estoque internalizado com sucesso!");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: transferencias.php?aba=em_transito&erro=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

// =======================================================
// DADOS DA VIEW
// =======================================================

// Produtos do Catálogo Global (para o formulário)
// Lembrete: A Matriz exibe todo o catálogo para a filial escolher
$produtosMatriz = [];
try {
    $produtosMatriz = $pdo->query("SELECT p.*, COALESCE((SELECT quantidade FROM estoque_filiais WHERE produto_id = p.id AND filial_id = 1), p.quantidade) as qtd_matriz FROM produtos p ORDER BY p.nome")->fetchAll();
} catch (\Exception $e) {
    // Caso a migration ainda não tenha rodado
    $produtosMatriz = $pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();
}

$filiais = $pdo->query("SELECT * FROM filiais WHERE principal = 0 ORDER BY nome")->fetchAll();

// Listagem de Transferências de Acordo com a Filial
if ($isMatriz) {
    $recebidas = $pdo->query("SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.destino_filial_id = f.id WHERE t.tipo = 'solicitacao' AND t.status = 'pendente' ORDER BY t.data_solicitacao DESC")->fetchAll();
    
    $historico_envios = $pdo->query("SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.destino_filial_id = f.id WHERE t.origem_filial_id = 1 AND t.status IN ('em_transito', 'concluida') ORDER BY t.data_envio DESC LIMIT 50")->fetchAll();
} else {
    $em_transito = $pdo->prepare("SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.origem_filial_id = f.id WHERE t.destino_filial_id = ? AND t.status = 'em_transito' ORDER BY t.data_envio DESC");
    $em_transito->execute([$filial_logada]);
    $em_transito = $em_transito->fetchAll();
    
    $historico = $pdo->prepare("SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.origem_filial_id = f.id WHERE t.destino_filial_id = ? ORDER BY t.data_solicitacao DESC LIMIT 50");
    $historico->execute([$filial_logada]);
    $historico = $historico->fetchAll();
}

// Chamar View
require 'views/estoque/transferencias.view.php';

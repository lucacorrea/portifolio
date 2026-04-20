<?php
namespace App\Controllers;

class TransferenciasController extends BaseController {

    private $pdo;
    private $isMatriz;
    private $filialLogada;
    private $matrizId; // ID real da Matriz (filial com principal = 1)

    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $this->pdo = $db;
        $this->filialLogada = $_SESSION['filial_id'] ?? 1;

        // Fonte da verdade: is_matriz definido pelo AuthService no login
        $this->isMatriz = $_SESSION['is_matriz'] ?? false;

        // ID real da Matriz (campo principal = 1 na tabela filiais)
        $m = $this->pdo->query("SELECT id FROM filiais WHERE principal = 1 LIMIT 1")->fetch();
        $this->matrizId = $m ? (int)$m['id'] : 1; // fallback para 1

        $this->ensureTables();
    }

    private function ensureTables() {
        try {
            // 1. Verificar colunas na tabela principal
            $cols = $this->pdo->query("DESCRIBE erp_transferencias")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('tem_problema', $cols)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias ADD COLUMN tem_problema TINYINT DEFAULT 0");
            }
            if (!in_array('relato_problema', $cols)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias ADD COLUMN relato_problema TEXT");
            }
            if (!in_array('data_relato', $cols)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias ADD COLUMN data_relato TIMESTAMP NULL");
            }
            if (!in_array('problema_resolvido', $cols)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias ADD COLUMN problema_resolvido TINYINT DEFAULT 0");
            }

            // check erp_transferencias_ocorrencias columns
            $colsOc = $this->pdo->query("DESCRIBE erp_transferencias_ocorrencias")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('foto', $colsOc)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias_ocorrencias ADD COLUMN foto VARCHAR(255) DEFAULT NULL");
            }
        } catch (\Exception $e) {
            // Tabela erp_transferencias não existe, cria do zero
            $this->pdo->exec("
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
                    data_recebimento TIMESTAMP NULL,
                    tem_problema TINYINT DEFAULT 0,
                    relato_problema TEXT,
                    data_relato TIMESTAMP NULL,
                    problema_resolvido TINYINT DEFAULT 0
                )
            ");
        }

        // 2. Criar tabelas auxiliares se não existirem
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS erp_transferencias_itens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transferencia_id INT NOT NULL,
                produto_id INT NOT NULL,
                quantidade_solicitada DECIMAL(10,3) NOT NULL,
                quantidade_enviada DECIMAL(10,3) DEFAULT 0,
                quantidade_recebida DECIMAL(10,3) DEFAULT 0,
                valor_custo_unitario DECIMAL(10,2) DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS erp_transferencias_ocorrencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                transferencia_id INT NOT NULL,
                produto_id INT NOT NULL,
                quantidade_problema DECIMAL(10,3) NOT NULL,
                motivo VARCHAR(100) DEFAULT 'defeito',
                descricao TEXT,
                foto VARCHAR(255) DEFAULT NULL,
                data_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_transf (transferencia_id)
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
        ");

        // 3. Garantir estoque da matriz (opcional/legado)
        $mid = $this->matrizId;
        $this->pdo->exec("INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
                          SELECT id, $mid, quantidade, estoque_minimo FROM produtos");
    }

    public function index() {
        $aba = $_GET['aba'] ?? ($this->isMatriz ? 'recebidas' : 'nova_solicitacao');
        $mid = $this->matrizId;

        // Produtos do catálogo da Matriz (com estoque específico da filial-matriz)
        try {
            $produtosMatriz = $this->pdo->query(
                "SELECT p.*, COALESCE(
                    (SELECT quantidade FROM estoque_filiais WHERE produto_id = p.id AND filial_id = $mid),
                    p.quantidade
                ) as qtd_matriz FROM produtos p ORDER BY p.nome"
            )->fetchAll();
        } catch (\Exception $e) {
            $produtosMatriz = $this->pdo->query("SELECT *, quantidade as qtd_matriz FROM produtos ORDER BY nome")->fetchAll();
        }

        // Filiais de destino (exceto a própria Matriz)
        $filiais = $this->pdo->query("SELECT * FROM filiais WHERE principal = 0 ORDER BY nome")->fetchAll();

        $recebidas = [];
        $historico_envios = [];
        $em_transito = [];
        $historico = [];
        $problemas_pendentes = 0;

        // Captura filtros
        $fCodigo = $_GET['filtro_codigo'] ?? '';
        $fStatus = $_GET['filtro_status'] ?? '';
        $fInicio = $_GET['filtro_inicio'] ?? '';
        $fFim    = $_GET['filtro_fim']    ?? '';

        if ($this->isMatriz) {
            // Solicitações pendentes recebidas das filiais
            $recebidas = $this->pdo->query(
                "SELECT t.*, f.nome as nome_filial
                 FROM erp_transferencias t
                 LEFT JOIN filiais f ON t.destino_filial_id = f.id
                 WHERE t.tipo = 'solicitacao' AND t.status = 'pendente'
                 ORDER BY t.data_solicitacao DESC"
            )->fetchAll();

            // Histórico de tudo que a Matriz já enviou (COM FILTRO)
            $sqlH = "SELECT t.*, f.nome as nome_filial FROM erp_transferencias t LEFT JOIN filiais f ON t.destino_filial_id = f.id WHERE t.origem_filial_id = $mid AND t.status IN ('em_transito', 'concluida')";
            $paramsH = [];

            if ($fCodigo) { $sqlH .= " AND t.codigo_transferencia LIKE ?"; $paramsH[] = "%$fCodigo%"; }
            if ($fStatus) { $sqlH .= " AND t.status = ?"; $paramsH[] = $fStatus; }
            if ($fInicio) { $sqlH .= " AND DATE(t.data_envio) >= ?"; $paramsH[] = $fInicio; }
            if ($fFim)    { $sqlH .= " AND DATE(t.data_envio) <= ?"; $paramsH[] = $fFim; }

            $sqlH .= " ORDER BY t.data_envio DESC LIMIT 100";
            $stmtH = $this->pdo->prepare($sqlH);
            $stmtH->execute($paramsH);
            $historico_envios = $stmtH->fetchAll();

            // Conta problemas pendentes
            $problemas_pendentes = $this->pdo->query(
                "SELECT COUNT(*) FROM erp_transferencias WHERE origem_filial_id = $mid AND tem_problema = 1 AND problema_resolvido = 0"
            )->fetchColumn();

        } else {
            // Em Trânsito: o que foi despachado pela Matriz para ESTA filial
            $stmt = $this->pdo->prepare(
                "SELECT t.*, COALESCE(f.nome, 'Matriz') as nome_filial
                 FROM erp_transferencias t
                 LEFT JOIN filiais f ON t.origem_filial_id = f.id
                 WHERE t.destino_filial_id = ? AND t.status = 'em_transito'
                 ORDER BY t.data_envio DESC"
            );
            $stmt->execute([$this->filialLogada]);
            $em_transito = $stmt->fetchAll();

            // Histórico completo desta filial (COM FILTRO)
            $sqlF = "SELECT t.*, COALESCE(f.nome, 'Matriz') as nome_filial FROM erp_transferencias t LEFT JOIN filiais f ON t.origem_filial_id = f.id WHERE t.destino_filial_id = ?";
            $paramsF = [$this->filialLogada];

            if ($fCodigo) { $sqlF .= " AND t.codigo_transferencia LIKE ?"; $paramsF[] = "%$fCodigo%"; }
            if ($fStatus) { $sqlF .= " AND t.status = ?"; $paramsF[] = $fStatus; }
            if ($fInicio) { $sqlF .= " AND DATE(COALESCE(t.data_recebimento, t.data_solicitacao)) >= ?"; $paramsF[] = $fInicio; }
            if ($fFim)    { $sqlF .= " AND DATE(COALESCE(t.data_recebimento, t.data_solicitacao)) <= ?"; $paramsF[] = $fFim; }

            $sqlF .= " ORDER BY t.data_solicitacao DESC LIMIT 100";
            $stmtF = $this->pdo->prepare($sqlF);
            $stmtF->execute($paramsF);
            $historico = $stmtF->fetchAll();
        }

        $this->render('estoque/transferencias', [
            'title'           => 'Logística B2B',
            'pageTitle'       => 'Central de Logística B2B',
            'isMatriz'        => $this->isMatriz,
            'aba'             => $aba,
            'produtosMatriz'  => $produtosMatriz,
            'filiais'         => $filiais,
            'recebidas'       => $recebidas,
            'historico_envios'=> $historico_envios,
            'em_transito'     => $em_transito,
            'historico'       => $historico,
            'pdo'             => $this->pdo,
            'matrizId'        => $this->matrizId,
            'problemas_pendentes' => $problemas_pendentes
        ]);
    }

    public function novaSolicitacao() {
        if ($this->isMatriz) {
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('A Matriz não pode solicitar materiais para si mesma.'));
        }

        $itens = $_POST['itens'] ?? [];
        $observacoes = trim($_POST['observacoes'] ?? '');
        $mid = $this->matrizId;

        $itensValidos = array_filter($itens, fn($item) => !empty($item['selecionado']) && $item['quantidade'] > 0);

        if (count($itensValidos) === 0) {
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('Selecione ao menos um produto com quantidade válida.'));
        }

        try {
            $this->pdo->beginTransaction();
            $codigo = 'REQ-' . date('YmdHis') . '-' . rand(100, 999);

            // origem = Matriz (CD que vai enviar), destino = esta filial
            $stmt = $this->pdo->prepare(
                "INSERT INTO erp_transferencias
                    (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id)
                 VALUES (?, 'solicitacao', ?, ?, 'pendente', ?, ?)"
            );
            $stmt->execute([$codigo, $mid, $this->filialLogada, $observacoes, $_SESSION['usuario_id'] ?? 0]);
            $transf_id = $this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare(
                "INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, valor_custo_unitario)
                 VALUES (?, ?, ?, ?)"
            );
            foreach ($itensValidos as $item) {
                $pd = $this->pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                $pd->execute([$item['produto_id']]);
                $custo = $pd->fetchColumn() ?: 0;
                $stmtItem->execute([$transf_id, $item['produto_id'], $item['quantidade'], $custo]);
            }

            $this->pdo->commit();
            setFlash('success', 'Solicitação enviada para a Matriz com sucesso!');
            $this->redirect('transferencias.php?aba=historico_recebimentos');
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('Erro: ' . $e->getMessage()));
        }
    }

    public function novaTransferencia() {
        if (!$this->isMatriz) {
            $this->redirect('transferencias.php?erro=Acesso negado.');
        }

        $itens = $_POST['itens'] ?? [];
        $destino_id = (int)($_POST['destino_filial_id'] ?? 0);
        $observacoes = trim($_POST['observacoes'] ?? '');
        $mid = $this->matrizId;

        $itensValidos = array_filter($itens, fn($item) => !empty($item['selecionado']) && $item['quantidade'] > 0);

        if (count($itensValidos) === 0 || $destino_id === 0) {
            $this->redirect('transferencias.php?aba=nova_transferencia&erro=' . urlencode('Selecione a filial destino e os produtos.'));
        }

        try {
            $this->pdo->beginTransaction();
            $codigo = 'ENV-' . date('YmdHis') . '-' . rand(100, 999);

            $stmt = $this->pdo->prepare(
                "INSERT INTO erp_transferencias
                    (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, data_envio)
                 VALUES (?, 'transferencia', ?, ?, 'em_transito', ?, ?, NOW())"
            );
            $stmt->execute([$codigo, $mid, $destino_id, $observacoes, $_SESSION['usuario_id'] ?? 0]);
            $transf_id = $this->pdo->lastInsertId();

            $stmtItem    = $this->pdo->prepare(
                "INSERT INTO erp_transferencias_itens
                    (transferencia_id, produto_id, quantidade_solicitada, quantidade_enviada, valor_custo_unitario)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $stmtDec     = $this->pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = $mid");
            $stmtDecGlob = $this->pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

            // Query para verificar estoque atual
            $stmtCheck = $this->pdo->prepare("
                SELECT p.nome, COALESCE(ef.quantidade, p.quantidade) as estoque_atual
                FROM produtos p
                LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE p.id = ?
            ");

            $totalItensValidos = 0;
            foreach ($itensValidos as $item) {
                $pid = $item['produto_id'];
                $qtd = (float)$item['quantidade'];

                if ($qtd <= 0) {
                    throw new \Exception("Erro: Não é permitido despachar itens com quantidade zero ou negativa na transferência manual.");
                }

                // 1. Validação de Estoque (Servidor)
                $stmtCheck->execute([$mid, $pid]);
                $estoque = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
                
                if (!$estoque || $estoque['estoque_atual'] < $qtd) {
                    $nomeProd = $estoque ? $estoque['nome'] : "Produto ID $pid";
                    $disponivel = $estoque ? (float)$estoque['estoque_atual'] : 0;
                    throw new \Exception("Estoque insuficiente na Matriz para '{$nomeProd}'. Disponível: {$disponivel}, Tentado: {$qtd}");
                }

                $pd = $this->pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                $pd->execute([$pid]);
                $custo = $pd->fetchColumn() ?: 0;
                
                $stmtItem->execute([$transf_id, $pid, $qtd, $qtd, $custo]);
                try {
                    $stmtDec->execute([$qtd, $pid]);
                } catch (\Exception $ex) {
                    $stmtDecGlob->execute([$qtd, $pid]);
                }
                $totalItensValidos++;
            }

            if ($totalItensValidos === 0) {
                throw new \Exception("Nenhum item com quantidade válida para despacho.");
            }

            $this->pdo->commit();
            setFlash('success', 'Transferência despachada com sucesso!');
            $this->redirect('transferencias.php?aba=historico_envios');
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=nova_transferencia&erro=' . urlencode('Erro: ' . $e->getMessage()));
        }
    }

    public function aprovarSolicitacao() {
        if (!$this->isMatriz) {
            $this->redirect('transferencias.php?erro=Acesso negado.');
        }

        $transf_id      = (int)($_POST['transferencia_id'] ?? 0);
        $itens_enviados = $_POST['qtd_enviada'] ?? [];
        $mid = $this->matrizId;

        try {
            $this->pdo->beginTransaction();

            // Atualiza status — origem deve ser a Matriz
            $stmt = $this->pdo->prepare(
                "UPDATE erp_transferencias
                 SET status = 'em_transito', data_envio = NOW(), data_aprovacao = NOW()
                 WHERE id = ? AND origem_filial_id = ?"
            );
            $stmt->execute([$transf_id, $mid]);

            if ($stmt->rowCount() === 0) {
                throw new \Exception("Transferência não encontrada ou não pertence à Matriz.");
            }

            $stmtItem    = $this->pdo->prepare("UPDATE erp_transferencias_itens SET quantidade_enviada = ? WHERE transferencia_id = ? AND produto_id = ?");
            $stmtDec     = $this->pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = $mid");
            $stmtDecGlob = $this->pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

            // Query para verificar estoque atual
            $stmtCheck = $this->pdo->prepare("
                SELECT p.nome, COALESCE(ef.quantidade, p.quantidade) as estoque_atual
                FROM produtos p
                LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE p.id = ?
            ");

            $totalQtdFinal = 0;
            foreach ($itens_enviados as $produto_id => $qtd) {
                $qtd = (float)$qtd;
                if ($qtd > 0) {
                    // 1. Validação de Estoque (Servidor)
                    $stmtCheck->execute([$mid, $produto_id]);
                    $estoque = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
                    
                    if (!$estoque || $estoque['estoque_atual'] < $qtd) {
                        $nomeProd = $estoque ? $estoque['nome'] : "Produto ID $produto_id";
                        $disponivel = $estoque ? (float)$estoque['estoque_atual'] : 0;
                        throw new \Exception("Estoque insuficiente na Matriz para '{$nomeProd}'. Disponível: {$disponivel}, Tentado: {$qtd}");
                    }

                    // 2. Processamento
                    $stmtItem->execute([$qtd, $transf_id, $produto_id]);
                    try {
                        $stmtDec->execute([$qtd, $produto_id]);
                    } catch (\Exception $ex) {
                        $stmtDecGlob->execute([$qtd, $produto_id]);
                    }
                    $totalQtdFinal += $qtd;
                } elseif ($qtd < 0) {
                    throw new \Exception("Quantidade negativa não é permitida.");
                }
            }

            if ($totalQtdFinal <= 0) {
                throw new \Exception("Erro: Não é possível despachar uma solicitação com quantidade total zero. Verifique o estoque da Matriz.");
            }

            $this->pdo->commit();
            setFlash('success', 'Solicitação aprovada e despachada com sucesso!');
            $this->redirect('transferencias.php?aba=recebidas');
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=recebidas&erro=' . urlencode('Erro: ' . $e->getMessage()));
        }
    }

    public function confirmarRecebimento() {
        if ($this->isMatriz) {
            $this->redirect('transferencias.php?erro=Ação inválida para a Matriz.');
        }

        $transf_id = (int)($_POST['transferencia_id'] ?? 0);

        try {
            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare(
                "SELECT * FROM erp_transferencias WHERE id = ? AND destino_filial_id = ? AND status = 'em_transito'"
            );
            $check->execute([$transf_id, $this->filialLogada]);
            if (!$check->fetch()) throw new \Exception("Requisição inválida ou já processada.");

            $this->pdo->prepare(
                "UPDATE erp_transferencias SET status = 'concluida', data_recebimento = NOW() WHERE id = ?"
            )->execute([$transf_id]);

            $itens = $this->pdo->prepare("SELECT produto_id, quantidade_enviada FROM erp_transferencias_itens WHERE transferencia_id = ?");
            $itens->execute([$transf_id]);

            $stmtInc = $this->pdo->prepare(
                "INSERT INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
                 SELECT p.id, ?, ?, p.estoque_minimo FROM produtos p WHERE p.id = ?
                 ON DUPLICATE KEY UPDATE estoque_filiais.quantidade = estoque_filiais.quantidade + ?"
            );
            $stmtUpdItem = $this->pdo->prepare(
                "UPDATE erp_transferencias_itens SET quantidade_recebida = ? WHERE transferencia_id = ? AND produto_id = ?"
            );

            // Busca ocorrências relatadas para subtrair
            $stmtOc = $this->pdo->prepare("SELECT SUM(quantidade_problema) as total FROM erp_transferencias_ocorrencias WHERE transferencia_id = ? AND produto_id = ?");

            foreach ($itens->fetchAll() as $item) {
                $pid = $item['produto_id'];
                $stmtOc->execute([$transf_id, $pid]);
                $qtdProblema = (float)($stmtOc->fetchColumn() ?: 0);
                
                $qtdFinal = $item['quantidade_enviada'] - $qtdProblema;
                if ($qtdFinal < 0) $qtdFinal = 0;

                if ($item['quantidade_enviada'] > 0) {
                    $stmtUpdItem->execute([$qtdFinal, $transf_id, $pid]);
                    if ($qtdFinal > 0) {
                        try {
                            $stmtInc->execute([$this->filialLogada, $qtdFinal, $pid, $qtdFinal]);
                        } catch (\Exception $ex) {
                            error_log("Erro ao atualizar estoque_filiais: " . $ex->getMessage());
                            throw new \Exception("Erro ao internalizar produto ID $pid: " . $ex->getMessage());
                        }
                    }
                }
            }

            $this->pdo->commit();
            setFlash('success', 'Estoque internalizado com sucesso!');
            $this->redirect('transferencias.php?aba=historico_recebimentos');
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=em_transito&erro=' . urlencode('Falha na Internalização: ' . $e->getMessage()));
        }
    }

    public function relatarProblema() {
        if ($this->isMatriz) {
            $this->redirect('transferencias.php?aba=em_transito&erro=Ação inválida para a Matriz.');
        }

        $transf_id = (int)($_POST['transferencia_id'] ?? 0);
        $mensagem  = trim($_POST['mensagem'] ?? '');
        $itens_problema = $_POST['ocorrencias'] ?? [];

        if (empty($itens_problema) && empty($mensagem)) {
            $this->redirect('transferencias.php?aba=em_transito&erro=' . urlencode('Informe ao menos um item com problema ou uma mensagem descritiva.'));
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Atualiza mestre
            $stmt = $this->pdo->prepare(
                "UPDATE erp_transferencias 
                 SET tem_problema = 1, relato_problema = ?, data_relato = NOW(), problema_resolvido = 0
                 WHERE id = ? AND destino_filial_id = ?"
            );
            $stmt->execute([$mensagem, $transf_id, $this->filialLogada]);

            // 2. Registra ocorrências por item
            $stmtOc = $this->pdo->prepare(
                "INSERT INTO erp_transferencias_ocorrencias (transferencia_id, produto_id, quantidade_problema, motivo, descricao, foto)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            // Garante pasta de upload
            $uploadDir = 'public/uploads/problemas/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($itens_problema as $produto_id => $oc) {
                if (!empty($oc['selecionado']) && $oc['quantidade'] > 0) {
                    $fotoPath = null;
                    
                    // Tenta capturar foto específica deste produto
                    $fileKey = "ocorrencias_{$produto_id}_foto";
                    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                        $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
                        $newName = "prob_" . $transf_id . "_" . $produto_id . "_" . time() . "." . $ext;
                        $dest = $uploadDir . $newName;
                        
                        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $dest)) {
                            $fotoPath = $dest;
                        }
                    }

                    $stmtOc->execute([
                        $transf_id, 
                        $produto_id, 
                        $oc['quantidade'], 
                        $oc['motivo'] ?? 'defeito', 
                        $oc['descricao'] ?? '',
                        $fotoPath
                    ]);
                }
            }

            $this->pdo->commit();
            setFlash('success', 'Problema detalhado relatado com sucesso. A Matriz foi notificada.');
            $this->redirect('transferencias.php?aba=em_transito');
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=em_transito&erro=' . urlencode($e->getMessage()));
        }
    }

    public function getTransferItems() {
        $transf_id = (int)($_GET['id'] ?? 0);
        
        // 1. Dados básicos da transferência
        $stmtT = $this->pdo->prepare("
            SELECT t.*, f_origem.nome as nome_origem, f_destino.nome as nome_destino
            FROM erp_transferencias t
            LEFT JOIN filiais f_origem ON t.origem_filial_id = f_origem.id
            LEFT JOIN filiais f_destino ON t.destino_filial_id = f_destino.id
            WHERE t.id = ?
        ");
        $stmtT->execute([$transf_id]);
        $transfer = $stmtT->fetch(\PDO::FETCH_ASSOC);

        if (!$transfer) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Transferência não encontrada.']);
            exit;
        }

        // 2. Itens
        $mid = $this->matrizId;
        $sqlItems = "SELECT ti.*, p.nome, p.codigo, 
                COALESCE(ef.quantidade, p.quantidade) as disp_matriz,
                (SELECT SUM(quantidade_problema) FROM erp_transferencias_ocorrencias WHERE transferencia_id = ti.transferencia_id AND produto_id = ti.produto_id) as quantidade_problema
                FROM erp_transferencias_itens ti 
                JOIN produtos p ON ti.produto_id = p.id 
                LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = $mid
                WHERE ti.transferencia_id = ?";
        
        $stmtI = $this->pdo->prepare($sqlItems);
        $stmtI->execute([$transf_id]);
        $items = $stmtI->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Ocorrências detalhadas
        $stmtO = $this->pdo->prepare("
            SELECT oc.*, p.nome, p.codigo
            FROM erp_transferencias_ocorrencias oc
            JOIN produtos p ON oc.produto_id = p.id
            WHERE oc.transferencia_id = ?
        ");
        $stmtO->execute([$transf_id]);
        $ocorrencias = $stmtO->fetchAll(\PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'transfer' => $transfer,
            'items' => $items,
            'ocorrencias' => $ocorrencias,
            'isMatriz' => $this->isMatriz
        ]);
        exit;
    }

    public function resolverProblema() {
        if (!$this->isMatriz) {
            $this->redirect('transferencias.php?erro=Acesso negado.');
        }

        $transf_id = (int)($_POST['transferencia_id'] ?? 0);
        $fluxo     = $_POST['fluxo'] ?? 'resolver'; // 'resolver' ou 'repor'

        try {
            $this->pdo->beginTransaction();

            // 1. Marca como resolvido no mestre original
            $stmt = $this->pdo->prepare(
                "UPDATE erp_transferencias SET problema_resolvido = 1 WHERE id = ? AND origem_filial_id = ?"
            );
            $stmt->execute([$transf_id, $this->matrizId]);

            // 2. Se for para repor, cria uma nova transferência
            if ($fluxo === 'repor') {
                // Busca dados da transferência original para saber o destino
                $orig = $this->pdo->prepare("SELECT destino_filial_id, observacoes FROM erp_transferencias WHERE id = ?");
                $orig->execute([$transf_id]);
                $dadosOrig = $orig->fetch(\PDO::FETCH_ASSOC);

                // Busca as ocorrências registradas
                $ocs = $this->pdo->prepare("SELECT * FROM erp_transferencias_ocorrencias WHERE transferencia_id = ?");
                $ocs->execute([$transf_id]);
                $itensComProblema = $ocs->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($itensComProblema)) {
                    $codigo = 'REP-' . date('YmdHis') . '-' . rand(100, 999);
                    $mid = $this->matrizId;

                    $stmtNew = $this->pdo->prepare(
                        "INSERT INTO erp_transferencias (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, data_envio)
                         VALUES (?, 'transferencia', ?, ?, 'em_transito', ?, ?, NOW())"
                    );
                    $obsNovo = "Reposição automática do pedido #" . $transf_id;
                    $stmtNew->execute([$codigo, $mid, $dadosOrig['destino_filial_id'], $obsNovo, $_SESSION['usuario_id'] ?? 0]);
                    $new_id = $this->pdo->lastInsertId();

                    $stmtItem    = $this->pdo->prepare("INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, quantidade_enviada, valor_custo_unitario) VALUES (?, ?, ?, ?, ?)");
                    $stmtDec     = $this->pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = $mid");
                    $stmtDecGlob = $this->pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

                    // Query para verificar estoque atual
                    $stmtCheck = $this->pdo->prepare("
                        SELECT p.nome, COALESCE(ef.quantidade, p.quantidade) as estoque_atual
                        FROM produtos p
                        LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                        WHERE p.id = ?
                    ");

                    $countReposto = 0;
                    foreach ($itensComProblema as $it) {
                        $pid = $it['produto_id'];
                        $qtd = (float)$it['quantidade_problema'];

                        if ($qtd <= 0) continue;

                        // 1. Validação de Estoque (Servidor)
                        $stmtCheck->execute([$mid, $pid]);
                        $estoque = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
                        
                        if (!$estoque || $estoque['estoque_atual'] < $qtd) {
                            $nomeProd = $estoque ? $estoque['nome'] : "Produto ID $pid";
                            $disponivel = $estoque ? (float)$estoque['estoque_atual'] : 0;
                            throw new \Exception("Estoque insuficiente na Matriz para repor '{$nomeProd}'. Disponível: {$disponivel}, Necessário: {$qtd}");
                        }

                        $pd = $this->pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                        $pd->execute([$pid]);
                        $custo = $pd->fetchColumn() ?: 0;

                        $stmtItem->execute([$new_id, $pid, $qtd, $qtd, $custo]);
                        
                        try {
                            $stmtDec->execute([$qtd, $pid]);
                        } catch (\Exception $ex) {
                            $stmtDecGlob->execute([$qtd, $pid]);
                        }
                        $countReposto++;
                    }

                    if ($countReposto === 0) {
                        throw new \Exception("Nenhum item válido para reposição foi encontrado.");
                    }
                }
            }

            $this->pdo->commit();
            setFlash('success', ($fluxo === 'repor') ? 'Problema resolvido e nova reposição despacho com sucesso!' : 'Ocorrência marcada como resolvida.');
            $this->redirect('transferencias.php?aba=historico_envios');
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=historico_envios&erro=' . urlencode($e->getMessage()));
        }
    }
}

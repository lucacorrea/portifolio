<?php
namespace App\Controllers;

class TransferenciasController extends BaseController {

    private $pdo;
    private $isMatriz;
    private $filialLogada;

    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $this->pdo = $db;
        $this->filialLogada = $_SESSION['filial_id'] ?? 1;
        $usuarioNivel = $_SESSION['usuario_nivel'] ?? '';
        $this->isMatriz = ($this->filialLogada == 1 && in_array($usuarioNivel, ['admin', 'master', 'gerente']));
        $this->ensureTables();
    }

    private function ensureTables() {
        try {
            $this->pdo->query("SELECT id FROM erp_transferencias LIMIT 1");
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), '42S02') !== false) {
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
    }

    public function index() {
        $aba = $_GET['aba'] ?? ($this->isMatriz ? 'recebidas' : 'nova_solicitacao');

        // Produtos do catálogo da Matriz
        try {
            $produtosMatriz = $this->pdo->query(
                "SELECT p.*, COALESCE((SELECT quantidade FROM estoque_filiais WHERE produto_id = p.id AND filial_id = 1), p.quantidade) as qtd_matriz FROM produtos p ORDER BY p.nome"
            )->fetchAll();
        } catch (\Exception $e) {
            $produtosMatriz = $this->pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();
        }

        $filiais = $this->pdo->query("SELECT * FROM filiais WHERE principal = 0 ORDER BY nome")->fetchAll();

        $recebidas = [];
        $historico_envios = [];
        $em_transito = [];
        $historico = [];

        if ($this->isMatriz) {
            $recebidas = $this->pdo->query(
                "SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.destino_filial_id = f.id WHERE t.tipo = 'solicitacao' AND t.status = 'pendente' ORDER BY t.data_solicitacao DESC"
            )->fetchAll();

            $historico_envios = $this->pdo->query(
                "SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.destino_filial_id = f.id WHERE t.origem_filial_id = 1 AND t.status IN ('em_transito', 'concluida') ORDER BY t.data_envio DESC LIMIT 50"
            )->fetchAll();
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.origem_filial_id = f.id WHERE t.destino_filial_id = ? AND t.status = 'em_transito' ORDER BY t.data_envio DESC"
            );
            $stmt->execute([$this->filialLogada]);
            $em_transito = $stmt->fetchAll();

            $stmt2 = $this->pdo->prepare(
                "SELECT t.*, f.nome as nome_filial FROM erp_transferencias t JOIN filiais f ON t.origem_filial_id = f.id WHERE t.destino_filial_id = ? ORDER BY t.data_solicitacao DESC LIMIT 50"
            );
            $stmt2->execute([$this->filialLogada]);
            $historico = $stmt2->fetchAll();
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
        ]);
    }

    public function novaSolicitacao() {
        if ($this->isMatriz) {
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=Ação inválida para a Matriz.');
        }

        $itens = $_POST['itens'] ?? [];
        $observacoes = trim($_POST['observacoes'] ?? '');

        $itensValidos = array_filter($itens, fn($item) => !empty($item['selecionado']) && $item['quantidade'] > 0);

        if (count($itensValidos) === 0) {
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('Selecione ao menos um produto com quantidade válida.'));
        }

        try {
            $this->pdo->beginTransaction();
            $codigo = 'REQ-' . date('YmdHis') . '-' . rand(100, 999);
            $stmt = $this->pdo->prepare("INSERT INTO erp_transferencias (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id) VALUES (?, 'solicitacao', 1, ?, 'pendente', ?, ?)");
            $stmt->execute([$codigo, $this->filialLogada, $observacoes, $_SESSION['usuario_id'] ?? 0]);
            $transf_id = $this->pdo->lastInsertId();

            $stmtItem = $this->pdo->prepare("INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, valor_custo_unitario) VALUES (?, ?, ?, ?)");
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

        $itensValidos = array_filter($itens, fn($item) => !empty($item['selecionado']) && $item['quantidade'] > 0);

        if (count($itensValidos) === 0 || $destino_id === 0) {
            $this->redirect('transferencias.php?aba=nova_transferencia&erro=' . urlencode('Selecione a filial destino e os produtos.'));
        }

        try {
            $this->pdo->beginTransaction();
            $codigo = 'ENV-' . date('YmdHis') . '-' . rand(100, 999);
            $stmt = $this->pdo->prepare("INSERT INTO erp_transferencias (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, data_envio) VALUES (?, 'transferencia', 1, ?, 'em_transito', ?, ?, NOW())");
            $stmt->execute([$codigo, $destino_id, $observacoes, $_SESSION['usuario_id'] ?? 0]);
            $transf_id = $this->pdo->lastInsertId();

            $stmtItem    = $this->pdo->prepare("INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, quantidade_enviada, valor_custo_unitario) VALUES (?, ?, ?, ?, ?)");
            $stmtDec     = $this->pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = 1");
            $stmtDecGlob = $this->pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

            foreach ($itensValidos as $item) {
                $pd = $this->pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                $pd->execute([$item['produto_id']]);
                $custo = $pd->fetchColumn() ?: 0;
                $stmtItem->execute([$transf_id, $item['produto_id'], $item['quantidade'], $item['quantidade'], $custo]);
                try {
                    $stmtDec->execute([$item['quantidade'], $item['produto_id']]);
                } catch (\Exception $ex) {
                    $stmtDecGlob->execute([$item['quantidade'], $item['produto_id']]);
                }
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

        $transf_id     = (int)($_POST['transferencia_id'] ?? 0);
        $itens_enviados = $_POST['qtd_enviada'] ?? [];

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE erp_transferencias SET status = 'em_transito', data_envio = NOW(), data_aprovacao = NOW() WHERE id = ? AND origem_filial_id = 1");
            $stmt->execute([$transf_id]);

            $stmtItem    = $this->pdo->prepare("UPDATE erp_transferencias_itens SET quantidade_enviada = ? WHERE transferencia_id = ? AND produto_id = ?");
            $stmtDec     = $this->pdo->prepare("UPDATE estoque_filiais SET quantidade = quantidade - ? WHERE produto_id = ? AND filial_id = 1");
            $stmtDecGlob = $this->pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");

            foreach ($itens_enviados as $produto_id => $qtd) {
                if ($qtd > 0) {
                    $stmtItem->execute([$qtd, $transf_id, $produto_id]);
                    try {
                        $stmtDec->execute([$qtd, $produto_id]);
                    } catch (\Exception $ex) {
                        $stmtDecGlob->execute([$qtd, $produto_id]);
                    }
                }
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

            $check = $this->pdo->prepare("SELECT * FROM erp_transferencias WHERE id = ? AND destino_filial_id = ? AND status = 'em_transito'");
            $check->execute([$transf_id, $this->filialLogada]);
            if (!$check->fetch()) throw new \Exception("Requisição inválida ou já processada.");

            $this->pdo->prepare("UPDATE erp_transferencias SET status = 'concluida', data_recebimento = NOW() WHERE id = ?")->execute([$transf_id]);

            $itens = $this->pdo->prepare("SELECT produto_id, quantidade_enviada FROM erp_transferencias_itens WHERE transferencia_id = ?");
            $itens->execute([$transf_id]);

            $stmtInc = $this->pdo->prepare("INSERT INTO estoque_filiais (produto_id, filial_id, quantidade) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantidade = quantidade + ?");
            $stmtUpdItem = $this->pdo->prepare("UPDATE erp_transferencias_itens SET quantidade_recebida = ? WHERE transferencia_id = ? AND produto_id = ?");

            foreach ($itens->fetchAll() as $item) {
                $qtd = $item['quantidade_enviada'];
                if ($qtd > 0) {
                    $stmtUpdItem->execute([$qtd, $transf_id, $item['produto_id']]);
                    try {
                        $stmtInc->execute([$item['produto_id'], $this->filialLogada, $qtd, $qtd]);
                    } catch (\Exception $ex) { /* silent */ }
                }
            }

            $this->pdo->commit();
            setFlash('success', 'Estoque internalizado com sucesso!');
            $this->redirect('transferencias.php?aba=historico_recebimentos');
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=em_transito&erro=' . urlencode($e->getMessage()));
        }
    }
}

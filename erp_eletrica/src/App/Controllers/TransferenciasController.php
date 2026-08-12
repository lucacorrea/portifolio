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
            if (!in_array('idempotency_key', $cols)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias ADD COLUMN idempotency_key VARCHAR(80) NULL DEFAULT NULL");
            }

            // check erp_transferencias_ocorrencias columns
            $colsOc = $this->pdo->query("DESCRIBE erp_transferencias_ocorrencias")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('foto', $colsOc)) {
                $this->pdo->exec("ALTER TABLE erp_transferencias_ocorrencias ADD COLUMN foto TEXT DEFAULT NULL");
            } else {
                // Se já existe, garante que seja TEXT para suportar JSON de múltiplas fotos
                $this->pdo->exec("ALTER TABLE erp_transferencias_ocorrencias MODIFY COLUMN foto TEXT DEFAULT NULL");
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
                    problema_resolvido TINYINT DEFAULT 0,
                    idempotency_key VARCHAR(80) NULL DEFAULT NULL,
                    UNIQUE KEY idx_erp_transferencias_idempotency_key (idempotency_key)
                )
            ");
        }

        // 2. Criar tabelas auxiliares se não existirem
        $this->ensureTransferIdempotencyIndex();

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
                foto TEXT DEFAULT NULL,
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
        $this->pdo->exec("INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
                          SELECT p.id, p.filial_id, p.quantidade, p.estoque_minimo
                          FROM produtos p
                          JOIN filiais f ON f.id = p.filial_id
                          WHERE p.filial_id IS NOT NULL AND p.filial_id > 0");
    }

    private function ensureTransferIdempotencyIndex(): void {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*)
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'erp_transferencias'
                   AND INDEX_NAME = 'idx_erp_transferencias_idempotency_key'"
            );
            $stmt->execute();

            if ((int)$stmt->fetchColumn() === 0) {
                $this->pdo->exec("CREATE UNIQUE INDEX idx_erp_transferencias_idempotency_key ON erp_transferencias (idempotency_key)");
            }
        } catch (\Throwable $e) {
            error_log("Erro ao garantir idempotencia de transferencias: " . $e->getMessage());
        }
    }

    private function normalizeTransferIdempotencyKey($key): ?string {
        $key = trim((string)$key);
        if ($key === '') {
            return null;
        }

        $key = preg_replace('/[^A-Za-z0-9:_-]/', '', $key);
        return $key === '' ? null : substr($key, 0, 80);
    }

    private function normalizeTransferItems(array $itens): array {
        $normalizados = [];

        foreach ($itens as $item) {
            if (empty($item['selecionado'])) {
                continue;
            }

            $produtoId = (int)($item['produto_id'] ?? 0);
            $quantidade = (float)str_replace(',', '.', (string)($item['quantidade'] ?? 0));

            if ($produtoId <= 0 || $quantidade <= 0) {
                continue;
            }

            if (!isset($normalizados[$produtoId])) {
                $normalizados[$produtoId] = [
                    'produto_id' => $produtoId,
                    'quantidade' => 0.0,
                ];
            }

            $normalizados[$produtoId]['quantidade'] += $quantidade;
        }

        return array_values($normalizados);
    }

    private function mergeDuplicateTransferItems(int $transferenciaId): void {
        if ($transferenciaId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            "SELECT
                produto_id,
                MIN(id) as keep_id,
                SUM(quantidade_solicitada) as quantidade_solicitada,
                SUM(quantidade_enviada) as quantidade_enviada,
                SUM(quantidade_recebida) as quantidade_recebida,
                MAX(valor_custo_unitario) as valor_custo_unitario,
                COUNT(*) as total_linhas
             FROM erp_transferencias_itens
             WHERE transferencia_id = ?
             GROUP BY produto_id
             HAVING COUNT(*) > 1"
        );
        $stmt->execute([$transferenciaId]);
        $duplicados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($duplicados)) {
            return;
        }

        $stmtUpdate = $this->pdo->prepare(
            "UPDATE erp_transferencias_itens
             SET quantidade_solicitada = ?,
                 quantidade_enviada = ?,
                 quantidade_recebida = ?,
                 valor_custo_unitario = ?
             WHERE id = ?"
        );
        $stmtDelete = $this->pdo->prepare(
            "DELETE FROM erp_transferencias_itens
             WHERE transferencia_id = ? AND produto_id = ? AND id <> ?"
        );

        foreach ($duplicados as $dup) {
            $stmtUpdate->execute([
                $dup['quantidade_solicitada'],
                $dup['quantidade_enviada'],
                $dup['quantidade_recebida'],
                $dup['valor_custo_unitario'],
                $dup['keep_id'],
            ]);
            $stmtDelete->execute([$transferenciaId, $dup['produto_id'], $dup['keep_id']]);
        }
    }

    private function atualizarValorTotalCusto(int $transferenciaId, string $quantidadeCol = 'quantidade_enviada'): void {
        if ($transferenciaId <= 0) {
            return;
        }

        $colunasPermitidas = ['quantidade_solicitada', 'quantidade_enviada', 'quantidade_recebida'];
        if (!in_array($quantidadeCol, $colunasPermitidas, true)) {
            $quantidadeCol = 'quantidade_enviada';
        }

        $stmt = $this->pdo->prepare(
            "UPDATE erp_transferencias
             SET valor_total_custo = (
                 SELECT COALESCE(SUM({$quantidadeCol} * valor_custo_unitario), 0)
                 FROM erp_transferencias_itens
                 WHERE transferencia_id = ?
             )
             WHERE id = ?"
        );
        $stmt->execute([$transferenciaId, $transferenciaId]);
    }

    private function nomeFilial(int $filialId): string {
        try {
            $stmt = $this->pdo->prepare("SELECT nome FROM filiais WHERE id = ? LIMIT 1");
            $stmt->execute([$filialId]);
            return (string)($stmt->fetchColumn() ?: "Filial #{$filialId}");
        } catch (\Throwable $e) {
            return "Filial #{$filialId}";
        }
    }

    private function filialUsaEstoqueLegadoGlobal(int $filialId): bool {
        if ($filialId <= 0) {
            return false;
        }

        if ((int)$filialId === (int)$this->matrizId) {
            return true;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT nome FROM filiais WHERE id = ? LIMIT 1");
            $stmt->execute([$filialId]);
            $nomeOriginal = (string)($stmt->fetchColumn() ?: '');
            $nome = function_exists('mb_strtolower')
                ? mb_strtolower($nomeOriginal, 'UTF-8')
                : strtolower($nomeOriginal);

            return str_contains($nome, 'deposito')
                || (str_contains($nome, 'dep') && str_contains($nome, 'sito'))
                || str_contains($nome, 'logistica')
                || (str_contains($nome, 'log') && str_contains($nome, 'stica'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function sincronizarEstoqueLegadoDaFilial(int $filialId): void {
        if (!$this->filialUsaEstoqueLegadoGlobal($filialId)) {
            return;
        }

        $isMatriz = ((int)$filialId === (int)$this->matrizId) ? 1 : 0;

        try {
            $stmtIns = $this->pdo->prepare(
                "INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
                 SELECT p.id, ?, p.quantidade, p.estoque_minimo
                 FROM produtos p
                 WHERE p.quantidade > 0
                   AND (? = 1 OR p.filial_id IS NULL OR p.filial_id = ?)"
            );
            $stmtIns->execute([$filialId, $isMatriz, $filialId]);

            $stmtFix = $this->pdo->prepare(
                "UPDATE estoque_filiais ef
                 JOIN produtos p ON p.id = ef.produto_id
                 SET ef.quantidade = p.quantidade,
                     ef.estoque_minimo = COALESCE(NULLIF(ef.estoque_minimo, 0), p.estoque_minimo)
                 WHERE ef.filial_id = ?
                   AND ef.quantidade <= 0
                   AND p.quantidade > 0
                   AND (? = 1 OR p.filial_id IS NULL OR p.filial_id = ?)"
            );
            $stmtFix->execute([$filialId, $isMatriz, $filialId]);
        } catch (\Throwable $e) {
            error_log("Erro ao sincronizar estoque legado da filial {$filialId}: " . $e->getMessage());
        }
    }

    private function sincronizarProdutoLegadoComEstoqueFilial(int $produtoId, int $filialId): void {
        if ($produtoId <= 0 || $filialId <= 0) {
            return;
        }

        $isMatriz = ((int)$filialId === (int)$this->matrizId) ? 1 : 0;
        $usaLegadoGlobal = $this->filialUsaEstoqueLegadoGlobal($filialId) ? 1 : 0;

        try {
            $stmtSync = $this->pdo->prepare(
                "UPDATE produtos p
                 JOIN estoque_filiais ef ON ef.produto_id = p.id AND ef.filial_id = ?
                 SET p.quantidade = ef.quantidade
                 WHERE p.id = ?
                   AND (
                        p.filial_id = ?
                        OR ? = 1
                        OR (? = 1 AND p.filial_id IS NULL)
                   )"
            );
            $stmtSync->execute([$filialId, $produtoId, $filialId, $isMatriz, $usaLegadoGlobal]);
        } catch (\Throwable $e) {
            error_log("Erro ao sincronizar produto legado {$produtoId} na filial {$filialId}: " . $e->getMessage());
        }
    }

    private function garantirLinhaEstoqueFilial(int $produtoId, int $filialId): void {
        $stmt = $this->pdo->prepare("SELECT id FROM estoque_filiais WHERE produto_id = ? AND filial_id = ? LIMIT 1");
        $stmt->execute([$produtoId, $filialId]);
        if ($stmt->fetchColumn()) {
            if ($this->filialUsaEstoqueLegadoGlobal($filialId)) {
                $isMatriz = ((int)$filialId === (int)$this->matrizId) ? 1 : 0;
                $stmtFix = $this->pdo->prepare(
                    "UPDATE estoque_filiais ef
                     JOIN produtos p ON p.id = ef.produto_id
                     SET ef.quantidade = p.quantidade,
                         ef.estoque_minimo = COALESCE(NULLIF(ef.estoque_minimo, 0), p.estoque_minimo)
                     WHERE ef.produto_id = ?
                       AND ef.filial_id = ?
                       AND ef.quantidade <= 0
                       AND p.quantidade > 0
                       AND (? = 1 OR p.filial_id IS NULL OR p.filial_id = ?)"
                );
                $stmtFix->execute([$produtoId, $filialId, $isMatriz, $filialId]);
            }
            return;
        }

        $stmtIns = $this->pdo->prepare(
            "INSERT IGNORE INTO estoque_filiais (produto_id, filial_id, quantidade, estoque_minimo)
             SELECT
                id,
                ?,
                CASE
                    WHEN ? = 1 OR filial_id = ? OR (? = 1 AND filial_id IS NULL) THEN quantidade
                    ELSE 0
                END,
                estoque_minimo
             FROM produtos
             WHERE id = ?"
        );
        $isMatriz = ((int)$filialId === (int)$this->matrizId) ? 1 : 0;
        $usaLegadoGlobal = $this->filialUsaEstoqueLegadoGlobal($filialId) ? 1 : 0;
        $stmtIns->execute([$filialId, $isMatriz, $filialId, $usaLegadoGlobal, $produtoId]);
    }

    private function baixarEstoqueTransferencia(int $produtoId, int $filialId, float $quantidade): void {
        if ($produtoId <= 0 || $filialId <= 0 || $quantidade <= 0) {
            throw new \Exception("Dados invalidos para baixar estoque da transferencia.");
        }

        $this->garantirLinhaEstoqueFilial($produtoId, $filialId);

        $stmt = $this->pdo->prepare(
            "UPDATE estoque_filiais
             SET quantidade = quantidade - ?
             WHERE produto_id = ? AND filial_id = ? AND quantidade >= ?"
        );
        $stmt->execute([$quantidade, $produtoId, $filialId, $quantidade]);

        if ($stmt->rowCount() === 0) {
            $stmtInfo = $this->pdo->prepare(
                "SELECT p.nome, COALESCE(ef.quantidade, 0) as estoque_atual
                 FROM produtos p
                 LEFT JOIN estoque_filiais ef ON ef.produto_id = p.id AND ef.filial_id = ?
                 WHERE p.id = ?"
            );
            $stmtInfo->execute([$filialId, $produtoId]);
            $info = $stmtInfo->fetch(\PDO::FETCH_ASSOC) ?: [];
            $nomeProd = $info['nome'] ?? "Produto ID {$produtoId}";
            $disponivel = (float)($info['estoque_atual'] ?? 0);
            $nomeFilial = $this->nomeFilial($filialId);

            throw new \Exception("Estoque insuficiente em {$nomeFilial} para '{$nomeProd}'. Disponivel: {$disponivel}, Tentado: {$quantidade}");
        }

        $this->sincronizarProdutoLegadoComEstoqueFilial($produtoId, $filialId);
    }

    private function findTransferByIdempotencyKey(?string $key): ?array {
        if (!$key) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM erp_transferencias WHERE idempotency_key = ? LIMIT 1");
            $stmt->execute([$key]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function redirectDuplicateTransferSubmit(?string $key, string $aba): void {
        if ($this->findTransferByIdempotencyKey($key)) {
            setFlash('success', 'Transferencia ja processada. O envio duplicado foi bloqueado.');
            $this->redirect('transferencias.php?aba=' . $aba);
        }
    }

    private function isDuplicateIdempotencyError(\Throwable $e): bool {
        return str_contains($e->getMessage(), 'Duplicate entry')
            || str_contains($e->getMessage(), 'idempotency_key')
            || str_contains($e->getMessage(), 'idx_erp_transferencias_idempotency_key');
    }

    public function index() {
        $aba = $_GET['aba'] ?? ($this->isMatriz ? 'recebidas' : 'nova_solicitacao');
        $mid = $this->matrizId;

        // Produtos do catálogo da Matriz (com estoque específico da filial-matriz)
        $origemEstoqueId = $this->isMatriz ? $mid : (int)$this->filialLogada;
        $this->sincronizarEstoqueLegadoDaFilial((int)$origemEstoqueId);
        $origemEhMatriz = ((int)$origemEstoqueId === (int)$mid) ? 1 : 0;
        $origemUsaEstoqueLegado = $this->filialUsaEstoqueLegadoGlobal((int)$origemEstoqueId) ? 1 : 0;

        try {
            $stmtProdutos = $this->pdo->prepare(
                "SELECT
                    p.*,
                    CASE
                        WHEN ef.id IS NOT NULL THEN ef.quantidade
                        WHEN ? = 1 OR p.filial_id = ? OR (? = 1 AND p.filial_id IS NULL) THEN p.quantidade
                        ELSE 0
                    END as qtd_matriz
                 FROM produtos p
                 LEFT JOIN estoque_filiais ef ON ef.produto_id = p.id AND ef.filial_id = ?
                 ORDER BY p.nome"
            );
            $stmtProdutos->execute([$origemEhMatriz, $origemEstoqueId, $origemUsaEstoqueLegado, $origemEstoqueId]);
            $produtosMatriz = $stmtProdutos->fetchAll();
        } catch (\Exception $e) {
            $produtosMatriz = $this->pdo->query("SELECT *, quantidade as qtd_matriz FROM produtos ORDER BY nome")->fetchAll();
        }

        // Filiais de destino (exceto a própria Matriz)
        $stmtFiliais = $this->pdo->prepare("SELECT * FROM filiais ORDER BY principal DESC, nome");
        $stmtFiliais->execute();
        $filiais = $stmtFiliais->fetchAll();

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

        $unidadeRecebimento = $this->isMatriz ? $mid : (int)$this->filialLogada;
        $stmt = $this->pdo->prepare(
            "SELECT t.*, COALESCE(f.nome, 'Matriz') as nome_filial, COALESCE(u.nome, 'Sistema') as usuario_nome
             FROM erp_transferencias t
             LEFT JOIN filiais f ON t.origem_filial_id = f.id
             LEFT JOIN usuarios u ON u.id = t.usuario_id
             WHERE t.destino_filial_id = ? AND t.status = 'em_transito'
             ORDER BY t.data_envio DESC"
        );
        $stmt->execute([$unidadeRecebimento]);
        $em_transito = $stmt->fetchAll();

        if ($this->isMatriz) {
            // Solicitações pendentes recebidas das filiais
            $recebidas = $this->pdo->query(
                "SELECT t.*, f.nome as nome_filial, COALESCE(u.nome, 'Sistema') as usuario_nome
                 FROM erp_transferencias t
                 LEFT JOIN filiais f ON t.destino_filial_id = f.id
                 LEFT JOIN usuarios u ON u.id = t.usuario_id
                 WHERE t.tipo = 'solicitacao' AND t.status = 'pendente'
                 ORDER BY t.data_solicitacao DESC"
            )->fetchAll();

            // Histórico de tudo que a Matriz já enviou (COM FILTRO)
            $sqlH = "
                SELECT
                    t.*,
                    CASE
                        WHEN t.origem_filial_id = ? THEN COALESCE(f_destino.nome, 'Matriz')
                        ELSE COALESCE(f_origem.nome, 'Matriz')
                    END as nome_filial,
                    CASE
                        WHEN t.tipo = 'solicitacao' AND t.status = 'pendente' THEN 'Solicitacao'
                        WHEN t.origem_filial_id = ? THEN 'Enviado para'
                        ELSE 'Recebido de'
                    END as tipo_movimento,
                    COALESCE(t.data_envio, t.data_solicitacao, t.data_recebimento) as data_movimento,
                    COALESCE(u.nome, 'Sistema') as usuario_nome
                FROM erp_transferencias t
                LEFT JOIN filiais f_origem ON t.origem_filial_id = f_origem.id
                LEFT JOIN filiais f_destino ON t.destino_filial_id = f_destino.id
                LEFT JOIN usuarios u ON u.id = t.usuario_id
                WHERE (t.origem_filial_id = ? OR t.destino_filial_id = ?)
            ";
            $paramsH = [$mid, $mid, $mid, $mid];

            if ($fCodigo) { $sqlH .= " AND t.codigo_transferencia LIKE ?"; $paramsH[] = "%$fCodigo%"; }
            if ($fStatus) { $sqlH .= " AND t.status = ?"; $paramsH[] = $fStatus; }
            if ($fInicio) { $sqlH .= " AND DATE(COALESCE(t.data_envio, t.data_solicitacao, t.data_recebimento)) >= ?"; $paramsH[] = $fInicio; }
            if ($fFim)    { $sqlH .= " AND DATE(COALESCE(t.data_envio, t.data_solicitacao, t.data_recebimento)) <= ?"; $paramsH[] = $fFim; }

            $sqlH .= " ORDER BY COALESCE(t.data_envio, t.data_solicitacao, t.data_recebimento) DESC LIMIT 100";
            $stmtH = $this->pdo->prepare($sqlH);
            $stmtH->execute($paramsH);
            $historico_envios = $stmtH->fetchAll();

            // Conta problemas pendentes
            $stmtProblemas = $this->pdo->prepare(
                "SELECT COUNT(*) FROM erp_transferencias WHERE (origem_filial_id = ? OR destino_filial_id = ?) AND tem_problema = 1 AND problema_resolvido = 0"
            );
            $stmtProblemas->execute([$mid, $mid]);
            $problemas_pendentes = $stmtProblemas->fetchColumn();

        } else {
            // Em Trânsito: o que foi despachado pela Matriz para ESTA filial
            $stmt = $this->pdo->prepare(
                "SELECT t.*, COALESCE(f.nome, 'Matriz') as nome_filial, COALESCE(u.nome, 'Sistema') as usuario_nome
                 FROM erp_transferencias t
                 LEFT JOIN filiais f ON t.origem_filial_id = f.id
                 LEFT JOIN usuarios u ON u.id = t.usuario_id
                 WHERE t.destino_filial_id = ? AND t.status = 'em_transito'
                 ORDER BY t.data_envio DESC"
            );
            $stmt->execute([$this->filialLogada]);
            $em_transito = $stmt->fetchAll();

            // Histórico completo desta filial (COM FILTRO)
            $sqlF = "SELECT t.*, COALESCE(f.nome, 'Matriz') as nome_filial, COALESCE(u.nome, 'Sistema') as usuario_nome FROM erp_transferencias t LEFT JOIN filiais f ON t.origem_filial_id = f.id LEFT JOIN usuarios u ON u.id = t.usuario_id WHERE (t.destino_filial_id = ? OR t.origem_filial_id = ? OR t.usuario_id = ?)";
            $paramsF = [$this->filialLogada, $this->filialLogada, $_SESSION['usuario_id'] ?? 0];

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
        $destino_id = (int)($_POST['destino_filial_id'] ?? $this->filialLogada);
        $mid = $this->matrizId;
        $idempotencyKey = $this->normalizeTransferIdempotencyKey($_POST['idempotency_key'] ?? null);

        $itensValidos = $this->normalizeTransferItems($itens);

        if ($destino_id === 0 || $destino_id === (int)$this->filialLogada) {
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('Selecione uma unidade diferente da unidade atual.'));
        }

        if (count($itensValidos) === 0) {
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('Selecione ao menos um produto com quantidade válida.'));
        }

        $this->redirectDuplicateTransferSubmit($idempotencyKey, 'historico_recebimentos');

        try {
            $this->pdo->beginTransaction();
            $codigo = 'REQ-' . date('YmdHis') . '-' . rand(100, 999);

            // origem = Matriz (CD que vai enviar), destino = esta filial
            $stmt = $this->pdo->prepare(
                "INSERT INTO erp_transferencias
                    (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, idempotency_key)
                 VALUES (?, 'solicitacao', ?, ?, 'pendente', ?, ?, ?)"
            );
            $stmt->execute([$codigo, $mid, $destino_id, $observacoes, $_SESSION['usuario_id'] ?? 0, $idempotencyKey]);
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

            $this->atualizarValorTotalCusto((int)$transf_id, 'quantidade_solicitada');

            $this->pdo->commit();
            setFlash('success', 'Solicitação enviada para a Matriz com sucesso!');
            $this->redirect('transferencias.php?aba=historico_recebimentos');
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($idempotencyKey && $this->isDuplicateIdempotencyError($e)) {
                $this->redirectDuplicateTransferSubmit($idempotencyKey, 'historico_recebimentos');
            }
            $this->redirect('transferencias.php?aba=nova_solicitacao&erro=' . urlencode('Erro: ' . $e->getMessage()));
        }
    }

    public function novaTransferencia() {
        $itens = $_POST['itens'] ?? [];
        $observacoes = trim($_POST['observacoes'] ?? '');
        $destino_id = (int)($_POST['destino_filial_id'] ?? 0);
        $mid = $this->matrizId;
        $origem_id = $this->isMatriz ? $mid : (int)$this->filialLogada;
        $idempotencyKey = $this->normalizeTransferIdempotencyKey($_POST['idempotency_key'] ?? null);
        $redirectAba = $this->isMatriz ? 'historico_envios' : 'historico_recebimentos';

        $itensValidos = $this->normalizeTransferItems($itens);

        if (count($itensValidos) === 0 || $destino_id === 0) {
            $this->redirect('transferencias.php?aba=nova_transferencia&erro=' . urlencode('Selecione a filial destino e os produtos.'));
        }

        if ($destino_id === $origem_id) {
            $this->redirect('transferencias.php?aba=nova_transferencia&erro=' . urlencode('A origem e o destino não podem ser a mesma unidade.'));
        }

        $this->redirectDuplicateTransferSubmit($idempotencyKey, $redirectAba);

        try {
            $this->pdo->beginTransaction();
            $codigo = 'ENV-' . date('YmdHis') . '-' . rand(100, 999);

            $stmt = $this->pdo->prepare(
                "INSERT INTO erp_transferencias
                    (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, data_envio, idempotency_key)
                 VALUES (?, 'transferencia', ?, ?, 'em_transito', ?, ?, NOW(), ?)"
            );
            $stmt->execute([$codigo, $origem_id, $destino_id, $observacoes, $_SESSION['usuario_id'] ?? 0, $idempotencyKey]);
            $transf_id = $this->pdo->lastInsertId();

            $stmtItem    = $this->pdo->prepare(
                "INSERT INTO erp_transferencias_itens
                    (transferencia_id, produto_id, quantidade_solicitada, quantidade_enviada, valor_custo_unitario)
                 VALUES (?, ?, ?, ?, ?)"
            );
            // Query para verificar estoque atual
            $stmtCheck = $this->pdo->prepare("
                SELECT
                    p.nome,
                    CASE
                        WHEN ef.id IS NOT NULL THEN ef.quantidade
                        WHEN ? = 1 OR p.filial_id = ? OR (? = 1 AND p.filial_id IS NULL) THEN p.quantidade
                        ELSE 0
                    END as estoque_atual
                FROM produtos p
                LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                WHERE p.id = ?
            ");

            $origemEhMatriz = ((int)$origem_id === (int)$mid) ? 1 : 0;
            $origemUsaEstoqueLegado = $this->filialUsaEstoqueLegadoGlobal((int)$origem_id) ? 1 : 0;
            $totalItensValidos = 0;
            foreach ($itensValidos as $item) {
                $pid = $item['produto_id'];
                $qtd = (float)$item['quantidade'];

                if ($qtd <= 0) {
                    throw new \Exception("Erro: Não é permitido despachar itens com quantidade zero ou negativa na transferência manual.");
                }

                // 1. Validação de Estoque (Servidor)
                $this->garantirLinhaEstoqueFilial((int)$pid, (int)$origem_id);
                $stmtCheck->execute([$origemEhMatriz, $origem_id, $origemUsaEstoqueLegado, $origem_id, $pid]);
                $estoque = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
                
                if (!$estoque || $estoque['estoque_atual'] < $qtd) {
                    $nomeProd = $estoque ? $estoque['nome'] : "Produto ID $pid";
                    $disponivel = $estoque ? (float)$estoque['estoque_atual'] : 0;
                    $nomeOrigem = $this->nomeFilial((int)$origem_id);
                    throw new \Exception("Estoque insuficiente em {$nomeOrigem} para '{$nomeProd}'. Disponível: {$disponivel}, Tentado: {$qtd}");
                }

                $pd = $this->pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                $pd->execute([$pid]);
                $custo = $pd->fetchColumn() ?: 0;
                
                $stmtItem->execute([$transf_id, $pid, $qtd, $qtd, $custo]);
                $this->baixarEstoqueTransferencia((int)$pid, (int)$origem_id, (float)$qtd);
                $totalItensValidos++;
            }

            if ($totalItensValidos === 0) {
                throw new \Exception("Nenhum item com quantidade válida para despacho.");
            }

            $this->atualizarValorTotalCusto((int)$transf_id, 'quantidade_enviada');

            $this->pdo->commit();
            setFlash('success', 'Transferência despachada com sucesso!');
            $this->redirect('transferencias.php?aba=' . $redirectAba);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($idempotencyKey && $this->isDuplicateIdempotencyError($e)) {
                $this->redirectDuplicateTransferSubmit($idempotencyKey, $redirectAba);
            }
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
                 WHERE id = ? AND origem_filial_id = ? AND status = 'pendente'"
            );
            $stmt->execute([$transf_id, $mid]);

            if ($stmt->rowCount() === 0) {
                throw new \Exception("Transferência não encontrada ou não pertence à Matriz.");
            }

            $stmtItem    = $this->pdo->prepare("UPDATE erp_transferencias_itens SET quantidade_enviada = ? WHERE transferencia_id = ? AND produto_id = ?");
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
                        $nomeOrigem = $this->nomeFilial((int)$mid);
                        throw new \Exception("Estoque insuficiente em {$nomeOrigem} para '{$nomeProd}'. Disponível: {$disponivel}, Tentado: {$qtd}");
                    }

                    // 2. Processamento (Atualiza estoque_filiais E produtos, pois a origem é a Matriz)
                    $stmtItem->execute([$qtd, $transf_id, $produto_id]);
                    $this->baixarEstoqueTransferencia((int)$produto_id, (int)$mid, (float)$qtd);
                    $totalQtdFinal += $qtd;
                } elseif ($qtd < 0) {
                    throw new \Exception("Quantidade negativa não é permitida.");
                }
            }

            if ($totalQtdFinal <= 0) {
                throw new \Exception("Erro: Não é possível despachar uma solicitação com quantidade total zero. Verifique o estoque da Matriz.");
            }

            $this->atualizarValorTotalCusto((int)$transf_id, 'quantidade_enviada');

            $this->pdo->commit();
            setFlash('success', 'Solicitação aprovada and despachada com sucesso!');
            $this->redirect('transferencias.php?aba=recebidas');
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=recebidas&erro=' . urlencode('Erro: ' . $e->getMessage()));
        }
    }

    public function confirmarRecebimento() {

        $transf_id = (int)($_POST['transferencia_id'] ?? 0);
        $unidadeRecebimento = $this->isMatriz ? (int)$this->matrizId : (int)$this->filialLogada;

        try {
            $this->pdo->beginTransaction();

            $check = $this->pdo->prepare(
                "SELECT * FROM erp_transferencias WHERE id = ? AND destino_filial_id = ? AND status = 'em_transito' FOR UPDATE"
            );
            $check->execute([$transf_id, $unidadeRecebimento]);
            if (!$check->fetch()) throw new \Exception("Requisição inválida ou já processada.");

            $stmtConcluir = $this->pdo->prepare(
                "UPDATE erp_transferencias SET status = 'concluida', data_recebimento = NOW() WHERE id = ? AND destino_filial_id = ? AND status = 'em_transito'"
            );
            $stmtConcluir->execute([$transf_id, $unidadeRecebimento]);

            if ($stmtConcluir->rowCount() === 0) {
                throw new \Exception("Requisicao ja processada por outra operacao.");
            }

            $itens = $this->pdo->prepare(
                "SELECT produto_id, SUM(quantidade_enviada) as quantidade_enviada
                 FROM erp_transferencias_itens
                 WHERE transferencia_id = ?
                 GROUP BY produto_id"
            );
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
                            $stmtInc->execute([$unidadeRecebimento, $qtdFinal, $pid, $qtdFinal]);
                            $this->sincronizarProdutoLegadoComEstoqueFilial((int)$pid, (int)$unidadeRecebimento);
                        } catch (\Exception $ex) {
                            error_log("Erro ao atualizar estoque_filiais: " . $ex->getMessage());
                            throw new \Exception("Erro ao internalizar produto ID $pid: " . $ex->getMessage());
                        }
                    }
                }
            }

            $this->pdo->commit();
            setFlash('success', 'Estoque internalizado com sucesso!');
            $this->redirect('transferencias.php?aba=' . ($this->isMatriz ? 'em_transito' : 'historico_recebimentos'));
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->redirect('transferencias.php?aba=em_transito&erro=' . urlencode('Falha na Internalização: ' . $e->getMessage()));
        }
    }

    public function relatarProblema() {

        $transf_id = (int)($_POST['transferencia_id'] ?? 0);
        $mensagem  = trim($_POST['mensagem'] ?? '');
        $itens_problema = $_POST['ocorrencias'] ?? [];
        $unidadeRecebimento = $this->isMatriz ? (int)$this->matrizId : (int)$this->filialLogada;

        $stmtQtdEnviada = $this->pdo->prepare(
            "SELECT produto_id, SUM(quantidade_enviada) as quantidade_enviada
             FROM erp_transferencias_itens
             WHERE transferencia_id = ?
             GROUP BY produto_id"
        );
        $stmtQtdEnviada->execute([$transf_id]);
        $quantidadesEnviadas = [];
        foreach ($stmtQtdEnviada->fetchAll(\PDO::FETCH_ASSOC) as $itemQtd) {
            $quantidadesEnviadas[(int)$itemQtd['produto_id']] = (float)$itemQtd['quantidade_enviada'];
        }

        $itensValidos = [];
        foreach ($itens_problema as $produto_id => $oc) {
            if (empty($oc['selecionado'])) {
                continue;
            }

            $pid = (int)$produto_id;
            $qtdEnviada = $quantidadesEnviadas[$pid] ?? 0.0;
            if ($pid <= 0 || $qtdEnviada <= 0) {
                continue;
            }

            $qtdInformada = (float)str_replace(',', '.', (string)($oc['quantidade'] ?? 0));
            $qtdProblema = $qtdInformada > 0 ? $qtdInformada : $qtdEnviada;
            if ($qtdProblema > $qtdEnviada) {
                $qtdProblema = $qtdEnviada;
            }

            $itensValidos[$pid] = [
                'quantidade' => $qtdProblema,
                'motivo' => $oc['motivo'] ?? 'faltante',
                'descricao' => $oc['descricao'] ?? '',
            ];
        }

        if (empty($itensValidos) && empty($mensagem)) {
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
            $stmt->execute([$mensagem, $transf_id, $unidadeRecebimento]);
            if ($stmt->rowCount() === 0) {
                throw new \Exception("Transferencia invalida para relatar problema.");
            }

            // 2. Registra ocorrências por item
            $stmtDelOc = $this->pdo->prepare(
                "DELETE FROM erp_transferencias_ocorrencias WHERE transferencia_id = ? AND produto_id = ?"
            );
            $stmtOc = $this->pdo->prepare(
                "INSERT INTO erp_transferencias_ocorrencias (transferencia_id, produto_id, quantidade_problema, motivo, descricao, foto)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            // Garante pasta de upload
            $uploadDir = 'public/uploads/problemas/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            foreach ($itensValidos as $produto_id => $oc) {
                    $fotosArray = [];
                    
                    // Tenta capturar fotos (múltiplas) deste produto
                    // Como usamos name="ocorrencias_ID_fotos[]", o PHP agrupa no $_FILES
                    $fileKey = "ocorrencias_{$produto_id}_fotos";
                    
                    if (isset($_FILES[$fileKey]) && is_array($_FILES[$fileKey]['name'])) {
                        foreach ($_FILES[$fileKey]['name'] as $idx => $name) {
                            if ($_FILES[$fileKey]['error'][$idx] === UPLOAD_ERR_OK) {
                                $ext = pathinfo($name, PATHINFO_EXTENSION);
                                $newName = "prob_" . $transf_id . "_" . $produto_id . "_" . time() . "_" . $idx . "." . $ext;
                                $dest = $uploadDir . $newName;
                                
                                if (move_uploaded_file($_FILES[$fileKey]['tmp_name'][$idx], $dest)) {
                                    $fotosArray[] = $dest;
                                }
                            }
                        }
                    }

                    $stmtDelOc->execute([$transf_id, $produto_id]);
                    $stmtOc->execute([
                        $transf_id, 
                        $produto_id, 
                        $oc['quantidade'], 
                        $oc['motivo'] ?? 'faltante', 
                        $oc['descricao'] ?? '',
                        !empty($fotosArray) ? json_encode($fotosArray) : null
                    ]);
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
            SELECT t.*, f_origem.nome as nome_origem, f_destino.nome as nome_destino, COALESCE(u.nome, 'Sistema') as usuario_nome
            FROM erp_transferencias t
            LEFT JOIN filiais f_origem ON t.origem_filial_id = f_origem.id
            LEFT JOIN filiais f_destino ON t.destino_filial_id = f_destino.id
            LEFT JOIN usuarios u ON u.id = t.usuario_id
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
        $sqlItems = "SELECT
                MIN(ti.id) as id,
                ti.transferencia_id,
                ti.produto_id,
                SUM(ti.quantidade_solicitada) as quantidade_solicitada,
                SUM(ti.quantidade_enviada) as quantidade_enviada,
                SUM(ti.quantidade_recebida) as quantidade_recebida,
                MAX(ti.valor_custo_unitario) as valor_custo_unitario,
                p.nome, p.codigo, COALESCE(NULLIF(p.unidade, ''), 'UN') as unidade,
                CASE
                    WHEN ef.id IS NOT NULL THEN ef.quantidade
                    WHEN t.origem_filial_id = ?
                        OR p.filial_id = t.origem_filial_id
                        OR (
                            p.filial_id IS NULL
                            AND (
                                LOWER(f_origem_estoque.nome) LIKE '%deposito%'
                                OR (LOWER(f_origem_estoque.nome) LIKE '%dep%' AND LOWER(f_origem_estoque.nome) LIKE '%sito%')
                                OR LOWER(f_origem_estoque.nome) LIKE '%logistica%'
                                OR (LOWER(f_origem_estoque.nome) LIKE '%log%' AND LOWER(f_origem_estoque.nome) LIKE '%stica%')
                            )
                        )
                    THEN p.quantidade
                    ELSE 0
                END as disp_matriz,
                COALESCE((SELECT SUM(quantidade_problema) FROM erp_transferencias_ocorrencias WHERE transferencia_id = ti.transferencia_id AND produto_id = ti.produto_id), 0) as quantidade_problema
                FROM erp_transferencias_itens ti 
                JOIN erp_transferencias t ON t.id = ti.transferencia_id
                JOIN produtos p ON ti.produto_id = p.id 
                LEFT JOIN filiais f_origem_estoque ON f_origem_estoque.id = t.origem_filial_id
                LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = t.origem_filial_id
                WHERE ti.transferencia_id = ?
                GROUP BY ti.transferencia_id, ti.produto_id, p.nome, p.codigo, p.unidade, ef.id, ef.quantidade, p.quantidade, p.filial_id, t.origem_filial_id, f_origem_estoque.nome";
        
        $stmtI = $this->pdo->prepare($sqlItems);
        $stmtI->execute([$this->matrizId, $transf_id]);
        $items = $stmtI->fetchAll(\PDO::FETCH_ASSOC);

        // 3. Ocorrências detalhadas
        $stmtO = $this->pdo->prepare("
            SELECT
                MIN(oc.id) as id,
                oc.transferencia_id,
                oc.produto_id,
                SUM(oc.quantidade_problema) as quantidade_problema,
                GROUP_CONCAT(oc.motivo ORDER BY oc.id SEPARATOR ', ') as motivo,
                GROUP_CONCAT(NULLIF(oc.descricao, '') ORDER BY oc.id SEPARATOR ' | ') as descricao,
                MAX(oc.foto) as foto,
                MIN(oc.data_registro) as data_registro,
                p.nome, p.codigo, COALESCE(NULLIF(p.unidade, ''), 'UN') as unidade
            FROM erp_transferencias_ocorrencias oc
            JOIN produtos p ON oc.produto_id = p.id
            WHERE oc.transferencia_id = ?
            GROUP BY oc.transferencia_id, oc.produto_id, p.nome, p.codigo, p.unidade
        ");
        $stmtO->execute([$transf_id]);
        $ocorrencias = $stmtO->fetchAll(\PDO::FETCH_ASSOC);

        // Apenas o remetente pode resolver
        $canResolve = ((int)$this->filialLogada === (int)$transfer['origem_filial_id']);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'transfer' => $transfer,
            'items' => $items,
            'ocorrencias' => $ocorrencias,
            'isMatriz' => $this->isMatriz,
            'canResolve' => $canResolve
        ]);
        exit;
    }

    public function resolverProblema() {
        $transf_id = (int)($_POST['transferencia_id'] ?? 0);
        $fluxo     = $_POST['fluxo'] ?? 'resolver'; // 'resolver' ou 'repor'

        // 1. Busca dados da transferência original
        $orig = $this->pdo->prepare("SELECT origem_filial_id, destino_filial_id, observacoes FROM erp_transferencias WHERE id = ?");
        $orig->execute([$transf_id]);
        $dadosOrig = $orig->fetch(\PDO::FETCH_ASSOC);

        if (!$dadosOrig) {
            $this->redirect('transferencias.php?erro=' . urlencode('Transferência não encontrada.'));
        }

        // Apenas a filial de origem (quem enviou) pode resolver o problema!
        if ((int)$this->filialLogada !== (int)$dadosOrig['origem_filial_id']) {
            $redirectTab = $this->isMatriz ? 'historico_envios' : 'historico_recebimentos';
            $this->redirect('transferencias.php?aba=' . $redirectTab . '&erro=' . urlencode('Apenas a unidade que enviou os produtos pode resolver esta ocorrência.'));
        }

        try {
            $this->pdo->beginTransaction();

            // 1. Marca como resolvido no mestre original
            $stmt = $this->pdo->prepare(
                "UPDATE erp_transferencias SET problema_resolvido = 1 WHERE id = ? AND origem_filial_id = ? AND problema_resolvido = 0"
            );
            $stmt->execute([$transf_id, $dadosOrig['origem_filial_id']]);

            if ($stmt->rowCount() === 0) {
                throw new \Exception("Ocorrencia ja resolvida ou processada.");
            }

            // 2. Se for para repor, cria uma nova transferência
            if ($fluxo === 'repor') {
                // Busca as ocorrências registradas
                $ocs = $this->pdo->prepare(
                    "SELECT produto_id, SUM(quantidade_problema) as quantidade_problema
                     FROM erp_transferencias_ocorrencias
                     WHERE transferencia_id = ?
                     GROUP BY produto_id"
                );
                $ocs->execute([$transf_id]);
                $itensComProblema = $ocs->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($itensComProblema)) {
                    $codigo = 'REP-' . date('YmdHis') . '-' . rand(100, 999);
                    $origem_id = $dadosOrig['origem_filial_id'];

                    $stmtNew = $this->pdo->prepare(
                        "INSERT INTO erp_transferencias (codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, observacoes, usuario_id, data_envio)
                         VALUES (?, 'transferencia', ?, ?, 'em_transito', ?, ?, NOW())"
                    );
                    $obsNovo = "Reposição automática do pedido #" . $transf_id;
                    $stmtNew->execute([$codigo, $origem_id, $dadosOrig['destino_filial_id'], $obsNovo, $_SESSION['usuario_id'] ?? 0]);
                    $new_id = $this->pdo->lastInsertId();

                    $stmtItem = $this->pdo->prepare("INSERT INTO erp_transferencias_itens (transferencia_id, produto_id, quantidade_solicitada, quantidade_enviada, valor_custo_unitario) VALUES (?, ?, ?, ?, ?)");

                    // Query para verificar estoque atual
                    $stmtCheck = $this->pdo->prepare("
                        SELECT
                            p.nome,
                            CASE
                                WHEN ef.id IS NOT NULL THEN ef.quantidade
                                WHEN ? = 1 OR p.filial_id = ? OR (? = 1 AND p.filial_id IS NULL) THEN p.quantidade
                                ELSE 0
                            END as estoque_atual
                        FROM produtos p
                        LEFT JOIN estoque_filiais ef ON p.id = ef.produto_id AND ef.filial_id = ?
                        WHERE p.id = ?
                    ");

                    $origemEhMatriz = ((int)$origem_id === (int)$this->matrizId) ? 1 : 0;
                    $origemUsaEstoqueLegado = $this->filialUsaEstoqueLegadoGlobal((int)$origem_id) ? 1 : 0;
                    $countReposto = 0;
                    foreach ($itensComProblema as $it) {
                        $pid = $it['produto_id'];
                        $qtd = (float)$it['quantidade_problema'];

                        if ($qtd <= 0) continue;

                        // 1. Validação de Estoque (Servidor)
                        $this->garantirLinhaEstoqueFilial((int)$pid, (int)$origem_id);
                        $stmtCheck->execute([$origemEhMatriz, $origem_id, $origemUsaEstoqueLegado, $origem_id, $pid]);
                        $estoque = $stmtCheck->fetch(\PDO::FETCH_ASSOC);
                        
                        if (!$estoque || $estoque['estoque_atual'] < $qtd) {
                            $nomeProd = $estoque ? $estoque['nome'] : "Produto ID $pid";
                            $disponivel = $estoque ? (float)$estoque['estoque_atual'] : 0;
                            throw new \Exception("Estoque insuficiente para repor '{$nomeProd}'. Disponível: {$disponivel}, Necessário: {$qtd}");
                        }

                        $pd = $this->pdo->prepare("SELECT preco_custo FROM produtos WHERE id = ?");
                        $pd->execute([$pid]);
                        $custo = $pd->fetchColumn() ?: 0;

                        $stmtItem->execute([$new_id, $pid, $qtd, $qtd, $custo]);
                        
                        $this->baixarEstoqueTransferencia((int)$pid, (int)$origem_id, (float)$qtd);
                        $countReposto++;
                    }

                    if ($countReposto === 0) {
                        throw new \Exception("Nenhum item válido para reposição foi encontrado.");
                    }
                    $this->atualizarValorTotalCusto((int)$new_id, 'quantidade_enviada');
                }
            }

            $this->pdo->commit();
            setFlash('success', ($fluxo === 'repor') ? 'Problema resolvido e nova reposição despachada com sucesso!' : 'Ocorrência marcada como resolvida.');
            $redirectTab = $this->isMatriz ? 'historico_envios' : 'historico_recebimentos';
            $this->redirect('transferencias.php?aba=' . $redirectTab);
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $redirectTab = $this->isMatriz ? 'historico_envios' : 'historico_recebimentos';
            $this->redirect('transferencias.php?aba=' . $redirectTab . '&erro=' . urlencode($e->getMessage()));
        }
    }
}

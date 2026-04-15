<?php
namespace App\Services;

/**
 * SyncService — Processa operações realizadas offline e as integra ao banco principal.
 * 
 * Responsabilidades:
 *  - Criar vendas a partir de dados offline
 *  - Criar pré-vendas a partir de dados offline
 *  - Resolver conflitos (estoque insuficiente, etc.)
 *  - Registrar logs de auditoria na tabela sync_audit_log
 *  - Marcar vendas fiscais offline como contingência
 */
class SyncService {

    private $db;

    public function __construct() {
        $this->db = \App\Config\Database::getInstance()->getConnection();
    }

    /**
     * Processa uma operação offline.
     * 
     * @param array $op Operação com keys: type, temp_id, temp_code, data, session, created_at, is_contingencia
     * @return array Resultado com success, real_id, real_code, etc.
     */
    public function processOperation(array $op): array {
        $type = $op['type'] ?? '';
        $tempId = $op['temp_id'] ?? null;
        $data = $op['data'] ?? [];
        $session = $op['session'] ?? [];
        $createdAt = $op['created_at'] ?? date('c');

        $this->log('INFO', "Processando operação offline: type=$type, temp_id=$tempId", $op);

        switch ($type) {
            case 'sale':
                return $this->processSale($op);
            case 'presale':
                return $this->processPreSale($op);
            default:
                throw new \Exception("Tipo de operação desconhecido: $type");
        }
    }

    /**
     * Processa uma VENDA offline.
     * Replica a lógica do SalesController::checkout() mas usando dados da sessão offline.
     */
    private function processSale(array $op): array {
        $data = $op['data'];
        $session = $op['session'];
        $tempId = $op['temp_id'];
        $isContingencia = $op['is_contingencia'] ?? false;
        $createdAt = $op['created_at'] ?? date('Y-m-d H:i:s');

        $this->log('INFO', "Sincronizando venda offline: temp_id=$tempId, total=R${$data['total']}", [
            'temp_id' => $tempId,
            'total' => $data['total'],
            'pagamento' => $data['pagamento'],
            'contingencia' => $isContingencia
        ]);

        try {
            $this->db->beginTransaction();

            // Verificar se o caixa está aberto para a filial
            $cashierModel = new \App\Models\Cashier();
            $filialId = $session['filial_id'] ?? 1;
            $caixaAberto = $cashierModel->getOpenForFilial($filialId);

            if (!$caixaAberto) {
                // Se o caixa foi fechado entre o offline e o sync, abrir um automático?
                // Não — registra a venda com log de aviso
                $this->log('WARN', "Caixa fechado durante sync — venda será registrada sem movimento de caixa", [
                    'temp_id' => $tempId, 'filial_id' => $filialId
                ]);
            }

            // Determinar tipo_nota
            $tipoNota = $data['tipo_nota'] ?? 'nao_fiscal';
            if ($isContingencia) {
                $tipoNota = 'contingencia';
            }

            // Resolver dados do cliente
            $cpfPersist = $data['cpf_cliente'] ?? null;
            $nomePersist = $data['nome_cliente_avulso'] ?? null;

            if (!empty($data['cliente_id'])) {
                $stmtC = $this->db->prepare("SELECT nome, cpf_cnpj FROM clientes WHERE id = ?");
                $stmtC->execute([$data['cliente_id']]);
                $cRow = $stmtC->fetch(\PDO::FETCH_ASSOC);
                if ($cRow) {
                    $cpfPersist = $cRow['cpf_cnpj'];
                    $nomePersist = $cRow['nome'];
                }
            }

            // Criar venda
            $saleModel = new \App\Models\Sale();
            $saleData = [
                'cliente_id'          => $data['cliente_id'] ?? null,
                'nome_cliente_avulso' => $data['nome_cliente_avulso'] ?? null,
                'cpf_cliente'         => $cpfPersist,
                'cliente_nome'        => $nomePersist,
                'usuario_id'          => $session['usuario_id'],
                'filial_id'           => $filialId,
                'valor_total'         => $data['total'],
                'desconto_total'      => ($data['subtotal'] ?? $data['total']) * (($data['discount_percent'] ?? 0) / 100),
                'forma_pagamento'     => $data['pagamento'],
                'autorizado_por'      => $data['supervisor_id'] ?? null,
                'tipo_nota'           => $tipoNota,
                'valor_recebido'      => $data['valor_recebido'] ?? null,
                'troco'               => $data['troco'] ?? null,
                'taxa_cartao'         => $data['taxa_cartao'] ?? 0,
                'dh_cont'             => $data['dh_cont'] ?? null,
                'x_just'              => $data['x_just'] ?? null,
            ];

            $saleId = $saleModel->create($saleData);
            $this->log('INFO', "Venda criada no DB principal: ID=$saleId", ['temp_id' => $tempId]);

            // Processar itens
            $productModel = new \App\Models\Product();
            $stockWarnings = [];

            foreach ($data['items'] as $item) {
                // Verificar estoque — mas NÃO bloquear (venda já foi "feita")
                if (!$productModel->hasEnoughStock($item['id'], $item['qty'], $filialId)) {
                    $stockWarnings[] = "Produto #{$item['id']} ({$item['nome']}): estoque insuficiente, marcado como negativo";
                    $this->log('WARN', "Estoque insuficiente durante sync para produto {$item['id']}", [
                        'produto_id' => $item['id'],
                        'nome' => $item['nome'],
                        'qty_vendida' => $item['qty']
                    ]);
                }

                // Inserir item
                $this->db->prepare(
                    "INSERT INTO vendas_itens (venda_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)"
                )->execute([$saleId, $item['id'], $item['qty'], $item['price']]);

                // Baixar estoque (mesmo que fique negativo)
                $productModel->updateStock($item['id'], $item['qty'], 'saida', $filialId);
                $this->log('INFO', "Estoque atualizado: Produto #{$item['id']}, Qty -{$item['qty']}");
            }

            // Registar movimento de caixa (se caixa estiver aberto)
            if ($caixaAberto) {
                $movementModel = new \App\Models\CashierMovement();

                if ($data['pagamento'] !== 'fiado') {
                    $movementModel->create([
                        'caixa_id'    => $caixaAberto['id'],
                        'tipo'        => 'entrada',
                        'valor'       => $data['total'],
                        'motivo'      => "Venda #{$saleId} (SYNC OFFLINE - " . strtoupper($data['pagamento']) . ")",
                        'operador_id' => $session['usuario_id'],
                    ]);
                }
            }

            // Marcar pré-venda como finalizada (se houver)
            if (!empty($data['pv_id'])) {
                $pvModel = new \App\Models\PreSale();
                try {
                    // O pv_id pode ser um temp_id (OFF-xxx) se a PV também era offline
                    // Nesse caso, buscar pelo temp_id na tabela de sync
                    if (str_starts_with((string)$data['pv_id'], 'OFF-')) {
                        // Será resolvido pelo SyncManager do JS que atualiza as referências
                        $this->log('INFO', "PV referenciada como offline ({$data['pv_id']}), será resolvida na próxima sync");
                    } else {
                        $pvModel->markAsFinalized($data['pv_id']);
                    }
                } catch (\Exception $e) {
                    $this->log('WARN', "Erro ao finalizar PV {$data['pv_id']}: " . $e->getMessage());
                }
            }

            // Fiado: criar contas a receber
            if ($data['pagamento'] === 'fiado' && !empty($data['cliente_id'])) {
                $entrada = (float)($data['entrada_valor'] ?? 0);
                $valorDivida = (float)$data['total'] - $entrada;

                $receivableModel = new \App\Models\AccountReceivable();
                $receivableModel->create([
                    'venda_id'        => $saleId,
                    'cliente_id'      => $data['cliente_id'],
                    'valor'           => $data['total'],
                    'valor_pago'      => $entrada,
                    'saldo'           => $valorDivida,
                    'status'          => ($valorDivida <= 0) ? 'pago' : 'pendente',
                    'data_vencimento' => date('Y-m-d', strtotime('+30 days')),
                    'filial_id'       => $filialId,
                ]);
            }

            $this->db->commit();
            $this->log('INFO', "Transação COMMITADA com sucesso para Venda #{$saleId}");

            // Registrar auditoria
            $audit = new AuditLogService();
            $audit->record(
                'Venda sincronizada do modo offline',
                'vendas',
                $saleId,
                null,
                json_encode([
                    'offline_temp_id' => $tempId,
                    'synced_at' => date('c'),
                    'contingencia' => $isContingencia,
                    'stock_warnings' => $stockWarnings
                ])
            );

            // Registrar no sync_audit_log
            $this->logSyncAudit('sale', $tempId, $saleId, 'success', null, $data, $session);

            $this->log('OK', "✅ Venda sincronizada: temp_id=$tempId → real_id=$saleId", [
                'temp_id' => $tempId,
                'real_id' => $saleId,
                'stock_warnings' => $stockWarnings
            ]);

            return [
                'success' => true,
                'temp_id' => $tempId,
                'real_id' => $saleId,
                'type' => 'sale',
                'stock_warnings' => $stockWarnings,
                'contingencia' => $isContingencia
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();

            $this->logSyncAudit('sale', $tempId, null, 'error', $e->getMessage(), $data, $session);
            $this->log('ERROR', "❌ Falha ao sincronizar venda: " . $e->getMessage(), [
                'temp_id' => $tempId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Processa uma PRÉ-VENDA offline.
     */
    private function processPreSale(array $op): array {
        $data = $op['data'];
        $session = $op['session'];
        $tempId = $op['temp_id'];
        $tempCode = $op['temp_code'] ?? $data['codigo'] ?? null;

        $this->log('INFO', "Sincronizando pré-venda offline: temp_id=$tempId, code=$tempCode");

        try {
            // Preparar dados da pré-venda
            $pvModel = new \App\Models\PreSale();

            $pvData = [
                'cliente_id'          => $data['cliente_id'] ?? null,
                'nome_cliente_avulso' => $data['nome_cliente_avulso'] ?? null,
                'cpf_cliente'         => $data['cpf_cliente'] ?? null,
                'usuario_id'          => $session['usuario_id'],
                'filial_id'           => $session['filial_id'] ?? 1,
                'valor_total'         => $data['valor_total'],
                'items'               => $data['items'] ?? [],
            ];

            $result = $pvModel->create($pvData);
            $realId = $result['id'];
            $realCode = $result['codigo'];

            // Registrar auditoria
            $audit = new AuditLogService();
            $audit->record(
                'Pré-venda sincronizada do modo offline',
                'pre_vendas',
                $realId,
                null,
                json_encode([
                    'offline_temp_id' => $tempId,
                    'offline_temp_code' => $tempCode,
                    'real_code' => $realCode,
                    'synced_at' => date('c')
                ])
            );

            $this->logSyncAudit('presale', $tempId, $realId, 'success', null, $data, $session);

            $this->log('OK', "✅ Pré-venda sincronizada: temp_id=$tempId → real_id=$realId (code=$realCode)", [
                'temp_id' => $tempId,
                'real_id' => $realId,
                'temp_code' => $tempCode,
                'real_code' => $realCode
            ]);

            return [
                'success' => true,
                'temp_id' => $tempId,
                'real_id' => $realId,
                'real_code' => $realCode,
                'type' => 'presale'
            ];

        } catch (\Exception $e) {
            $this->logSyncAudit('presale', $tempId, null, 'error', $e->getMessage(), $data, $session);
            $this->log('ERROR', "❌ Falha ao sincronizar pré-venda: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registra entrada no sync_audit_log (tabela do banco)
     */
    private function logSyncAudit(string $type, ?string $tempId, ?int $realId, string $status, ?string $errorMsg, array $data, array $session): void {
        try {
            // Verificar se a tabela existe
            $stmt = $this->db->query("SHOW TABLES LIKE 'sync_audit_log'");
            if ($stmt->rowCount() === 0) return;

            $this->db->prepare("
                INSERT INTO sync_audit_log (operation_type, temp_id, real_id, status, error_message, payload, session_data, synced_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([
                $type,
                $tempId,
                $realId,
                $status,
                $errorMsg,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                json_encode($session, JSON_UNESCAPED_UNICODE)
            ]);
        } catch (\Exception $e) {
            error_log("[SYNC_AUDIT] Erro ao registrar log: " . $e->getMessage());
        }
    }

    /**
     * Log em arquivo para rastreabilidade
     */
    private function log(string $level, string $message, array $context = []): void {
        $logDir = __DIR__ . '/../../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/sync_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        $line = "[$timestamp] [$level] $message$contextStr\n";
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

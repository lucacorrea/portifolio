<?php
namespace App\Controllers;

use App\Services\SefazConsultaService;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\AuditLogService;

class ImportacaoAutomaticaController extends BaseController {
    public function index() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $filialId = $_SESSION['filial_id'] ?? 1;

        // Filtros
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 'todas';
        $desde  = $_GET['desde'] ?? '';
        $ate    = $_GET['ate'] ?? '';

        $params = [$filialId];
        $where  = ["filial_id = ?"];

        if ($search) {
            $where[] = "(fornecedor_nome LIKE ? OR fornecedor_cnpj LIKE ? OR numero_nota LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($status === 'pendente' || $status === 'importada') {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if ($desde) {
            $where[] = "data_emissao >= ?";
            $params[] = $desde . " 00:00:00";
        }
        if ($ate) {
            $where[] = "data_emissao <= ?";
            $params[] = $ate . " 23:59:59";
        }

        $sql = "SELECT * FROM nfe_importadas 
                WHERE " . implode(" AND ", $where) . "
                ORDER BY data_emissao DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $notas = $stmt->fetchAll();

        // Buscar última sincronização geral
        $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'nfe_last_sync_timestamp'");
        $stmt->execute();
        $lastSync = $stmt->fetchColumn();
        
        $this->render('importacao_automatica', [
            'notas' => $notas,
            'lastSync' => $lastSync,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'desde' => $desde,
                'ate' => $ate
            ],
            'title' => 'Importação Automática SEFAZ',
            'pageTitle' => 'Notas Fiscais Destinadas (Certificado A1)'
        ]);
    }

    public function sincronizar() {
        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $filialId = $_SESSION['filial_id'] ?? 1;
            $forceReset = ($_GET['reset'] ?? '0') === '1';
            
            // Buscar CNPJ da filial
            $stmt = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
            $stmt->execute([$filialId]);
            $cnpj = $stmt->fetchColumn();

            $service = new SefazConsultaService();
            
            // Se reset for 1, passamos 0 como NSU inicial para buscar os últimos 90 dias
            $ultNSU = $forceReset ? '0' : null;
            $resultado = $service->consultarNotas($cnpj, $ultNSU);
            
            $count = count($resultado['documentos'] ?? []);
            
            // Contar registros efetivamente no banco
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM nfe_importadas WHERE filial_id = ?");
            $stmtCount->execute([$filialId]);
            $totalNoBanco = $stmtCount->fetchColumn();
            
            $hasMore = ($resultado['ultNSU'] < $resultado['maxNSU']);
            $message = $count > 0 
                ? "Sincronização concluída. $count novos registros processados. Total no banco: $totalNoBanco notas." 
                : "Nenhuma nota nova encontrada na SEFAZ para este período. Total no banco: $totalNoBanco notas.";

            if ($hasMore) {
                $message .= " Atenção: Ainda existem mais notas disponíveis. Clique em 'Atualizar' novamente.";
            }

            // Save last sync timestamp
            $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
            $stmt->execute(['nfe_last_sync_timestamp', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

            echo json_encode([
                'success' => true, 
                'count' => $count, 
                'totalBanco' => (int)$totalNoBanco,
                'hasMore' => $hasMore,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function manifestar() {
        try {
            $id = $_GET['id'] ?? null;
            $type = $_GET['type'] ?? '210210'; // Default Ciência
            
            if (!$id) throw new \Exception("ID da nota não fornecido.");

            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM nfe_importadas WHERE id = ? AND filial_id = ?");
            $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
            $nota = $stmt->fetch();

            if (!$nota) throw new \Exception("Nota não encontrada.");

            $service = new SefazConsultaService();
            // Buscar CNPJ da filial para o cabeçalho do evento
            $stmt = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
            $stmt->execute([$_SESSION['filial_id'] ?? 1]);
            $cnpjFilial = $stmt->fetchColumn();

            $service->manifestarNota($cnpjFilial, $nota['chave_nfe'], $type);

            // Atualizar o banco com a manifestação realizada
            $stmtUp = $db->prepare("UPDATE nfe_importadas SET manifestacao_tipo = ?, manifestacao_data = NOW() WHERE id = ?");
            $stmtUp->execute([$type, $id]);

            $msg = [
                '210200' => 'Confirmação da Operação realizada.',
                '210210' => 'Ciência da Operação realizada.',
                '210220' => 'Desconhecimento da Operação realizado.',
                '210240' => 'Operação Não Realizada registrada.'
            ][$type] ?? 'Manifestação realizada.';

            echo json_encode(['success' => true, 'message' => $msg . ' Sincronize novamente em instantes para baixar os produtos.']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function visualizar_produtos() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT xml_conteudo FROM nfe_importadas WHERE id = ? AND filial_id = ?");
        $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
        $nota = $stmt->fetch();

        if (!$nota || empty($nota['xml_conteudo'])) {
            echo json_encode(['success' => false, 'error' => 'XML não encontrado.']);
            exit;
        }

        // Tentar ler o XML
        $xml = simplexml_load_string($nota['xml_conteudo']);
        if ($xml->getName() == 'resNFe') {
             echo json_encode(['success' => false, 'error' => 'A SEFAZ retornou apenas o resumo da nota. É necessário manifestar a nota para baixar o XML completo. (Funcionalidade de Manifestação Pendente)']);
             exit;
        }

        // Se for procNFe completo (mockado ou real)
        $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
        $detalhes = $xml->xpath('//nfe:det');
        $produtos = [];

        foreach ($detalhes as $det) {
            $prod = $det->prod;
            $produtos[] = [
                'codigo' => (string)$prod->cProd,
                'nome' => (string)$prod->xProd,
                'ncm' => (string)$prod->NCM,
                'cfop' => (string)$prod->CFOP,
                'qCom' => (float)$prod->qCom,
                'vUnCom' => (float)$prod->vUnCom,
                'vUnComFormatted' => number_format((float)$prod->vUnCom, 2, ',', '.')
            ];
        }

        echo json_encode(['success' => true, 'produtos' => $produtos]);
        exit;
    }

    public function processar_entrada() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['nota_id'] ?? null;
            $items = $data['items'] ?? [];
            
            if (!$id || empty($items)) {
                echo json_encode(['success' => false, 'error' => 'Dados incompletos para importação.']);
                exit;
            }

            $db = \App\Config\Database::getInstance()->getConnection();
            $productModel = new \App\Models\Product();
            $stockModel = new \App\Models\StockMovement();
            $audit = new \App\Services\AuditLogService();

            try {
                $db->beginTransaction();

                // Verificar status atual
                $stmt = $db->prepare("SELECT status FROM nfe_importadas WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() === 'importada') {
                    throw new \Exception("Esta nota já foi importada anteriormente.");
                }

                foreach ($items as $item) {
                    $stmt = $db->prepare("SELECT id FROM produtos WHERE nome = ? OR codigo = ?");
                    $stmt->execute([$item['nome'], $item['codigo']]);
                    $p = $stmt->fetch();

                    if ($p) {
                        $productId = $p['id'];
                        $productModel->updateStock($productId, $item['qCom'], 'entrada');
                    } else {
                        // Cadastro rápido
                        $productId = $productModel->create([
                            'nome' => $item['nome'],
                            'codigo' => $item['codigo'],
                            'ncm' => $item['ncm'],
                            'unidade' => 'UN',
                            'preco_venda' => $item['vUnCom'] * 1.5,
                            'estoque_atual' => $item['qCom'],
                            'filial_id' => $_SESSION['filial_id'] ?? 1
                        ]);
                    }

                    $stockModel->create([
                        'produto_id' => $productId,
                        'quantidade' => $item['qCom'],
                        'tipo' => 'entrada',
                        'motivo' => 'Importação Automática SEFAZ #' . $id,
                        'usuario_id' => $_SESSION['usuario_id'],
                        'filial_id' => $_SESSION['filial_id'] ?? 1
                    ]);
                }

                $stmt = $db->prepare("UPDATE nfe_importadas SET status = 'importada' WHERE id = ?");
                $stmt->execute([$id]);

                $audit->record('Entrada de Estoque via SEFAZ Automática', 'nfe_importadas', $id, null, count($items) . " itens processados");
                
                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }

    public function baixar_xml() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT xml_conteudo, chave_nfe FROM nfe_importadas WHERE id = ? AND filial_id = ?");
        $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
        $nota = $stmt->fetch();

        if ($nota && !empty($nota['xml_conteudo'])) {
            header('Content-Type: text/xml');
            header('Content-Disposition: attachment; filename="NFe_' . $nota['chave_nfe'] . '.xml"');
            echo $nota['xml_conteudo'];
        }
        exit;
    }
}

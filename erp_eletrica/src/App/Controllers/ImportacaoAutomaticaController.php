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

        // Paginação
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        // Calcular total
        $sqlTotal = "SELECT COUNT(*) FROM nfe_importadas WHERE " . implode(" AND ", $where);
        $stmtTotal = $db->prepare($sqlTotal);
        $stmtTotal->execute($params);
        $totalItems = (int)$stmtTotal->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);

        // Buscar itens paginados
        $sql = "SELECT * FROM nfe_importadas 
                WHERE " . implode(" AND ", $where) . "
                ORDER BY data_emissao DESC
                LIMIT $perPage OFFSET $offset";
        
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
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems
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

            // 🔍 Buscar CNPJ da filial
            $stmt = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
            $stmt->execute([$filialId]);
            $cnpjRaw = $stmt->fetchColumn();
            $cnpj = preg_replace('/\D/', '', $cnpjRaw);

            // ⛔ CONTROLE DE TEMPO (⏱️ 1 consulta por hora automático)
            $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = 'nfe_last_sync_timestamp'");
            $stmt->execute();
            $lastSync = $stmt->fetchColumn();

            if ($lastSync && !$forceReset) {
                $diff = time() - strtotime($lastSync);
                if ($diff < 3600) {
                    $minutosRestantes = ceil((3600 - $diff) / 60);
                    echo json_encode([
                        'success' => false,
                        'error' => "⏱️ Limite atingido. Aguarde {$minutosRestantes} minutos para uma nova consulta automática à SEFAZ."
                    ]);
                    return;
                }
            }

            // 🔌 CONSULTA SEFAZ (Agora com LOOP e persistência automática)
            $service = new SefazConsultaService();
            // Passamos null para o NSU para que o service busque o correto no banco (nfe_last_nsu)
            $resultado = $service->consultarNotas($cnpj, $forceReset ? '000000000000000' : null);

            $count = $resultado['count'] ?? 0;
            $loops = $resultado['loops'] ?? 1;

            // 💾 SALVAR TIMESTAMP DA ÚLTIMA SINCRONIZAÇÃO
            $stmt = $db->prepare("
                INSERT INTO configuracoes (chave, valor) 
                VALUES ('nfe_last_sync_timestamp', ?) 
                ON DUPLICATE KEY UPDATE valor = ?
            ");
            $now = date('Y-m-d H:i:s');
            $stmt->execute([$now, $now]);

            // 📊 CONTAR TOTAL ATUALIZADO NO BANCO (para esta filial)
            $stmt = $db->prepare("SELECT COUNT(*) FROM nfe_importadas WHERE filial_id = ?");
            $stmt->execute([$filialId]);
            $totalNoBanco = $stmt->fetchColumn();

            $message = $count > 0 
                ? "Sincronização concluída ({$loops} lotes). $count novos registros processados."
                : "Nenhuma nota nova encontrada.";

            if (!empty($resultado['db_error'])) {
                $message .= "\nERRO (BD): " . $resultado['db_error'];
            }
            
            $message .= "\nTotal no banco: $totalNoBanco notas.";

            if (($resultado['ultNSU'] ?? 0) < ($resultado['maxNSU'] ?? 0)) {
                $message .= "\nAinda existem mais notas pendentes na SEFAZ (Limite de loop atingido).";
            }

            echo json_encode([
                'success' => true,
                'count' => $count,
                'totalBanco' => (int)$totalNoBanco,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            error_log("Erro na sincronização SEFAZ: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
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

            $service->manifestarNota($cnpjFilial, $nota['chave_acesso'], $type);

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

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT xml FROM nfe_importadas WHERE id = ? AND filial_id = ?");
            $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
            $nota = $stmt->fetch();

            if (!$nota || empty($nota['xml'])) {
                echo json_encode(['success' => false, 'error' => 'XML não encontrado.']);
                exit;
            }

            // Tentar ler o XML de forma robusta
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($nota['xml'], 'SimpleXMLElement', LIBXML_PARSEHUGE);
            
            if ($xml === false) {
                echo json_encode(['success' => false, 'error' => 'O XML da nota está malformado ou corrompido.']);
                exit;
            }

            if ($xml->getName() == 'resNFe') {
                 echo json_encode(['success' => false, 'error' => 'A SEFAZ retornou apenas o resumo da nota. É necessário manifestar a nota para baixar o XML completo.']);
                 exit;
            }

            // Se for procNFe completo (mockado ou real)
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            $detalhes = $xml->xpath('//nfe:det');
            $produtos = [];

            if ($detalhes) {
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
            }

            echo json_encode(['success' => true, 'produtos' => $produtos]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro interno ao processar: ' . $e->getMessage()]);
        }
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
        $stmt = $db->prepare("SELECT xml, chave_acesso FROM nfe_importadas WHERE id = ? AND filial_id = ?");
        $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
        $nota = $stmt->fetch();

        if ($nota && !empty($nota['xml'])) {
            header('Content-Type: text/xml');
            header('Content-Disposition: attachment; filename="NFe_' . $nota['chave_acesso'] . '.xml"');
            echo $nota['xml'];
        }
        exit;
    }

    public function baixar_danfe() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT xml, chave_acesso FROM nfe_importadas WHERE id = ? AND filial_id = ?");
        $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
        $nota = $stmt->fetch();

        if (!$nota || empty($nota['xml'])) {
            die("Erro: XML não encontrado no banco de dados.");
        }

        $xml = $nota['xml'];
        
        // Verificar se é apenas resumo
        if (strpos($xml, '<resNFe') !== false) {
            die("Erro: Não é possível gerar o DANFE a partir de um resumo da nota. É necessário manifestar a nota primeiro para que o XML completo seja liberado pela SEFAZ.");
        }

        try {
            // Incluir autoloader do vendor que contém o sped-da
            require_once dirname(__DIR__) . '/Services/vendor/autoload.php';

            // Tentar localizar um logo
            $logoPath = dirname(__DIR__, 3) . '/logo_sistema_erp_eletrica.png';
            $logo = file_exists($logoPath) ? $logoPath : '';

            $danfe = new \NFePHP\DA\NFe\Danfe($xml);
            $danfe->setDefaultFont('times');
            
            $pdf = $danfe->render($logo);

            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="DANFE_' . $nota['chave_acesso'] . '.pdf"');
            echo $pdf;
        } catch (\Exception $e) {
            die("Erro ao gerar DANFE: " . $e->getMessage());
        }
        exit;
    }
}

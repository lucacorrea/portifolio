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

        // Buscar notas do cache agrupadas por fornecedor
        $sql = "SELECT fornecedor_cnpj, fornecedor_nome, COUNT(*) as total_notas, SUM(valor_total) as valor_acumulado 
                FROM nfe_importadas 
                WHERE filial_id = ? AND status = 'pendente'
                GROUP BY fornecedor_cnpj, fornecedor_nome
                ORDER BY fornecedor_nome ASC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$filialId]);
        $fornecedores = $stmt->fetchAll();

        // Para cada fornecedor, buscar as notas
        foreach ($fornecedores as &$f) {
            $stmt = $db->prepare("SELECT * FROM nfe_importadas WHERE filial_id = ? AND fornecedor_cnpj = ? AND status = 'pendente' ORDER BY data_emissao DESC");
            $stmt->execute([$filialId, $f['fornecedor_cnpj']]);
            $f['notas'] = $stmt->fetchAll();
        }

        $this->render('importacao_automatica', [
            'fornecedores' => $fornecedores,
            'title' => 'Importação Automática SEFAZ',
            'pageTitle' => 'Notas Fiscais Destinadas (Certificado A1)'
        ]);
    }

    public function sincronizar() {
        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $filialId = $_SESSION['filial_id'] ?? 1;
            
            // Buscar CNPJ da filial
            $stmt = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
            $stmt->execute([$filialId]);
            $cnpj = $stmt->fetchColumn();

            $service = new SefazConsultaService();
            $resultado = $service->consultarNotas($cnpj, '0');
            
            if (!empty($resultado['documentos'])) {
                $service->salvarNotasCache($filialId, $resultado['documentos']);
                echo json_encode(['success' => true, 'count' => count($resultado['documentos'])]);
            } else {
                echo json_encode(['success' => true, 'count' => 0, 'message' => 'Nenhuma nota nova encontrada na SEFAZ.']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function manifestar() {
        try {
            $id = $_GET['id'] ?? null;
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

            $service->manifestarNota($cnpjFilial, $nota['chave_acesso']);

            // Após manifestar, precisamos sincronizar novamente para baixar o XML completo (procNFe)
            // A SEFAZ pode demorar alguns segundos, mas geralmente o próximo 'sincronizar' resolve.
            echo json_encode(['success' => true, 'message' => 'Manifestação (Ciência da Operação) realizada com sucesso. Sincronize novamente em instantes para baixar os produtos.']);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function visualizar_produtos() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        $db = \App\Config\Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT xml FROM nfe_importadas WHERE id = ? AND filial_id = ?");
        $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
        $nota = $stmt->fetch();

        if (!$nota || empty($nota['xml'])) {
            echo json_encode(['success' => false, 'error' => 'XML não encontrado.']);
            exit;
        }

        // Tentar ler o XML
        $xml = simplexml_load_string($nota['xml']);
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
}

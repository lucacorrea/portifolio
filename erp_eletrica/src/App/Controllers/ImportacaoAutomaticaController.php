<?php
namespace App\Controllers;

use App\Services\SefazConsultaService;
use App\Models\Product;
use App\Models\StockMovement;
use App\Config\Database;
use PDO;

// Carregar autoloader globalmente para este controller
require_once dirname(__DIR__) . '/Services/vendor/autoload.php';

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
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
                echo json_encode([
                    'success' => false,
                    'error' => "FATAL ERROR: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']
                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }
        });

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $filialId = $_SESSION['filial_id'] ?? 1;
            $forceReset = ($_GET['reset'] ?? '0') === '1';

            // 🔍 Buscar CNPJ da filial
            $stmt = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
            $stmt->execute([$filialId]);
            $cnpjRaw = $stmt->fetchColumn();
            
            if (!$cnpjRaw) {
                throw new \Exception("CNPJ não encontrado para a filial atual. Verifique o cadastro da filial e configure sua empresa.");
            }
            
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
                    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
                'message' => mb_convert_encoding($message, 'UTF-8', 'UTF-8')
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        } catch (\Throwable $e) {
            error_log("Erro na sincronização SEFAZ: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => mb_convert_encoding($e->getMessage(), 'UTF-8', 'UTF-8')
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
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
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function iniciar_analise() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM nfe_importadas WHERE id = ? AND filial_id = ?");
            $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
            $nota = $stmt->fetch();

            if (!$nota || empty($nota['xml'])) {
                throw new \Exception('XML não encontrado no banco de dados. Manifeste a nota primeiro.');
            }

            // AUTO-REGISTRO DO FORNECEDOR
            $this->autoRegisterSupplier($nota);

            $xml = simplexml_load_string($nota['xml'], 'SimpleXMLElement', LIBXML_PARSEHUGE);
            $xml->registerXPathNamespace('nfe', 'http://www.portalfiscal.inf.br/nfe');
            $detalhes = $xml->xpath('//nfe:det');

            if (!$detalhes) {
                throw new \Exception('A nota não possui itens ou o XML está incompleto.');
            }

            $db->beginTransaction();

            // Limpar análise anterior se existir (re-start)
            $db->prepare("DELETE FROM nfe_analise_itens WHERE nfe_id = ?")->execute([$id]);

            foreach ($detalhes as $det) {
                $prod = $det->prod;
                $codigoForn = (string)$prod->cProd;
                $nomeForn = (string)$prod->xProd;
                $cnpjForn = preg_replace('/\D/', '', $nota['fornecedor_cnpj']);

                // Tentar encontrar mapping prévio
                $stmtMap = $db->prepare("SELECT produto_id FROM produto_fornecedor_map WHERE fornecedor_cnpj = ? AND codigo_fornecedor = ?");
                $stmtMap->execute([$cnpjForn, $codigoForn]);
                $map = $stmtMap->fetch();
                $produtoId = $map['produto_id'] ?? null;

                // Tentar encontrar por EAN/GTIN se não houver mapping
                if (!$produtoId && !empty($prod->cEAN) && (string)$prod->cEAN !== 'SEM GTIN') {
                    $stmtEan = $db->prepare("SELECT id FROM produtos WHERE cean = ?");
                    $stmtEan->execute([(string)$prod->cEAN]);
                    $produtoId = $stmtEan->fetchColumn() ?: null;
                }

                // Tentar encontrar por Código Exato se não houver mapping
                if (!$produtoId) {
                    $stmtCod = $db->prepare("SELECT id FROM produtos WHERE codigo = ?");
                    $stmtCod->execute([$codigoForn]);
                    $produtoId = $stmtCod->fetchColumn() ?: null;
                }

                $stmtIns = $db->prepare("
                    INSERT INTO nfe_analise_itens (nfe_id, codigo_fornecedor, nome_item, unidade, quantidade, valor_unitario, ncm, ean, produto_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([
                    $id, $codigoForn, $nomeForn, (string)$prod->uCom, (float)$prod->qCom, (float)$prod->vUnCom, (string)$prod->NCM, (string)$prod->cEAN,
                    $produtoId, $produtoId ? 'vinculado' : 'pendente'
                ]);
            }

            $db->prepare("UPDATE nfe_importadas SET status = 'em_analise' WHERE id = ?")->execute([$id]);
            $db->commit();

            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function autoRegisterSupplier($nota) {
        $db = \App\Config\Database::getInstance()->getConnection();
        $cnpj = preg_replace('/\D/', '', $nota['fornecedor_cnpj']);
        
        $stmt = $db->prepare("SELECT id FROM fornecedores WHERE cnpj = ?");
        $stmt->execute([$cnpj]);
        if ($stmt->fetch()) return; // Já existe

        $endereco = ''; $telefone = ''; $email = '';
        
        try {
            $xml = simplexml_load_string($nota['xml']);
            if ($xml) {
                $xml->registerXPathNamespace('n', 'http://www.portalfiscal.inf.br/nfe');
                $emit = $xml->xpath('//n:emit')[0] ?? null;
                if ($emit) {
                    $ender = $emit->enderEmit;
                    if ($ender) {
                        $endereco = "{$ender->xLgr}, {$ender->nro}" . ($ender->xCpl ? " - {$ender->xCpl}" : "") . ", {$ender->xBairro}, {$ender->xMun} - {$ender->UF}";
                        $telefone = (string)$ender->fone;
                    }
                }
            }
        } catch(\Exception $e) {}

        $supplier = new \App\Models\Supplier();
        $supplier->save([
            'nome_fantasia' => $nota['fornecedor_nome'],
            'cnpj' => $cnpj,
            'email' => $email,
            'telefone' => $telefone,
            'endereco' => $endereco
        ]);
    }

    public function listar_analise() {
        $db = \App\Config\Database::getInstance()->getConnection();
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        $sql = "SELECT a.*, p.nome as sistema_nome, p.codigo as sistema_codigo 
                FROM nfe_analise_itens a
                LEFT JOIN produtos p ON a.produto_id = p.id
                WHERE a.nfe_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $itens = $stmt->fetchAll();

        // Para cada item pendente, buscar sugestões
        foreach ($itens as &$item) {
            if ($item['status'] === 'pendente') {
                $stmtSug = $db->prepare("SELECT id, nome, codigo FROM produtos WHERE nome LIKE ? OR codigo = ? LIMIT 5");
                $stmtSug->execute(['%' . $item['nome_item'] . '%', $item['codigo_fornecedor']]);
                $item['sugestoes'] = $stmtSug->fetchAll();
            }
        }

        echo json_encode(['success' => true, 'itens' => $itens]);
        exit;
    }

    public function vincular_item() {
        $data = json_decode(file_get_contents('php://input'), true);
        $analiseId = $data['analise_id'] ?? null;
        $produtoId = $data['produto_id'] ?? null;
        $updatePermanently = $data['update_code'] ?? false;

        if (!$analiseId || !$produtoId) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
            exit;
        }

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM nfe_analise_itens WHERE id = ?");
            $stmt->execute([$analiseId]);
            $analise = $stmt->fetch();
            $nfeId = $analise['nfe_id'];

            // Buscar CNPJ do fornecedor da nota
            $stmtNfe = $db->prepare("SELECT fornecedor_cnpj FROM nfe_importadas WHERE id = ?");
            $stmtNfe->execute([$nfeId]);
            $cnpjForn = $stmtNfe->fetchColumn();

            // Salvar no mapping histórico
            $db->prepare("INSERT INTO produto_fornecedor_map (fornecedor_cnpj, codigo_fornecedor, produto_id) 
                          VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE produto_id = ?")
               ->execute([$cnpjForn, $analise['codigo_fornecedor'], $produtoId, $produtoId]);

            // Atualizar item da análise
            $db->prepare("UPDATE nfe_analise_itens SET produto_id = ?, status = 'vinculado' WHERE id = ?")
               ->execute([$produtoId, $analiseId]);

            // Se solicitado, atualizar o código interno do produto permanentemente
            if ($updatePermanently) {
                $db->prepare("UPDATE produtos SET codigo = ? WHERE id = ?")
                   ->execute([$analise['codigo_fornecedor'], $produtoId]);
            }

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function cadastrar_e_vincular() {
        $data = json_decode(file_get_contents('php://input'), true);
        $analiseId = $data['analise_id'] ?? null;

        if (!$analiseId) exit;

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM nfe_analise_itens WHERE id = ?");
            $stmt->execute([$analiseId]);
            $analise = $stmt->fetch();

            $productModel = new \App\Models\Product();
            $produtoId = $productModel->save([
                'codigo' => $analise['codigo_fornecedor'],
                'nome' => $analise['nome_item'],
                'ncm' => $analise['ncm'],
                'unidade' => $analise['unidade'],
                'preco_custo' => $analise['valor_unitario'],
                'preco_venda' => $analise['valor_unitario'] * 1.5,
                'estoque_minimo' => 0,
                'categoria' => 'Lançamentos'
            ]);

            // Vincular
            $db->prepare("UPDATE nfe_analise_itens SET produto_id = ?, status = 'novo' WHERE id = ?")
               ->execute([$produtoId, $analiseId]);

            $db->commit();
            echo json_encode(['success' => true, 'produto_id' => $produtoId]);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function visualizar_produtos() {
        $id = $_GET['id'] ?? null;
        if (!$id) exit;

        try {
            $db = \App\Config\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM nfe_importadas WHERE id = ? AND filial_id = ?");
            $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
            $nota = $stmt->fetch();

            if (!$nota || empty($nota['xml'])) {
                echo json_encode(['success' => false, 'error' => 'XML não encontrado no banco de dados.']);
                exit;
            }

            if ($nota['status'] === 'em_analise') {
                echo json_encode(['success' => true, 'em_analise' => true]);
                exit;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($nota['xml'], 'SimpleXMLElement', LIBXML_PARSEHUGE);
            
            if ($xml === false) {
                echo json_encode(['success' => false, 'error' => 'O XML da nota está corrompido ou malformado.']);
                exit;
            }

            if ($xml->getName() == 'resNFe') {
                 // 🕵️ FALLBACK: Tentar baixar o XML completo via Chave de Acesso (Active Fetch)
                 $stmtF = $db->prepare("SELECT cnpj FROM filiais WHERE id = ?");
                 $stmtF->execute([$_SESSION['filial_id'] ?? 1]);
                 $cnpjFilial = $stmtF->fetchColumn();

                 $service = new SefazConsultaService();
                 $service->consultarPorChave($cnpjFilial, $nota['chave_acesso']);

                 // Recarregar do banco para ver se agora temos o procNFe
                 $stmt->execute([$id, $_SESSION['filial_id'] ?? 1]);
                 $nota = $stmt->fetch();
                 
                 $xml = simplexml_load_string($nota['xml'], 'SimpleXMLElement', LIBXML_PARSEHUGE);
                 if (!$xml || $xml->getName() == 'resNFe') {
                    echo json_encode(['success' => false, 'error' => 'A SEFAZ retornou apenas o resumo da nota e o download do XML completo ainda não foi liberado. Certifique-se de que a nota foi manifestada e aguarde alguns minutos antes de tentar novamente.']);
                    exit;
                 }
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

            echo json_encode(['success' => true, 'produtos' => $produtos, 'em_analise' => false]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => 'Erro interno ao processar: ' . $e->getMessage()]);
        }
        exit;
    }

    public function finalizar_importacao() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['nota_id'] ?? null;
            
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID da nota não fornecido.']);
                exit;
            }

            try {
                $db = \App\Config\Database::getInstance()->getConnection();
                $db->beginTransaction();

                // Verificar se todos os itens estão vinculados
                $stmtPend = $db->prepare("SELECT COUNT(*) FROM nfe_analise_itens WHERE nfe_id = ? AND produto_id IS NULL");
                $stmtPend->execute([$id]);
                if ($stmtPend->fetchColumn() > 0) {
                    throw new \Exception("Ainda existem itens pendentes de vínculo nesta nota.");
                }

                $stmtItens = $db->prepare("SELECT * FROM nfe_analise_itens WHERE nfe_id = ?");
                $stmtItens->execute([$id]);
                $itens = $stmtItens->fetchAll();

                $productModel = new \App\Models\Product();
                $stockModel = new \App\Models\StockMovement();

                foreach ($itens as $item) {
                    $productModel->updateStock($item['produto_id'], $item['quantidade'], 'entrada');

                    $stockModel->create([
                        'produto_id' => $item['produto_id'],
                        'quantidade' => $item['quantidade'],
                        'tipo' => 'entrada',
                        'motivo' => 'Importação Revisada SEFAZ #' . $id,
                        'usuario_id' => $_SESSION['usuario_id'],
                        'filial_id' => $_SESSION['filial_id'] ?? 1
                    ]);
                }

                $db->prepare("UPDATE nfe_importadas SET status = 'importada' WHERE id = ?")->execute([$id]);

                $audit = new \App\Services\AuditLogService();
                $audit->record('Entrada de Estoque via SEFAZ Analisada', 'nfe_importadas', $id, null, count($itens) . " itens processados");

                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
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
            // O autoloader já foi carregado no topo do arquivo para garantir que as classes NFePHP estejam disponíveis
            
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

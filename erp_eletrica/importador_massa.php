<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/App/Config/Database.php';

// Apenas administradores
if (empty($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_nivel'], ['master', 'admin'])) {
    die("Acesso restrito.");
}

$db = \App\Config\Database::getInstance()->getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo_csv'])) {
    $file = $_FILES['arquivo_csv'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Erro no upload do arquivo.";
    } else {
        $handle = fopen($file['tmp_name'], "r");
        if ($handle !== FALSE) {
            $inserted = 0;
            $updated = 0;
            $skipped = 0;
            $rowIndex = 0;
            
            $db->beginTransaction();
            try {
                $stmtCheck = $db->prepare("SELECT id FROM produtos WHERE codigo = ?");
                
                $stmtInsert = $db->prepare("
                    INSERT INTO produtos (codigo, nome, unidade, categoria, preco_custo, preco_venda, quantidade, tipo_produto, cean, cfop_interno, cfop_externo, csosn, origem) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'simples', ?, '5102', '6102', '102', 0)
                ");
                
                $stmtUpdate = $db->prepare("
                    UPDATE produtos SET nome = ?, categoria = ?, preco_custo = ?, preco_venda = ?, quantidade = quantidade + ?, unidade = ? WHERE codigo = ?
                ");

                while (($lineStr = fgets($handle)) !== false) {
                    $rowIndex++;
                    if (trim($lineStr) === '') continue;
                    
                    // Auto-detect delimiter based on occurrences in the line
                    $delimiter = (substr_count($lineStr, ';') > substr_count($lineStr, ',')) ? ';' : ',';
                    $data = str_getcsv(trim($lineStr), $delimiter);
                    
                    // Ignore titles or empty rows from Excel export
                    if (count($data) < 5) {
                        continue;
                    }
                    
                    // Ignore header row
                    if (stripos($data[1] ?? '', 'produto') !== false || stripos($data[2] ?? '', 'código') !== false) {
                        continue;
                    }
                    
                    // Fallbacks and parsing
                    $nome = isset($data[1]) ? trim($data[1]) : '';
                    $codigo = isset($data[2]) ? trim($data[2]) : '';
                    $categoria = isset($data[3]) ? trim($data[3]) : 'Diversos';
                    
                    $parseNumber = function($val) {
                        $val = str_replace('.', '', preg_replace('/[^0-9.,-]/', '', $val));
                        $val = str_replace(',', '.', $val);
                        return (float)$val;
                    };
                    
                    $estoque = isset($data[5]) ? $parseNumber($data[5]) : 0;
                    $precoCusto = isset($data[7]) ? $parseNumber($data[7]) : 0;
                    $precoVenda = isset($data[8]) ? $parseNumber($data[8]) : 0;
                    $unidade = !empty($data[9]) ? trim($data[9]) : 'UN';
                    
                    if (empty($nome)) {
                        continue;
                    }
                    
                    if (empty($codigo)) {
                        $codigo = 'IMP' . time() . rand(1000, 9999);
                    }

                    $stmtCheck->execute([$codigo]);
                    $exists = $stmtCheck->fetchColumn();

                    if ($exists) {
                        $stmtUpdate->execute([$nome, $categoria, $precoCusto, $precoVenda, $estoque, substr($unidade, 0, 3), $codigo]);
                        $updated++;
                    } else {
                        $stmtInsert->execute([$codigo, $nome, substr($unidade, 0, 3), $categoria, $precoCusto, $precoVenda, $estoque, $codigo]);
                        $inserted++;
                    }
                }
                
                if ($inserted == 0 && $updated == 0) {
                    $error = "Nenhum produto válido encontrado. Verifique se o arquivo segue as colunas corretas.";
                    $db->rollBack();
                } else {
                    $db->commit();
                    $message = "Importação concluída! $inserted inseridos e $updated atualizados.";
                }
            } catch (\Exception $e) {
                $db->rollBack();
                $error = "Erro no banco na linha $rowIndex: " . $e->getMessage();
            }
            fclose($handle);
        } else {
            $error = "Não foi possível abrir o arquivo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador em Massa - Hiper ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .upload-card { border: 2px dashed #dee2e6; border-radius: 10px; background: #fff; padding: 40px; text-align: center; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-file-import fa-2x text-primary me-3"></i>
                    <div>
                        <h2 class="mb-0 fw-bold">Importador de Estoque (Hiper para ERP)</h2>
                        <p class="text-muted mb-0">Ferramenta temporária para carga inicial através de planilha</p>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success fw-bold"><i class="fas fa-check-circle me-2"></i><?= $message ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger fw-bold"><i class="fas fa-exclamation-triangle me-2"></i><?= $error ?></div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>Instruções de Preparação</h5>
                        <ol class="mb-0 text-muted small">
                            <li class="mb-1">Exporte a Listagem de Produtos do Hiper para <b>PDF</b> ou mande extrair como <b>Excel (.xlsx)</b>.</li>
                            <li class="mb-1">Se você possuir o PDF, use um site (como o iLovePDF.com) para converter "PDF para Excel".</li>
                            <li class="mb-1">Abra o Excel e <b>salve o arquivo como CSV (Separado por Vírgulas)</b>.</li>
                            <li>Faça o upload do arquivo CSV gerado na caixa abaixo. Ele deve manter as colunas na mesma ordem da foto enviada (Nome na Coluna B, Código na C, Preços H e I, etc).</li>
                        </ol>
                    </div>
                </div>

                <div class="upload-card shadow-sm">
                    <form action="importador_massa.php" method="POST" enctype="multipart/form-data">
                        <i class="fas fa-file-csv fa-4x text-muted mb-3 opacity-50"></i>
                        <h4 class="fw-bold mb-2">Selecione o arquivo CSV</h4>
                        <p class="text-muted small mb-4">Apenas arquivos no formato .csv</p>
                        
                        <input type="file" name="arquivo_csv" class="form-control mb-4 w-75 mx-auto" accept=".csv" required>
                        
                        <button type="submit" class="btn btn-primary btn-lg px-5 fw-bold shadow-sm">
                            <i class="fas fa-upload me-2"></i> INICIAR IMPORTAÇÃO MACIÇA
                        </button>
                    </form>
                </div>
                
                <div class="text-center mt-4">
                    <a href="estoque.php" class="btn btn-light border shadow-sm fw-bold">
                        <i class="fas fa-arrow-left me-2"></i> Voltar ao ERP Principal
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

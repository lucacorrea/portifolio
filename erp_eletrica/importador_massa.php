<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/App/Config/Database.php';

if (empty($_SESSION['usuario_id']) || !in_array($_SESSION['usuario_nivel'], ['master', 'admin'])) {
    die("Acesso restrito.");
}

$db = \App\Config\Database::getInstance()->getConnection();

// Process JSON payload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'importar_json') {
    $input = file_get_contents('php://input');
    $produtos = json_decode($input, true);
    
    if (!$produtos || !is_array($produtos)) {
        echo json_encode(['success' => false, 'error' => 'Nenhum dado válido recebido.']);
        exit;
    }

    $inserted = 0;
    $updated = 0;
    $errors = 0;
    
    try {
        $db->beginTransaction();
        
        $stmtCheck = $db->prepare("SELECT id FROM produtos WHERE codigo = ?");
        $stmtInsert = $db->prepare("
            INSERT INTO produtos (codigo, nome, unidade, categoria, preco_custo, preco_venda, quantidade, tipo_produto, cean, cfop_interno, cfop_externo, csosn, origem) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'simples', ?, '5102', '6102', '102', 0)
        ");
        $stmtUpdate = $db->prepare("
            UPDATE produtos SET nome = ?, categoria = ?, preco_custo = ?, preco_venda = ?, quantidade = quantidade + ?, unidade = ? WHERE codigo = ?
        ");

        foreach ($produtos as $p) {
            $nome = $p['nome'];
            $codigo = $p['codigo'] ?: ('IMP' . time() . rand(100, 999));
            $categoria = $p['categoria'];
            $precoCusto = (float)$p['preco_custo'];
            $precoVenda = (float)$p['preco_venda'];
            $estoque = (float)$p['estoque'];
            $unidade = substr($p['unidade'], 0, 3);

            $stmtCheck->execute([$codigo]);
            if ($stmtCheck->fetchColumn()) {
                $stmtUpdate->execute([$nome, $categoria, $precoCusto, $precoVenda, $estoque, $unidade, $codigo]);
                $updated++;
            } else {
                $stmtInsert->execute([$codigo, $nome, $unidade, $categoria, $precoCusto, $precoVenda, $estoque, $codigo]);
                $inserted++;
            }
        }
        
        $db->commit();
        echo json_encode(['success' => true, 'inserted' => $inserted, 'updated' => $updated]);
    } catch (\Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador Inteligente - Hiper ERP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SheetJS (Lê Excel direto no navegador e ignora as abas) -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <style>
        body { background-color: #f8f9fa; }
        .upload-card { border: 2px dashed #dee2e6; border-radius: 10px; background: #fff; padding: 40px; text-align: center; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="d-flex align-items-center mb-4">
                    <i class="fas fa-file-excel fa-2x text-success me-3"></i>
                    <div>
                        <h2 class="mb-0 fw-bold">Importador Inteligente de Excel (.xlsx)</h2>
                        <p class="text-muted mb-0">Esta página vai juntar todas as 158 abas automaticamente!</p>
                    </div>
                </div>

                <div id="alertArea"></div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>Tudo resolvido!</h5>
                        <p class="mb-0 text-muted small">
                            Como o seu PDF gerou um arquivo Excel com múltiplas abas (Table 1, Table 2, etc), salvar como CSV não vai funcionar porque o Excel só salva a aba que estiver aberta. <br><br>
                            Em vez disso, nós adaptamos esse sistema para <b>ler o arquivo Excel (.xlsx) diretamente</b>. Ele vai entrar em cada uma das 158 abas, extrair os produtos, juntar tudo em uma lista gigante e salvar de uma vez no banco de dados!
                        </p>
                    </div>
                </div>

                <div class="upload-card shadow-sm">
                    <i class="fas fa-file-excel fa-4x text-success mb-3 opacity-50"></i>
                    <h4 class="fw-bold mb-2">Selecione a Planilha do Excel</h4>
                    <p class="text-muted small mb-4">Selecione o arquivo "lista_produtos.xlsx" exato que você baixou do iLovePDF.</p>
                    
                    <input type="file" id="arquivo_excel" class="form-control mb-4 w-75 mx-auto" accept=".xlsx,.xls">
                    
                    <button type="button" onclick="processarExcel()" id="btnProcessar" class="btn btn-success btn-lg px-5 fw-bold shadow-sm">
                        <i class="fas fa-magic me-2"></i> PROCESSAR E IMPORTAR
                    </button>
                    
                    <div id="loaderArea" class="mt-4 d-none">
                        <div class="spinner-border text-success" role="status"></div>
                        <h6 class="mt-2 fw-bold text-success" id="loadingText">Lendo abas do Excel...</h6>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="estoque.php" class="btn btn-light border shadow-sm fw-bold">
                        <i class="fas fa-arrow-left me-2"></i> Voltar ao ERP Principal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
    async function processarExcel() {
        const fileInput = document.getElementById('arquivo_excel');
        const file = fileInput.files[0];
        
        if (!file) {
            showAlert('Por favor, selecione o arquivo Excel (.xlsx)', 'danger');
            return;
        }

        document.getElementById('btnProcessar').classList.add('d-none');
        document.getElementById('loaderArea').classList.remove('d-none');
        document.getElementById('loadingText').innerText = 'Unindo as 158 abas, por favor aguarde...';

        const reader = new FileReader();
        reader.onload = async function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, {type: 'array'});
                
                let todosOsProdutos = [];
                let rowCount = 0;

                // Percorrer TODAS as abas (Table 1, Table 2, etc...)
                workbook.SheetNames.forEach(sheetName => {
                    const sheet = workbook.Sheets[sheetName];
                    // Ler toda a aba como Array de Arrays (A=0, B=1, C=2, etc)
                    const rows = XLSX.utils.sheet_to_json(sheet, {header: 1, defval: ""});
                    
                    rows.forEach(row => {
                        // Ignorar cabeçalho, títulos ou linhas vazias (precisa ter no mínimo o nome do produto na coluna B)
                        if (row.length < 5) return;
                        
                        const nome = row[1] ? row[1].toString().trim() : '';
                        const codigo = row[2] ? row[2].toString().trim() : '';
                        
                        // Ignorar se contiver a palavra "Produto" ou "código" (cabeçalhos)
                        if (nome.toLowerCase().includes('produto') && codigo.toLowerCase().includes('código')) return;
                        if (!nome || nome === 'Produto') return;
                        
                        // Limpa números formato PT-BR
                        const parseNumber = (val) => {
                            if (!val && val !== 0) return 0;
                            if (typeof val === 'number') return val;
                            let v = val.toString().replace(/\./g, '').replace(/[^-0-9,]/g, '').replace(',', '.');
                            return parseFloat(v) || 0;
                        };

                        todosOsProdutos.push({
                            nome: nome,
                            codigo: codigo,
                            categoria: row[3] ? row[3].toString().trim() : 'Diversos',
                            estoque: parseNumber(row[5]),
                            preco_custo: parseNumber(row[7]),
                            preco_venda: parseNumber(row[8]),
                            unidade: row[9] ? row[9].toString().trim() : 'UN'
                        });
                        rowCount++;
                    });
                });

                if (todosOsProdutos.length === 0) {
                    throw new Error("Não conseguimos encontrar nenhum produto válido com preços nas abas. Verifique a planilha.");
                }

                document.getElementById('loadingText').innerText = `Enviando ${todosOsProdutos.length} produtos para o Banco de Dados...`;

                // Submeter para o PHP usando chunks de 300 produtos para segurança se for gigantesco, ou tudo de uma vez.
                // Como não deve ter mais que 2000 produtos em 158 pgs, 1 request só funciona bem.
                const response = await fetch('importador_massa.php?action=importar_json', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(todosOsProdutos)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert(`<i class="fas fa-check-circle"></i> SUCESSO ABSOLUTO! ${result.inserted} novos foram inseridos e ${result.updated} atualizados através das ${workbook.SheetNames.length} abas lidas!`, 'success');
                } else {
                    showAlert('Erro no banco: ' + result.error, 'danger');
                }

            } catch (err) {
                showAlert('Erro ao ler a planilha: ' + err.message, 'danger');
            } finally {
                document.getElementById('btnProcessar').classList.remove('d-none');
                document.getElementById('loaderArea').classList.add('d-none');
            }
        };
        
        reader.readAsArrayBuffer(file);
    }

    function showAlert(msg, type) {
        document.getElementById('alertArea').innerHTML = `<div class="alert alert-${type} fw-bold">${msg}</div>`;
    }
    </script>
</body>
</html>

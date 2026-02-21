<?php
require_once 'config.php';
checkAuth();

// Dados para os selects
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();
$produtos = $pdo->query("SELECT id, nome, preco_venda, quantidade, unidade FROM produtos ORDER BY nome")->fetchAll();
$tecnicos = $pdo->query("SELECT id, nome FROM usuarios WHERE nivel IN ('admin', 'tecnico') AND ativo = 1 ORDER BY nome")->fetchAll();

$proximo_numero = gerarProximoNumeroOS($pdo);

// Processar POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Inserir OS Base
        $stmt = $pdo->prepare("
            INSERT INTO os (numero_os, cliente_id, tecnico_id, data_abertura, data_previsao, descricao, status, valor_total) 
            VALUES (?, ?, ?, ?, ?, ?, 'orcamento', ?)
        ");
        
        $valor_total = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['valor_total_final']);
        
        $stmt->execute([
            $_POST['numero_os'],
            $_POST['cliente_id'],
            $_POST['tecnico_id'] ?: null,
            $_POST['data_abertura'],
            $_POST['data_previsao'] ?: null,
            $_POST['descricao'],
            $valor_total
        ]);
        
        $os_id = $pdo->lastInsertId();
        
        // Inserir Itens (Produtos)
        if (isset($_POST['item_id']) && is_array($_POST['item_id'])) {
            $stmt = $pdo->prepare("
                INSERT INTO itens_os (os_id, produto_id, quantidade, valor_unitario, subtotal) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['item_id'] as $key => $prod_id) {
                if ($prod_id) {
                    $qtd = $_POST['item_qtd'][$key];
                    $val = str_replace(['R$', '.', ','], ['', '', '.'], $_POST['item_preco'][$key]);
                    $sub = $qtd * $val;
                    $stmt->execute([$os_id, $prod_id, $qtd, $val, $sub]);
                }
            }
        }
        
        // Histórico Inicial
        $stmt = $pdo->prepare("INSERT INTO os_historico (os_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?, NULL, 'orcamento', ?, 'Abertura da Ordem de Serviço')");
        $stmt->execute([$os_id, $_SESSION['usuario_id']]);
        
        $pdo->commit();
        header("Location: os_detalhes.php?id=$os_id&msg=OS $proximo_numero criada com sucesso");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erro técnico ao salvar OS: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abertura de OS Técnica - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .item-row { border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .item-row:last-child { border: none; }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="top-bar">
                <div style="display: flex; align-items: center;">
                    <button class="toggle-sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Abertura de Ordem de Serviço</h1>
                </div>
            </header>
            
            <main class="dash-content fade-in">
                <?php if (isset($error)): ?>
                    <div class="card" style="background: #ffebee; border-bottom: 3px solid var(--danger-color); padding: 15px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-triangle" style="color: var(--danger-color);"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="formNovaOS">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                        <!-- Coluna Principal -->
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <div class="card">
                                <div class="card-header"><h3 class="card-title">Informações Gerais</h3></div>
                                <div style="padding: 20px;">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1.5;">
                                            <label class="form-label">Cliente / Solicitante *</label>
                                            <select name="cliente_id" class="form-control" required>
                                                <option value="">Selecione o cliente...</option>
                                                <?php foreach ($clientes as $c): ?>
                                                    <option value="<?php echo $c['id']; ?>"><?php echo $c['nome']; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Técnico Responsável</label>
                                            <select name="tecnico_id" class="form-control">
                                                <option value="">Atribuir depois...</option>
                                                <?php foreach ($tecnicos as $t): ?>
                                                    <option value="<?php echo $t['id']; ?>" <?php echo $t['id'] == $_SESSION['usuario_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $t['nome']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Descrição Técnica do Defeito / Solicitação *</label>
                                        <textarea name="descricao" class="form-control" rows="4" required placeholder="Descreva detalhadamente o serviço a ser executado..."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                                    <h3 class="card-title">Materiais e Componentes</h3>
                                    <button type="button" class="btn btn-outline" style="padding: 5px 10px;" onclick="addItemRow()">
                                        <i class="fas fa-plus"></i> Adicionar Item
                                    </button>
                                </div>
                                <div style="padding: 20px;" id="itemsContainer">
                                    <!-- Dynamic rows here -->
                                </div>
                            </div>
                        </div>

                        <!-- Coluna Lateral -->
                        <div style="display: flex; flex-direction: column; gap: 20px;">
                            <div class="card" style="background: var(--secondary-color); color: white;">
                                <div class="card-header"><h3 class="card-title" style="color: white;">Dados do Registro</h3></div>
                                <div style="padding: 20px;">
                                    <div class="form-group">
                                        <label class="form-label" style="color: rgba(255,255,255,0.7);">Número Identificador</label>
                                        <input type="text" name="numero_os" class="form-control" value="<?php echo $proximo_numero; ?>" readonly style="background: rgba(255,255,255,0.1); color: white; border: none; font-family: 'Roboto Mono'; font-weight: 700;">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="color: rgba(255,255,255,0.7);">Data de Abertura</label>
                                        <input type="date" name="data_abertura" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" style="color: rgba(255,255,255,0.7);">Previsão da Entrega</label>
                                        <input type="date" name="data_previsao" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header"><h3 class="card-title">Resumo Financeiro</h3></div>
                                <div style="padding: 20px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                        <span>Subtotal Itens:</span>
                                        <span id="subtotalItens">R$ 0,00</span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px; font-weight: 700; font-size: 1.2rem; border-top: 1px solid #eee; padding-top: 10px;">
                                        <span>Total OS:</span>
                                        <input type="hidden" name="valor_total_final" id="inputTotalFinal" value="0.00">
                                        <span id="totalFinal" style="color: var(--primary-color);">R$ 0,00</span>
                                    </div>
                                    
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <button type="submit" class="btn btn-primary" style="width: 100%; border-radius: 4px; padding: 12px;">
                                            <i class="fas fa-save"></i> FINALIZAR ABERTURA
                                        </button>
                                        <a href="os.php" class="btn btn-outline" style="width: 100%; text-align: center;">Cancelar</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Template para Linha de Item -->
    <template id="itemTemplate">
        <div class="item-row">
            <div style="display: grid; grid-template-columns: 3fr 1fr 1.5fr 1.5fr 40px; gap: 10px;">
                <div class="form-group">
                    <select name="item_id[]" class="form-control prod-select" onchange="updateProdDetails(this)">
                        <option value="">Selecionar componente...</option>
                        <?php foreach ($produtos as $p): ?>
                            <option value="<?php echo $p['id']; ?>" data-preco="<?php echo $p['preco_venda']; ?>" data-un="<?php echo $p['unidade']; ?>">
                                <?php echo $p['nome']; ?> (Est: <?php echo $p['quantidade']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <input type="number" name="item_qtd[]" class="form-control item-qtd" value="1" min="1" onchange="calcRows()">
                </div>
                <div class="form-group">
                    <input type="text" name="item_preco[]" class="form-control item-preco money" readonly>
                </div>
                <div class="form-group">
                    <input type="text" class="form-control item-subtotal" readonly value="R$ 0,00">
                </div>
                <div>
                    <button type="button" class="btn btn-outline" style="padding: 7px; color: var(--danger-color); border-color: #eee;" onclick="this.closest('.item-row').remove(); calcRows();">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <script src="script.js"></script>
    <script>
        function addItemRow() {
            const container = document.getElementById('itemsContainer');
            const template = document.getElementById('itemTemplate');
            const clone = template.content.cloneNode(true);
            container.appendChild(clone);
            
            // Re-aplicar máscaras se necessário
            if (window.initMasks) window.initMasks();
        }

        function updateProdDetails(select) {
            const row = select.closest('.item-row');
            const option = select.selectedOptions[0];
            const price = parseFloat(option.dataset.preco || 0);
            
            row.querySelector('.item-preco').value = 'R$ ' + price.toFixed(2).replace('.', ',');
            calcRows();
        }

        function calcRows() {
            const rows = document.querySelectorAll('.item-row');
            let totalItens = 0;
            
            rows.forEach(row => {
                const qtd = parseFloat(row.querySelector('.item-qtd').value || 0);
                const precoStr = row.querySelector('.item-preco').value.replace('R$ ', '').replace('.', '').replace(',', '.');
                const preco = parseFloat(precoStr || 0);
                
                const sub = qtd * preco;
                row.querySelector('.item-subtotal').value = 'R$ ' + sub.toFixed(2).replace('.', ',');
                totalItens += sub;
            });

            document.getElementById('subtotalItens').innerText = 'R$ ' + totalItens.toFixed(2).replace('.', ',');
            document.getElementById('totalFinal').innerText = 'R$ ' + totalItens.toFixed(2).replace('.', ',');
            document.getElementById('inputTotalFinal').value = totalItens.toFixed(2);
        }

        // Iniciar com uma linha vazia
        addItemRow();
    </script>
</body>
</html>

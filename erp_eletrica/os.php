<?php
require_once 'config.php';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            try {
                $pdo->beginTransaction();
                
                // Gerar número da OS
                $numero_os = 'OS' . date('Ymd') . rand(100, 999);
                
                // Inserir OS
                $stmt = $pdo->prepare("
                    INSERT INTO os (numero_os, cliente_id, data_abertura, descricao, valor_total, status) 
                    VALUES (?, ?, ?, ?, ?, 'aberta')
                ");
                $stmt->execute([
                    $numero_os,
                    $_POST['cliente_id'],
                    $_POST['data_abertura'],
                    $_POST['descricao'],
                    $_POST['valor_total']
                ]);
                
                $os_id = $pdo->lastInsertId();
                
                // Inserir itens da OS
                if (isset($_POST['produtos']) && is_array($_POST['produtos'])) {
                    for ($i = 0; $i < count($_POST['produtos']); $i++) {
                        if (!empty($_POST['produtos'][$i])) {
                            $stmt = $pdo->prepare("
                                INSERT INTO itens_os (os_id, produto_id, quantidade, valor_unitario, subtotal) 
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $os_id,
                                $_POST['produtos'][$i],
                                $_POST['quantidades'][$i],
                                $_POST['valores'][$i],
                                $_POST['subtotais'][$i]
                            ]);
                            
                            // Atualizar estoque
                            $stmt2 = $pdo->prepare("
                                UPDATE produtos 
                                SET quantidade = quantidade - ? 
                                WHERE id = ?
                            ");
                            $stmt2->execute([
                                $_POST['quantidades'][$i],
                                $_POST['produtos'][$i]
                            ]);
                        }
                    }
                }
                
                // Criar conta a receber
                $stmt = $pdo->prepare("
                    INSERT INTO contas_receber (os_id, descricao, valor, data_vencimento, status) 
                    VALUES (?, ?, ?, ?, 'pendente')
                ");
                $stmt->execute([
                    $os_id,
                    'OS ' . $numero_os,
                    $_POST['valor_total'],
                    $_POST['data_vencimento']
                ]);
                
                $pdo->commit();
                
                header('Location: os.php?msg=OS criada com sucesso');
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Erro ao criar OS: " . $e->getMessage();
            }
        }
        
        if ($_POST['action'] == 'update_status') {
            $stmt = $pdo->prepare("UPDATE os SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['id']]);
            
            if ($_POST['status'] == 'concluida') {
                $stmt = $pdo->prepare("
                    UPDATE os 
                    SET data_conclusao = CURRENT_DATE 
                    WHERE id = ?
                ");
                $stmt->execute([$_POST['id']]);
            }
            
            header('Location: os.php?msg=Status atualizado');
            exit;
        }
    }
}

// Buscar OS
$os_list = $pdo->query("
    SELECT os.*, clientes.nome as cliente_nome 
    FROM os 
    JOIN clientes ON os.cliente_id = clientes.id 
    ORDER BY os.created_at DESC
")->fetchAll();

// Buscar clientes para o select
$clientes = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome")->fetchAll();

// Buscar produtos para o select
$produtos = $pdo->query("SELECT id, nome, preco_venda, quantidade FROM produtos WHERE quantidade > 0 ORDER BY nome")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Ordens de Serviço</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Ordens de Serviço</h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                    <button class="btn btn-primary" onclick="openModal('modalOS')">
                        <i class="fas fa-plus"></i> Nova OS
                    </button>
                </div>
            </div>
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="notification notification-success">
                    <?php echo $_GET['msg']; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="notification notification-error">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="form-row" style="margin-bottom: 20px;">
                <div class="form-group">
                    <select id="filtro_status" class="form-control" style="width: 200px;">
                        <option value="">Todos os status</option>
                        <option value="aberta">Abertas</option>
                        <option value="em_andamento">Em Andamento</option>
                        <option value="concluida">Concluídas</option>
                        <option value="cancelada">Canceladas</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" id="busca_cliente" class="form-control" placeholder="Buscar por cliente..." style="width: 300px;">
                </div>
            </div>
            
            <div class="table-container">
                <table class="datatable" id="tabelaOS">
                    <thead>
                        <tr>
                            <th>Nº OS</th>
                            <th>Cliente</th>
                            <th>Data Abertura</th>
                            <th>Data Conclusão</th>
                            <th>Status</th>
                            <th>Valor Total</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($os_list as $os): ?>
                        <tr data-status="<?php echo $os['status']; ?>">
                            <td><?php echo $os['numero_os']; ?></td>
                            <td class="cliente-nome"><?php echo $os['cliente_nome']; ?></td>
                            <td><?php echo formatarData($os['data_abertura']); ?></td>
                            <td><?php echo $os['data_conclusao'] ? formatarData($os['data_conclusao']) : '-'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $os['status']; ?>">
                                    <?php 
                                    $status_text = [
                                        'aberta' => 'Aberta',
                                        'em_andamento' => 'Em Andamento',
                                        'concluida' => 'Concluída',
                                        'cancelada' => 'Cancelada'
                                    ];
                                    echo $status_text[$os['status']];
                                    ?>
                                </span>
                            </td>
                            <td><?php echo formatarMoeda($os['valor_total']); ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="verOS(<?php echo $os['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($os['status'] != 'concluida' && $os['status'] != 'cancelada'): ?>
                                <button class="btn btn-success btn-sm" onclick="atualizarStatus(<?php echo $os['id']; ?>, 'concluida')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="atualizarStatus(<?php echo $os['id']; ?>, 'em_andamento')">
                                    <i class="fas fa-play"></i>
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-danger btn-sm" onclick="imprimirOS(<?php echo $os['id']; ?>)">
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Nova OS -->
    <div id="modalOS" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <span class="close">&times;</span>
            <h2>Nova Ordem de Serviço</h2>
            
            <form method="POST" id="formOS">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Cliente *</label>
                        <select name="cliente_id" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>"><?php echo $cliente['nome']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Data Abertura *</label>
                        <input type="date" name="data_abertura" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Data Vencimento *</label>
                        <input type="date" name="data_vencimento" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Descrição do Serviço</label>
                    <textarea name="descricao" class="form-control" rows="3"></textarea>
                </div>
                
                <h3>Itens da OS</h3>
                <div id="osItems">
                    <!-- Items will be added here -->
                </div>
                
                <button type="button" class="btn btn-primary" onclick="addOSItem()">
                    <i class="fas fa-plus"></i> Adicionar Item
                </button>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label>Valor Total</label>
                    <input type="text" id="osTotal" name="valor_total" class="form-control" readonly value="R$ 0,00">
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Criar OS
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal Visualizar OS -->
    <div id="modalVerOS" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Detalhes da OS</h2>
            <div id="osDetalhes">
                <!-- OS details will be loaded here -->
            </div>
        </div>
    </div>
    
    <!-- Form de atualização de status -->
    <form id="formStatus" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" id="status_os_id">
        <input type="hidden" name="status" id="status_novo_status">
    </form>
    
    <script src="script.js"></script>
    <script>
        // Dados dos produtos para o select
        const produtos = <?php echo json_encode($produtos); ?>;
        
        function addOSItem() {
            const container = document.getElementById('osItems');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'form-row os-item';
            itemDiv.style.marginBottom = '10px';
            itemDiv.style.padding = '10px';
            itemDiv.style.border = '1px solid #ddd';
            itemDiv.style.borderRadius = '5px';
            
            let options = '<option value="">Selecione...</option>';
            produtos.forEach(p => {
                options += `<option value="${p.id}" data-preco="${p.preco_venda}" data-estoque="${p.quantidade}">${p.nome} (Estoque: ${p.quantidade})</option>`;
            });
            
            itemDiv.innerHTML = `
                <div class="form-group" style="flex: 2;">
                    <label>Produto</label>
                    <select class="form-control produto-select" required onchange="atualizarPrecoItem(this)">
                        ${options}
                    </select>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Quantidade</label>
                    <input type="number" class="form-control quantidade" min="1" value="1" required onchange="calcularSubtotal(this)">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Valor Unit.</label>
                    <input type="text" class="form-control valor-unitario money" readonly>
                </div>
                <div class="form-group" style="flex: 1;">
                    <label>Subtotal</label>
                    <input type="text" class="form-control subtotal" readonly>
                </div>
                <div class="form-group" style="flex: 0.5;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger" style="display: block;" onclick="this.closest('.os-item').remove(); calcularTotal()">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            
            container.appendChild(itemDiv);
        }
        
        function atualizarPrecoItem(select) {
            const itemDiv = select.closest('.os-item');
            const preco = select.selectedOptions[0]?.dataset.preco || 0;
            const valorUnitario = itemDiv.querySelector('.valor-unitario');
            
            valorUnitario.value = 'R$ ' + parseFloat(preco).toFixed(2).replace('.', ',');
            
            calcularSubtotal(itemDiv.querySelector('.quantidade'));
        }
        
        function calcularSubtotal(input) {
            const itemDiv = input.closest('.os-item');
            const quantidade = parseFloat(input.value) || 0;
            const valorUnitario = parseFloat(itemDiv.querySelector('.valor-unitario').value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            const subtotal = quantidade * valorUnitario;
            
            itemDiv.querySelector('.subtotal').value = 'R$ ' + subtotal.toFixed(2).replace('.', ',');
            
            calcularTotal();
        }
        
        function calcularTotal() {
            const subtotals = document.querySelectorAll('.subtotal');
            let total = 0;
            
            subtotals.forEach(sub => {
                const valor = parseFloat(sub.value.replace(/[^\d,]/g, '').replace(',', '.')) || 0;
                total += valor;
            });
            
            document.getElementById('osTotal').value = 'R$ ' + total.toFixed(2).replace('.', ',');
        }
        
        // Preparar formulário para envio
        document.getElementById('formOS').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const items = document.querySelectorAll('.os-item');
            const produtos = [];
            const quantidades = [];
            const valores = [];
            const subtotais = [];
            
            items.forEach(item => {
                const produtoSelect = item.querySelector('.produto-select');
                const quantidade = item.querySelector('.quantidade');
                const valorUnitario = item.querySelector('.valor-unitario');
                const subtotal = item.querySelector('.subtotal');
                
                if (produtoSelect.value) {
                    produtos.push(produtoSelect.value);
                    quantidades.push(quantidade.value);
                    valores.push(valorUnitario.value.replace('R$ ', '').replace('.', '').replace(',', '.'));
                    subtotais.push(subtotal.value.replace('R$ ', '').replace('.', '').replace(',', '.'));
                }
            });
            
            // Adicionar arrays ao formulário
            produtos.forEach((p, i) => {
                const inputP = document.createElement('input');
                inputP.type = 'hidden';
                inputP.name = `produtos[${i}]`;
                inputP.value = p;
                this.appendChild(inputP);
                
                const inputQ = document.createElement('input');
                inputQ.type = 'hidden';
                inputQ.name = `quantidades[${i}]`;
                inputQ.value = quantidades[i];
                this.appendChild(inputQ);
                
                const inputV = document.createElement('input');
                inputV.type = 'hidden';
                inputV.name = `valores[${i}]`;
                inputV.value = valores[i];
                this.appendChild(inputV);
                
                const inputS = document.createElement('input');
                inputS.type = 'hidden';
                inputS.name = `subtotais[${i}]`;
                inputS.value = subtotais[i];
                this.appendChild(inputS);
            });
            
            this.submit();
        });
        
        function atualizarStatus(id, status) {
            if (confirm('Tem certeza que deseja alterar o status desta OS?')) {
                document.getElementById('status_os_id').value = id;
                document.getElementById('status_novo_status').value = status;
                document.getElementById('formStatus').submit();
            }
        }
        
        function verOS(id) {
            // Aqui você faria uma requisição AJAX para buscar os detalhes da OS
            // Por enquanto, vamos apenas mostrar uma mensagem
            alert('Funcionalidade em desenvolvimento: Visualizar OS #' + id);
        }
        
        function imprimirOS(id) {
            window.open('imprimir_os.php?id=' + id, '_blank');
        }
        
        // Filtros
        document.getElementById('filtro_status').addEventListener('change', function() {
            filtrarTabela();
        });
        
        document.getElementById('busca_cliente').addEventListener('keyup', function() {
            filtrarTabela();
        });
        
        function filtrarTabela() {
            const statusFiltro = document.getElementById('filtro_status').value;
            const buscaCliente = document.getElementById('busca_cliente').value.toLowerCase();
            const linhas = document.querySelectorAll('#tabelaOS tbody tr');
            
            linhas.forEach(linha => {
                const status = linha.dataset.status;
                const cliente = linha.querySelector('.cliente-nome').textContent.toLowerCase();
                
                const statusMatch = !statusFiltro || status === statusFiltro;
                const clienteMatch = !buscaCliente || cliente.includes(buscaCliente);
                
                linha.style.display = statusMatch && clienteMatch ? '' : 'none';
            });
        }
    </script>
</body>
</html>
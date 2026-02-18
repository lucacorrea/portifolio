<?php
require_once 'config.php';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO produtos (codigo, nome, descricao, categoria, preco_custo, preco_venda, quantidade, estoque_minimo) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['nome'],
                $_POST['descricao'],
                $_POST['categoria'],
                $_POST['preco_custo'],
                $_POST['preco_venda'],
                $_POST['quantidade'],
                $_POST['estoque_minimo']
            ]);
            
            header('Location: produtos.php?msg=Produto adicionado com sucesso');
            exit;
        }
        
        if ($_POST['action'] == 'edit') {
            $stmt = $pdo->prepare("
                UPDATE produtos 
                SET codigo = ?, nome = ?, descricao = ?, categoria = ?, 
                    preco_custo = ?, preco_venda = ?, quantidade = ?, estoque_minimo = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['nome'],
                $_POST['descricao'],
                $_POST['categoria'],
                $_POST['preco_custo'],
                $_POST['preco_venda'],
                $_POST['quantidade'],
                $_POST['estoque_minimo'],
                $_POST['id']
            ]);
            
            header('Location: produtos.php?msg=Produto atualizado com sucesso');
            exit;
        }
        
        if ($_POST['action'] == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            header('Location: produtos.php?msg=Produto excluído com sucesso');
            exit;
        }
    }
}

// Buscar produtos
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();

// Categorias predefinidas
$categorias = [
    'Fiação',
    'Disjuntores',
    'Tomadas',
    'Interruptores',
    'Lâmpadas',
    'Quadros Elétricos',
    'Ferramentas',
    'Outros'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Produtos</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Produtos</h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                    <button class="btn btn-primary" onclick="openModal('modalProduto')">
                        <i class="fas fa-plus"></i> Novo Produto
                    </button>
                </div>
            </div>
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="notification notification-success">
                    <?php echo $_GET['msg']; ?>
                </div>
            <?php endif; ?>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total de Produtos</h3>
                        <div class="stat-value"><?php echo count($produtos); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Valor em Estoque</h3>
                        <div class="stat-value">
                            <?php
                            $valor_estoque = $pdo->query("
                                SELECT COALESCE(SUM(preco_custo * quantidade), 0) 
                                FROM produtos
                            ")->fetchColumn();
                            echo formatarMoeda($valor_estoque);
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Produtos em Estoque Baixo</h3>
                        <div class="stat-value">
                            <?php
                            $estoque_baixo = $pdo->query("
                                SELECT COUNT(*) 
                                FROM produtos 
                                WHERE quantidade <= estoque_minimo
                            ")->fetchColumn();
                            echo $estoque_baixo;
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle" style="color: #f39c12;"></i>
                    </div>
                </div>
            </div>
            
            <div class="table-container">
                <table class="datatable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Categoria</th>
                            <th>Preço Custo</th>
                            <th>Preço Venda</th>
                            <th>Quantidade</th>
                            <th>Estoque Mínimo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): ?>
                        <tr>
                            <td><?php echo $produto['codigo']; ?></td>
                            <td><?php echo $produto['nome']; ?></td>
                            <td><?php echo $produto['categoria']; ?></td>
                            <td><?php echo formatarMoeda($produto['preco_custo']); ?></td>
                            <td><?php echo formatarMoeda($produto['preco_venda']); ?></td>
                            <td><?php echo $produto['quantidade']; ?></td>
                            <td><?php echo $produto['estoque_minimo']; ?></td>
                            <td>
                                <?php if ($produto['quantidade'] <= $produto['estoque_minimo']): ?>
                                    <span class="status-badge status-atrasado">Estoque Baixo</span>
                                <?php else: ?>
                                    <span class="status-badge status-pago">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editarProduto(<?php echo htmlspecialchars(json_encode($produto)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="excluirProduto(<?php echo $produto['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Produto -->
    <div id="modalProduto" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Novo Produto</h2>
            
            <form method="POST" data-validate>
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="codigo" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Categoria *</label>
                        <select name="categoria" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço Custo *</label>
                        <input type="text" name="preco_custo" class="form-control money" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Preço Venda *</label>
                        <input type="text" name="preco_venda" class="form-control money" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade Inicial *</label>
                        <input type="number" name="quantidade" class="form-control" min="0" value="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Estoque Mínimo *</label>
                        <input type="number" name="estoque_minimo" class="form-control" min="0" value="5" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal Editar Produto -->
    <div id="modalEditarProduto" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Editar Produto</h2>
            
            <form method="POST" data-validate>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="codigo" id="edit_codigo" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Categoria *</label>
                        <select name="categoria" id="edit_categoria" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="edit_nome" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" id="edit_descricao" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço Custo *</label>
                        <input type="text" name="preco_custo" id="edit_preco_custo" class="form-control money" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Preço Venda *</label>
                        <input type="text" name="preco_venda" id="edit_preco_venda" class="form-control money" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade *</label>
                        <input type="number" name="quantidade" id="edit_quantidade" class="form-control" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Estoque Mínimo *</label>
                        <input type="number" name="estoque_minimo" id="edit_estoque_minimo" class="form-control" min="0" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Atualizar
                </button>
            </form>
        </div>
    </div>
    
    <!-- Form de exclusão -->
    <form id="formDelete" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>
    
    <script src="script.js"></script>
    <script>
        function editarProduto(produto) {
            document.getElementById('edit_id').value = produto.id;
            document.getElementById('edit_codigo').value = produto.codigo;
            document.getElementById('edit_nome').value = produto.nome;
            document.getElementById('edit_descricao').value = produto.descricao;
            document.getElementById('edit_categoria').value = produto.categoria;
            document.getElementById('edit_preco_custo').value = 'R$ ' + parseFloat(produto.preco_custo).toFixed(2).replace('.', ',');
            document.getElementById('edit_preco_venda').value = 'R$ ' + parseFloat(produto.preco_venda).toFixed(2).replace('.', ',');
            document.getElementById('edit_quantidade').value = produto.quantidade;
            document.getElementById('edit_estoque_minimo').value = produto.estoque_minimo;
            
            openModal('modalEditarProduto');
        }
        
        function excluirProduto(id) {
            if (confirm('Tem certeza que deseja excluir este produto?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('formDelete').submit();
            }
        }
    </script>
</body>
</html>
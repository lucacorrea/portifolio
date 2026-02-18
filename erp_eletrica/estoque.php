<?php
require_once 'config.php';

// Processar movimentação
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'ajustar') {
        $stmt = $pdo->prepare("UPDATE produtos SET quantidade = ? WHERE id = ?");
        $stmt->execute([$_POST['nova_quantidade'], $_POST['produto_id']]);
        
        header('Location: estoque.php?msg=Estoque ajustado com sucesso');
        exit;
    }
}

// Buscar produtos com estoque baixo
$estoque_baixo = $pdo->query("
    SELECT * FROM produtos 
    WHERE quantidade <= estoque_minimo 
    ORDER BY quantidade ASC
")->fetchAll();

// Buscar todos os produtos
$produtos = $pdo->query("SELECT * FROM produtos ORDER BY nome")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Estoque</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Controle de Estoque</h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                </div>
            </div>
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="notification notification-success">
                    <?php echo $_GET['msg']; ?>
                </div>
            <?php endif; ?>
            
            <!-- Alertas de Estoque Baixo -->
            <?php if (count($estoque_baixo) > 0): ?>
            <div class="notification notification-warning" style="background: #fff3cd; color: #856404; border-left: 4px solid #f39c12;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Atenção!</strong> Existem <?php echo count($estoque_baixo); ?> produtos com estoque baixo.
            </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Total de Itens</h3>
                        <div class="stat-value">
                            <?php 
                            $total_itens = $pdo->query("SELECT COALESCE(SUM(quantidade), 0) FROM produtos")->fetchColumn();
                            echo $total_itens;
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
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
                        <h3>Valor de Venda</h3>
                        <div class="stat-value">
                            <?php 
                            $valor_venda = $pdo->query("
                                SELECT COALESCE(SUM(preco_venda * quantidade), 0) 
                                FROM produtos
                            ")->fetchColumn();
                            echo formatarMoeda($valor_venda);
                            ?>
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Produtos</h3>
                        <div class="stat-value"><?php echo count($produtos); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-cube"></i>
                    </div>
                </div>
            </div>
            
            <!-- Produtos com Estoque Baixo -->
            <?php if (count($estoque_baixo) > 0): ?>
            <div class="table-container" style="margin-bottom: 30px; border-left: 4px solid #f39c12;">
                <h3 style="color: #f39c12;">Produtos com Estoque Baixo</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Estoque Mínimo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($estoque_baixo as $produto): ?>
                        <tr>
                            <td><?php echo $produto['codigo']; ?></td>
                            <td><?php echo $produto['nome']; ?></td>
                            <td><?php echo $produto['categoria']; ?></td>
                            <td><strong style="color: #e74c3c;"><?php echo $produto['quantidade']; ?></strong></td>
                            <td><?php echo $produto['estoque_minimo']; ?></td>
                            <td>
                                <span class="status-badge status-atrasado">Estoque Crítico</span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="ajustarEstoque(<?php echo $produto['id']; ?>, '<?php echo $produto['nome']; ?>', <?php echo $produto['quantidade']; ?>)">
                                    <i class="fas fa-edit"></i> Ajustar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Todos os Produtos -->
            <div class="table-container">
                <h3>Todos os Produtos</h3>
                <table class="datatable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Categoria</th>
                            <th>Quantidade</th>
                            <th>Estoque Mínimo</th>
                            <th>Preço Custo</th>
                            <th>Preço Venda</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produtos as $produto): 
                            $status = 'normal';
                            $status_class = 'pago';
                            $status_text = 'Normal';
                            
                            if ($produto['quantidade'] <= $produto['estoque_minimo']) {
                                $status = 'baixo';
                                $status_class = 'atrasado';
                                $status_text = 'Estoque Baixo';
                            }
                            
                            if ($produto['quantidade'] == 0) {
                                $status = 'zerado';
                                $status_class = 'cancelada';
                                $status_text = 'Sem Estoque';
                            }
                        ?>
                        <tr>
                            <td><?php echo $produto['codigo']; ?></td>
                            <td><?php echo $produto['nome']; ?></td>
                            <td><?php echo $produto['categoria']; ?></td>
                            <td>
                                <strong <?php echo $status != 'normal' ? 'style="color: #e74c3c;"' : ''; ?>>
                                    <?php echo $produto['quantidade']; ?>
                                </strong>
                            </td>
                            <td><?php echo $produto['estoque_minimo']; ?></td>
                            <td><?php echo formatarMoeda($produto['preco_custo']); ?></td>
                            <td><?php echo formatarMoeda($produto['preco_venda']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="ajustarEstoque(<?php echo $produto['id']; ?>, '<?php echo $produto['nome']; ?>', <?php echo $produto['quantidade']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-info btn-sm" onclick="historicoMovimento(<?php echo $produto['id']; ?>)">
                                    <i class="fas fa-history"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Relatório de Movimentação -->
            <div class="form-row" style="margin-top: 30px;">
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Últimas Movimentações</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Produto</th>
                                    <th>Tipo</th>
                                    <th>Quantidade</th>
                                    <th>OS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $movimentos = $pdo->query("
                                    SELECT i.*, p.nome as produto_nome, os.numero_os
                                    FROM itens_os i
                                    JOIN produtos p ON i.produto_id = p.id
                                    JOIN os ON i.os_id = os.id
                                    ORDER BY i.id DESC
                                    LIMIT 10
                                ")->fetchAll();
                                
                                foreach ($movimentos as $mov):
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($mov['created_at'] ?? 'now')); ?></td>
                                    <td><?php echo $mov['produto_nome']; ?></td>
                                    <td><span class="status-badge status-pago">Saída</span></td>
                                    <td><?php echo $mov['quantidade']; ?></td>
                                    <td><?php echo $mov['numero_os']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <div class="table-container">
                        <h3>Resumo por Categoria</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Categoria</th>
                                    <th>Quantidade</th>
                                    <th>Valor Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $categorias = $pdo->query("
                                    SELECT categoria, 
                                           SUM(quantidade) as total_qtd,
                                           SUM(preco_custo * quantidade) as valor_total
                                    FROM produtos 
                                    GROUP BY categoria
                                ")->fetchAll();
                                
                                foreach ($categorias as $cat):
                                ?>
                                <tr>
                                    <td><?php echo $cat['categoria']; ?></td>
                                    <td><?php echo $cat['total_qtd']; ?></td>
                                    <td><?php echo formatarMoeda($cat['valor_total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajustar Estoque -->
    <div id="modalAjuste" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Ajustar Estoque</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="ajustar">
                <input type="hidden" name="produto_id" id="ajuste_produto_id">
                
                <div class="form-group">
                    <label>Produto</label>
                    <input type="text" id="ajuste_produto_nome" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Quantidade Atual</label>
                    <input type="text" id="ajuste_quantidade_atual" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Nova Quantidade *</label>
                    <input type="number" name="nova_quantidade" id="ajuste_nova_quantidade" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Motivo do Ajuste</label>
                    <select class="form-control">
                        <option>Compra</option>
                        <option>Ajuste de Inventário</option>
                        <option>Devolução</option>
                        <option>Perda</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Observação</label>
                    <textarea class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Salvar Ajuste
                </button>
            </form>
        </div>
    </div>
    
    <script src="script.js"></script>
    <script>
        function ajustarEstoque(id, nome, quantidade) {
            document.getElementById('ajuste_produto_id').value = id;
            document.getElementById('ajuste_produto_nome').value = nome;
            document.getElementById('ajuste_quantidade_atual').value = quantidade;
            document.getElementById('ajuste_nova_quantidade').value = quantidade;
            
            openModal('modalAjuste');
        }
        
        function historicoMovimento(id) {
            alert('Funcionalidade em desenvolvimento: Histórico do produto #' + id);
        }
    </script>
</body>
</html>
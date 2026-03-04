<?php
require_once 'config.php';
checkAuth();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO produtos (codigo, ncm, nome, unidade, peso, dimensoes, descricao, categoria, preco_custo, preco_venda, preco_venda_atacado, quantidade, estoque_minimo, tipo_produto) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['ncm'],
                $_POST['nome'],
                $_POST['unidade'],
                $_POST['peso'],
                $_POST['dimensoes'],
                $_POST['descricao'],
                $_POST['categoria'],
                $_POST['preco_custo'],
                $_POST['preco_venda'],
                $_POST['preco_venda_atacado'] ?: null,
                $_POST['quantidade'],
                $_POST['estoque_minimo'],
                $_POST['tipo_produto']
            ]);
            
            header('Location: produtos.php?msg=Produto cadastrado com sucesso');
            exit;
        }
        
        if ($_POST['action'] == 'edit') {
            $stmt = $pdo->prepare("
                UPDATE produtos 
                SET codigo = ?, ncm = ?, nome = ?, unidade = ?, peso = ?, dimensoes = ?, 
                    descricao = ?, categoria = ?, preco_custo = ?, preco_venda = ?, 
                    preco_venda_atacado = ?, quantidade = ?, estoque_minimo = ?, tipo_produto = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['codigo'],
                $_POST['ncm'],
                $_POST['nome'],
                $_POST['unidade'],
                $_POST['peso'],
                $_POST['dimensoes'],
                $_POST['descricao'],
                $_POST['categoria'],
                $_POST['preco_custo'],
                $_POST['preco_venda'],
                $_POST['preco_venda_atacado'] ?: null,
                $_POST['quantidade'],
                $_POST['estoque_minimo'],
                $_POST['tipo_produto'],
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

// Categorias técnicas
$categorias = [
    'Fiação (Cabos/Fios)',
    'Proteção (Disjuntores/Fusíveis)',
    'Iluminação (Lâmpadas/LEDs)',
    'Instalação (Canaletas/Eletrodutos)',
    'Comunicação (Redes/Telefonia)',
    'Painéis & Quadros',
    'Chaves & Contatores',
    'Conexões (Bornes/Barramentos)',
    'Ferramentas Técnicas',
    'Automação Industrial'
];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventário Técnico de Produtos - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
                    <h1 class="page-title">Engenharia de Produtos</h1>
                </div>
                
                <div class="user-nav">
                    <button class="btn btn-primary" onclick="openModal('modalProduto')">
                        <i class="fas fa-plus"></i> Novo Item Técnico
                    </button>
                </div>
            </header>
            
            <main class="dash-content fade-in">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="card" style="background: #e3f2fd; border-bottom: 3px solid #0056b3; padding: 15px; margin-bottom: 20px;">
                        <i class="fas fa-info-circle" style="color: #0056b3;"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div>
                            <div class="stat-label">Itens Cadastrados</div>
                            <div class="stat-value"><?php echo count($produtos); ?></div>
                        </div>
                        <i class="fas fa-box stat-icon"></i>
                    </div>
                    
                    <div class="stat-card success">
                        <div>
                            <div class="stat-label">Investimento Total</div>
                            <div class="stat-value">
                                <?php
                                $investimento = array_sum(array_map(function($p) { return $p['preco_custo'] * $p['quantidade']; }, $produtos));
                                echo formatarMoeda($investimento);
                                ?>
                            </div>
                        </div>
                        <i class="fas fa-hand-holding-usd stat-icon"></i>
                    </div>
                    
                    <div class="stat-card danger">
                        <div>
                            <div class="stat-label">Estoque Crítico</div>
                            <div class="stat-value">
                                <?php
                                $criticos = count(array_filter($produtos, function($p) { return $p['quantidade'] <= $p['estoque_minimo']; }));
                                echo $criticos;
                                ?>
                            </div>
                        </div>
                        <i class="fas fa-exclamation-triangle stat-icon" style="opacity: 1; color: var(--danger-color);"></i>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Matriz de Itens e Componentes</h3>
                    </div>
                    <div class="table-responsive">
                        <table class="industrial-table">
                            <thead>
                                <tr>
                                    <th>Código / NCM</th>
                                    <th>Descrição Técnica</th>
                                    <th>UM / Peso</th>
                                    <th>Preço Venda</th>
                                    <th>Estoque Atual</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td style="font-family: 'Roboto Mono', monospace; font-size: 0.8rem;">
                                        <div style="font-weight: 700;"><?php echo $produto['codigo']; ?></div>
                                        <div style="opacity: 0.6;">NCM: <?php echo $produto['ncm'] ?: '---'; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo $produto['nome']; ?></div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo $produto['categoria']; ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo $produto['unidade']; ?></div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?php echo $produto['peso'] ? number_format($produto['peso'],3,',','.').'kg' : '---'; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary-color);"><?php echo formatarMoeda($produto['preco_venda']); ?></div>
                                        <?php if ($produto['preco_venda_atacado']): ?>
                                            <div style="font-size: 0.7rem; color: var(--success-color);">Atac: <?php echo formatarMoeda($produto['preco_venda_atacado']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: 'Roboto Mono', monospace; font-weight: 700;">
                                        <?php echo $produto['quantidade']; ?> <span style="font-size: 0.7rem; font-weight: 400; opacity: 0.6;">(mín: <?php echo $produto['estoque_minimo']; ?>)</span>
                                    </td>
                                    <td>
                                        <?php if ($produto['quantidade'] <= $produto['estoque_minimo']): ?>
                                            <span class="badge badge-danger">Crítico</span>
                                        <?php elseif ($produto['quantidade'] <= ($produto['estoque_minimo'] * 1.5)): ?>
                                            <span class="badge badge-warning">Atenção</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">OK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button class="btn btn-outline" style="padding: 5px 8px;" onclick="editarProduto(<?php echo htmlspecialchars(json_encode($produto)); ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline" style="padding: 5px 8px;" onclick="excluirProduto(<?php echo $produto['id']; ?>)" title="Excluir">
                                                <i class="fas fa-trash" style="color: var(--danger-color);"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal Produto -->
    <div id="modalProduto" class="modal">
        <div class="modal-content" style="max-width: 850px;">
            <span class="close">&times;</span>
            <h2 style="margin-bottom: 25px;">Ficha Técnica do Produto</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Código (SKU) *</label>
                        <input type="text" name="codigo" class="form-control" required style="font-family: 'Roboto Mono';">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NCM (Fiscal)</label>
                        <input type="text" name="ncm" class="form-control" placeholder="8544.x.x">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Produto *</label>
                        <select name="tipo_produto" class="form-control" required>
                            <option value="simples">Item Simples</option>
                            <option value="composto">Kit / Composto</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Nome Técnico / Descritivo *</label>
                        <input type="text" name="nome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria Industrial *</label>
                        <select name="categoria" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unidade de Medida *</label>
                        <input type="text" name="unidade" class="form-control" placeholder="UN, M, CX, KG" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Peso Bruto (KG)</label>
                        <input type="number" step="0.001" name="peso" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dimensões (CxLxA)</label>
                        <input type="text" name="dimensoes" class="form-control" placeholder="10x15x5cm">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Especificações Técnicas</label>
                    <textarea name="descricao" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Preço Custo (R$) *</label>
                        <input type="text" name="preco_custo" class="form-control money" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Venda Varejo (R$) *</label>
                        <input type="text" name="preco_venda" class="form-control money" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Atacado (R$)</label>
                        <input type="text" name="preco_venda_atacado" class="form-control money">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantidade em Estoque *</label>
                        <input type="number" name="quantidade" class="form-control" value="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nível de Alerta (Mínimo) *</label>
                        <input type="number" name="estoque_minimo" class="form-control" value="5" required>
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalProduto')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Homologar Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="modalEditarProduto" class="modal">
        <div class="modal-content" style="max-width: 850px;">
            <span class="close">&times;</span>
            <h2 style="margin-bottom: 25px;">Atualização de Ficha Técnica</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Código (SKU) *</label>
                        <input type="text" name="codigo" id="edit_codigo" class="form-control" required style="font-family: 'Roboto Mono';">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NCM (Fiscal)</label>
                        <input type="text" name="ncm" id="edit_ncm" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Produto *</label>
                        <select name="tipo_produto" id="edit_tipo_produto" class="form-control" required>
                            <option value="simples">Item Simples</option>
                            <option value="composto">Kit / Composto</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Nome Técnico / Descritivo *</label>
                        <input type="text" name="nome" id="edit_nome" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoria Industrial *</label>
                        <select name="categoria" id="edit_categoria" class="form-control" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Unidade de Medida *</label>
                        <input type="text" name="unidade" id="edit_unidade" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Peso Bruto (KG)</label>
                        <input type="number" step="0.001" name="peso" id="edit_peso" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dimensões (CxLxA)</label>
                        <input type="text" name="dimensoes" id="edit_dimensoes" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Especificações Técnicas</label>
                    <textarea name="descricao" id="edit_descricao" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Preço Custo (R$) *</label>
                        <input type="text" name="preco_custo" id="edit_preco_custo" class="form-control money" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Venda Varejo (R$) *</label>
                        <input type="text" name="preco_venda" id="edit_preco_venda" class="form-control money" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Atacado (R$)</label>
                        <input type="text" name="preco_venda_atacado" id="edit_preco_venda_atacado" class="form-control money">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Quantidade Atual *</label>
                        <input type="number" name="quantidade" id="edit_quantidade" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nível de Alerta *</label>
                        <input type="number" name="estoque_minimo" id="edit_estoque_minimo" class="form-control" required>
                    </div>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalEditarProduto')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
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
        function editarProduto(p) {
            document.getElementById('edit_id').value = p.id;
            document.getElementById('edit_codigo').value = p.codigo;
            document.getElementById('edit_ncm').value = p.ncm || '';
            document.getElementById('edit_tipo_produto').value = p.tipo_produto || 'simples';
            document.getElementById('edit_nome').value = p.nome;
            document.getElementById('edit_categoria').value = p.categoria;
            document.getElementById('edit_unidade').value = p.unidade || 'UN';
            document.getElementById('edit_peso').value = p.peso || '';
            document.getElementById('edit_dimensoes').value = p.dimensoes || '';
            document.getElementById('edit_descricao').value = p.descricao || '';
            
            // Format money for the inputs
            const formatMoneyInput = (val) => 'R$ ' + parseFloat(val || 0).toFixed(2).replace('.', ',');
            
            document.getElementById('edit_preco_custo').value = formatMoneyInput(p.preco_custo);
            document.getElementById('edit_preco_venda').value = formatMoneyInput(p.preco_venda);
            document.getElementById('edit_preco_venda_atacado').value = p.preco_venda_atacado ? formatMoneyInput(p.preco_venda_atacado) : '';
            
            document.getElementById('edit_quantidade').value = p.quantidade;
            document.getElementById('edit_estoque_minimo').value = p.estoque_minimo;
            
            openModal('modalEditarProduto');
        }
        
        function excluirProduto(id) {
            if (confirm('Atenção: A exclusão deste item pode afetar cadastros de Kits e OS. Confirmar exclusão?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('formDelete').submit();
            }
        }
    </script>
</body>
</html>
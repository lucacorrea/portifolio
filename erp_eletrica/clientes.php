<?php
require_once 'config.php';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Adicionar cliente
        if ($_POST['action'] == 'add') {
            $stmt = $pdo->prepare("
                INSERT INTO clientes (nome, cpf_cnpj, telefone, email, endereco) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['nome'],
                $_POST['cpf_cnpj'],
                $_POST['telefone'],
                $_POST['email'],
                $_POST['endereco']
            ]);
            
            header('Location: clientes.php?msg=Cliente adicionado com sucesso');
            exit;
        }
        
        // Editar cliente
        if ($_POST['action'] == 'edit') {
            $stmt = $pdo->prepare("
                UPDATE clientes 
                SET nome = ?, cpf_cnpj = ?, telefone = ?, email = ?, endereco = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['nome'],
                $_POST['cpf_cnpj'],
                $_POST['telefone'],
                $_POST['email'],
                $_POST['endereco'],
                $_POST['id']
            ]);
            
            header('Location: clientes.php?msg=Cliente atualizado com sucesso');
            exit;
        }
        
        // Excluir cliente
        if ($_POST['action'] == 'delete') {
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            header('Location: clientes.php?msg=Cliente excluído com sucesso');
            exit;
        }
    }
}

// Buscar clientes
$clientes = $pdo->query("SELECT * FROM clientes ORDER BY nome")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Elétrica - Clientes</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title">Clientes</h1>
                <div class="user-info">
                    <span>Bem-vindo, <?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                    <button class="btn btn-primary" onclick="openModal('modalCliente')">
                        <i class="fas fa-plus"></i> Novo Cliente
                    </button>
                </div>
            </div>
            
            <?php if (isset($_GET['msg'])): ?>
                <div class="notification notification-success">
                    <?php echo $_GET['msg']; ?>
                </div>
            <?php endif; ?>
            
            <div class="table-container">
                <table class="datatable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>CPF/CNPJ</th>
                            <th>Telefone</th>
                            <th>Email</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><?php echo $cliente['id']; ?></td>
                            <td><?php echo $cliente['nome']; ?></td>
                            <td><?php echo $cliente['cpf_cnpj']; ?></td>
                            <td><?php echo $cliente['telefone']; ?></td>
                            <td><?php echo $cliente['email']; ?></td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="editarCliente(<?php echo htmlspecialchars(json_encode($cliente)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="excluirCliente(<?php echo $cliente['id']; ?>)">
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
    
    <!-- Modal Cliente -->
    <div id="modalCliente" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Novo Cliente</h2>
            
            <form method="POST" data-validate>
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" id="cpf_cnpj" name="cpf_cnpj" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" id="telefone" name="telefone" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Endereço</label>
                    <textarea name="endereco" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </form>
        </div>
    </div>
    
    <!-- Modal Editar Cliente -->
    <div id="modalEditarCliente" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Editar Cliente</h2>
            
            <form method="POST" data-validate>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" id="edit_nome" class="form-control" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" id="edit_cpf_cnpj" name="cpf_cnpj" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" id="edit_telefone" name="telefone" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label>Endereço</label>
                    <textarea name="endereco" id="edit_endereco" class="form-control" rows="3"></textarea>
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
        function editarCliente(cliente) {
            document.getElementById('edit_id').value = cliente.id;
            document.getElementById('edit_nome').value = cliente.nome;
            document.getElementById('edit_cpf_cnpj').value = cliente.cpf_cnpj;
            document.getElementById('edit_telefone').value = cliente.telefone;
            document.getElementById('edit_email').value = cliente.email;
            document.getElementById('edit_endereco').value = cliente.endereco;
            
            openModal('modalEditarCliente');
        }
        
        function excluirCliente(id) {
            if (confirm('Tem certeza que deseja excluir este cliente?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('formDelete').submit();
            }
        }
    </script>
</body>
</html>
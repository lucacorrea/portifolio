<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
suporte_check();

$page_title = "Configurações do Sistema";

// Lógica de Cadastro Simples via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_sec'])) {
        $nome = $_POST['nome'];
        $cod = $_POST['codigo'];
        $resp = $_POST['responsavel'];
        $stmt = $pdo->prepare("INSERT INTO secretarias (nome, codigo_acesso, responsavel) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $cod, $resp]);
        flash_message('success', "Secretaria cadastrada!");
    }
    if (isset($_POST['add_forn'])) {
        $nome = $_POST['nome'];
        $cnpj = $_POST['cnpj'];
        $contato = $_POST['contato'];
        $stmt = $pdo->prepare("INSERT INTO fornecedores (nome, cnpj, contato) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $cnpj, $contato]);
        flash_message('success', "Fornecedor cadastrado!");
    }
}

$secretarias = $pdo->query("SELECT * FROM secretarias ORDER BY nome")->fetchAll();
$fornecedores = $pdo->query("SELECT * FROM fornecedores ORDER BY nome")->fetchAll();

include 'views/layout/header.php';
?>

<div class="row" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
    <!-- Gerenciar Secretarias -->
    <div class="card">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom: 1.5rem; font-weight: 700; font-size: 1rem;">
                <i class="fas fa-building" style="margin-right: 8px; color: var(--primary);"></i> Secretarias
            </h3>
            <form action="" method="POST" style="margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Nome da Secretaria</label>
                    <input type="text" name="nome" class="form-control" placeholder="Ex: Secretaria de Saúde" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Código de Acesso</label>
                    <input type="text" name="codigo" class="form-control" placeholder="Ex: SEC2024" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Responsável</label>
                    <input type="text" name="responsavel" class="form-control" placeholder="Nome do Responsável">
                </div>
                <div style="grid-column: span 2; text-align: right;">
                    <button type="submit" name="add_sec" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Adicionar</button>
                </div>
            </form>
            <div class="table-responsive" style="max-height: 300px;">
                <table class="table-vcenter">
                    <thead><tr><th>Nome</th><th>Código</th></tr></thead>
                    <tbody>
                        <?php foreach($secretarias as $s): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-dark);"><?php echo $s['nome']; ?></td>
                                <td><span class="badge badge-outline" style="font-family: monospace; border: 1px solid var(--border-color); color: var(--text-muted);"><?php echo $s['codigo_acesso']; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Gerenciar Fornecedores -->
    <div class="card">
        <div class="card-body">
            <h3 class="card-title" style="margin-bottom: 1.5rem; font-weight: 700; font-size: 1rem;">
                <i class="fas fa-truck" style="margin-right: 8px; color: var(--primary);"></i> Fornecedores
            </h3>
            <form action="" method="POST" style="margin-bottom: 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Razão Social / Nome Fantasia</label>
                    <input type="text" name="nome" class="form-control" placeholder="Nome da Empresa" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CNPJ</label>
                    <input type="text" name="cnpj" class="form-control" placeholder="00.000.000/0000-00">
                </div>
                <div class="form-group">
                    <label class="form-label">Contato</label>
                    <input type="text" name="contato" class="form-control" placeholder="Telefone ou E-mail">
                </div>
                <div style="grid-column: span 2; text-align: right;">
                    <button type="submit" name="add_forn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Adicionar</button>
                </div>
            </form>
            <div class="table-responsive" style="max-height: 300px;">
                <table class="table-vcenter">
                    <thead><tr><th>Nome</th><th>CNPJ</th></tr></thead>
                    <tbody>
                        <?php foreach($fornecedores as $f): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--text-dark);"><?php echo $f['nome']; ?></td>
                                <td><span class="text-muted"><?php echo $f['cnpj'] ?: '-'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: var(--text-dark); font-weight: 700; font-size: 1rem;">
                <i class="fas fa-history" style="margin-right: 10px; color: var(--primary);"></i> Logs Recentes do Sistema
            </h3>
            <a href="relatorios.php" class="btn btn-outline btn-sm">Auditoria Completa</a>
        </div>
        <div class="table-responsive">
            <?php 
               $logs = $pdo->query("SELECT l.*, u.nome as usuario, s.nome as secretaria FROM logs l LEFT JOIN usuarios u ON l.usuario_id = u.id LEFT JOIN secretarias s ON l.secretaria_id = s.id ORDER BY l.criado_em DESC LIMIT 10")->fetchAll();
            ?>
            <table class="table-vcenter">
                <thead><tr><th>Data</th><th>Usuário/Sec</th><th>Ação</th><th>IP</th></tr></thead>
                <tbody>
                    <?php foreach($logs as $l): ?>
                        <tr>
                            <td><span class="text-muted"><?php echo format_date($l['criado_em']); ?></span></td>
                            <td style="font-weight: 600;"><?php echo $l['usuario'] ?: ($l['secretaria'] ?: 'Sistema'); ?></td>
                            <td><span class="badge badge-outline" style="border-color: var(--border-color); color: var(--text-dark);"><?php echo $l['acao']; ?></span></td>
                            <td><code style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $l['ip']; ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'views/layout/footer.php'; ?>

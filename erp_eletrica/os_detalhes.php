<?php
require_once 'config.php';
checkAuth();
\App\Services\AuthService::checkPermission('os', 'visualizar');

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: os.php');
    exit;
}

// Buscar OS detalhada
$stmt = $pdo->prepare("
    SELECT os.*, clientes.nome as cliente_nome, clientes.telefone, clientes.whatsapp, clientes.endereco,
           usuarios.nome as tecnico_nome, criador.nome as criador_nome
    FROM os 
    JOIN clientes ON os.cliente_id = clientes.id 
    LEFT JOIN usuarios ON os.tecnico_id = usuarios.id 
    LEFT JOIN usuarios criador ON os.usuario_id = criador.id
    WHERE os.id = ?
");
$stmt->execute([$id]);
$os = $stmt->fetch();

if (!$os) {
    header('Location: os.php?msg=OS não encontrada');
    exit;
}

// Buscar itens da OS
$stmt = $pdo->prepare("
    SELECT i.*, p.nome as produto_nome, p.unidade 
    FROM itens_os i 
    LEFT JOIN produtos p ON i.produto_id = p.id 
    WHERE i.os_id = ?
");
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

// Buscar histórico
$stmt = $pdo->prepare("
    SELECT h.*, u.nome as usuario_nome 
    FROM os_historico h 
    JOIN usuarios u ON h.usuario_id = u.id 
    WHERE h.os_id = ? 
    ORDER BY h.data_historico DESC
");
$stmt->execute([$id]);
$historico = $stmt->fetchAll();

// Processar Post (Status, Checklist, etc.)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    \App\Services\AuthService::checkPermission('os', 'editar');
    if ($_POST['action'] == 'update_status') {
        $novo_status = $_POST['status'];
        $obs = $_POST['observacao'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Lógica de Estoque
            $status_baixa = ['aprovado', 'em_andamento', 'concluido', 'entregue'];
            $status_retorno = ['orcamento', 'cancelado'];
            
            // Se vai para status de baixa e ainda não baixou
            if (in_array($novo_status, $status_baixa) && $os['estoque_baixado'] == 0) {
                foreach ($itens as $item) {
                    if ($item['produto_id']) {
                        $stmt = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");
                        $stmt->execute([$item['quantidade'], $item['produto_id']]);
                        
                        // Registrar movimento
                        $stmt = $pdo->prepare("INSERT INTO movimentacao_estoque (produto_id, quantidade, tipo, motivo, usuario_id, referencia_id) VALUES (?, ?, 'saida', ?, ?, ?)");
                        $stmt->execute([$item['produto_id'], $item['quantidade'], "Baixa OS #".$os['numero_os'], $_SESSION['usuario_id'], $id]);
                    }
                }
                $stmt = $pdo->prepare("UPDATE os SET estoque_baixado = 1 WHERE id = ?");
                $stmt->execute([$id]);
            }
            // Se volta para orçamento/cancelado e já tinha baixado
            elseif (in_array($novo_status, $status_retorno) && $os['estoque_baixado'] == 1) {
                foreach ($itens as $item) {
                    if ($item['produto_id']) {
                        $stmt = $pdo->prepare("UPDATE produtos SET quantidade = quantidade + ? WHERE id = ?");
                        $stmt->execute([$item['quantidade'], $item['produto_id']]);
                        
                        // Registrar movimento
                        $stmt = $pdo->prepare("INSERT INTO movimentacao_estoque (produto_id, quantidade, tipo, motivo, usuario_id, referencia_id) VALUES (?, ?, 'entrada', ?, ?, ?)");
                        $stmt->execute([$item['produto_id'], $item['quantidade'], "Estorno OS #".$os['numero_os'], $_SESSION['usuario_id'], $id]);
                    }
                }
                $stmt = $pdo->prepare("UPDATE os SET estoque_baixado = 0 WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            // Atualizar OS
            $stmt = $pdo->prepare("UPDATE os SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$novo_status, $id]);
            
            // Log histórico
            $stmt = $pdo->prepare("INSERT INTO os_historico (os_id, status_anterior, status_novo, observacao, usuario_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id, $os['status'], $novo_status, $obs, $_SESSION['usuario_id']]);
            
            $pdo->commit();
            header("Location: os_detalhes.php?id=$id&msg=Status atualizado com sucesso");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erro ao atualizar: " . $e->getMessage();
        }
    }
    
    if ($_POST['action'] == 'update_checklist') {
        $checklist = json_encode($_POST['check'] ?? []);
        $stmt = $pdo->prepare("UPDATE os SET checklist_tecnico = ? WHERE id = ?");
        $stmt->execute([$checklist, $id]);
        header("Location: os_detalhes.php?id=$id&msg=Checklist técnico salvo");
        exit;
    }
}

$checklist_atual = json_decode($os['checklist_tecnico'] ?? '[]', true);
$tecnicos = $pdo->query("SELECT id, nome FROM usuarios WHERE nivel IN ('admin', 'tecnico') AND ativo = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão OS #<?php echo $os['numero_os']; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="top-bar">
                <div style="display: flex; align-items: center;">
                    <a href="os.php" class="btn btn-outline" style="margin-right: 15px; padding: 5px 10px;"><i class="fas fa-arrow-left"></i></a>
                    <h1 class="page-title">Gerenciamento Técnico: OS #<?php echo $os['numero_os']; ?></h1>
                </div>
                
                <div class="user-nav">
                    <a href="imprimir_os.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-outline">
                        <i class="fas fa-print"></i> Relatório A4
                    </a>
                </div>
            </header>
            
            <main class="dash-content fade-in">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="card" style="background: #e3f2fd; border-bottom: 3px solid var(--primary-color); padding: 15px; margin-bottom: 20px;">
                        <i class="fas fa-info-circle" style="color: var(--primary-color);"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                    </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    <!-- Coluna Esquerda: Detalhes e Itens -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Status Bar -->
                        <div class="card" style="padding: 20px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid <?php echo getStatusColor($os['status']); ?>;">
                            <div>
                                <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Status Atual do Workflow</span>
                                <span style="font-weight: 700; font-size: 1.2rem; color: var(--secondary-color);">
                                    <?php echo strtoupper(str_replace('_', ' ', $os['status'])); ?>
                                </span>
                            </div>
                            <button class="btn btn-primary" onclick="openModal('modalUpdateStatus')">Alterar Stage</button>
                        </div>

                        <!-- Info Cliente e Serviço -->
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Escopo do Serviço</h3></div>
                            <div style="padding: 20px;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                                    <div>
                                        <label style="font-size: 0.75rem; color: var(--text-muted);">Requisitante</label>
                                        <div style="font-weight: 600;"><?php echo $os['cliente_nome']; ?></div>
                                        <div style="font-size: 0.85rem;"><?php echo $os['telefone']; ?> / <?php echo $os['whatsapp']; ?></div>
                                    </div>
                                    <div>
                                        <label style="font-size: 0.75rem; color: var(--text-muted);">Responsável Técnico</label>
                                        <div style="font-weight: 600; color: var(--primary-color);">
                                            <i class="fas fa-user-hard-hat"></i> <?php echo $os['tecnico_nome'] ?: 'NÃO DEFINIDO'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; border: 1px dashed #ddd;">
                                    <label style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">Descrição da Solicitação:</label>
                                    <p style="margin-top: 5px; line-height: 1.5; font-size: 0.95rem;"><?php echo nl2br($os['descricao']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Itens e Peças -->
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Materiais e Insumos Applicados</h3></div>
                            <div class="table-responsive">
                                <table class="industrial-table">
                                    <thead>
                                        <tr>
                                            <th>Item / Descrição</th>
                                            <th>Qtd</th>
                                            <th>UN</th>
                                            <th>V. Unit</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($itens)): ?>
                                            <tr><td colspan="5" style="text-align: center; opacity: 0.5; padding: 30px;">Nenhum material registrado.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($itens as $item): ?>
                                        <tr>
                                            <td style="font-weight: 600;"><?php echo $item['produto_nome'] ?: 'Item Manual'; ?></td>
                                            <td style="font-family: 'Roboto Mono';"><?php echo $item['quantidade']; ?></td>
                                            <td><?php echo $item['unidade']; ?></td>
                                            <td><?php echo formatarMoeda($item['valor_unitario']); ?></td>
                                            <td style="font-weight: 700; color: var(--primary-color);"><?php echo formatarMoeda($item['subtotal']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr style="background: #f8f9fa;">
                                            <td colspan="4" style="text-align: right; font-weight: 700;">TOTAL MATERIAIS:</td>
                                            <td style="font-weight: 800; font-size: 1.1rem; color: var(--primary-color);">
                                                <?php echo formatarMoeda($os['valor_total']); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: Checklist e Timeline -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <!-- Checklist Técnico -->
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Checklist de Engenharia</h3></div>
                            <div style="padding: 20px;">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_checklist">
                                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                                        <?php 
                                        $itens_checklist = [
                                            'Verificação de Aterramento',
                                            'Teste de Tensão de Entrada',
                                            'Verificação de Torque em Parafusos',
                                            'Limpeza de Componentes',
                                            'Identificação de Cabos',
                                            'Teste de Carga / Funcionalidade',
                                            'Fotos do Serviço Realizado'
                                        ];
                                        foreach ($itens_checklist as $chk):
                                            $checked = in_array($chk, $checklist_atual) ? 'checked' : '';
                                        ?>
                                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                            <input type="checkbox" name="check[]" value="<?php echo $chk; ?>" <?php echo $checked; ?>>
                                            <span style="font-size: 0.85rem;"><?php echo $chk; ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="btn btn-outline" style="width: 100%;">Salvar Checklist</button>
                                </form>
                            </div>
                        </div>

                        <!-- Histórico Timeline -->
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Log de Atividades</h3></div>
                            <div style="padding: 20px; max-height: 400px; overflow-y: auto;">
                                <div class="timeline" style="border-left: 2px solid #eee; padding-left: 20px; position: relative;">
                                    <?php foreach ($historico as $h): ?>
                                    <div style="margin-bottom: 20px; position: relative;">
                                        <div style="position: absolute; left: -26px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: var(--primary-color); border: 2px solid white;"></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo formatarDataHora($h['data_historico']); ?></div>
                                        <div style="font-weight: 700; font-size: 0.85rem; color: var(--secondary-color);">
                                            Mode: <?php echo strtoupper($h['status_novo']); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; margin-top: 3px; font-style: italic;"><?php echo $h['observacao']; ?></div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);">Por: <?php echo $h['usuario_nome']; ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Status -->
    <div id="modalUpdateStatus" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close">&times;</span>
            <h2 style="margin-bottom: 20px;">Atualizar Workflow</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <div class="form-group">
                    <label class="form-label">Próximo Estágio:</label>
                    <select name="status" class="form-control" required>
                        <option value="orcamento" <?php echo $os['status'] == 'orcamento' ? 'selected' : ''; ?>>Orçamento (Estoque Livre)</option>
                        <option value="aprovado" <?php echo $os['status'] == 'aprovado' ? 'selected' : ''; ?>>Aprovado (Reserva Estoque)</option>
                        <option value="em_andamento" <?php echo $os['status'] == 'em_andamento' ? 'selected' : ''; ?>>Em Execução (Reserva Estoque)</option>
                        <option value="aguardando_peca" <?php echo $os['status'] == 'aguardando_peca' ? 'selected' : ''; ?>>Aguardando Componentes</option>
                        <option value="concluido" <?php echo $os['status'] == 'concluido' ? 'selected' : ''; ?>>Concluido (Processar Financeiro)</option>
                        <option value="entregue" <?php echo $os['status'] == 'entregue' ? 'selected' : ''; ?>>Entregue ao Cliente</option>
                        <option value="cancelado" <?php echo $os['status'] == 'cancelado' ? 'selected' : ''; ?>>Cancelado (Retornar Estoque)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nota de Progresso (Interno):</label>
                    <textarea name="observacao" class="form-control" rows="2" placeholder="Descreva o motivo da alteração..."></textarea>
                </div>
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('modalUpdateStatus')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Processar Alteração</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>

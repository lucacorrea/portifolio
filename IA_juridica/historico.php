<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/api/Documento.php';

$docManager = new Documento();

$tipo = $_GET['tipo'] ?? '';
$busca = $_GET['busca'] ?? '';

$documentos = $docManager->getAll(['tipo' => $tipo, 'busca' => $busca]);

getHeader("Histórico - IA Jurídica");
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Histórico de Documentos</h1>
</div>

<div class="card" style="margin-bottom: 2rem;">
    <form method="GET" style="display: flex; gap: 1rem; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; flex: 2;">
            <label class="form-label">Busca por Assunto ou Destinatário</label>
            <input type="text" name="busca" class="form-control" value="<?php echo htmlspecialchars($busca); ?>" placeholder="Pesquisar...">
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1;">
            <label class="form-label">Filtro por Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos</option>
                <option value="Oficio" <?php echo $tipo == 'Oficio' ? 'selected' : ''; ?>>Ofício</option>
                <option value="Memorando" <?php echo $tipo == 'Memorando' ? 'selected' : ''; ?>>Memorando</option>
                <option value="Parecer" <?php echo $tipo == 'Parecer' ? 'selected' : ''; ?>>Parecer</option>
                <option value="Relatorio" <?php echo $tipo == 'Relatorio' ? 'selected' : ''; ?>>Relatório</option>
                <option value="Despacho" <?php echo $tipo == 'Despacho' ? 'selected' : ''; ?>>Despacho</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="height: 44px;">
            <i class="fas fa-search"></i> Filtrar
        </button>
        <a href="historico.php" class="btn" style="height: 44px; background: #eee; color: #333;">Limpar</a>
    </form>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Data Criação</th>
                <th>Documento</th>
                <th>Destinatário</th>
                <th>Assunto</th>
                <th>Responsável</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($documentos as $doc): ?>
            <tr>
                <td><?php echo date('d/m/Y H:i', strtotime($doc['data_criacao'])); ?></td>
                <td><strong><?php echo strtoupper($doc['tipo_documento']); ?> <?php echo $doc['numero_documento']; ?></strong></td>
                <td><?php echo htmlspecialchars($doc['destinatario']); ?></td>
                <td><?php echo htmlspecialchars($doc['assunto']); ?></td>
                <td><?php echo htmlspecialchars($doc['responsavel']); ?></td>
                <td>
                    <div style="display: flex; gap: 5px;">
                        <a href="visualizar_documento.php?id=<?php echo $doc['id']; ?>" class="btn no-print" style="padding: 0.4rem 0.6rem; font-size: 0.8rem; background: var(--primary-color); color: white;" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="gerar_pdf.php?id=<?php echo $doc['id']; ?>" class="btn no-print" style="padding: 0.4rem 0.6rem; font-size: 0.8rem; background: var(--danger); color: white;" title="Gerar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($documentos)): ?>
            <tr>
                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 3rem;">Nenhum documento encontrado para os filtros aplicados.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php getFooter(); ?>

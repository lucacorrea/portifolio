<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/api/Documento.php';

$docManager = new Documento();
$stats = $docManager->getStats();

getHeader("Dashboard - IA Jurídica");
?>

<div class="header-action" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h1>Painel Administrativo</h1>
    <a href="index.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Documento</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total']; ?></div>
        <div class="stat-label">Documentos Gerados</div>
    </div>
    <?php foreach ($stats['por_tipo'] as $tipo): ?>
    <div class="stat-card">
        <div class="stat-value"><?php echo $tipo['qtd']; ?></div>
        <div class="stat-label"><?php echo $tipo['tipo_documento']; ?>s</div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2 class="card-title"><i class="fas fa-clock"></i> Atividade Recente</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Código</th>
                <th>Tipo</th>
                <th>Destinatário</th>
                <th>Assunto</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($stats['recentes'] as $doc): ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($doc['data_criacao'])); ?></td>
                <td><strong><?php echo strtoupper($doc['tipo_documento']); ?> <?php echo $doc['numero_documento']; ?></strong></td>
                <td><?php echo $doc['tipo_documento']; ?></td>
                <td><?php echo $doc['destinatario']; ?></td>
                <td><?php echo $doc['assunto']; ?></td>
                <td>
                    <a href="visualizar_documento.php?id=<?php echo $doc['id']; ?>" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                        <i class="fas fa-eye"></i> Ver
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($stats['recentes'])): ?>
            <tr>
                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2rem;">Nenhum documento gerado ainda.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php getFooter(); ?>

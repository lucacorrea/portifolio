<?php
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/api/Documento.php';

$id = $_GET['id'] ?? 0;
$docManager = new Documento();
$doc = $docManager->getById($id);

if (!$doc) {
    die("Documento não encontrado.");
}

$success = $_GET['success'] ?? 0;

getHeader("Visualizar Documento - IA Jurídica");
?>

<div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <a href="historico.php" style="text-decoration: none; color: var(--primary-color); font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Voltar ao Histórico
        </a>
        <h1 style="margin-top: 0.5rem;"><?php echo strtoupper($doc['tipo_documento']); ?> <?php echo $doc['numero_documento']; ?></h1>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn" style="background: #555; color: white;">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <a href="gerar_pdf.php?id=<?php echo $doc['id']; ?>" class="btn" style="background: var(--danger); color: white;">
            <i class="fas fa-file-pdf"></i> Baixar PDF
        </a>
    </div>
</div>

<?php if ($success): ?>
<div class="no-print" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; display: flex; align-items: center; gap: 10px;">
    <i class="fas fa-check-circle"></i> Documento gerado com sucesso pela IA e salvo no banco de dados!
</div>
<?php endif; ?>

<div class="document-preview">
<?php echo htmlspecialchars($doc['conteudo']); ?>
</div>

<div class="no-print" style="margin-top: 3rem; padding-bottom: 3rem;">
    <div class="card" style="background: #f8f9fa;">
        <h3 class="card-title"><i class="fas fa-info-circle"></i> Detalhes do Documento</h3>
        <p><strong>Destinatário:</strong> <?php echo htmlspecialchars($doc['destinatario']); ?></p>
        <p><strong>Assunto:</strong> <?php echo htmlspecialchars($doc['assunto']); ?></p>
        <p><strong>Responsável:</strong> <?php echo htmlspecialchars($doc['responsavel']); ?> (<?php echo htmlspecialchars($doc['cargo']); ?>)</p>
        <p><strong>Local e Data:</strong> <?php echo htmlspecialchars($doc['cidade']); ?>, <?php echo date('d/m/Y', strtotime($doc['data_documento'])); ?></p>
        <p><strong>Criado em:</strong> <?php echo date('d/m/Y H:i', strtotime($doc['data_criacao'])); ?></p>
    </div>
</div>

<?php getFooter(); ?>

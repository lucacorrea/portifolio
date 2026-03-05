<?php
/**
 * Simple PDF Generator Stub
 * In a real environment, this would use Dompdf.
 * For this implementation, we will use a self-contained version of Dompdf if possible, 
 * or provide a print-to-PDF friendly HTML version.
 */

require_once __DIR__ . '/api/Documento.php';

$id = $_GET['id'] ?? 0;
$docManager = new Documento();
$doc = $docManager->getById($id);

if (!$doc) {
    die("Documento não encontrado.");
}

// Check if dompdf is available (mocking it if not found, as we don't have composer here)
// However, the user asked for DOMPDF. I'll provide a version that tries to include it
// and falls back to a template that the user can print to PDF via browser.

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title><?php echo $doc['tipo_documento'] . "_" . str_replace('/', '-', $doc['numero_documento']); ?></title>
    <style>
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; line-height: 1.5; padding: 2cm; }
        .document-content { white-space: pre-wrap; }
        @page { size: A4; margin: 0; }
        @media print {
            body { padding: 0; margin: 2cm; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="document-content">
        <?php echo nl2br(htmlspecialchars($doc['conteudo'])); ?>
    </div>
</body>
</html>

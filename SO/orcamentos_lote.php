<?php
require_once __DIR__ . '/config/functions.php';
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['error' => 'Sessão expirada. Entre novamente.']); exit; }
    header('Location: login.php'); exit;
}
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/orcamentos_lote_service.php';
$stmt = $pdo->prepare('SELECT nivel FROM usuarios WHERE id = ?');
$stmt->execute([(int)$_SESSION['user_id']]);
$nivelLote = strtoupper((string)$stmt->fetchColumn());
$canApprove = in_array($nivelLote, ['ADMIN', 'SUPORTE'], true);
$canImport = in_array($nivelLote, ['ADMIN', 'SUPORTE', 'FUNCIONARIO', 'CASA_CIVIL', 'SEFAZ'], true);
$userIdLote = (int)$_SESSION['user_id'];
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (isset($_GET['arquivo'])) {
    $token = is_string($_GET['arquivo']) ? $_GET['arquivo'] : '';
    if (!preg_match('/^[a-f0-9]{32}$/D', $token)) { http_response_code(404); exit; }
    $stmt = $pdo->prepare('SELECT i.*, o.secretaria_id FROM orcamentos_importacoes i JOIN oficios o ON o.id = i.oficio_id WHERE i.token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $allowed = $row && ($canApprove || $nivelLote === 'SEFAZ' || (int)$row['usuario_id'] === $userIdLote
        || ((int)($_SESSION['secretaria_id'] ?? 0) > 0 && (int)$row['secretaria_id'] === (int)$_SESSION['secretaria_id']));
    if (!$allowed) { http_response_code(404); exit; }
    $path = lote_storage() . '/' . $token . '.pdf';
    if (!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="orcamento.pdf"');
    header('Content-Security-Policy: sandbox');
    header('Content-Length: ' . filesize($path));
    readfile($path); exit;
}
if (!$canImport) {
    http_response_code(403);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { header('Content-Type: application/json'); echo json_encode(['error' => 'Perfil sem permissão para importar orçamentos.']); }
    else echo 'Perfil sem permissão para importar orçamentos.';
    exit;
}
if (empty($_SESSION['csrf_orcamentos_lote'])) $_SESSION['csrf_orcamentos_lote'] = bin2hex(random_bytes(32));
$loteSetupError = null;
try { lote_schema($pdo); } catch (Throwable $e) { $loteSetupError = 'Não foi possível preparar o registro de importações. Solicite ao suporte a criação da tabela orcamentos_importacoes.'; error_log('SO lote schema: ' . $e->getMessage()); }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
        if (!hash_equals($_SESSION['csrf_orcamentos_lote'], $csrf)) { http_response_code(403); throw new DomainException('Sessão expirada ou arquivo acima do limite do servidor. Atualize a página e confira o tamanho do PDF.'); }
        if ($loteSetupError) throw new RuntimeException($loteSetupError);
        $raw = $_POST['dados'] ?? '';
        if (!is_string($raw) || strlen($raw) > 512000) throw new DomainException('Dados do orçamento acima do limite permitido.');
        $data = json_decode($raw, true, 32);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) throw new DomainException('Dados do orçamento inválidos.');
        $result = lote_import($pdo, $data, $_FILES['pdf'] ?? [], $userIdLote, $nivelLote);
        echo json_encode(['ok' => true, 'resultado' => $result], JSON_UNESCAPED_UNICODE);
    } catch (DomainException $e) {
        if (http_response_code() < 400) http_response_code(422);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('SO lote import: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Não foi possível concluir a gravação. Tente novamente; documentos já gravados não serão duplicados. Se persistir, contate o suporte.']);
    }
    exit;
}
$secretariasLote = $pdo->query('SELECT id, nome FROM secretarias ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$fornecedoresLote = $pdo->query('SELECT id, nome, cnpj FROM fornecedores ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
$recentesLote = [];
if (!$loteSetupError) {
    $stmt = $pdo->prepare('SELECT i.nome_original, i.token, i.oficio_id, o.numero, o.status,
        (SELECT MIN(a.id) FROM aquisicoes a WHERE a.oficio_id = i.oficio_id) AS aquisicao_id
        FROM orcamentos_importacoes i JOIN oficios o ON o.id = i.oficio_id WHERE i.usuario_id = ? ORDER BY i.id DESC LIMIT 20');
    $stmt->execute([$userIdLote]); $recentesLote = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$page_title = 'Orçamentos em lote';
require __DIR__ . '/views/layout/header.php';
?>
<link rel="stylesheet" href="assets/css/orcamentos-lote.css">
<section class="budget-batch" id="budget-batch">
    <div class="batch-heading"><div><h1>Orçamentos em lote</h1><p>Um PDF por orçamento. Uma aquisição para cada documento aprovado.</p></div><a class="btn btn-outline" href="oficios_lista.php">Ver solicitações</a></div>
    <?php if ($loteSetupError): ?><p class="batch-alert"><?php echo htmlspecialchars($loteSetupError); ?></p><?php endif; ?>
    <div class="batch-upload" id="batch-drop">
        <div><strong>Adicione os orçamentos</strong><p>Até 20 PDFs por lote, 15 MB e 12 páginas por arquivo. Os documentos são lidos um de cada vez.</p></div>
        <label class="btn btn-primary" for="batch-files">Selecionar PDFs</label><input id="batch-files" type="file" accept="application/pdf,.pdf" multiple class="batch-file-input">
    </div>
    <div class="batch-defaults">
        <label>Secretaria para novos arquivos<select id="batch-secretaria"><option value="">Selecione</option><?php foreach ($secretariasLote as $s): ?><option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['nome']); ?></option><?php endforeach; ?></select></label>
        <label>Fornecedor para novos arquivos<select id="batch-fornecedor"><option value="">Selecionar em cada orçamento</option><?php foreach ($fornecedoresLote as $f): ?><option value="<?php echo (int)$f['id']; ?>"><?php echo htmlspecialchars($f['nome'] . ($f['cnpj'] ? ' · ' . $f['cnpj'] : '')); ?></option><?php endforeach; ?></select></label>
    </div>
    <p id="batch-status" role="status" aria-live="polite">Selecione os PDFs para iniciar a leitura das tabelas.</p>
    <div class="batch-workspace" hidden id="batch-workspace">
        <aside class="batch-queue" aria-label="Orçamentos do lote"><h2>Documentos <span id="batch-count">0</span></h2><div id="batch-documents"></div></aside>
        <div class="batch-editor" id="batch-editor"></div>
    </div>
    <div class="batch-actions"><p id="batch-summary">Nenhum orçamento adicionado.</p><button class="btn btn-primary" id="batch-submit" disabled><?php echo $canApprove ? 'Revisar geração de aquisições' : 'Revisar envio para aprovação'; ?></button></div>
    <div id="batch-results" aria-live="polite"></div>
    <?php if ($recentesLote): ?><details class="batch-history"><summary>Minhas últimas importações</summary><ul><?php foreach ($recentesLote as $r): ?><li><a href="orcamentos_lote.php?arquivo=<?php echo htmlspecialchars($r['token']); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($r['nome_original']); ?></a> — <?php echo htmlspecialchars($r['numero'] . ' · ' . $r['status']); ?><?php if ($r['aquisicao_id']): ?> · <a href="aquisicoes_visualizar.php?id=<?php echo (int)$r['aquisicao_id']; ?>">Abrir aquisição</a><?php endif; ?></li><?php endforeach; ?></ul></details><?php endif; ?>
</section>
<dialog id="batch-confirm" class="batch-dialog"><form method="dialog"><h2><?php echo $canApprove ? 'Aprovar e gerar aquisições' : 'Enviar solicitações para aprovação'; ?></h2><div id="batch-confirm-summary"></div><p>Somente os documentos conferidos e sem pendências serão processados. Cada arquivo terá uma solicitação própria.</p><div class="batch-dialog-actions"><button class="btn btn-outline" value="cancel">Voltar à conferência</button><button class="btn btn-primary" id="batch-confirm-go" value="confirm"><?php echo $canApprove ? 'Aprovar e gerar' : 'Confirmar envio'; ?></button></div></form></dialog>
<script type="application/json" id="batch-config"><?php echo json_encode(['csrf' => $_SESSION['csrf_orcamentos_lote'], 'canApprove' => $canApprove, 'secretarias' => $secretariasLote, 'fornecedores' => $fornecedoresLote, 'disabled' => (bool)$loteSetupError], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script src="assets/js/vendor/pdfjs/pdf.min.js"></script>
<script src="assets/js/vendor/tesseract/tesseract.min.js"></script>
<script type="module" src="assets/js/orcamentos-lote.js"></script>
<?php require __DIR__ . '/views/layout/footer.php'; ?>

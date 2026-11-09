<?php
require_once 'config/database.php';
require_once 'config/functions.php';
login_check();
suporte_check();

$page_title = 'Assinatura de Autorização';
$finalidade = 'AUTORIZACAO_FORNECEDOR';
$upload_dir = __DIR__ . '/storage/assinaturas';
$upload_relativo = 'storage/assinaturas';
$max_upload_bytes = 5 * 1024 * 1024;

if (empty($_SESSION['csrf_config_assinatura'])) {
    $_SESSION['csrf_config_assinatura'] = bin2hex(random_bytes(32));
}
$csrf_token = (string)$_SESSION['csrf_config_assinatura'];

function h_assinatura($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function assinatura_redirect()
{
    header('Location: configuracoes_assinatura.php', true, 303);
    exit;
}

function assinatura_buscar_ativa(PDO $pdo, $finalidade)
{
    $stmt = $pdo->prepare("
        SELECT id, nome, arquivo_path, finalidade, ativo, criado_por, criado_em, atualizado_em
        FROM assinaturas_sistema
        WHERE finalidade = ?
          AND ativo = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$finalidade]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function assinatura_buscar_ultima(PDO $pdo, $finalidade)
{
    $stmt = $pdo->prepare("
        SELECT id, nome, arquivo_path, finalidade, ativo, criado_por, criado_em, atualizado_em
        FROM assinaturas_sistema
        WHERE finalidade = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$finalidade]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = isset($_POST['csrf_token']) && is_scalar($_POST['csrf_token'])
        ? (string)$_POST['csrf_token']
        : '';
    $acao = isset($_POST['acao']) && is_scalar($_POST['acao'])
        ? trim((string)$_POST['acao'])
        : '';

    if ($post_csrf === '' || !hash_equals($csrf_token, $post_csrf)) {
        flash_message('danger', 'A sessão expirou. Atualize a página e tente novamente.');
        assinatura_redirect();
    }

    try {
        if ($acao === 'upload') {
            if (!isset($_FILES['assinatura']) || !is_array($_FILES['assinatura'])) {
                throw new DomainException('Selecione uma imagem de assinatura.');
            }

            $arquivo = $_FILES['assinatura'];
            $erro_upload = isset($arquivo['error']) ? (int)$arquivo['error'] : UPLOAD_ERR_NO_FILE;

            if ($erro_upload !== UPLOAD_ERR_OK) {
                $mensagens_upload = [
                    UPLOAD_ERR_INI_SIZE => 'O arquivo excede o limite do servidor.',
                    UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o limite permitido.',
                    UPLOAD_ERR_PARTIAL => 'O upload foi enviado parcialmente. Tente novamente.',
                    UPLOAD_ERR_NO_FILE => 'Selecione uma imagem de assinatura.',
                    UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem diretório temporário para upload.',
                    UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo.',
                    UPLOAD_ERR_EXTENSION => 'Uma extensão do servidor bloqueou o upload.',
                ];
                throw new DomainException(isset($mensagens_upload[$erro_upload]) ? $mensagens_upload[$erro_upload] : 'Falha no upload da assinatura.');
            }

            $tmp_name = isset($arquivo['tmp_name']) ? (string)$arquivo['tmp_name'] : '';
            $tamanho = isset($arquivo['size']) ? (int)$arquivo['size'] : 0;

            if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
                throw new DomainException('O arquivo enviado não é um upload válido.');
            }
            if ($tamanho <= 0) {
                throw new DomainException('A imagem enviada está vazia.');
            }
            if ($tamanho > $max_upload_bytes) {
                throw new DomainException('A imagem deve ter no máximo 5 MB.');
            }

            $mime = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime_detectado = finfo_file($finfo, $tmp_name);
                    finfo_close($finfo);
                    if (is_string($mime_detectado)) {
                        $mime = strtolower(trim($mime_detectado));
                    }
                }
            }

            if ($mime === '' && function_exists('mime_content_type')) {
                $mime_detectado = @mime_content_type($tmp_name);
                if (is_string($mime_detectado)) {
                    $mime = strtolower(trim($mime_detectado));
                }
            }

            $tipos_permitidos = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
            ];

            if (!isset($tipos_permitidos[$mime])) {
                throw new DomainException('Formato não permitido. Use PNG, JPG/JPEG ou WebP.');
            }

            $imagem_info = @getimagesize($tmp_name);
            if ($imagem_info === false || empty($imagem_info[0]) || empty($imagem_info[1])) {
                throw new DomainException('O arquivo enviado não contém uma imagem válida.');
            }

            if ((int)$imagem_info[0] > 6000 || (int)$imagem_info[1] > 6000) {
                throw new DomainException('A imagem possui dimensões muito grandes. O limite é 6000 x 6000 pixels.');
            }

            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0750, true)) {
                throw new RuntimeException('Não foi possível preparar a pasta de assinaturas.');
            }
            if (!is_writable($upload_dir)) {
                throw new RuntimeException('A pasta de assinaturas não possui permissão de escrita.');
            }

            $nome_informado = isset($_POST['nome']) && is_scalar($_POST['nome'])
                ? trim((string)$_POST['nome'])
                : '';
            if ($nome_informado === '') {
                $nome_informado = 'Assinatura de Autorização';
            }
            if (function_exists('mb_substr')) {
                $nome_informado = mb_substr($nome_informado, 0, 150, 'UTF-8');
            } else {
                $nome_informado = substr($nome_informado, 0, 150);
            }

            $extensao = $tipos_permitidos[$mime];
            $nome_arquivo = 'assinatura_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extensao;
            $destino = $upload_dir . DIRECTORY_SEPARATOR . $nome_arquivo;
            $caminho_banco = $upload_relativo . '/' . $nome_arquivo;

            if (!move_uploaded_file($tmp_name, $destino)) {
                throw new RuntimeException('Não foi possível salvar a imagem da assinatura.');
            }
            @chmod($destino, 0640);

            try {
                $pdo->beginTransaction();

                $stmt_desativar = $pdo->prepare("UPDATE assinaturas_sistema SET ativo = 0 WHERE finalidade = ? AND ativo = 1");
                $stmt_desativar->execute([$finalidade]);

                $stmt_insert = $pdo->prepare("
                    INSERT INTO assinaturas_sistema
                        (nome, arquivo_path, finalidade, ativo, criado_por)
                    VALUES (?, ?, ?, 1, ?)
                ");
                $stmt_insert->execute([
                    $nome_informado,
                    $caminho_banco,
                    $finalidade,
                    (string)($_SESSION['user_nome'] ?? 'SUPORTE'),
                ]);

                $nova_id = (int)$pdo->lastInsertId();
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                @unlink($destino);
                throw $e;
            }

            log_action($pdo, 'ALTERAR_ASSINATURA', 'Nova assinatura de autorização cadastrada e ativada. ID: ' . $nova_id);
            flash_message('success', 'Assinatura enviada e ativada com sucesso.');
            $_SESSION['csrf_config_assinatura'] = bin2hex(random_bytes(32));
            assinatura_redirect();
        }

        if ($acao === 'desativar') {
            $stmt = $pdo->prepare("UPDATE assinaturas_sistema SET ativo = 0 WHERE finalidade = ? AND ativo = 1");
            $stmt->execute([$finalidade]);
            log_action($pdo, 'DESATIVAR_ASSINATURA', 'Assinatura de autorização desativada.');
            flash_message('success', 'Assinatura desativada. As próximas impressões sairão sem assinatura automática.');
            $_SESSION['csrf_config_assinatura'] = bin2hex(random_bytes(32));
            assinatura_redirect();
        }

        if ($acao === 'ativar') {
            $assinatura_id_raw = isset($_POST['assinatura_id']) && is_scalar($_POST['assinatura_id'])
                ? trim((string)$_POST['assinatura_id'])
                : '';
            $assinatura_id = ctype_digit($assinatura_id_raw) ? (int)$assinatura_id_raw : 0;

            if ($assinatura_id <= 0) {
                throw new DomainException('Assinatura inválida para ativação.');
            }

            $stmt = $pdo->prepare("
                SELECT id, arquivo_path
                FROM assinaturas_sistema
                WHERE id = ? AND finalidade = ?
                LIMIT 1
            ");
            $stmt->execute([$assinatura_id, $finalidade]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$registro) {
                throw new DomainException('A assinatura selecionada não foi encontrada.');
            }

            $arquivo_relativo = str_replace('\\', '/', trim((string)$registro['arquivo_path']));
            if (strpos($arquivo_relativo, 'SO/') === 0) {
                $arquivo_relativo = substr($arquivo_relativo, 3);
            }
            if ($arquivo_relativo === '' || preg_match('~(^|/)\.\.(/|$)~', $arquivo_relativo)) {
                throw new DomainException('O caminho do arquivo desta assinatura é inválido.');
            }

            $base = realpath($upload_dir);
            $arquivo_real = realpath(__DIR__ . '/' . ltrim($arquivo_relativo, '/'));
            if ($base === false || $arquivo_real === false || !is_file($arquivo_real)) {
                throw new DomainException('O arquivo desta assinatura não está mais disponível no servidor.');
            }
            $prefixo = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($arquivo_real, $prefixo) !== 0) {
                throw new DomainException('O arquivo desta assinatura está fora da pasta permitida.');
            }

            $pdo->beginTransaction();
            $stmt_desativar = $pdo->prepare("UPDATE assinaturas_sistema SET ativo = 0 WHERE finalidade = ? AND ativo = 1");
            $stmt_desativar->execute([$finalidade]);
            $stmt_ativar = $pdo->prepare("UPDATE assinaturas_sistema SET ativo = 1 WHERE id = ? AND finalidade = ?");
            $stmt_ativar->execute([$assinatura_id, $finalidade]);
            $pdo->commit();

            log_action($pdo, 'ATIVAR_ASSINATURA', 'Assinatura de autorização reativada. ID: ' . $assinatura_id);
            flash_message('success', 'Assinatura ativada com sucesso.');
            $_SESSION['csrf_config_assinatura'] = bin2hex(random_bytes(32));
            assinatura_redirect();
        }

        throw new DomainException('Ação de assinatura inválida.');
    } catch (DomainException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_message('danger', $e->getMessage());
        assinatura_redirect();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash_message('danger', 'Não foi possível concluir a operação da assinatura. Verifique o arquivo e tente novamente.');
        assinatura_redirect();
    }
}

$assinatura_ativa = assinatura_buscar_ativa($pdo, $finalidade);
$assinatura_ultima = assinatura_buscar_ultima($pdo, $finalidade);

$stmt_historico = $pdo->prepare("
    SELECT id, nome, arquivo_path, ativo, criado_por, criado_em, atualizado_em
    FROM assinaturas_sistema
    WHERE finalidade = ?
    ORDER BY id DESC
    LIMIT 10
");
$stmt_historico->execute([$finalidade]);
$historico = $stmt_historico->fetchAll(PDO::FETCH_ASSOC);

$assinatura_preview = $assinatura_ativa ?: $assinatura_ultima;

include 'views/layout/header.php';
?>

<style>
.assinatura-page{max-width:1120px;margin:0 auto}.assinatura-page-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem}.assinatura-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.8fr);gap:1.5rem;margin-bottom:1.5rem}.assinatura-card-title{display:flex;align-items:center;gap:.65rem;margin:0 0 1.25rem;font-size:1.05rem}.assinatura-preview{min-height:210px;border:1px dashed #cbd5e1;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;padding:1.5rem;position:relative;overflow:hidden}.assinatura-preview img{max-width:100%;max-height:150px;object-fit:contain}.assinatura-empty{color:var(--text-muted);text-align:center}.assinatura-status{display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;border-radius:999px;font-size:.78rem;font-weight:800}.assinatura-status.ativa{background:#dcfce7;color:#166534}.assinatura-status.inativa{background:#f1f5f9;color:#475569}.assinatura-meta{margin-top:1rem;display:grid;gap:.45rem;font-size:.86rem;color:var(--text-muted)}.assinatura-meta strong{color:var(--text-dark)}.assinatura-upload-box{border:1px solid #e2e8f0;border-radius:12px;padding:1rem;background:#f8fafc}.assinatura-file{display:block;width:100%;padding:.8rem;border:1px solid #cbd5e1;border-radius:10px;background:#fff}.assinatura-help{font-size:.78rem;color:var(--text-muted);line-height:1.5;margin-top:.65rem}.assinatura-actions{display:flex;gap:.65rem;flex-wrap:wrap;margin-top:1rem}.assinatura-history-status{font-weight:800;font-size:.75rem}.assinatura-history-status.on{color:#15803d}.assinatura-history-status.off{color:#64748b}@media(max-width:900px){.assinatura-grid{grid-template-columns:1fr}}@media(max-width:600px){.assinatura-page-head,.assinatura-actions{flex-direction:column;align-items:stretch}.assinatura-page-head .btn,.assinatura-actions .btn{width:100%;justify-content:center}}
</style>

<div class="assinatura-page">
    <div class="assinatura-page-head">
        <div>
            <h2 style="margin:0 0 .35rem;"><i class="fas fa-signature" style="color:var(--primary);margin-right:.45rem;"></i>Assinatura de Autorização</h2>
            <div style="color:var(--text-muted);font-size:.9rem;">Gerencie a assinatura usada na autorização de fornecedor. Acesso exclusivo do SUPORTE.</div>
        </div>
        <a href="configuracoes.php" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Voltar às configurações</a>
    </div>

    <?php display_flash(); ?>

    <div class="assinatura-grid">
        <div class="card">
            <div class="card-body">
                <h3 class="assinatura-card-title"><i class="fas fa-eye" style="color:var(--primary);"></i> Assinatura cadastrada</h3>

                <div class="assinatura-preview" id="assinaturaPreviewAtual">
                    <?php if ($assinatura_preview): ?>
                        <img src="assinatura_suporte.php?id=<?= (int)$assinatura_preview['id'] ?>&v=<?= urlencode((string)($assinatura_preview['atualizado_em'] ?? $assinatura_preview['criado_em'])) ?>" alt="Pré-visualização da assinatura">
                    <?php else: ?>
                        <div class="assinatura-empty"><i class="fas fa-signature" style="font-size:2rem;margin-bottom:.6rem;display:block;"></i>Nenhuma assinatura cadastrada.</div>
                    <?php endif; ?>
                </div>

                <?php if ($assinatura_preview): ?>
                    <div class="assinatura-meta">
                        <div><strong>Status:</strong> <span class="assinatura-status <?= $assinatura_ativa ? 'ativa' : 'inativa' ?>"><i class="fas <?= $assinatura_ativa ? 'fa-check-circle' : 'fa-pause-circle' ?>"></i><?= $assinatura_ativa ? 'ATIVA' : 'INATIVA' ?></span></div>
                        <div><strong>Nome:</strong> <?= h_assinatura($assinatura_preview['nome']) ?></div>
                        <div><strong>Cadastrada por:</strong> <?= h_assinatura($assinatura_preview['criado_por'] ?: 'Não informado') ?></div>
                        <div><strong>Data:</strong> <?= h_assinatura(format_date($assinatura_preview['criado_em'])) ?></div>
                    </div>
                <?php endif; ?>

                <div class="assinatura-actions">
                    <?php if ($assinatura_ativa): ?>
                        <form method="POST" onsubmit="return confirm('Desativar a assinatura automática? As próximas impressões sairão sem assinatura.');">
                            <input type="hidden" name="csrf_token" value="<?= h_assinatura($csrf_token) ?>">
                            <input type="hidden" name="acao" value="desativar">
                            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-pause"></i> Desativar assinatura</button>
                        </form>
                    <?php elseif ($assinatura_ultima): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= h_assinatura($csrf_token) ?>">
                            <input type="hidden" name="acao" value="ativar">
                            <input type="hidden" name="assinatura_id" value="<?= (int)$assinatura_ultima['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Ativar assinatura</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="assinatura-card-title"><i class="fas fa-upload" style="color:var(--primary);"></i> Enviar nova assinatura</h3>

                <form method="POST" enctype="multipart/form-data" id="assinaturaUploadForm">
                    <input type="hidden" name="csrf_token" value="<?= h_assinatura($csrf_token) ?>">
                    <input type="hidden" name="acao" value="upload">

                    <div class="form-group" style="margin-bottom:1rem;">
                        <label class="form-label">Identificação</label>
                        <input type="text" name="nome" class="form-control" maxlength="150" value="Assinatura de Autorização" required>
                    </div>

                    <div class="assinatura-upload-box">
                        <label class="form-label">Imagem da assinatura</label>
                        <input type="file" name="assinatura" id="assinaturaArquivo" class="assinatura-file" accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" required>
                        <div class="assinatura-help">
                            Formatos aceitos: PNG, JPG/JPEG e WebP. Máximo de 5 MB. Para melhor resultado, use PNG com fundo transparente e somente a assinatura, sem bordas.
                        </div>
                    </div>

                    <div class="assinatura-preview" id="assinaturaNovoPreview" style="min-height:150px;margin-top:1rem;display:none;"></div>

                    <button type="submit" class="btn btn-primary" style="margin-top:1rem;width:100%;justify-content:center;"><i class="fas fa-cloud-upload-alt"></i> Enviar e ativar assinatura</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3 class="assinatura-card-title"><i class="fas fa-history" style="color:var(--primary);"></i> Histórico recente</h3>
            <div class="table-responsive">
                <table class="table-vcenter">
                    <thead>
                        <tr><th>ID</th><th>Nome</th><th>Status</th><th>Cadastrada por</th><th>Data</th><th style="text-align:right;">Ação</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($historico)): ?>
                        <tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:1.5rem;">Nenhuma assinatura cadastrada.</td></tr>
                    <?php else: ?>
                        <?php foreach ($historico as $registro): ?>
                            <tr>
                                <td>#<?= (int)$registro['id'] ?></td>
                                <td style="font-weight:700;"><?= h_assinatura($registro['nome']) ?></td>
                                <td><span class="assinatura-history-status <?= (int)$registro['ativo'] === 1 ? 'on' : 'off' ?>"><?= (int)$registro['ativo'] === 1 ? 'ATIVA' : 'INATIVA' ?></span></td>
                                <td><?= h_assinatura($registro['criado_por'] ?: '-') ?></td>
                                <td><?= h_assinatura(format_date($registro['criado_em'])) ?></td>
                                <td style="text-align:right;">
                                    <?php if ((int)$registro['ativo'] !== 1): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= h_assinatura($csrf_token) ?>">
                                            <input type="hidden" name="acao" value="ativar">
                                            <input type="hidden" name="assinatura_id" value="<?= (int)$registro['id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-check"></i> Ativar</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);font-size:.8rem;">Em uso</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('assinaturaArquivo');
    var preview = document.getElementById('assinaturaNovoPreview');
    if (!input || !preview) return;

    input.addEventListener('change', function () {
        preview.innerHTML = '';
        preview.style.display = 'none';

        if (!input.files || !input.files[0]) return;
        var file = input.files[0];
        if (file.size > 5 * 1024 * 1024) {
            alert('A imagem deve ter no máximo 5 MB.');
            input.value = '';
            return;
        }

        var allowed = ['image/png', 'image/jpeg', 'image/webp'];
        if (allowed.indexOf(file.type) === -1) {
            alert('Use uma imagem PNG, JPG/JPEG ou WebP.');
            input.value = '';
            return;
        }

        var reader = new FileReader();
        reader.onload = function (event) {
            var img = document.createElement('img');
            img.src = event.target.result;
            img.alt = 'Pré-visualização da nova assinatura';
            preview.appendChild(img);
            preview.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    });
});
</script>

<?php include 'views/layout/footer.php'; ?>

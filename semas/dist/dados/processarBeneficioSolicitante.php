<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/authGuard.php';
auth_guard();

/* Conexão */
require_once __DIR__ . '/../assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro de conexão.');location.href='../atribuirBeneficio.php';</script>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Helpers */
function only_digits(?string $s): string
{
    return preg_replace('/\D+/', '', (string)$s) ?? '';
}

function moneyToFloat(?string $s): float
{
    $s = trim((string)$s);
    if ($s === '') return 0.0;
    // pt-BR: 1.234,56 -> 1234.56
    if (preg_match('/,\d{1,2}$/', $s)) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '0';
    return (float)$s;
}

function normalizeDate(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '') return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return null;
    return $s;
}

function normalizeTime(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '') return null;
    // aceita HH:MM ou HH:MM:SS
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) return $s;
    if (preg_match('/^\d{2}:\d{2}$/', $s)) return $s . ':00';
    return null;
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
    ");
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function alert_back(string $msg): void
{
    $m = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "<script>alert($m);history.back();</script>";
    exit;
}

function alert_go(string $msg, string $url): void
{
    $m = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $u = json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "<script>alert($m);location.href=$u;</script>";
    exit;
}

/* Somente POST */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo "<script>location.href='../atribuirBeneficio.php';</script>";
    exit;
}

/* ===== Usuário logado (fallback p/ responsável) ===== */
$nomeLogado =
    ((string)($_SESSION['usuario_nome'] ?? '')) ?: ((string)($_SESSION['nome'] ?? '')) ?: ((string)($_SESSION['user_nome'] ?? '')) ?: ((string)($_SESSION['usuario'] ?? '')) ?: ((string)($_SESSION['username'] ?? '')) ?:
    '';

/* ===== Dados do POST ===== */
$pessoaId        = (int)($_POST['pessoa_id'] ?? 0);
$pessoaCpf       = only_digits($_POST['pessoa_cpf'] ?? '');
$ajudaTipoId     = (int)($_POST['ajuda_tipo_id'] ?? 0);
$solicitacaoId   = (int)($_POST['solicitacao_id'] ?? 0);

$dataEntrega     = normalizeDate($_POST['data_entrega'] ?? '');
$horaEntrega     = normalizeTime($_POST['hora_entrega'] ?? ''); // pega do form

$quantidade      = (int)($_POST['quantidade'] ?? 1);
$valorStr        = (string)($_POST['valor_aplicado'] ?? '');
$valor           = moneyToFloat($valorStr);

$responsavelPost = trim((string)($_POST['responsavel'] ?? ''));
$responsavel     = $responsavelPost !== '' ? $responsavelPost : $nomeLogado;

$observacao      = trim((string)($_POST['observacao'] ?? ''));
$marcarNoCadastro = isset($_POST['marcar_cadastro']);

/* ✅ Sempre salvar como entregue */
$entregue = 'Sim';

/* ✅ Foto vinda do form (base64) */
$fotoBase64 = trim((string)($_POST['foto_base64'] ?? ''));
$fotoMime   = trim((string)($_POST['foto_mime'] ?? ''));

/* Validações */
if ($pessoaId <= 0)     alert_back('Selecione o solicitante.');
if ($solicitacaoId <= 0) alert_back('Selecione a solicitação que será atendida.');
if ($ajudaTipoId <= 0)  alert_back('Selecione o tipo de ajuda.');
if (!$dataEntrega)      alert_back('Informe a data de entrega.');
if ($quantidade <= 0)   alert_back('Quantidade deve ser maior que zero.');

/* Se hora não vier, salva NULL (não impede) */
if ($horaEntrega === null) $horaEntrega = null;

try {
    if (!table_has_column($pdo, 'ajudas_entregas', 'solicitacao_id')) {
        throw new RuntimeException('A coluna solicitacao_id ainda não existe em ajudas_entregas. Execute a migration de solicitações.');
    }

    /* Confirma solicitante e pega nome/cpf do cadastro (pra usar no nome do arquivo) */
    $ck1 = $pdo->prepare("SELECT id, nome, cpf FROM solicitantes WHERE id=:id LIMIT 1");
    $ck1->execute([':id' => $pessoaId]);
    $sRow = $ck1->fetch(PDO::FETCH_ASSOC);
    if (!$sRow) throw new RuntimeException('Solicitante inexistente.');

    $nomeSolicitante = (string)($sRow['nome'] ?? 'solicitante');

    // CPF final: do form (se veio) ou do cadastro
    $cpfFinal = $pessoaCpf !== '' ? $pessoaCpf : only_digits($sRow['cpf'] ?? '');
    if (strlen($cpfFinal) !== 11) $cpfFinal = null;

    /* Confirma tipo de ajuda */
    $ck2 = $pdo->prepare("SELECT id FROM ajudas_tipos WHERE id=:id LIMIT 1");
    $ck2->execute([':id' => $ajudaTipoId]);
    if (!$ck2->fetchColumn()) throw new RuntimeException('Tipo de ajuda inválido.');

    /* Confirma a solicitação específica por ID; se o CPF for o mesmo, usa o dono real da solicitação. */
    $ckSol = $pdo->prepare("
        SELECT sol.id, sol.solicitante_id, sol.ajuda_tipo_id, sol.data_solicitacao, sol.status,
               s.nome AS solicitante_nome, s.cpf AS solicitante_cpf
          FROM solicitacoes sol
          INNER JOIN solicitantes s ON s.id = sol.solicitante_id
         WHERE sol.id = :id
         LIMIT 1
    ");
    $ckSol->execute([':id' => $solicitacaoId]);
    $solRow = $ckSol->fetch(PDO::FETCH_ASSOC);
    if (!$solRow) {
        throw new RuntimeException('Solicitação não encontrada.');
    }

    $solPessoaId = (int)$solRow['solicitante_id'];
    $solCpf = only_digits((string)($solRow['solicitante_cpf'] ?? ''));
    $formCpf = only_digits((string)($sRow['cpf'] ?? $pessoaCpf));

    if ($solPessoaId !== $pessoaId && ($solCpf === '' || $formCpf === '' || $solCpf !== $formCpf)) {
        throw new RuntimeException('A solicitação selecionada não pertence ao solicitante informado.');
    }

    if ($solPessoaId !== $pessoaId) {
        $pessoaId = $solPessoaId;
        $nomeSolicitante = (string)($solRow['solicitante_nome'] ?? $nomeSolicitante);
        $cpfFinal = strlen($solCpf) === 11 ? $solCpf : $cpfFinal;
    }

    $solAjudaTipoId = (int)($solRow['ajuda_tipo_id'] ?? 0);
    if ($solAjudaTipoId > 0 && $solAjudaTipoId !== $ajudaTipoId) {
        throw new RuntimeException('O tipo de ajuda não corresponde à solicitação selecionada.');
    }

    /* ===== FOTO: salva em uploads/fotos e gera foto_path_rel ===== */
    $foto_path_rel = null;
    $foto_mime_db  = null;

    if ($fotoBase64 !== '') {
        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $fotoBase64, $m0)) {
            $fotoBase64 = '';
        } else {
            $mimeFromData = 'image/' . strtolower($m0[1] ?? 'jpeg');
            $foto_mime_db = $fotoMime !== '' ? $fotoMime : $mimeFromData;

            $ext = 'jpg';
            $mimeLower = strtolower((string)$foto_mime_db);
            if (str_contains($mimeLower, 'png'))  $ext = 'png';
            if (str_contains($mimeLower, 'webp')) $ext = 'webp';
            if (str_contains($mimeLower, 'jpeg') || str_contains($mimeLower, 'jpg')) $ext = 'jpg';

            $parts = explode(',', $fotoBase64, 2);
            $b64 = $parts[1] ?? '';
            $bin = base64_decode($b64, true);

            if ($bin === false || $bin === '') {
                alert_back('Foto inválida (base64).');
            }

            $fotosDir = realpath(__DIR__ . '/..') . '/uploads/fotos';
            if (!is_dir($fotosDir)) {
                if (!@mkdir($fotosDir, 0775, true) && !is_dir($fotosDir)) {
                    alert_back('Não foi possível criar a pasta uploads/fotos.');
                }
            }

            $nome = $nomeSolicitante;
            $baseName = 'fotos_' . date('Ymd_His') . '_' . substr(sha1($nome . microtime(true)), 0, 8) . '.' . $ext;
            $destAbs  = $fotosDir . '/' . $baseName;

            if (@file_put_contents($destAbs, $bin) === false) {
                alert_back('Falha ao salvar a foto.');
            }

            $foto_path_rel = 'uploads/fotos/' . $baseName;
        }
    }

    $pdo->beginTransaction();

    /* ✅ INSERT com entregue='Sim' */
    $ins = $pdo->prepare("
        INSERT INTO ajudas_entregas
        (ajuda_tipo_id, pessoa_id, solicitacao_id, pessoa_cpf, familia_id, data_entrega, hora_entrega, quantidade, valor_aplicado, responsavel, observacao, foto_path, foto_mime, entregue)
        VALUES
        (:tid, :pid, :sid, :pcpf, NULL, :data, :hora, :qtd, :valor, :resp, :obs, :foto_path, :foto_mime, :entregue)
    ");

    $ins->execute([
        ':tid'       => $ajudaTipoId,
        ':pid'       => $pessoaId,
        ':sid'       => ($solicitacaoId > 0 ? $solicitacaoId : null),
        ':pcpf'      => $cpfFinal,
        ':data'      => $dataEntrega,
        ':hora'      => $horaEntrega,
        ':qtd'       => $quantidade,
        ':valor'     => ($valor > 0 ? $valor : null),
        ':resp'      => ($responsavel !== '' ? $responsavel : null),
        ':obs'       => ($observacao !== '' ? $observacao : null),
        ':foto_path' => $foto_path_rel,
        ':foto_mime' => $foto_mime_db,
        ':entregue'  => $entregue, // ✅ sempre "Sim"
    ]);

    /* Reflete no cadastro, se marcado */
    if ($marcarNoCadastro) {
        $up = $pdo->prepare("
            UPDATE solicitantes
               SET beneficio_semas='Sim',
                   beneficio_semas_valor=:v
             WHERE id=:id
        ");
        $up->execute([
            ':v'  => ($valor > 0 ? $valor : 0),
            ':id' => $pessoaId
        ]);
    }

    $upSolicitacao = $pdo->prepare("
        UPDATE solicitacoes
           SET status = 'Concluído'
         WHERE id = :id
         LIMIT 1
    ");
    $upSolicitacao->execute([':id' => $solicitacaoId]);

    $pdo->commit();

    alert_go('Entrega registrada com sucesso.', '../atribuirBeneficio.php?cpf=' . urlencode((string)$cpfFinal) . '&solicitacao_id=' . urlencode((string)$solicitacaoId));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msg = ($e instanceof RuntimeException) ? $e->getMessage() : 'Falha ao registrar a entrega.';
    alert_back($msg);
}

?>

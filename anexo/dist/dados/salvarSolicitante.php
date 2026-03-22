<?php

declare(strict_types=1);


ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ===== Helpers ===== */
function alert_back(string $msg): never
{
    $msg = addslashes($msg);
    echo "<script>alert('{$msg}');history.back();</script>";
    exit;
}
function alert_redirect(string $msg, string $url): never
{
    $msg = addslashes($msg);
    $url = addslashes($url);
    echo "<script>alert('{$msg}');window.location.href='{$url}';</script>";
    exit;
}
function only_digits(?string $v): string
{
    return preg_replace('/\D+/', '', (string) $v);
}
/** Normaliza dinheiro para string SQL "1234.43" (lida com 1.234,43 / 1,234.43 / 1234,43 / 1234.43 / 1234) */
function money_to_sql(?string $v): ?string
{
    if ($v === null)
        return null;
    $v = trim($v);
    if ($v === '')
        return null;
    $s = preg_replace('/[^\d\.,]/', '', $v);
    if ($s === '')
        return null;

    $lastComma = strrpos($s, ',');
    $lastDot = strrpos($s, '.');
    $decPos = -1;
    if ($lastComma !== false || $lastDot !== false) {
        $decPos = max($lastComma === false ? -1 : $lastComma, $lastDot === false ? -1 : $lastDot);
    }

    if ($decPos >= 0) {
        $intPart = preg_replace('/\D+/', '', substr($s, 0, $decPos));
        $decPart = preg_replace('/\D+/', '', substr($s, $decPos + 1));
        if ($decPart === '')
            $decPart = '00';
        else
            $decPart = substr(str_pad($decPart, 2, '0'), 0, 2);
        if ($intPart === '')
            $intPart = '0';
        return $intPart . '.' . $decPart;
    }

    $intPart = preg_replace('/\D+/', '', $s);
    if ($intPart === '')
        return null;
    return $intPart . '.00';
}

/* ===== Conexão ===== */
try {
    include __DIR__ . '/../assets/conexao.php'; // define $pdo
    if (!isset($pdo) || !($pdo instanceof PDO))
        alert_back('Falha na conexão com o banco.');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    alert_back('Erro ao conectar ao banco: ' . $e->getMessage());
}


/* ===== (Opcional) Garante coluna ajuda_tipo_id (sem FK) ===== */
function ensure_ajuda_tipo_column(PDO $pdo): void
{
    try {
        $db = (string) $pdo->query("SELECT DATABASE()")->fetchColumn();
        if ($db === '')
            return;

        $sqlChk = "
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db
              AND TABLE_NAME   = 'solicitantes'
              AND COLUMN_NAME  = 'ajuda_tipo_id'
        ";
        $st = $pdo->prepare($sqlChk);
        $st->execute([':db' => $db]);
        $exists = (int) $st->fetchColumn();

        if ($exists === 0) {
            // Sem chave estrangeira, só a coluna e índice
            $pdo->exec("ALTER TABLE solicitantes ADD COLUMN ajuda_tipo_id BIGINT UNSIGNED NULL AFTER tipificacao");
            $pdo->exec("CREATE INDEX ix_solicitantes_ajuda_tipo_id ON solicitantes (ajuda_tipo_id)");
        }
    } catch (Throwable $e) {
        // Se não tiver permissão de ALTER, só ignora e segue
    }
}
ensure_ajuda_tipo_column($pdo);

/* ===== Garante coluna resumo_caso ===== */
function ensure_resumo_caso_column(PDO $pdo): void
{
    try {
        $db = (string) $pdo->query("SELECT DATABASE()")->fetchColumn();
        if ($db === '')
            return;

        $sqlChk = "
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db
              AND TABLE_NAME   = 'solicitantes'
              AND COLUMN_NAME  = 'resumo_caso'
        ";
        $st = $pdo->prepare($sqlChk);
        $st->execute([':db' => $db]);
        $exists = (int) $st->fetchColumn();

        if ($exists === 0) {
            $pdo->exec("ALTER TABLE solicitantes ADD COLUMN resumo_caso TEXT DEFAULT NULL AFTER entorno");
        }
    } catch (Throwable $e) {
        // Ignora
    }
}
ensure_resumo_caso_column($pdo);

/* ===== Somente POST ===== */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo "<script>location.href='../cadastrarSolicitante.php';</script>";
    exit;
}

/* ===== Campos principais (Etapa 1) ===== */
$nome = trim($_POST['nome'] ?? '');
$nis = only_digits($_POST['nis'] ?? '');
$cpf = only_digits($_POST['cpf'] ?? '');
$rg = trim($_POST['rg'] ?? '');
$rg_emissao = $_POST['rg_emissao'] ?? null;
$rg_uf = $_POST['rg_uf'] ?? null;
$tel = only_digits($_POST['telefone'] ?? '');
$data_nasc = $_POST['data_nascimento'] ?? null;
$genero = $_POST['genero'] ?? null;
$estado_civil = $_POST['estado_civil'] ?? null;

$data_input = trim($_POST['data_cadastro'] ?? '');
$hora_input = trim($_POST['hora_cadastro'] ?? '');

// Fallback se vazio
if ($data_input === '')
    $data_input = date('Y-m-d');
if ($hora_input === '')
    $hora_input = date('H:i:s');

// Validação básica de hora (HH:MM -> HH:MM:00)
if (preg_match('/^\d{2}:\d{2}$/', $hora_input)) {
    $hora_input .= ':00';
}
// Validação básica de data
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_input)) {
    alert_back("Data de cadastro inválida: $data_input");
}

// Cria DATETIME unificado
$hora_cadastro = $data_input . ' ' . $hora_input;

$responsavel = trim($_POST['responsavel'] ?? '');
if ($responsavel === '') {
    $responsavel = ((string) ($_SESSION['usuario_nome'] ?? '')) ?: ((string) ($_SESSION['nome'] ?? '')) ?: 'Sistema';
}

$endereco = trim($_POST['endereco'] ?? '');
$numero = trim($_POST['numero'] ?? '');
$bairro_id = $_POST['bairro_id'] ?? '';
$referencia = trim($_POST['referencia'] ?? '');
$complemento = trim($_POST['complemento'] ?? '');

$nacionalidade = trim($_POST['nacionalidade'] ?? '');
$naturalidade = trim($_POST['naturalidade'] ?? '');
$tempo_anos = ($_POST['tempo_anos'] ?? '') !== '' ? (int) $_POST['tempo_anos'] : null;
$tempo_meses = ($_POST['tempo_meses'] ?? '') !== '' ? (int) $_POST['tempo_meses'] : null;

function pick_outros(?string $main, ?string $outros): ?string
{
    $main = $main !== null ? trim($main) : null;
    $outros = $outros !== null ? trim($outros) : null;
    if ($main === null || $main === '')
        return null;
    if ($main === 'Outros')
        return ($outros !== '' ? $outros : 'Outros');
    return $main;
}

$grupo_tradicional_main = $_POST['grupo_tradicional'] ?? null;
$grupo_tradicional = pick_outros($grupo_tradicional_main, $_POST['grupo_outros'] ?? null);
$grupo_outros = ($grupo_tradicional_main === 'Outros' && isset($_POST['grupo_outros']) && trim($_POST['grupo_outros']) !== '')
    ? trim($_POST['grupo_outros'])
    : null;

/** PCD / BPC / PBF */
$pcd = $_POST['pcd'] ?? 'Não';
$pcd_tipo = trim($_POST['pcd_tipo'] ?? '');
$bpc = $_POST['bpc'] ?? 'Não';
$bpc_valor = money_to_sql($_POST['bpc_valor'] ?? null);
$pbf = $_POST['pbf'] ?? 'Não';
$pbf_valor = money_to_sql($_POST['pbf_valor'] ?? null);

/** Benefícios Municipal/Estadual */
$beneficio_municipal = $_POST['beneficio_municipal'] ?? 'Não';
$beneficio_municipal_valor = money_to_sql($_POST['beneficio_municipal_valor'] ?? null);
$beneficio_estadual = $_POST['beneficio_estadual'] ?? 'Não';
$beneficio_estadual_valor = money_to_sql($_POST['beneficio_estadual_valor'] ?? null);

/** Renda */
$renda_mensal_faixa = $_POST['renda_mensal_faixa'] ?? null;
$renda_mensal_outros = trim($_POST['renda_mensal_outros'] ?? '');
$trabalho = $_POST['trabalho'] ?? null;
$local_trabalho = ($trabalho === 'Empregado(a)') ? trim($_POST['local_trabalho'] ?? '') : null;
$renda_individual = money_to_sql($_POST['renda_individual'] ?? null);

/* ===== Dados do cônjuge ===== */
$conj_nome = trim($_POST['conj_nome'] ?? '');
$conj_nis = only_digits($_POST['conj_nis'] ?? '');
$conj_cpf = only_digits($_POST['conj_cpf'] ?? '');
$conj_rg = trim($_POST['conj_rg'] ?? '');
$conj_nasc = $_POST['conj_nasc'] ?? null;
$conj_genero = $_POST['conj_genero'] ?? null;
$conj_nacionalidade = trim($_POST['conj_nacionalidade'] ?? '');
$conj_naturalidade = trim($_POST['conj_naturalidade'] ?? '');
$conj_trabalho = $_POST['conj_trabalho'] ?? null;
$conj_renda = money_to_sql($_POST['conj_renda'] ?? null);
$conj_pcd = $_POST['conj_pcd'] ?? 'Não';
$conj_bpc = $_POST['conj_bpc'] ?? 'Não';
$conj_bpc_valor = money_to_sql($_POST['conj_bpc_valor'] ?? null);

/* ===== Família / totais ===== */
$total_moradores = ($_POST['total_moradores'] ?? '') !== '' ? (int) $_POST['total_moradores'] : null;
$total_familias = ($_POST['total_familias'] ?? '') !== '' ? (int) $_POST['total_familias'] : null;
$pcd_residencia = $_POST['pcd_residencia'] ?? null;
$total_pcd = ($_POST['total_pcd'] ?? '') !== '' ? (int) $_POST['total_pcd'] : null;
$renda_familiar = money_to_sql($_POST['renda_familiar'] ?? null);
$total_rendimentos = money_to_sql($_POST['total_rendimentos'] ?? null);
$tipificacao = trim($_POST['tipificacao'] ?? '');

/* ===== NOVO: Auxílio (ajudas_tipos) =====
   Esperado do select: name="ajuda_tipo_id"
*/
$ajuda_tipo_id = ($_POST['categoria_entrevista'] ?? '') !== '' ? (int) $_POST['categoria_entrevista'] : null;


/* ===== Habitação ===== */
$situacao_imovel = $_POST['situacao_imovel'] ?? null;
$situacao_imovel_valor = money_to_sql($_POST['situacao_imovel_valor'] ?? null);

$tipo_moradia = pick_outros($_POST['tipo_moradia'] ?? null, $_POST['tipo_moradia_outros'] ?? null);
$abastecimento = pick_outros($_POST['abastecimento'] ?? null, $_POST['abastecimento_outros'] ?? null);
$iluminacao = pick_outros($_POST['iluminacao'] ?? null, $_POST['iluminacao_outros'] ?? null);
$esgoto = pick_outros($_POST['esgoto'] ?? null, $_POST['esgoto_outros'] ?? null);
$lixo = pick_outros($_POST['lixo'] ?? null, $_POST['lixo_outros'] ?? null);
$entorno = pick_outros($_POST['entorno'] ?? null, $_POST['entorno_outros'] ?? null);

$resumo_caso = $_POST['resumo_caso'] ?? null;

/* ===== Foto base64 ===== */
$foto_base64 = $_POST['foto_base64'] ?? '';

/* ===== Validação básica ===== */
if (
    $nome === '' ||
    $cpf === '' || strlen($cpf) !== 11 ||
    $rg === '' ||
    $tel === '' || (strlen($tel) < 10 || strlen($tel) > 11) ||
    $endereco === '' || $numero === '' || empty($bairro_id)
) {
    alert_back('Preencha todos os campos obrigatórios corretamente (CPF 11 dígitos e telefone 10/11 dígitos).');
}

/* ===== Pastas de upload ===== */
$rootDir = realpath(__DIR__ . '/..');
$fotosDir = $rootDir . '/uploads/fotos';
$docsDir = $rootDir . '/uploads/documentos';
if (!is_dir($fotosDir) && !@mkdir($fotosDir, 0755, true))
    alert_back('Não foi possível criar a pasta de fotos.');
if (!is_dir($docsDir) && !@mkdir($docsDir, 0755, true))
    alert_back('Não foi possível criar a pasta de documentos.');

/* ===== Salva foto (se vier) ===== */
$foto_path_rel = null;

// 1. Prioridade: Upload de Arquivo
if (isset($_FILES['foto_upload']) && $_FILES['foto_upload']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['foto_upload'];
    // Limite 10MB (igual documentos)
    if ($f['size'] > 10 * 1024 * 1024)
        alert_back('A foto excede o tamanho máximo de 10MB.');

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowedFotos = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];
    if (!in_array($ext, $allowedFotos))
        alert_back('Formato de foto inválido. Use JPG, PNG, WEBP ou BMP.');

    $baseName = 'fotos_' . date('Ymd_His') . '_' . substr(sha1($nome . microtime(true)), 0, 8) . '.' . $ext;
    $destAbs = $fotosDir . '/' . $baseName;

    if (!@move_uploaded_file($f['tmp_name'], $destAbs))
        alert_back('Falha ao salvar a foto enviada.');
    $foto_path_rel = 'uploads/fotos/' . $baseName;
}
// 2. Fallback: Câmera (Base64)
elseif ($foto_base64) {
    if (!preg_match('/^data:(image\/(jpeg|png|jpg));base64,/', $foto_base64, $m)) {
        alert_back('Formato de foto inválido.');
    }
    $ext = ($m[2] === 'png') ? 'png' : 'jpg';
    $bin = base64_decode(preg_replace('/^data:image\/(jpeg|png|jpg);base64,/', '', $foto_base64), true);
    if ($bin === false)
        alert_back('Não foi possível decodificar a imagem.');

    $baseName = 'fotos_' . date('Ymd_His') . '_' . substr(sha1($nome . microtime(true)), 0, 8) . '.' . $ext;
    $destAbs = $fotosDir . '/' . $baseName;
    if (@file_put_contents($destAbs, $bin) === false)
        alert_back('Falha ao salvar a foto.');
    $foto_path_rel = 'uploads/fotos/' . $baseName;
}

try {
    $pdo->beginTransaction();

    /* ===== INSERT solicitantes ===== */
    $sql = "
        INSERT INTO solicitantes
        (
            nome, nis, cpf, rg, rg_emissao, rg_uf, data_nascimento, genero, estado_civil, telefone,
            hora_cadastro, responsavel,
            endereco, numero, complemento, bairro_id, referencia,
            nacionalidade, naturalidade, tempo_anos, tempo_meses,
            grupo_tradicional, grupo_outros,
            pcd, pcd_tipo, bpc, bpc_valor, pbf, pbf_valor,
            beneficio_municipal, beneficio_municipal_valor,
            beneficio_estadual, beneficio_estadual_valor,
            renda_mensal_faixa, renda_mensal_outros,
            trabalho, local_trabalho, renda_individual,
            total_moradores, total_familias, pcd_residencia, total_pcd, renda_familiar, total_rendimentos, tipificacao,
            ajuda_tipo_id,
            situacao_imovel, situacao_imovel_valor,
            tipo_moradia, abastecimento, iluminacao, esgoto, lixo, entorno,
            resumo_caso, foto_path,
            conj_nome, conj_nis, conj_cpf, conj_rg, conj_nasc, conj_genero,
            conj_nacionalidade, conj_naturalidade, conj_trabalho, conj_renda,
            conj_pcd, conj_bpc, conj_bpc_valor,
            created_at
        )
        VALUES
        (
            :nome, :nis, :cpf, :rg, :rg_emissao, :rg_uf, :data_nascimento, :genero, :estado_civil, :telefone,
            :hora_cadastro, :responsavel,
            :endereco, :numero, :complemento, :bairro_id, :referencia,
            :nacionalidade, :naturalidade, :tempo_anos, :tempo_meses,
            :grupo_tradicional, :grupo_outros,
            :pcd, :pcd_tipo, :bpc, :bpc_valor, :pbf, :pbf_valor,
            :beneficio_municipal, :beneficio_municipal_valor,
            :beneficio_estadual, :beneficio_estadual_valor,
            :renda_mensal_faixa, :renda_mensal_outros,
            :trabalho, :local_trabalho, :renda_individual,
            :total_moradores, :total_familias, :pcd_residencia, :total_pcd, :renda_familiar, :total_rendimentos, :tipificacao,
            :ajuda_tipo_id,
            :situacao_imovel, :situacao_imovel_valor,
            :tipo_moradia, :abastecimento, :iluminacao, :esgoto, :lixo, :entorno,
            :resumo_caso, :foto_path,
            :conj_nome, :conj_nis, :conj_cpf, :conj_rg, :conj_nasc, :conj_genero,
            :conj_nacionalidade, :conj_naturalidade, :conj_trabalho, :conj_renda,
            :conj_pcd, :conj_bpc, :conj_bpc_valor,
            :created_at
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':nis' => $nis ?: null,
        ':cpf' => $cpf,
        ':rg' => $rg,
        ':rg_emissao' => $rg_emissao ?: null,
        ':rg_uf' => $rg_uf ?: null,
        ':data_nascimento' => $data_nasc ?: null,
        ':genero' => $genero ?: null,
        ':estado_civil' => $estado_civil ?: null,
        ':telefone' => $tel,

        ':hora_cadastro' => $hora_cadastro ?: null,
        ':responsavel' => $responsavel ?: null,

        ':endereco' => $endereco,
        ':numero' => $numero,
        ':complemento' => $complemento !== '' ? $complemento : null,
        ':bairro_id' => $bairro_id,
        ':referencia' => $referencia !== '' ? $referencia : null,

        ':nacionalidade' => $nacionalidade !== '' ? $nacionalidade : null,
        ':naturalidade' => $naturalidade !== '' ? $naturalidade : null,
        ':tempo_anos' => $tempo_anos,
        ':tempo_meses' => $tempo_meses,

        ':grupo_tradicional' => $grupo_tradicional,
        ':grupo_outros' => $grupo_outros,

        ':pcd' => $pcd,
        ':pcd_tipo' => $pcd_tipo !== '' ? $pcd_tipo : null,
        ':bpc' => $bpc,
        ':bpc_valor' => ($bpc === 'Sim') ? $bpc_valor : null,
        ':pbf' => $pbf,
        ':pbf_valor' => ($pbf === 'Sim') ? $pbf_valor : null,

        ':beneficio_municipal' => $beneficio_municipal,
        ':beneficio_municipal_valor' => ($beneficio_municipal === 'Sim') ? $beneficio_municipal_valor : null,
        ':beneficio_estadual' => $beneficio_estadual,
        ':beneficio_estadual_valor' => ($beneficio_estadual === 'Sim') ? $beneficio_estadual_valor : null,

        ':renda_mensal_faixa' => $renda_mensal_faixa ?: null,
        ':renda_mensal_outros' => ($renda_mensal_faixa === 'Outros' && $renda_mensal_outros !== '') ? $renda_mensal_outros : null,

        ':trabalho' => $trabalho ?: null,
        ':local_trabalho' => ($local_trabalho !== '' && $local_trabalho !== null) ? $local_trabalho : null,
        ':renda_individual' => $renda_individual,

        ':total_moradores' => $total_moradores,
        ':total_familias' => $total_familias,
        ':pcd_residencia' => $pcd_residencia ?: null,
        ':total_pcd' => $total_pcd,
        ':renda_familiar' => $renda_familiar,
        ':total_rendimentos' => $total_rendimentos,
        ':tipificacao' => $tipificacao !== '' ? $tipificacao : null,

        // NOVO
        ':ajuda_tipo_id' => $ajuda_tipo_id,

        ':situacao_imovel' => $situacao_imovel ?: null,
        ':situacao_imovel_valor' => ($situacao_imovel === 'Alugado') ? $situacao_imovel_valor : null,
        ':tipo_moradia' => $tipo_moradia ?: null,
        ':abastecimento' => $abastecimento ?: null,
        ':iluminacao' => $iluminacao ?: null,
        ':esgoto' => $esgoto ?: null,
        ':lixo' => $lixo ?: null,
        ':entorno' => $entorno ?: null,

        ':resumo_caso' => ($resumo_caso !== null && trim($resumo_caso) !== '') ? trim($resumo_caso) : null,
        ':foto_path' => $foto_path_rel,

        ':conj_nome' => $conj_nome !== '' ? $conj_nome : null,
        ':conj_nis' => $conj_nis !== '' ? $conj_nis : null,
        ':conj_cpf' => $conj_cpf !== '' ? $conj_cpf : null,
        ':conj_rg' => $conj_rg !== '' ? $conj_rg : null,
        ':conj_nasc' => $conj_nasc ?: null,
        ':conj_genero' => $conj_genero ?: null,
        ':conj_nacionalidade' => $conj_nacionalidade !== '' ? $conj_nacionalidade : null,
        ':conj_naturalidade' => $conj_naturalidade !== '' ? $conj_naturalidade : null,
        ':conj_trabalho' => $conj_trabalho ?: null,
        ':conj_renda' => $conj_renda,
        ':conj_pcd' => $conj_pcd,
        ':conj_bpc' => $conj_bpc,
        ':conj_bpc_valor' => ($conj_bpc === 'Sim') ? $conj_bpc_valor : null,
        ':created_at' => $hora_cadastro,
    ]);

    $solicitante_id = (int) $pdo->lastInsertId();

    /* ===== Familiares ===== */
    $fam_nomes = $_POST['fam_nome'] ?? [];
    $fam_nascs = $_POST['fam_nasc'] ?? [];
    $fam_par = $_POST['fam_parentesco'] ?? [];
    $fam_esc = $_POST['fam_escolaridade'] ?? [];
    $fam_obs = $_POST['fam_obs'] ?? [];

    if (is_array($fam_nomes) && count($fam_nomes)) {
        $stmtFam = $pdo->prepare("
            INSERT INTO familiares (solicitante_id, nome, data_nascimento, parentesco, escolaridade, obs)
            VALUES (:sid, :nome, :nasc, :parent, :esc, :obs)
        ");
        $n = count($fam_nomes);
        for ($i = 0; $i < $n; $i++) {
            $fn = trim((string) ($fam_nomes[$i] ?? ''));
            $fd = (string) ($fam_nascs[$i] ?? null);
            $fp = trim((string) ($fam_par[$i] ?? ''));
            $fe = trim((string) ($fam_esc[$i] ?? ''));
            $fo = trim((string) ($fam_obs[$i] ?? ''));
            if ($fn === '' && $fp === '' && $fe === '' && $fo === '' && ($fd === '' || $fd === null))
                continue;

            $stmtFam->execute([
                ':sid' => $solicitante_id,
                ':nome' => $fn ?: null,
                ':nasc' => $fd ?: null,
                ':parent' => $fp ?: null,
                ':esc' => $fe ?: null,
                ':obs' => $fo ?: null,
            ]);
        }
    }

    /* ===== Documentos múltiplos ===== */
    $allowedExt = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
    if (isset($_FILES['documentos']) && is_array($_FILES['documentos']['name'])) {
        $names = $_FILES['documentos']['name'];
        $tmp = $_FILES['documentos']['tmp_name'];
        $errs = $_FILES['documentos']['error'];
        $sizes = $_FILES['documentos']['size'];

        $stmtDoc = $pdo->prepare("
            INSERT INTO solicitante_documentos
            (solicitante_id, arquivo_path, original_name, mime_type, size_bytes, created_at)
            VALUES (:sid, :path, :orig, :mime, :size, :created_at)
        ");

        // Fallback para schema antigo (sem size_bytes)
        $stmtDocOld = $pdo->prepare("
            INSERT INTO solicitante_documentos
            (solicitante_id, arquivo_path, original_name, mime_type, created_at)
            VALUES (:sid, :path, :orig, :mime, :created_at)
        ");

        for ($i = 0; $i < count($names); $i++) {
            if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
                continue;
            if (($errs[$i] ?? 0) !== UPLOAD_ERR_OK)
                throw new RuntimeException('Erro no upload de documento: código ' . (int) $errs[$i]);
            if (($sizes[$i] ?? 0) > 10 * 1024 * 1024)
                throw new RuntimeException('Um dos documentos excede 10MB.');

            $origName = (string) $names[$i];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true))
                throw new RuntimeException('Extensão de documento não permitida: ' . $ext);

            $safeBase = 'documentos_' . date('Ymd_His') . '_' . substr(sha1($origName . microtime(true)), 0, 8) . '.' . $ext;
            $destAbs = $docsDir . '/' . $safeBase;
            if (!@move_uploaded_file($tmp[$i], $destAbs))
                throw new RuntimeException('Falha ao salvar um documento.');

            $relPath = 'uploads/documentos/' . $safeBase;
            $mime = @mime_content_type($destAbs) ?: null;

            try {
                $stmtDoc->execute([
                    ':sid' => $solicitante_id,
                    ':path' => $relPath,
                    ':orig' => $origName,
                    ':mime' => $mime,
                    ':size' => (int) $sizes[$i],
                    ':created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (PDOException $e) {
                $stmtDocOld->execute([
                    ':sid' => $solicitante_id,
                    ':path' => $relPath,
                    ':orig' => $origName,
                    ':mime' => $mime,
                    ':created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    $pdo->commit();
    alert_redirect('Cadastro salvo com sucesso!', '../pessoasCadastradas.php');
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction())
        $pdo->rollBack();
    alert_back('Erro ao salvar: ' . $e->getMessage());
}


?>
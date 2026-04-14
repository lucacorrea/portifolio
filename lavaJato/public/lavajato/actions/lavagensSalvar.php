<?php
declare(strict_types=1);

// autoErp/public/lavajato/actions/lavagensSalvar.php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../lib/auth_guard.php';
require_once __DIR__ . '/../../../lib/util.php';

require_post();
guard_empresa_user(['dono', 'administrativo', 'caixa']);

/* ========= Conexão ========= */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) {
    require_once $pathCon;
}

if (!$pdo instanceof PDO) {
    header('Location: ../pages/lavagemRapida.php?err=1&msg=' . urlencode('Conexão indisponível.'));
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/* ========= Helpers ========= */
function norm_cpf(?string $c): string
{
    return preg_replace('/\D+/', '', (string)$c);
}

function norm_cnpj(?string $c): string
{
    return preg_replace('/\D+/', '', (string)$c);
}

function back_to(string $path, array $qs = []): void
{
    $q = http_build_query($qs);
    header('Location: ' . $path . ($q ? ('?' . $q) : ''));
    exit;
}

if (!function_exists('empresa_cnpj_logada')) {
    function empresa_cnpj_logada(PDO $pdo): ?string
    {
        if (!empty($_SESSION['empresa_cnpj'])) {
            return (string)$_SESSION['empresa_cnpj'];
        }

        try {
            if (!empty($_SESSION['user_id'])) {
                $st = $pdo->prepare("SELECT empresa_cnpj FROM usuarios_peca WHERE id = :id LIMIT 1");
                $st->execute([
                    ':id' => (int)$_SESSION['user_id']
                ]);

                $cnpj = $st->fetchColumn();

                if ($cnpj) {
                    $_SESSION['empresa_cnpj'] = (string)$cnpj;
                    return (string)$cnpj;
                }
            }
        } catch (Throwable $e) {
            error_log('Erro em empresa_cnpj_logada: ' . $e->getMessage());
        }

        return null;
    }
}

/* ========= Roteador ========= */
$op = (string)($_POST['op'] ?? '');
if ($op !== 'lav_rapida_nova') {
    back_to('../pages/lavagemRapida.php', [
        'err' => 1,
        'msg' => 'Operação inválida.'
    ]);
}

/* ========= CSRF ========= */
$csrfPost = (string)($_POST['csrf'] ?? '');
if (
    empty($_SESSION['csrf_lavagem_rapida']) ||
    !hash_equals((string)$_SESSION['csrf_lavagem_rapida'], $csrfPost)
) {
    back_to('../pages/lavagemRapida.php', [
        'err' => 1,
        'msg' => 'Sessão expirada. Recarregue a página.'
    ]);
}

/* ========= Empresa ========= */
$empresaCnpjRaw = (string)empresa_cnpj_logada($pdo);
$empresaCnpjNum = norm_cnpj($empresaCnpjRaw);

if (!preg_match('/^\d{14}$/', $empresaCnpjNum)) {
    back_to('../pages/lavagemRapida.php', [
        'err' => 1,
        'msg' => 'Empresa não vinculada ao usuário.'
    ]);
}

/* ========= Agora ========= */
$agoraCoari = (new DateTimeImmutable('now', new DateTimeZone('America/Manaus')))
    ->format('Y-m-d H:i:s');

$criadoEm = $agoraCoari;

/* ========= Entrada ========= */
$lavadorCpf = norm_cpf($_POST['lavador_cpf'] ?? '');
$placa      = strtoupper(trim((string)($_POST['placa'] ?? '')));
$modelo     = trim((string)($_POST['modelo'] ?? ''));
$cor        = trim((string)($_POST['cor'] ?? ''));

$catIdStr = (string)($_POST['categoria_id'] ?? '');
$catId    = ctype_digit($catIdStr) ? (int)$catIdStr : 0;

$valorRaw = str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
$valor    = is_numeric($valorRaw) ? (float)$valorRaw : 0.0;

$pgto = trim((string)($_POST['forma_pagamento'] ?? 'dinheiro'));
if ($pgto === '') {
    $pgto = 'Não informado';
}

$statusRaw = strtolower(trim((string)($_POST['status'] ?? 'aberta')));
$statusMap = [
    'aberta'     => 'aberta',
    'lavando'    => 'aberta',
    'concluida'  => 'concluida',
    'concluída'  => 'concluida',
    'finalizada' => 'concluida',
    'cancelada'  => 'cancelada',
];
$status = $statusMap[$statusRaw] ?? 'aberta';

$obs = trim((string)($_POST['observacoes'] ?? ''));

$adicionais_json  = trim((string)($_POST['adicionais_json'] ?? '[]'));
$adicionais_array = json_decode($adicionais_json, true);
if (!is_array($adicionais_array)) {
    $adicionais_array = [];
}

/* ========= Validações ========= */
$errs = [];

if ($lavadorCpf === '' || !preg_match('/^\d{11}$/', $lavadorCpf)) {
    $errs[] = 'Selecione um lavador válido.';
}

if ($valor <= 0) {
    $errs[] = 'Informe um valor maior que zero.';
}

if ($catId <= 0) {
    $errs[] = 'Selecione um serviço (categoria).';
}

if (!empty($errs)) {
    back_to('../pages/lavagemRapida.php', [
        'err' => 1,
        'msg' => implode(' ', $errs)
    ]);
}

try {
    /* ========= Confere lavador ativo ========= */
    $st = $pdo->prepare("
        SELECT nome
        FROM lavadores_peca
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(empresa_cnpj,''),'.',''),'-',''),'/',''),' ','') = :c
          AND REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cpf,''),'.',''),'-',''),'/',''),' ','') = :cpf
          AND ativo = 1
        LIMIT 1
    ");
    $st->execute([
        ':c'   => $empresaCnpjNum,
        ':cpf' => $lavadorCpf
    ]);

    $lavador = $st->fetch();

    if (!$lavador) {
        back_to('../pages/lavagemRapida.php', [
            'err' => 1,
            'msg' => 'Lavador não encontrado ou inativo.'
        ]);
    }

    /* ========= Confere categoria ========= */
    $st = $pdo->prepare("
        SELECT nome
        FROM categorias_lavagem_peca
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(empresa_cnpj,''),'.',''),'-',''),'/',''),' ','') = :c
          AND id = :id
          AND ativo = 1
        LIMIT 1
    ");
    $st->execute([
        ':c'  => $empresaCnpjNum,
        ':id' => $catId
    ]);

    $categoria = $st->fetch();

    if (!$categoria) {
        back_to('../pages/lavagemRapida.php', [
            'err' => 1,
            'msg' => 'Serviço (categoria) não encontrado ou inativo.'
        ]);
    }

    $catNome = (string)$categoria['nome'];
    $checkout = ($status === 'concluida') ? $agoraCoari : null;

    $pdo->beginTransaction();

    /* ========= Insere lavagem ========= */
    $sql = "
        INSERT INTO lavagens_peca (
            empresa_cnpj,
            lavador_cpf,
            placa,
            modelo,
            cor,
            categoria_id,
            categoria_nome,
            valor,
            forma_pagamento,
            status,
            checkin_at,
            checkout_at,
            observacoes,
            criado_em
        ) VALUES (
            :c,
            :lav,
            :placa,
            :modelo,
            :cor,
            :catid,
            :catnome,
            :valor,
            :pgto,
            :status,
            :checkin,
            :checkout,
            :obs,
            :criado
        )
    ";

    $ins = $pdo->prepare($sql);
    $ins->execute([
        ':c'        => $empresaCnpjRaw,
        ':lav'      => $lavadorCpf,
        ':placa'    => ($placa !== '' ? $placa : null),
        ':modelo'   => ($modelo !== '' ? $modelo : null),
        ':cor'      => ($cor !== '' ? $cor : null),
        ':catid'    => $catId,
        ':catnome'  => $catNome,
        ':valor'    => $valor,
        ':pgto'     => $pgto,
        ':status'   => $status,
        ':checkin'  => $agoraCoari,
        ':checkout' => $checkout,
        ':obs'      => ($obs !== '' ? $obs : null),
        ':criado'   => $criadoEm,
    ]);

    $lavagemId = (int)$pdo->lastInsertId();

    if ($lavagemId <= 0) {
        throw new RuntimeException('Falha ao obter ID da lavagem.');
    }

    /* ========= Insere adicionais ========= */
    if (!empty($adicionais_array)) {
        $stmtAdd = $pdo->prepare("
            INSERT INTO lavagem_adicionais_peca (
                lavagem_id,
                adicional_id,
                nome,
                valor,
                criado_em
            ) VALUES (
                :lavagem_id,
                :add_id,
                :nome,
                :valor,
                :criado
            )
        ");

        foreach ($adicionais_array as $a) {
            $addId = (int)($a['id'] ?? 0);
            $nome  = trim((string)($a['nome'] ?? ''));
            $val   = (float)($a['valor'] ?? 0);

            if ($nome === '' || $val <= 0) {
                continue;
            }

            $stmtAdd->execute([
                ':lavagem_id' => $lavagemId,
                ':add_id'     => ($addId > 0 ? $addId : null),
                ':nome'       => $nome,
                ':valor'      => $val,
                ':criado'     => $criadoEm,
            ]);
        }
    }

    $pdo->commit();

    header('Location: ../pages/comprovanteLavagem.php?id=' . $lavagemId);
    exit;

} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro ao salvar lavagem: ' . $e->getMessage());
    error_log('Arquivo: ' . $e->getFile() . ' | Linha: ' . $e->getLine());
    error_log($e->getTraceAsString());

    back_to('../pages/lavagemRapida.php', [
        'err' => 1,
        'msg' => 'Erro ao salvar lavagem: ' . $e->getMessage()
    ]);
}
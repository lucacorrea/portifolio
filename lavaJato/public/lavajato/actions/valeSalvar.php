<?php
// autoErp/public/lavajato/actions/valeSalvar.php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../lib/auth_guard.php';
guard_empresa_user(['dono', 'administrativo', 'caixa', 'estoque']);

/* ==== Conexão ==== */
$pdo = null;
$pathCon = realpath(__DIR__ . '/../../../conexao/conexao.php');
if ($pathCon && file_exists($pathCon)) {
    require_once $pathCon;
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Conexão indisponível.');
}

/* ==== Helpers ==== */
function redirectBack(string $weekRef, string $ini, string $fim, string $lav, string $q, string $msg, bool $err = false): void
{
    $args = [
        'lav' => $lav,
        'msg' => $msg,
        'err' => $err ? 1 : 0,
        'ok'  => $err ? 0 : 1,
    ];

    if ($weekRef !== '') {
        $args['week_ref'] = $weekRef;
    } else {
        $args['ini'] = $ini;
        $args['fim'] = $fim;
    }

    if ($q !== '') {
        $args['q'] = $q;
    }

    header('Location: ../pages/lavagensResumo.php?' . http_build_query($args));
    exit;
}

function parse_money(string $v): float
{
    $v = str_replace(['R$', ' '], '', $v);
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
    return (float)$v;
}

/* ==== Inputs ==== */
$csrf = (string)($_POST['csrf'] ?? '');

$weekRef = trim((string)($_POST['week_ref'] ?? ''));
$ini     = trim((string)($_POST['ini'] ?? ''));
$fim     = trim((string)($_POST['fim'] ?? ''));

$lav = trim((string)($_POST['lav'] ?? ''));
$q   = trim((string)($_POST['q'] ?? ''));

$valor  = parse_money((string)($_POST['valor'] ?? '0'));
$motivo = trim((string)($_POST['motivo'] ?? ''));
$forma  = trim((string)($_POST['forma_pagamento'] ?? 'dinheiro'));

$lavadorId   = (int)($_POST['lavador_id'] ?? 0);
$lavadorNome = trim((string)($_POST['lavador_nome'] ?? ''));

/* ==== Validações ==== */
if (empty($_SESSION['csrf_vale_lavador']) || !hash_equals((string)$_SESSION['csrf_vale_lavador'], $csrf)) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Token inválido.', true);
}

if ($lav === '' || $lavadorId <= 0 || $valor <= 0) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Dados inválidos.', true);
}

if ($weekRef === '' && ($ini === '' || $fim === '')) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Semana não informada.', true);
}

if ($weekRef !== '' && !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $weekRef)) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'week_ref inválido.', true);
}

if ($weekRef === '' && (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $ini) || !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $fim))) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Período inválido.', true);
}

/* ==== Empresa ==== */
$cnpj = preg_replace('/\D+/', '', (string)($_SESSION['empresa_cnpj'] ?? $_SESSION['user_empresa_cnpj'] ?? ''));
if (!preg_match('/^\d{14}$/', $cnpj)) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Empresa inválida.', true);
}

/* ==== Segurança adicional: conferir lavador ==== */
try {
    $st = $pdo->prepare("
        SELECT id, nome
          FROM lavadores_peca
         WHERE empresa_cnpj = :c
           AND id = :id
         LIMIT 1
    ");
    $st->execute([
        ':c'  => $cnpj,
        ':id' => $lavadorId,
    ]);

    $lavadorDb = $st->fetch(PDO::FETCH_ASSOC);
    if (!$lavadorDb) {
        redirectBack($weekRef, $ini, $fim, $lav, $q, 'Lavador não encontrado.', true);
    }

    if ($lavadorNome === '' && !empty($lavadorDb['nome'])) {
        $lavadorNome = (string)$lavadorDb['nome'];
    }
} catch (Throwable $e) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Erro ao validar lavador: ' . $e->getMessage(), true);
}

/* ==== Insert ==== */
$agora = date('Y-m-d H:i:s');
$criadoPorCpf = isset($_SESSION['user_cpf']) ? preg_replace('/\D+/', '', (string)$_SESSION['user_cpf']) : null;

try {
    $sql = "
        INSERT INTO vales_lavadores_peca
        (
            empresa_cnpj,
            lavador_id,
            valor,
            motivo,
            forma_pagamento,
            criado_em,
            criado_por_cpf
        )
        VALUES
        (
            :cnpj,
            :lavador_id,
            :valor,
            :motivo,
            :forma_pagamento,
            :criado_em,
            :criado_por_cpf
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':cnpj'            => $cnpj,
        ':lavador_id'      => $lavadorId,
        ':valor'           => $valor,
        ':motivo'          => $motivo,
        ':forma_pagamento' => $forma !== '' ? $forma : 'dinheiro',
        ':criado_em'       => $agora,
        ':criado_por_cpf'  => $criadoPorCpf,
    ]);

    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Vale lançado com sucesso.');
} catch (Throwable $e) {
    redirectBack($weekRef, $ini, $fim, $lav, $q, 'Erro ao salvar vale: ' . $e->getMessage(), true);
}
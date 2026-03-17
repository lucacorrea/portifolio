<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: text/html; charset=utf-8');

/* ========= Helpers ========= */
function getBodyJson(): array
{
    $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if ($raw && stripos($ct, 'application/json') !== false) {
        try {
            $j = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($j)) return $j;
        } catch (Throwable $e) {
        }
    }
    return [];
}
function val($arr, $key, $default = '')
{
    return isset($arr[$key]) ? trim((string)$arr[$key]) : $default;
}
function onlyDigits($s)
{
    return preg_replace('/\D+/', '', (string)$s);
}
function redirVendaRapida($empresaId, $ok, $modelo, $msg = '')
{
    $status = $ok ? 'Sucesso' : 'Aviso';
    $msgJs = addslashes($msg);
    $empresaIdJs = rawurlencode((string)$empresaId);
    
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Resultado do Cancelamento</title></head><body>";
    echo "<script>
        alert('{$status}: {$msgJs}');
        if (window.opener || window.history.length === 1) {
            if (window.opener && !window.opener.closed) {
                window.opener.location.reload();
            }
            window.close();
        } else {
            window.location.href = '../vendas.php?id={$empresaIdJs}';
        }
    </script>";
    echo "</body></html>";
    exit;
}

/* ========= Entrada ========= */
$in = array_merge($_POST, getBodyJson());

$modelo           = val($in, 'modelo');            // 'por_chave' | 'por_motivo' | 'por_substituicao'
$empresa_id       = val($in, 'empresa_id', $_GET['id'] ?? $_GET['empresa_id'] ?? '');
$venda_id         = (int)val($in, 'venda_id', $_GET['venda_id'] ?? '');
$chave            = onlyDigits(val($in, 'chave'));
$last4            = onlyDigits(val($in, 'last4'));
$motivo           = val($in, 'motivo');
$chaveSubstituta  = onlyDigits(val($in, 'chave_substituta'));
/* numero_caixa NÃO é de vendas; entra opcionalmente pela requisição
   só para ajudar a localizar a abertura aberta correta */
$numero_caixa_in  = val($in, 'numero_caixa', $_GET['numero_caixa'] ?? '');
$numero_caixa_in  = ($numero_caixa_in !== '' && is_numeric($numero_caixa_in)) ? (int)$numero_caixa_in : null;

/* ========= Conexão ========= */
$pdo = null;
$candidates = [
    __DIR__ . '/assets/conexao.php',
    __DIR__ . '/../assets/conexao.php',
    __DIR__ . '/../assets/php/conexao.php',
    __DIR__ . '/../../assets/conexao.php',
    __DIR__ . '/../../assets/php/conexao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/conexao.php',
    $_SERVER['DOCUMENT_ROOT'] . '/assets/php/conexao.php',
    __DIR__ . '/../conexao/conexao.php',
    __DIR__ . '/../../conexao/conexao.php',
];
foreach ($candidates as $p) {
    if (is_file($p)) {
        require_once $p;
        if (isset($pdo) && $pdo instanceof PDO) break;
    }
}
if (!($pdo instanceof PDO)) {
    redirVendaRapida($empresa_id, false, $modelo ?: 'desconhecido', 'Sem conexão com o banco.');
}

/* ========= Utilitários ========= */
function hasColumn(PDO $pdo, string $table, string $column): bool
{
    $sql = "SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = :t
               AND COLUMN_NAME  = :c
             LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':t' => $table, ':c' => $column]);
    return (bool)$st->fetchColumn();
}
function vendaSelectCols(PDO $pdo): string
{
    // Nunca incluir numero_caixa aqui (não existe em vendas)
    $cols = ['id', 'empresa_id', 'valor_total', 'chave_nfce']; // básicas
    if (hasColumn($pdo, 'vendas', 'abertura_id')) $cols[] = 'abertura_id';
    return implode(',', $cols);
}
function carregarVendaPorId(PDO $pdo, string $empresa_id, int $venda_id): ?array
{
    $cols = vendaSelectCols($pdo);
    $sql = "SELECT {$cols}
              FROM vendas
             WHERE id = :id AND empresa_id = :emp
             LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':emp' => $empresa_id, ':id' => $venda_id]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}
function carregarVendaPorChave(PDO $pdo, string $empresa_id, string $chave): ?array
{
    $cols = vendaSelectCols($pdo);
    $sql = "SELECT {$cols}
              FROM vendas
             WHERE empresa_id = :emp AND chave_nfce = :ch
             ORDER BY id DESC
             LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':emp' => $empresa_id, ':ch' => $chave]);
    $v = $st->fetch(PDO::FETCH_ASSOC);
    return $v ?: null;
}
// Funções de abertura/caixa removidas (não utilizadas neste ERP)

/* ========= Descobrir venda / empresa / chave ========= */
$venda = null;
if ($empresa_id && $venda_id) {
    $venda = carregarVendaPorId($pdo, $empresa_id, (int)$venda_id);
}
if (!$venda && $empresa_id && $chave) {
    $venda = carregarVendaPorChave($pdo, $empresa_id, $chave);
}
if (!$empresa_id && $chave) {
    $st = $pdo->prepare("SELECT empresa_id FROM vendas WHERE chave_nfce = :ch LIMIT 1");
    $st->execute([':ch' => $chave]);
    $empresa_id = (string)($st->fetchColumn() ?: '');
    if ($empresa_id && $venda_id) $venda = carregarVendaPorId($pdo, $empresa_id, (int)$venda_id);
    elseif ($empresa_id && !$venda && $chave) $venda = carregarVendaPorChave($pdo, $empresa_id, $chave);
}
if (!$empresa_id) {
    redirVendaRapida($empresa_id, false, $modelo ?: 'desconhecido', 'empresa_id ausente.');
}
if (!$venda) {
    redirVendaRapida($empresa_id, false, $modelo ?: 'desconhecido', 'Venda não encontrada.');
}
if (!$chave && !empty($venda['chave_nfce'])) $chave = onlyDigits($venda['chave_nfce']);

/* ========= Deduz modelo se não veio ========= */
if (!$modelo) {
    if ($chave && $last4 && !$chaveSubstituta) $modelo = 'por_chave';
    elseif ($chave && $chaveSubstituta)        $modelo = 'por_substituicao';
    elseif ($motivo)                            $modelo = 'por_motivo';
}

/* ========= Validações ========= */
if ($modelo === 'por_chave') {
    if (strlen($chave) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave inválida (44 dígitos).');
    }
    if (strlen($last4) !== 4 || substr($chave, -4) !== $last4) {
        redirVendaRapida($empresa_id, false, $modelo, 'Confirmação (últimos 4) não confere.');
    }
} elseif ($modelo === 'por_motivo') {
    if ($motivo === '') {
        redirVendaRapida($empresa_id, false, $modelo, 'Motivo não informado.');
    }
} elseif ($modelo === 'por_substituicao') {
    if (strlen($chave) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave (original) inválida (44 dígitos).');
    }
    if (strlen($chaveSubstituta) !== 44) {
        redirVendaRapida($empresa_id, false, $modelo, 'Chave substituta inválida (44 dígitos).');
    }
    if ($chaveSubstituta === $chave) {
        redirVendaRapida($empresa_id, false, $modelo, 'A chave substituta deve ser diferente da chave a cancelar.');
    }
} else {
    redirVendaRapida($empresa_id, false, $modelo ?: 'desconhecido', 'Modelo de cancelamento inválido.');
}

/* ========= Integração SEFAZ (placeholder) ========= */
function runCancel($modelo, $empresa_id, $chave, $motivo, $chaveSubstituta)
{
    try {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) require_once __DIR__ . '/vendor/autoload.php';
        
        // Define constants for config.php by providing $_GET['id']
        $_GET['id'] = $empresa_id;
        if (file_exists(__DIR__ . '/config.php')) require_once __DIR__ . '/config.php';
        
        $config = require __DIR__ . '/nfce_config.php';
        $configJson = json_encode($config);
        
        $pfxContent = file_get_contents(PFX_PATH);
        $certificate = \NFePHP\Common\Certificate::readPfx($pfxContent, PFX_PASSWORD);
        
        $tools = new \NFePHP\NFe\Tools($configJson, $certificate);
        $tools->model('65');

        $xJust = $motivo ?: 'Cancelamento de venda por solicitacao do cliente';
        if (strlen($xJust) < 15) $xJust = str_pad($xJust, 15, '.');

        if ($modelo === 'por_substituicao') {
            $nProt = ''; // Will try to find protocol from database if not provided
            $db = \App\Config\Database::getInstance()->getConnection();
            $st = $db->prepare("SELECT protocolo FROM nfce_emitidas WHERE chave = ? LIMIT 1");
            $st->execute([$chave]);
            $nProt = $st->fetchColumn() ?: '';
            
            $response = $tools->sefazCancelSubst($chave, $nProt, $chaveSubstituta, $xJust);
        } else if ($modelo === 'por_chave') {
            $nProt = '';
            $db = \App\Config\Database::getInstance()->getConnection();
            $st = $db->prepare("SELECT protocolo FROM nfce_emitidas WHERE chave = ? LIMIT 1");
            $st->execute([$chave]);
            $nProt = $st->fetchColumn() ?: '';
            
            $response = $tools->sefazCancela($chave, $xJust, $nProt);
        } else {
            // Interno
            return true;
        }

        $std = new \NFePHP\NFe\Common\Standardize();
        $res = $std->toStd($response);
        
        $cStat = (string)($res->retEvento->infEvento->cStat ?? $res->cStat ?? '');
        // 135: Evento registrado e vinculado a NF-e, 136: Evento registrado, mas nao vinculado a NF-e
        return in_array($cStat, ['135', '136', '155']); 
        
    } catch (\Exception $e) {
        error_log("Erro no cancelamento SEFAZ: " . $e->getMessage());
        return false;
    }
}
$ok = runCancel($modelo, $empresa_id, $chave, $motivo, $chaveSubstituta);
if (!$ok) {
    redirVendaRapida($empresa_id, false, $modelo, 'Falha ao cancelar na SEFAZ.');
}

/* ========= Cancelar: repor estoque + ajustar abertura + remover venda ========= */
try {
    $pdo->beginTransaction();

    // 1) Itens da venda → repor estoque
    $selItens = $pdo->prepare("
        SELECT produto_id, quantidade
          FROM vendas_itens
         WHERE venda_id = :id
    ");
    $selItens->execute([':id' => $venda['id']]);
    $itens = $selItens->fetchAll(PDO::FETCH_ASSOC);

    if ($itens) {
        $updEst = $pdo->prepare("
            UPDATE produtos
               SET quantidade = quantidade + :qtd
             WHERE id = :id
        ");
        foreach ($itens as $it) {
            $pid = (int)$it['produto_id'];
            $qtd = (float)$it['quantidade'];
            $updEst->execute([':qtd' => $qtd, ':id' => $pid]);
        }
    }

    // 2) Ajustar abertura (valor_total e quantidade_vendas) - N/A no ERP atual, ignorado
    // atualizarAberturaPorVenda($pdo, $venda, $numero_caixa_in);

    // 3) Marcar como cancelada (vendas e nfce_emitidas)
    $stmtV = $pdo->prepare("UPDATE vendas SET status = 'cancelada', status_nfce = 'cancelada' WHERE id = :id AND empresa_id = :emp");
    $stmtV->execute([':id' => $venda['id'], ':emp' => $empresa_id]);

    $stmtN = $pdo->prepare("UPDATE nfce_emitidas SET status_sefaz = '101', mensagem = 'Cancelamento homologado' WHERE venda_id = :id AND empresa_id = :emp");
    $stmtN->execute([':id' => $venda['id'], ':emp' => $empresa_id]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    redirVendaRapida($empresa_id, false, $modelo, 'Erro ao cancelar: ' . $e->getMessage());
}

/* ========= Redireciona ========= */
redirVendaRapida($empresa_id, true, $modelo, 'Cancelado com sucesso.');

?>
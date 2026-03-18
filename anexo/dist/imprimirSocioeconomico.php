<?php

declare(strict_types=1);

require_once __DIR__ . '/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  exit('Erro de conexão');
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------- Helpers ---------- */
function only_digits(?string $s): string
{
  $r = preg_replace('/\D+/', '', (string)$s);
  return $r === null ? '' : $r;
}

function h(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function fmt_date_br(?string $ymd): string
{
  if (!$ymd) return '';
  $ymd = trim($ymd);
  if ($ymd === '' || $ymd === '0000-00-00') return '';
  $p = explode('-', $ymd);
  if (count($p) !== 3) return '';
  return $p[2] . '/' . $p[1] . '/' . $p[0];
}

function fmt_cpf(?string $cpf): string
{
  $d = only_digits($cpf);
  if (strlen($d) !== 11) return $cpf ?? '';
  return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}

function fmt_money($n): string
{
  return ($n === null || $n === '') ? '' : 'R$ ' . number_format((float)$n, 2, ',', '.');
}

function ynMark(?string $val): array
{
  $y = mb_strtolower((string)$val) === 'sim';
  return [$y ? 'X' : ' ', $y ? ' ' : 'X'];
}

function has(string $hay, string $needle): bool
{
  return mb_stripos($hay, $needle) !== false;
}

function fmt_datetime_br(?string $dt): string
{
  if (!$dt) return '';
  $dt = trim($dt);
  if ($dt === '' || $dt === '0000-00-00 00:00:00') return '';
  $d = DateTime::createFromFormat('Y-m-d H:i:s', $dt);
  if (!$d) return '';
  return $d->format('d/m/Y H:i');
}

/** ✅ formata DATE + TIME (ajudas_entregas) */
function fmt_date_time_br(?string $date, ?string $time): string
{
  $d = fmt_date_br($date);
  if ($d === '') return '';
  $t = trim((string)$time);
  if ($t !== '') $d .= ' ' . substr($t, 0, 5);
  return $d;
}

/** ✅ Função auxiliar para data curta (DATETIME -> dd/mm/YYYY) */
function fmt_date_short(?string $datetime): string
{
  if (!$datetime) return '';
  $datetime = trim($datetime);
  if ($datetime === '' || $datetime === '0000-00-00 00:00:00') return '';
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
  if (!$dt) {
    // fallback: se vier só YYYY-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) return fmt_date_br($datetime);
    return '';
  }
  return $dt->format('d/m/Y');
}

/** ✅ Checa se tabela existe (pra não quebrar se ainda não criou) */
function table_exists(PDO $pdo, string $table): bool
{
  try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
    $stmt->execute([':t' => $table]);
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

/**
 * Normaliza e valida um caminho de foto (foto_path) para usar em <img src>.
 * - Não permite URL remota
 * - Bloqueia ".."
 * - Exige arquivo existir no servidor
 * Retorna caminho relativo (para usar no src) ou '' se inválido.
 */
function photo_src(?string $path): string
{
  $p = trim((string)$path);
  if ($p === '') return '';

  $p = str_replace('\\', '/', $p);

  // Bloqueia URL remota
  if (preg_match('~^[a-z][a-z0-9+\-.]*://~i', $p)) return '';

  // Bloqueia path traversal
  if (strpos($p, '..') !== false) return '';

  // Converte para relativo
  $p = ltrim($p, '/');

  $fs = __DIR__ . '/' . $p;
  if (!is_file($fs)) return '';

  return $p;
}

/**
 * ✅ pega status (CONCLUÍDO/ABERTO) + dados direto da ajudas_entregas
 * Regra:
 * - Se existir entrega com entregue='Sim' para (CPF + ajuda_tipo_id), então CONCLUÍDO
 * - Se não existir, ABERTO
 */
function status_por_entrega(PDO $pdo, string $cpf, ?int $ajudaTipoId = null): array
{
  $cpf = only_digits($cpf);
  $ajudaTipoId = $ajudaTipoId ? (int)$ajudaTipoId : null;

  // Se tabela não existe ainda, não quebra
  if (!table_exists($pdo, 'ajudas_entregas')) {
    return [
      'status'      => 'ABERTO',
      'data'        => '',
      'entregue_em' => '',
      'responsavel' => '',
      'quantidade'  => 0,
      'valor'       => '',
    ];
  }

  $sql = "SELECT data_entrega, hora_entrega, responsavel, quantidade, valor_aplicado
          FROM ajudas_entregas
          WHERE pessoa_cpf = :cpf
            AND entregue IN ('Sim','SIM','sim','1')";

  if ($ajudaTipoId) {
    $sql .= " AND ajuda_tipo_id = :tid";
  }

  $sql .= " ORDER BY data_entrega DESC, hora_entrega DESC, id DESC
            LIMIT 1";

  try {
    $stmt = $pdo->prepare($sql);
    $params = [':cpf' => $cpf];
    if ($ajudaTipoId) $params[':tid'] = $ajudaTipoId;

    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      $ent = fmt_date_time_br($row['data_entrega'] ?? '', $row['hora_entrega'] ?? '');
      return [
        'status'      => 'CONCLUÍDO',
        'data'        => $ent,          // compat com seu VI (usa 'data')
        'entregue_em' => $ent,          // compat com cabeçalho (se usar)
        'responsavel' => (string)($row['responsavel'] ?? ''),
        'quantidade'  => (int)($row['quantidade'] ?? 0),
        'valor'       => fmt_money($row['valor_aplicado'] ?? null),
      ];
    }
  } catch (Throwable $e) {
    // qualquer erro: não quebra a página
  }

  return [
    'status'      => 'ABERTO',
    'data'        => '',
    'entregue_em' => '',
    'responsavel' => '',
    'quantidade'  => 0,
    'valor'       => '',
  ];
}

/* ---------- Entrada ---------- */
$cpfParam = only_digits($_GET['cpf'] ?? '');
if (strlen($cpfParam) !== 11) {
  http_response_code(400);
  exit('CPF inválido.');
}

/* ---------- Consulta (traz Tipo de Ajuda + Categoria via ajudas_tipos) ---------- */
$sql = "SELECT 
          s.*,
          COALESCE(b.nome,'')  AS bairro_nome,
          COALESCE(at.nome,'') AS ajuda_tipo_nome,
          COALESCE(at.categoria,'') AS ajuda_tipo_categoria
        FROM solicitantes s
        LEFT JOIN bairros b ON b.id = s.bairro_id
        LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
        WHERE s.cpf = :cpf
        ORDER BY s.id DESC
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([':cpf' => $cpfParam]);
$S = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$S) {
  http_response_code(404);
  exit('Solicitante não encontrado.');
}

/* Texto pronto para exibir (Tipo de Ajuda) */
$ajudaTipoNome = trim((string)($S['ajuda_tipo_nome'] ?? ''));
$ajudaTipoCat  = trim((string)($S['ajuda_tipo_categoria'] ?? ''));
if ($ajudaTipoNome === '') $ajudaTipoNome = '—';
if ($ajudaTipoCat === '')  $ajudaTipoCat  = '—';

/* Foto do solicitante (3x4 no cabeçalho) */
$fotoSolicitanteSrc = photo_src($S['foto_path'] ?? '');

/* Familiares */
$familiares = $pdo->prepare("SELECT nome, data_nascimento, parentesco, escolaridade, obs
                             FROM familiares
                             WHERE solicitante_id = :sid
                             ORDER BY id");
$familiares->execute([':sid' => (int)$S['id']]);
$FAMS = $familiares->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Status do caso (por CPF + ajuda_tipo_id do solicitante) */
$infoStatus = status_por_entrega(
  $pdo,
  $cpfParam,
  isset($S['ajuda_tipo_id']) ? (int)$S['ajuda_tipo_id'] : null
);

$statusCaso  = (string)($infoStatus['status'] ?? 'ABERTO');
$entregueEm  = (string)($infoStatus['entregue_em'] ?? '');
$respEntrega = (string)($infoStatus['responsavel'] ?? '');
$qtdEntrega  = (int)($infoStatus['quantidade'] ?? 0);
$valEntrega  = (string)($infoStatus['valor'] ?? '');

/* ---------- Marcas ---------- */
[$pcdSim, $pcdNao] = ynMark($S['pcd'] ?? 'Não');
[$bpcSim, $bpcNao] = ynMark($S['bpc'] ?? 'Não');
[$pbfSim, $pbfNao] = ynMark($S['pbf'] ?? 'Não');

$genero = mb_strtolower((string)($S['genero'] ?? ''));
$genMasc = has($genero, 'masc') ? 'X' : ' ';
$genFem  = has($genero, 'fem')  ? 'X' : ' ';
$genOut  = ($genMasc === ' ' && $genFem === ' ') ? 'X' : ' ';

$ec = mb_strtolower((string)($S['estado_civil'] ?? ''));
$ecCasado   = has($ec, 'casad') ? 'X' : ' ';
$ecSolteiro = has($ec, 'solteir') ? 'X' : ' ';
$ecViuvo    = (has($ec, 'viúv') || has($ec, 'viuv')) ? 'X' : ' ';
$ecUniao    = (has($ec, 'união') || has($ec, 'uniao')) ? 'X' : ' ';
$ecOutros   = (!$ecCasado && !$ecSolteiro && !$ecViuvo && !$ecUniao) ? 'X' : ' ';

$conjGen = mb_strtolower((string)($S['conj_genero'] ?? ''));
$conjFem  = has($conjGen, 'fem')  ? 'X' : ' ';
$conjMasc = has($conjGen, 'masc') ? 'X' : ' ';
$conjOut  = ($conjFem === ' ' && $conjMasc === ' ') ? 'X' : ' ';
[$conjPcdSim, $conjPcdNao] = ynMark($S['conj_pcd'] ?? 'Não');
[$conjBpcSim, $conjBpcNao] = ynMark($S['conj_bpc'] ?? 'Não');

$faixa = mb_strtolower((string)($S['renda_mensal_faixa'] ?? ''));
$fxInf1 = has($faixa, 'inferior') ? 'X' : ' ';
$fx1    = (has($faixa, '1 salário') || has($faixa, '1 salario') || has($faixa, '1 sm')) ? 'X' : ' ';
$fx2    = (has($faixa, '2 salário') || has($faixa, '2 salario') || has($faixa, '2 sm')) ? 'X' : ' ';
$fxOut  = ($fxInf1 === ' ' && $fx1 === ' ' && $fx2 === ' ') ? 'X' : ' ';

/* Habitação/Infra */
$sit = mb_strtolower((string)($S['situacao_imovel'] ?? ''));
$hPais = has($sit, 'pai') ? 'X' : ' ';
$hProp = (has($sit, 'próprio') || has($sit, 'proprio')) ? 'X' : ' ';
$hAlug = has($sit, 'alug') ? 'X' : ' ';
$hCedido = has($sit, 'cedid') ? 'X' : ' ';
$hOcup = has($sit, 'ocupa') ? 'X' : ' ';
$hFin = has($sit, 'financi') ? 'X' : ' ';
$hOutros = ($hPais === ' ' && $hProp === ' ' && $hAlug === ' ' && $hCedido === ' ' && $hOcup === ' ' && $hFin === ' ') ? 'X' : ' ';

/* Tipo moradia */
$tipo = mb_strtolower((string)($S['tipo_moradia'] ?? ''));
$tAlv = has($tipo, 'alvenaria') ? 'X' : ' ';
$tMad = has($tipo, 'madeira') ? 'X' : ' ';
$tMis = has($tipo, 'mista') ? 'X' : ' ';
$tOut = ($tAlv === ' ' && $tMad === ' ' && $tMis === ' ') ? 'X' : ' ';

/* Água */
$ab = mb_strtolower((string)($S['abastecimento'] ?? ''));
$aRede = has($ab, 'rede') ? 'X' : ' ';
$aPoco = (has($ab, 'poço') || has($ab, 'poco')) ? 'X' : ' ';
$aOut = ($aRede === ' ' && $aPoco === ' ') ? 'X' : ' ';

/* Iluminação */
$il = mb_strtolower((string)($S['iluminacao'] ?? ''));
$iProp = (has($il, 'próprio') || has($il, 'proprio')) ? 'X' : ' ';
$iCom = has($il, 'comunit') ? 'X' : ' ';
$iSem = has($il, 'sem') ? 'X' : ' ';
$iLam = has($il, 'lampi') ? 'X' : ' ';
$iOut = ($iProp === ' ' && $iCom === ' ' && $iSem === ' ' && $iLam === ' ') ? 'X' : ' ';

/* Esgoto */
$esg = mb_strtolower((string)($S['esgoto'] ?? ''));
$eRede = has($esg, 'rede') ? 'X' : ' ';
$eFosR = (has($esg, 'rudiment') || has($esg, 'rdiment')) ? 'X' : ' ';
$eFosS = (has($esg, 'sépt') || has($esg, 'sept')) ? 'X' : ' ';
$eCeu  = (has($esg, 'céu') || has($esg, 'ceu')) ? 'X' : ' ';

/* Lixo */
$lx = mb_strtolower((string)($S['lixo'] ?? ''));
$lCol = has($lx, 'colet') ? 'X' : ' ';
$lQue = has($lx, 'queim') ? 'X' : ' ';
$lEnt = has($lx, 'enterr') ? 'X' : ' ';
$lCeu = (has($lx, 'céu') || has($lx, 'ceu')) ? 'X' : ' ';
$lOut = ($lCol === ' ' && $lQue === ' ' && $lEnt === ' ' && $lCeu === ' ') ? 'X' : ' ';

/* Entorno */
$ent = mb_strtolower((string)($S['entorno'] ?? ''));
$enPav = has($ent, 'pavimentada') ? 'X' : ' ';
$enNao = (has($ent, 'não pav') || has($ent, 'nao pav')) ? 'X' : ' ';
$enIga = has($ent, 'igarap') ? 'X' : ' ';
$enBar = has($ent, 'barranco') ? 'X' : ' ';
$enInv = has($ent, 'invas') ? 'X' : ' ';
$enOut = ($enPav === ' ' && $enNao === ' ' && $enIga === ' ' && $enBar === ' ' && $enInv === ' ') ? 'X' : ' ';

/* ✅ Importante: tipo principal (para não repetir no VI) */
$principalTipoId = isset($S['ajuda_tipo_id']) ? (int)$S['ajuda_tipo_id'] : 0;

/* ✅ Solicitações adicionais (VI) - NÃO repete a principal */
$SOLS = [];
try {
  $solicitacoes = $pdo->prepare("
    SELECT 
      sol.resumo_caso,
      sol.data_solicitacao,
      sol.status,
      sol.ajuda_tipo_id,
      COALESCE(at.nome,'') AS ajuda_tipo_nome
    FROM solicitacoes sol
    LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
    WHERE sol.solicitante_id = :sid
      AND (sol.ajuda_tipo_id IS NULL OR sol.ajuda_tipo_id <> :principal)
    ORDER BY sol.data_solicitacao DESC, sol.id DESC
  ");
  $solicitacoes->execute([
    ':sid' => (int)$S['id'],
    ':principal' => $principalTipoId
  ]);
  $SOLS = $solicitacoes->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $SOLS = [];
}
?>


<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <title>FORMULÁRIO SOCIOECONÔMICO – ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --ink: #000;
      --grid: #000;
      --fs: 9.3pt;
      --lh: 1.25;
      --pad: 10mm 5mm;
      --ruled-lines: 4;

      /* ✅ ajuste fino do recorte (remove “tarjas pretas” da foto) */
      --photo-zoom: 1.22;
      /* aumente p/ cortar mais (ex: 1.30) */
      --photo-pos-y: 30%;
      /* sobe/desce o enquadramento (0% topo / 50% centro) */
    }

    * {
      box-sizing: border-box;
      color: var(--ink);
      font-family: Arial, Helvetica, sans-serif;
      overflow-wrap: anywhere
    }

    html,
    body {
      margin: 0;
      background: #eef2f7
    }

    .viewport-center {
      min-height: 100vh;
      display: flex;
      justify-content: center
    }

    .sheet {
      width: 210mm;
      min-height: 297mm;
      background: #fff;
      border: 1px solid #ccc;
      box-shadow: 0 6px 16px rgba(0, 0, 0, .08);
      padding: var(--pad);
      font-size: var(--fs);
      line-height: var(--lh)
    }

    .print-bar {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 6px;
    }

    .print-btn {
      padding: .45rem .8rem;
      border: 1px solid #888;
      border-radius: 6px;
      background: #fff;
      cursor: pointer
    }

    .hdr {
      display: grid;
      grid-template-columns: 80px 1fr 90px;
      align-items: center;
      gap: 4px
    }

    /* ✅ só o logo usa essa regra */
    .hdr img.logo {
      height: 38px;
      width: auto;
      object-fit: contain;
      display: block;
    }

    /* ✅ Foto 3x4 (frame) — corrige as “partes pretas” com recorte/zoom */
    .photo-3x4 {

      width: 100px !important;
      height: 154px !important;
      border: 1px solid #000;
      border-radius: 4px;
      margin-left: -30px !important;
      margin-top: -7.5px !important;
      background: #fff;
      overflow: hidden;
      --photo-zoom: 2;
      /* dobra o zoom = corta metade */
      --photo-pos-y: 0%;
      /* MUITO importante */
      display: flex;
      align-items: center;
      justify-content: center;
    }


    .photo-3x4 img {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
      object-position: 50% var(--photo-pos-y);
      transform: scale(var(--photo-zoom));
      transform-origin: center;
    }

    /* se não tiver foto, deixa o logo “bonito” dentro do frame */
    .photo-3x4.photo-empty img {
      object-fit: contain;
      transform: none;
      padding: 6px;
      background: #fff;
    }

    .hdr .mid {
      text-align: center;
      font-weight: 700;
      line-height: 1.2;
      font-size: 10.5pt;
      text-transform: uppercase
    }

    .hdr .mid small {
      display: block;
      font-weight: 700;
      color: #000;
      font-size: 9.5pt;
      text-transform: none
    }

    .divisor {
      height: 1px;
      background: #000;
      margin: 6px 0 6px;
      opacity: .85
    }

    h1.titulo {
      text-align: center;
      font-size: 10.8pt;
      margin: 4px 0 6px;
      text-transform: uppercase
    }

    .barra {
      height: 1px;
      background: #000;
      margin: 2px 0 5px;
      opacity: .8
    }

    .box {
      border: 1px solid var(--grid);
      margin-bottom: 5px;
      break-inside: avoid
    }

    .box .cap {
      background: #f4f4f4;
      font-weight: 700;
      padding: 3px 6px;
      border-bottom: 1px solid var(--grid);
      font-size: 9.8pt
    }

    .box .body {
      padding: 0 5px
    }

    .row {
      display: grid;
      gap: 0;
      margin: 0
    }

    .grid-2 {
      grid-template-columns: 1fr 1fr
    }

    .grid-3 {
      grid-template-columns: 1fr 1fr 1fr
    }

    .grid-4-uf {
      grid-template-columns: 1.1fr 1fr 1.6fr 70px;
    }

    .grid-3-uf {
      grid-template-columns: 1.2fr .6fr 1.7fr;
    }

    .grid-pcd-ec {
      grid-template-columns: 260px 1fr
    }

    .full {
      grid-template-columns: 1fr
    }

    .cell {
      display: grid;
      grid-template-rows: auto minmax(18px, auto);
      border-bottom: 1px solid var(--grid)
    }

    .lbl {
      font-size: 8.8pt;
      padding: 2px 2px 0 2px;
      font-weight: 700
    }

    .val {
      padding: 0 4px;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 6px;
      min-height: 18px;
      font-size: 9.3pt;
      font-weight: 400
    }

    table.grid {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      margin-top: 0
    }

    table.grid th,
    table.grid td {
      border: 1px solid var(--grid);
      padding: 2px 3px;
      font-size: 9pt;
      text-align: left
    }

    table.grid th {
      background: #f5f5f5;
      font-weight: 700
    }

    .center {
      text-align: center
    }

    .linha-assina {
      display: flex;
      gap: 10px;
      margin-top: 15px
    }

    .ass {
      flex: 1;
      border-top: 1px solid var(--grid);
      text-align: center;
      padding-top: 4px;
      font-size: 9pt
    }

    .rodape-data {
      display: flex;
      justify-content: flex-end;
      gap: 5px;
      margin-top: 8px;
      font-size: 9pt
    }

    .risco {
      min-width: 22px;
      border-bottom: 1px solid #000
    }

    .ruled {
      --step: 1.45em;
      position: relative;
      min-height: calc(var(--ruled-lines) * var(--step));
      line-height: 1.45;
      padding: 2px 4px;
      background-image:
        repeating-linear-gradient(to bottom,
          transparent,
          transparent calc(var(--step) - 1px),
          rgba(0, 0, 0, .6) calc(var(--step) - 1px),
          rgba(0, 0, 0, .6) var(--step));
    }

    .ruled p {
      margin: 0;
      white-space: pre-wrap;
    }

    @page {
      size: A4;
      margin: 10mm 5mm
    }

    @media print {

      html,
      body {
        background: #fff
      }

      .sheet {
        border: 0;
        box-shadow: none;
        padding: 1mm
      }

      .noprint {
        display: none !important
      }

      .box,
      table,
      tr,
      td,
      th {
        break-inside: avoid
      }
    }
  </style>
</head>

<body>
  <div class="viewport-center">
    <div class="sheet">

      <div class="print-bar noprint" style="margin-bottom: 30px !important;">
        <button class="print-btn" onclick="window.print()">Imprimir</button>
      </div>

      <div class="hdr">
        <img class="logo" src="assets/images/logo/logo_pmc_2025.jpg" alt="Prefeitura">

        <div class="mid">
          ESTADO DO AMAZONAS<br>
          PREFEITURA MUNICIPAL DE COARI
          <small>SECRETARIA MUNICIPAL – ANEXO - PMC</small>
        </div>

        <?php if ($fotoSolicitanteSrc !== ''): ?>
          <div class="photo-3x4" title="Foto 3x4 do solicitante">
            <img src="<?= h($fotoSolicitanteSrc) ?>" alt="Foto 3x4 do solicitante">
          </div>
        <?php else: ?>
          <div class="photo-3x4 photo-empty" title="Sem foto">
            <img src="assets/images/logo/logo_pmc_2025.jpg" alt="Sem foto">
          </div>
        <?php endif; ?>
      </div>

      <div class="divisor"></div>
      <h1 class="titulo">FORMULÁRIO SOCIOECONÔMICO</h1>

      <div class="barra"></div>

      <!-- I – IDENTIFICAÇÃO -->
      <div class="box">
        <div class="cap" style="display:flex; justify-content:space-between; align-items:center;">

          <div>
            I – DADOS DE IDENTIFICAÇÃO
          </div>

          <div style="font-size:9pt; font-weight:normal;">
            <b>Cadastro:</b>
            <?= h(fmt_datetime_br($S['created_at'] ?? '')) ?>
          </div>

        </div>

        <div class="body">
          <div class="row <?= trim((string)($S['local_trabalho'] ?? '')) !== '' ? 'grid-3' : 'grid-2' ?>">
            <div class="cell">
              <div class="lbl">Nome:</div>
              <div class="val"><?= h($S['nome'] ?? '') ?></div>
            </div>
            <div class="cell">
              <div class="lbl">NIS:</div>
              <div class="val"><?= h($S['nis'] ?? '') ?></div>
            </div>
            <?php if (trim((string)($S['local_trabalho'] ?? '')) !== ''): ?>
              <div class="cell">
                <div class="lbl">Local de Trabalho:</div>
                <div class="val"><?= h($S['local_trabalho']) ?></div>
              </div>
            <?php endif; ?>
          </div>

          <div class="row grid-3">
            <div class="cell">
              <div class="lbl">Nascimento:</div>
              <div class="val"><?= h(fmt_date_br($S['data_nascimento'] ?? '')) ?></div>
            </div>
            <div class="cell">
              <div class="lbl">Naturalidade:</div>
              <div class="val"><?= h($S['naturalidade'] ?? '') ?></div>
            </div>
            <div class="cell">
              <div class="lbl">Gênero:</div>
              <div class="val">( <?= $genMasc ?> ) Masc ( <?= $genFem ?> ) Fem ( <?= $genOut ?> ) Outros</div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Grupos Tradicionais:</div>
              <div class="val" style="flex-wrap:wrap;">
                ( <?= has(mb_strtolower((string)($S['grupo_tradicional'] ?? '')), 'indígena') || has(mb_strtolower((string)($S['grupo_tradicional'] ?? '')), 'indigena') ? 'X' : ' ' ?> ) Indígena
                ( <?= has(mb_strtolower((string)($S['grupo_tradicional'] ?? '')), 'quilombola') ? 'X' : ' ' ?> ) Quilombola
                ( <?= has(mb_strtolower((string)($S['grupo_tradicional'] ?? '')), 'cigano') ? 'X' : ' ' ?> ) Cigano
                ( <?= has(mb_strtolower((string)($S['grupo_tradicional'] ?? '')), 'ribeirinho') ? 'X' : ' ' ?> ) Ribeirinho
                ( <?= has(mb_strtolower((string)($S['grupo_tradicional'] ?? '')), 'extrativista') ? 'X' : ' ' ?> ) Extrativista
                ( <?= ($S['grupo_outros'] ?? '') !== '' ? 'X' : ' ' ?> ) Outros <?= h($S['grupo_outros'] ?? '') ?>
              </div>
            </div>
          </div>

          <div class="row grid-pcd-ec">
            <div class="cell">
              <div class="lbl">PCD:</div>
              <div class="val">( <?= $pcdSim ?> ) Sim ( <?= $pcdNao ?> ) Não &nbsp; <?= ($S['pcd_tipo'] ?? '') !== '' ? 'Tipo: ' . h($S['pcd_tipo']) : '' ?></div>
            </div>
            <div class="cell">
              <div class="lbl">Estado Civil:</div>
              <div class="val" style="flex-wrap:wrap;">
                ( <?= $ecCasado ?> ) Casado(a)
                ( <?= $ecSolteiro ?> ) Solteiro(a)
                ( <?= $ecViuvo ?> ) Viúvo(a)
                ( <?= $ecUniao ?> ) União Estável
                ( <?= $ecOutros ?> ) Outros
              </div>
            </div>
          </div>

          <div class="row grid-4-uf">
            <div class="cell">
              <div class="lbl">CPF:</div>
              <div class="val"><?= h(fmt_cpf($S['cpf'] ?? '')) ?></div>
            </div>
            <div class="cell">
              <div class="lbl">RG:</div>
              <div class="val"><?= h($S['rg'] ?? '') ?></div>
            </div>
            <div class="cell">
              <div class="lbl">Emissão:</div>
              <div class="val"><?= h(fmt_date_br($S['rg_emissao'] ?? '')) ?></div>
            </div>
            <div class="cell">
              <div class="lbl">UF:</div>
              <div class="val"><?= h($S['rg_uf'] ?? '') ?></div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Endereço:</div>
              <div class="val"><?= h(($S['endereco'] ?? '')) . ', ' . h(($S['numero'] ?? '')) ?> — <?= h($S['bairro_nome'] ?? '') ?></div>
            </div>
          </div>

          <div class="row grid-3">
            <div class="cell">
              <div class="lbl">Complemento:</div>
              <div class="val"><?= h($S['complemento'] ?? '') ?></div>
            </div>
            <div class="cell">
              <div class="lbl">Tempo de Moradia:</div>
              <div class="val"><?= (int)($S['tempo_anos'] ?? 0) ?> ano(s), <?= (int)($S['tempo_meses'] ?? 0) ?> mês(es)</div>
            </div>
            <div class="cell">
              <div class="lbl">Telefone:</div>
              <div class="val"><?= h($S['telefone'] ?? '') ?></div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Ponto de Referência:</div>
              <div class="val"><?= h($S['referencia'] ?? '') ?></div>
            </div>
          </div>

          <div class="row grid-2">
            <div class="cell">
              <div class="lbl">PBF:</div>
              <div class="val">( <?= $pbfSim ?> ) Sim ( <?= $pbfNao ?> ) Não &nbsp; Valor: <?= h(fmt_money($S['pbf_valor'])) ?></div>
            </div>
            <div class="cell">
              <div class="lbl">BPC:</div>
              <div class="val">( <?= $bpcSim ?> ) Sim ( <?= $bpcNao ?> ) Não &nbsp; Valor: <?= h(fmt_money($S['bpc_valor'])) ?></div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Benefícios:</div>
              <div class="val">
                ( <?= ($S['beneficio_municipal'] ?? 'Não') === 'Sim' ? 'X' : ' ' ?> ) Municipal – Valor: <?= h(fmt_money($S['beneficio_municipal_valor'])) ?> &nbsp;
                ( <?= ($S['beneficio_estadual'] ?? 'Não') === 'Sim'  ? 'X' : ' ' ?> ) Estadual – Valor: <?= h(fmt_money($S['beneficio_estadual_valor'])) ?>
              </div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Situação:</div>
              <div class="val" style="flex-wrap:wrap;">
                ( <?= has(mb_strtolower((string)($S['trabalho'] ?? '')), 'empreg') ? 'X' : ' ' ?> ) Empregado(a)
                ( <?= has(mb_strtolower((string)($S['trabalho'] ?? '')), 'desempreg') ? 'X' : ' ' ?> ) Desempregado(a)
                ( <?= has(mb_strtolower((string)($S['trabalho'] ?? '')), 'autôn') || has(mb_strtolower((string)($S['trabalho'] ?? '')), 'auton') ? 'X' : ' ' ?> ) Autônomo(a)
                ( <?= has(mb_strtolower((string)($S['trabalho'] ?? '')), 'aposent') || has(mb_strtolower((string)($S['trabalho'] ?? '')), 'pension') ? 'X' : ' ' ?> ) Aposentado(a)/Pensionista – Valor <?= h(fmt_money($S['renda_individual'])) ?>
              </div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Renda Mensal:</div>
              <div class="val">
                ( <?= $fxInf1 ?> ) Inferior a 1 Salário Mínimo
                ( <?= $fx1 ?> ) 1 Salário Mínimo
                ( <?= $fx2 ?> ) 2 Salários Mínimos
                ( <?= $fxOut ?> ) Outros: <?= h($S['renda_mensal_outros'] ?? '') ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- II – CÔNJUGE -->
      <div class="box">
        <div class="cap">II – DADOS DO CÔNJUGE</div>
        <div class="body">
          <div class="row grid-2">
            <div class="cell">
              <div class="lbl">Nome:</div>
              <div class="val"><?= h($S['conj_nome'] ?? '') ?></div>
            </div>
            <div class="cell">
              <div class="lbl">NIS:</div>
              <div class="val"><?= h($S['conj_nis'] ?? '') ?></div>
            </div>
          </div>
          <div class="row grid-3">
            <div class="cell">
              <div class="lbl">Nascimento:</div>
              <div class="val"><?= h(fmt_date_br($S['conj_nasc'] ?? '')) ?></div>
            </div>
            <div class="cell">
              <div class="lbl">Gênero:</div>
              <div class="val">( <?= $conjFem ?> ) Fem ( <?= $conjMasc ?> ) Masc ( <?= $conjOut ?> ) Outros</div>
            </div>
            <div class="cell">
              <div class="lbl">Naturalidade:</div>
              <div class="val"><?= h($S['conj_naturalidade'] ?? '') ?></div>
            </div>
          </div>

          <div class="row grid-3-uf">
            <div class="cell">
              <div class="lbl">Emissão:</div>
              <div class="val"></div>
            </div>
            <div class="cell">
              <div class="lbl">UF:</div>
              <div class="val"></div>
            </div>
            <div class="cell">
              <div class="lbl">CPF:</div>
              <div class="val"><?= h(fmt_cpf($S['conj_cpf'] ?? '')) ?></div>
            </div>
          </div>

          <div class="row full">
            <div class="cell">
              <div class="lbl">Situação:</div>
              <div class="val" style="flex-wrap:wrap;">
                ( <?= has(mb_strtolower((string)($S['conj_trabalho'] ?? '')), 'empreg') ? 'X' : ' ' ?> ) Empregado(a)
                ( <?= has(mb_strtolower((string)($S['conj_trabalho'] ?? '')), 'desempreg') ? 'X' : ' ' ?> ) Desempregado(a)
                ( <?= has(mb_strtolower((string)($S['conj_trabalho'] ?? '')), 'autôn') || has(mb_strtolower((string)($S['conj_trabalho'] ?? '')), 'auton') ? 'X' : ' ' ?> ) Autônomo(a)
                ( <?= has(mb_strtolower((string)($S['conj_trabalho'] ?? '')), 'aposent') || has(mb_strtolower((string)($S['conj_trabalho'] ?? '')), 'pension') ? 'X' : ' ' ?> ) Aposentado(a)/Pensionista – Valor <?= h(fmt_money($S['conj_renda'])) ?>
              </div>
            </div>
          </div>
          <div class="row full">
            <div class="cell">
              <div class="lbl">PCD / BPC:</div>
              <div class="val">
                PCD ( <?= $conjPcdSim ?> ) Sim ( <?= $conjPcdNao ?> ) Não &nbsp;
                BPC ( <?= $conjBpcSim ?> ) Sim ( <?= $conjBpcNao ?> ) Não &nbsp; Valor: <?= h(fmt_money($S['conj_bpc_valor'])) ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- III – COMPOSIÇÃO FAMILIAR -->
      <div class="box">
        <div class="cap">III – COMPOSIÇÃO FAMILIAR</div>
        <div class="body">
          <table class="grid">
            <colgroup>
              <col style="width:6%">
              <col style="width:34%">
              <col style="width:12%">
              <col style="width:18%">
              <col style="width:18%">
              <col style="width:12%">
            </colgroup>
            <thead>
              <tr>
                <th>Nº</th>
                <th>Nome</th>
                <th>Data de Nasc.</th>
                <th>Parentesco</th>
                <th>Escolaridade</th>
                <th>Observação</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1;
              foreach ($FAMS as $f): ?>
                <tr>
                  <td class="center"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></td>
                  <td><?= h($f['nome'] ?? '') ?></td>
                  <td><?= h(fmt_date_br($f['data_nascimento'] ?? '')) ?></td>
                  <td><?= h($f['parentesco'] ?? '') ?></td>
                  <td><?= h($f['escolaridade'] ?? '') ?></td>
                  <td><?= h($f['obs'] ?? '') ?></td>
                </tr>
              <?php $i++;
              endforeach;
              for (; $i <= 7; $i++): ?>
                <tr>
                  <td class="center"><?= str_pad((string)$i, 2, '0', STR_PAD_LEFT) ?></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td></td>
                </tr>
              <?php endfor; ?>
            </tbody>
          </table>

          <table class="grid" style="margin-top:3px;table-layout:fixed;">
            <tbody>
              <tr>
                <td style="width:50%;">Total de Membros na Residência: <?= (int)($S['total_moradores'] ?? 0) ?></td>
                <td style="width:50%;">Total de Famílias na Residência: <?= (int)($S['total_familias'] ?? 0) ?></td>
              </tr>
              <tr>
                <td>Pessoas Com Deficiência: ( <?= ($S['pcd_residencia'] ?? '') === 'Sim' ? 'X' : ' ' ?> ) Sim &nbsp; ( <?= ($S['pcd_residencia'] ?? '') === 'Sim' ? ' ' : 'X' ?> ) Não</td>
                <td>Tipificação: <?= h($S['tipificacao'] ?? '') ?></td>
              </tr>

              <!-- ✅ NOVO: Tipo de Ajuda (vem de solicitantes.ajuda_tipo_id -> ajudas_tipos) -->
              <tr>
                <td>Tipo de Ajuda: <?= h($ajudaTipoNome) ?></td>
                <td>Categoria da Ajuda: <?= h($ajudaTipoCat) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- IV – CONDIÇÕES HABITACIONAIS -->
      <div class="box">
        <div class="cap">IV – CONDIÇÕES HABITACIONAIS</div>
        <div class="body">
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            ( <?= $hPais ?> ) Reside com os pais
            ( <?= $hProp ?> ) Próprio
            ( <?= $hAlug ?> ) Alugado – <?= h(fmt_money($S['situacao_imovel_valor'])) ?>
            ( <?= $hCedido ?> ) Cedido
            ( <?= $hOcup ?> ) Ocupação
            ( <?= $hFin ?> ) Financiado
            ( <?= $hOutros ?> ) Outros
          </div>
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            Tipo de construção: ( <?= $tAlv ?> ) Alvenaria ( <?= $tMad ?> ) Madeira ( <?= $tMis ?> ) Mista ( <?= $tOut ?> ) Outros
          </div>
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            Abastecimento de Água: ( <?= $aRede ?> ) Rede Pública ( <?= $aPoco ?> ) Poço ( <?= $aOut ?> ) Outros
          </div>
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            Iluminação: ( <?= $iProp ?> ) Próprio ( <?= $iCom ?> ) Comunitário ( <?= $iSem ?> ) Sem ( <?= $iLam ?> ) Lampião ( <?= $iOut ?> ) Outros
          </div>
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            Escoamento Sanitário: ( <?= $eRede ?> ) Rede Pública ( <?= $eFosR ?> ) Fossa Rudimentar ( <?= $eFosS ?> ) Fossa Séptica ( <?= $eCeu ?> ) Céu Aberto
          </div>
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            Lixo: ( <?= $lCol ?> ) Coletado ( <?= $lQue ?> ) Queimado ( <?= $lEnt ?> ) Enterrado ( <?= $lCeu ?> ) Céu Aberto ( <?= $lOut ?> ) Outros
          </div>
          <div class="val" style="flex-wrap:wrap;border-bottom:1px solid var(--grid);">
            Características do Entorno: ( <?= $enPav ?> ) Rua Pavimentada ( <?= $enNao ?> ) Rua não Pavimentada ( <?= $enIga ?> ) Às Margens de Igarapé ( <?= $enBar ?> ) Barranco ( <?= $enInv ?> ) Invasão ( <?= $enOut ?> ) Outros
          </div>
        </div>
      </div>

      <!-- V – RESUMO -->
      <div class="box">
        <div class="cap cap-flex">
          <span>V – RESUMO DO CASO CADASTRAL</span>

          <?php
          $entV = status_por_entrega(
            $pdo,
            $cpfParam,
            isset($S['ajuda_tipo_id']) ? (int)$S['ajuda_tipo_id'] : null
          );

          $statusV   = (string)($entV['status'] ?? 'ABERTO');
          $entregueV = (string)($entV['entregue_em'] ?? ($entV['data'] ?? ''));
          ?>
          <span class="cap-right">
            <b>STATUS:</b> <span><?= h(mb_strtoupper($statusV, 'UTF-8')) ?></span>
            <?php if ($entregueV !== ''): ?>
              &nbsp;|&nbsp; <b>ENTREGUE EM:</b> <span><?= h($entregueV) ?></span>
            <?php endif; ?>
          </span>
        </div>

        <div class="body">
          <div class="ruled">
            <b><?= h(mb_strtoupper($ajudaTipoNome ?? '—', 'UTF-8')) ?> :</b><br>
            <p><?= nl2br(h($S['resumo_caso'] ?? '')) ?></p>
          </div>
        </div>
      </div>

      <style>
        .cap-flex {
          display: flex;
          justify-content: space-between;
          align-items: baseline;
          gap: 10px;
          flex-wrap: wrap;
        }

        .cap-right {
          font-weight: 400;
          font-size: 9pt;
          white-space: nowrap;
        }

        .cap-right b {
          font-weight: 700;
        }
      </style>

      <?php
      /* ==============================
         Solicitações Adicionais (VI)
         - traz todas do solicitante
         - mesmo se ajuda_tipo_id for igual
         - se quiser mostrar as duas, não excluir por ajuda_tipo_id
      ============================== */

      $solicitacoes = $pdo->prepare("
        SELECT 
          sol.id,
          sol.resumo_caso,
          sol.data_solicitacao,
          sol.status,
          sol.ajuda_tipo_id,
          COALESCE(at.nome,'') AS ajuda_tipo_nome
        FROM solicitacoes sol
        LEFT JOIN ajudas_tipos at ON at.id = sol.ajuda_tipo_id
        WHERE sol.solicitante_id = :sid
        ORDER BY sol.data_solicitacao DESC, sol.id DESC
      ");
      $solicitacoes->execute([
        ':sid' => (int)$S['id']
      ]);
      $SOLS = $solicitacoes->fetchAll(PDO::FETCH_ASSOC);
      ?>

      <style>
        .sol-header-flex {
          display: flex;
          justify-content: space-between;
          align-items: baseline;
          margin-bottom: 2px;
        }

        .sol-header-flex b {
          flex: 1;
        }

        .sol-header-flex .sol-date {
          font-weight: 400;
          font-size: 9pt;
          margin-left: 10px;
          white-space: nowrap;
        }

        .sol-status-line {
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
          margin-top: 2px;
          font-weight: 700;
        }

        .sol-status-line span {
          font-weight: 400;
        }
      </style>

      <!-- VI – SOLICITAÇÕES ADICIONAIS -->
      <div class="box">
        <div class="cap">VI – SOLICITAÇÕES ADICIONAIS</div>
        <div class="body">
          <?php if (!empty($SOLS)): ?>
            <?php foreach ($SOLS as $sol): ?>
              <?php
              $entInfo = status_por_entrega(
                $pdo,
                $cpfParam,
                isset($sol['ajuda_tipo_id']) ? (int)$sol['ajuda_tipo_id'] : null
              );

              $statusExibir = (string)(($entInfo['status'] ?? '') ?: ($sol['status'] ?? 'ABERTO'));
              $dataEntregaSol = (string)($entInfo['entregue_em'] ?? ($entInfo['data'] ?? ''));
              ?>
              <div class="ruled">
                <div class="sol-header-flex">
                  <b><?= h(mb_strtoupper(($sol['ajuda_tipo_nome'] ?: 'SOLICITAÇÃO'), 'UTF-8')) ?> :</b>
                  <span class="sol-date"><?= h(fmt_date_short($sol['data_solicitacao'] ?? '')) ?></span>
                </div>

                <p><?= nl2br(h($sol['resumo_caso'] ?? '')) ?></p>

                <div class="sol-status-line">
                  <div>STATUS: <span><?= h(mb_strtoupper($statusExibir, 'UTF-8')) ?></span></div>

                  <?php if ($dataEntregaSol !== ''): ?>
                    <div>ENTREGUE EM: <span><?= h($dataEntregaSol) ?></span></div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="ruled">
              <p></p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="rodape-data" style="margin-top:20px">
        <span>Coari – AM,</span><span class="risco" style="width:50px"></span> /
        <span class="risco" style="width:50px"></span> / <?= date('Y') ?>
      </div>

      <div class="linha-assina" style="margin-top:35px">
        <div class="ass">Entrevistado(a)</div>
        <div class="ass">Técnico(a) Responsável</div>
      </div>
    </div>
  </div>



  </div>
  </div>
</body>

</html>
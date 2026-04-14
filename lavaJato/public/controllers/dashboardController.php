<?php
// autoErp/public/controllers/dashboardController.php
declare(strict_types=1);

// Garantias mínimas
$nomeUser    = $_SESSION['user_nome'] ?? 'Usuário';
$empresaCnpj = preg_replace('/\D+/', '', (string)($_SESSION['user_empresa_cnpj'] ?? ''));
$empresaNome = '—';

if (isset($pdo) && $pdo instanceof PDO && $empresaCnpj !== '') {
  // Nome da empresa
  try {
    $st = $pdo->prepare("SELECT nome_fantasia FROM empresas_peca WHERE REPLACE(REPLACE(REPLACE(cnpj,'.',''),'-',''),'/','') = :c LIMIT 1");
    $st->execute([':c' => $empresaCnpj]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $empresaNome = (string)$row['nome_fantasia'];
  } catch (Throwable $e) { /* silencioso */
  }
}

// Métricas (30 dias)
$vendasQtde     = 0;
$faturamento30d = 0.0;
$itensEstoque   = 0;
$despesas30d    = 0.0; // placeholder (sem tabela de despesas)

if (isset($pdo) && $pdo instanceof PDO && $empresaCnpj !== '') {
  try {
    // Vendas 30 dias
    $st = $pdo->prepare("
      SELECT COUNT(*) AS qtde, COALESCE(SUM(total_liquido),0) AS total
        FROM vendas_peca
       WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
         AND status = 'fechada'
         AND criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $st->execute([':c' => $empresaCnpj]);
    $v = $st->fetch(PDO::FETCH_ASSOC) ?: ['qtde' => 0, 'total' => 0];
    $vendasQtde     = (int)$v['qtde'];
    $faturamento30d = (float)$v['total'];
  } catch (Throwable $e) { /* noop */
  }

  try {
    // Itens em estoque (conta produtos ativos)
    $st = $pdo->prepare("
      SELECT COUNT(*)
        FROM produtos_peca
       WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
         AND ativo = 1
    ");
    $st->execute([':c' => $empresaCnpj]);
    $itensEstoque = (int)$st->fetchColumn();
  } catch (Throwable $e) { /* noop */
  }

  // despesas30d permanece 0.00 até existir tabela de despesas
}

// === Metas para preencher o círculo (ajuste como quiser) ===
$vendasMeta       = 350;       // alvo para 100%
$estoqueMeta      = 3210;      // alvo para 100%
$faturamentoMeta  = 7400.00;   // alvo para 100% (R$)
$despesasMeta     = 1250.00;   // alvo para 100% (R$)

// helper de % limitado a 0..100
$__pct = static function (float $valor, float $meta): int {
  if ($meta <= 0) return 0;
  $p = (int)round(($valor / $meta) * 100);
  return max(0, min(100, $p));
};

// percentuais para os círculos
$vendasPct       = $__pct((float)$vendasQtde,       (float)$vendasMeta);
$estoquePct      = $__pct((float)$itensEstoque,     (float)$estoqueMeta);
$faturamentoPct  = $__pct((float)$faturamento30d,   (float)$faturamentoMeta);
$despesasPct     = $__pct((float)$despesas30d,      (float)$despesasMeta);

// Gráfico vendas últimos 6 meses
$chartLabels = [];
$chartSeries = [];

if (isset($pdo) && $pdo instanceof PDO && $empresaCnpj !== '') {
  // Monta meses (do mais antigo ao atual)
  $labels = [];
  $now = new DateTime('first day of this month');
  for ($i = 5; $i >= 0; $i--) {
    $p = (clone $now)->modify("-$i months");
    $labels[] = $p->format('m/Y');
  }
  $vals = array_fill(0, count($labels), 0.0);

  try {
    $st = $pdo->prepare("
      SELECT DATE_FORMAT(criado_em, '%m/%Y') AS mes, COALESCE(SUM(total_liquido),0) AS total
        FROM vendas_peca
       WHERE REPLACE(REPLACE(REPLACE(empresa_cnpj,'.',''),'-',''),'/','') = :c
         AND status = 'fechada'
         AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
       GROUP BY YEAR(criado_em), MONTH(criado_em)
       ORDER BY YEAR(criado_em), MONTH(criado_em)
    ");
    $st->execute([':c' => $empresaCnpj]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
      $mes = (string)$r['mes'];
      $idx = array_search($mes, $labels, true);
      if ($idx !== false) $vals[$idx] = (float)$r['total'];
    }
  } catch (Throwable $e) { /* noop */
  }

  $chartLabels = $labels;
  $chartSeries = $vals;
}

/* ==================== LAVAGENS DA SEMANA (segunda→domingo) — sem LIMIT ==================== */
$lavagens       = [];
$lavSemanaLabel = '—';

if (isset($pdo) && $pdo instanceof PDO && $empresaCnpj !== '') {
  $today     = new DateTimeImmutable('today');
  $weekStart = $today->modify('monday this week')->setTime(0, 0, 0);
  $weekEnd   = $today->modify('sunday this week')->setTime(23, 59, 59);
  $ini       = $weekStart->format('Y-m-d H:i:s');
  $fim       = $weekEnd->format('Y-m-d H:i:s');
  $lavSemanaLabel = $weekStart->format('d/m') . ' — ' . $weekEnd->format('d/m');

  try {
    $sql = "
      SELECT
        COALESCE(l.categoria_nome,'Serviço') AS servico,
        TRIM(CONCAT(
          COALESCE(l.modelo,''), 
          CASE WHEN l.modelo IS NOT NULL AND l.modelo<>'' AND (l.cor IS NOT NULL AND l.cor<>'') THEN ' ' ELSE '' END,
          COALESCE(l.cor,''),
          CASE WHEN ( (l.modelo IS NOT NULL AND l.modelo<>'') OR (l.cor IS NOT NULL AND l.cor<>'') )
                    AND (l.placa IS NOT NULL AND l.placa<>'') THEN ' • ' ELSE '' END,
          COALESCE(l.placa,'')
        )) AS veiculo,
        l.valor,
        l.status,
        DATE_FORMAT(l.criado_em, '%d/%m/%Y %H:%i') AS quando,
        COALESCE(NULLIF(lp.nome,''), NULLIF(u.nome,''), '—') AS lavador
      FROM lavagens_peca l
      LEFT JOIN lavadores_peca lp
        ON REPLACE(REPLACE(REPLACE(lp.cpf,'.',''),'-',''),'/','')
           = REPLACE(REPLACE(REPLACE(l.lavador_cpf,'.',''),'-',''),'/','')
       AND REPLACE(REPLACE(REPLACE(lp.empresa_cnpj,'.',''),'-',''),'/','')
           = REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','')
      LEFT JOIN usuarios_peca u
        ON REPLACE(REPLACE(REPLACE(u.cpf,'.',''),'-',''),'/','')
           = REPLACE(REPLACE(REPLACE(l.lavador_cpf,'.',''),'-',''),'/','')
       AND REPLACE(REPLACE(REPLACE(u.empresa_cnpj,'.',''),'-',''),'/','')
           = REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','')
      WHERE REPLACE(REPLACE(REPLACE(l.empresa_cnpj,'.',''),'-',''),'/','') = :c
        AND l.criado_em BETWEEN :ini AND :fim
      ORDER BY l.criado_em DESC, l.id DESC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':c' => $empresaCnpj, ':ini' => $ini, ':fim' => $fim]);
    $lavagens = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { /* silencioso */ }
}

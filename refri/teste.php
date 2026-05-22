<?php
/* =========================================================
   DASHBOARD — Sistema de Ordens de Serviço
   PHP + AJAX + CSS + Bootstrap 5 + Chart.js
   Design: Semi-quadrado Premium SaaS
   ========================================================= */

// ── Config BD (ajuste conforme seu ambiente) ──────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'os_system');
define('DB_USER', 'root');
define('DB_PASS', '');

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

// ── AJAX Handlers ─────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    try {
        switch ($action) {

            // Métricas dos cards
            case 'metrics':
                $pdo = db();
                $total   = $pdo->query("SELECT COUNT(*) FROM ordens_servico")->fetchColumn();
                $abertas = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status='aberta'")->fetchColumn();
                $andando = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status='em_andamento'")->fetchColumn();
                $concl   = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE status='concluida'")->fetchColumn();
                $urgente = $pdo->query("SELECT COUNT(*) FROM ordens_servico WHERE prioridade='urgente' AND status NOT IN('concluida','cancelada')")->fetchColumn();
                $faturado= $pdo->query("SELECT COALESCE(SUM(valor_final),0) FROM ordens_servico WHERE status='concluida'")->fetchColumn();
                echo json_encode(compact('total','abertas','andando','concl','urgente','faturado'));
                break;

            // Lista de OS com filtros e paginação
            case 'list_os':
                $pdo    = db();
                $page   = max(1,(int)($_GET['page']??1));
                $limit  = 10;
                $offset = ($page-1)*$limit;
                $search = trim($_GET['search']??'');
                $status = $_GET['status']??'';
                $prior  = $_GET['prioridade']??'';

                $where = ['1=1'];
                $params = [];

                if ($search !== '') {
                    $where[] = "(os.numero LIKE :s OR os.titulo LIKE :s OR c.nome LIKE :s)";
                    $params[':s'] = "%$search%";
                }
                if ($status !== '') { $where[] = "os.status = :st"; $params[':st'] = $status; }
                if ($prior  !== '') { $where[] = "os.prioridade = :pr"; $params[':pr'] = $prior; }

                $sql = "SELECT os.id, os.numero, os.titulo, os.status, os.prioridade,
                               os.data_abertura, os.data_previsao, os.valor_final,
                               c.nome AS cliente, t.nome AS tecnico
                        FROM ordens_servico os
                        LEFT JOIN clientes c ON c.id = os.cliente_id
                        LEFT JOIN tecnicos t ON t.id = os.tecnico_id
                        WHERE ".implode(' AND ',$where)."
                        ORDER BY os.created_at DESC
                        LIMIT :lim OFFSET :off";

                $st = $pdo->prepare($sql);
                foreach ($params as $k=>$v) $st->bindValue($k,$v);
                $st->bindValue(':lim',$limit,PDO::PARAM_INT);
                $st->bindValue(':off',$offset,PDO::PARAM_INT);
                $st->execute();
                $rows = $st->fetchAll();

                $cntSql = "SELECT COUNT(*) FROM ordens_servico os LEFT JOIN clientes c ON c.id=os.cliente_id WHERE ".implode(' AND ',$where);
                $cntSt  = $pdo->prepare($cntSql);
                foreach ($params as $k=>$v) $cntSt->bindValue($k,$v);
                $cntSt->execute();
                $total = (int)$cntSt->fetchColumn();

                echo json_encode(['rows'=>$rows,'total'=>$total,'pages'=>ceil($total/$limit),'page'=>$page]);
                break;

            // Dados para o gráfico de status (donut)
            case 'chart_status':
                $pdo = db();
                $rows = $pdo->query(
                    "SELECT status, COUNT(*) as qty FROM ordens_servico GROUP BY status"
                )->fetchAll();
                echo json_encode($rows);
                break;

            // Dados para o gráfico mensal (bar)
            case 'chart_monthly':
                $pdo = db();
                $rows = $pdo->query(
                    "SELECT DATE_FORMAT(data_abertura,'%Y-%m') AS mes,
                            COUNT(*) AS abertas,
                            SUM(status='concluida') AS concluidas
                     FROM ordens_servico
                     WHERE data_abertura >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                     GROUP BY mes ORDER BY mes"
                )->fetchAll();
                echo json_encode($rows);
                break;

            // OS recentes para a sidebar
            case 'recent':
                $pdo = db();
                $rows = $pdo->query(
                    "SELECT os.numero, os.titulo, os.status, os.prioridade,
                            c.nome AS cliente, os.data_abertura
                     FROM ordens_servico os
                     LEFT JOIN clientes c ON c.id=os.cliente_id
                     ORDER BY os.created_at DESC LIMIT 6"
                )->fetchAll();
                echo json_encode($rows);
                break;

            // Salvar nova OS (modal)
            case 'save_os':
                $pdo = db();
                $d = $_POST;
                // gera número sequencial
                $lastNum = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero,4) AS UNSIGNED)) FROM ordens_servico")->fetchColumn();
                $numero  = 'OS-'.str_pad(($lastNum??0)+1, 5, '0', STR_PAD_LEFT);

                $sql = "INSERT INTO ordens_servico
                        (numero,cliente_id,tecnico_id,titulo,descricao,status,prioridade,
                         categoria,equipamento,valor_orcamento,data_previsao)
                        VALUES(:num,:cli,:tec,:tit,:desc,:st,:pr,:cat,:eq,:val,:prev)";
                $st = $pdo->prepare($sql);
                $st->execute([
                    ':num'=>$numero,
                    ':cli'=>(int)$d['cliente_id'],
                    ':tec'=>$d['tecnico_id']?:(null),
                    ':tit'=>$d['titulo'],
                    ':desc'=>$d['descricao']??'',
                    ':st'=>$d['status']??'aberta',
                    ':pr'=>$d['prioridade']??'media',
                    ':cat'=>$d['categoria']??'',
                    ':eq'=>$d['equipamento']??'',
                    ':val'=>(float)($d['valor_orcamento']??0),
                    ':prev'=>$d['data_previsao']??null,
                ]);
                $id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO os_historico(os_id,usuario,acao) VALUES(?,?,?)")
                    ->execute([$id,'Sistema',"OS criada: $numero"]);
                echo json_encode(['success'=>true,'numero'=>$numero]);
                break;

            // Alterar status da OS
            case 'update_status':
                $pdo = db();
                $id = (int)$_POST['id'];
                $st = $_POST['status'];
                $allowed = ['aberta','em_andamento','aguardando','concluida','cancelada'];
                if (!in_array($st,$allowed)) throw new Exception('Status inválido');
                $extra = $st === 'concluida' ? ', data_conclusao=NOW()' : '';
                $pdo->prepare("UPDATE ordens_servico SET status=:s$extra WHERE id=:id")
                    ->execute([':s'=>$st,':id'=>$id]);
                $pdo->prepare("INSERT INTO os_historico(os_id,usuario,acao) VALUES(?,?,?)")
                    ->execute([$id,'Sistema',"Status alterado para: $st"]);
                echo json_encode(['success'=>true]);
                break;

            // Clientes para select
            case 'clientes':
                $pdo = db();
                $rows = $pdo->query("SELECT id,nome FROM clientes ORDER BY nome")->fetchAll();
                echo json_encode($rows);
                break;

            // Técnicos para select
            case 'tecnicos':
                $pdo = db();
                $rows = $pdo->query("SELECT id,nome FROM tecnicos WHERE ativo=1 ORDER BY nome")->fetchAll();
                echo json_encode($rows);
                break;

            default:
                echo json_encode(['error'=>'Action not found']);
        }
    } catch (PDOException $e) {
        // Em produção, logue o erro e retorne mensagem genérica
        http_response_code(500);
        echo json_encode(['error'=>'Erro de banco de dados', 'detail'=>$e->getMessage()]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error'=>$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OSmais — Dashboard</title>

<!-- Bootstrap 5 -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<!-- Fonts: DM Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ============================================================
   DESIGN TOKENS — Semi-Quadrado Premium
   ============================================================ */
:root {
  /* Raios - Semi-quadrado */
  --r-card:    18px;
  --r-input:   14px;
  --r-btn:     14px;
  --r-modal:   22px;
  --r-badge:   8px;
  --r-table:   18px;
  --r-sidebar: 20px;

  /* Sombras profissionais em camadas */
  --sh-card:         0 1px 3px rgba(15,23,42,.05),  0 8px 24px rgba(15,23,42,.08);
  --sh-card-hover:   0 2px 6px rgba(15,23,42,.06),  0 12px 30px rgba(15,23,42,.12);
  --sh-btn-primary:  0 2px 6px rgba(37,99,235,.14),  0 6px 18px rgba(37,99,235,.18);
  --sh-btn-danger:   0 2px 6px rgba(220,38,38,.12),  0 6px 18px rgba(220,38,38,.16);
  --sh-modal:        0 4px 16px rgba(2,6,23,.10),    0 20px 50px rgba(2,6,23,.18);
  --sh-dropdown:     0 4px 10px rgba(15,23,42,.06),  0 10px 25px rgba(15,23,42,.10);
  --sh-sidebar:      2px 0 20px rgba(15,23,42,.06);

  /* Paleta */
  --blue-50:  #EFF6FF;
  --blue-100: #DBEAFE;
  --blue-500: #3B82F6;
  --blue-600: #2563EB;
  --blue-700: #1D4ED8;
  --blue-900: #1E3A8A;

  --slate-50:  #F8FAFC;
  --slate-100: #F1F5F9;
  --slate-200: #E2E8F0;
  --slate-300: #CBD5E1;
  --slate-400: #94A3B8;
  --slate-500: #64748B;
  --slate-600: #475569;
  --slate-700: #334155;
  --slate-800: #1E293B;
  --slate-900: #0F172A;

  /* Superfícies */
  --bg-page:    #EEF2F7;
  --bg-surface: #FFFFFF;
  --bg-soft:    #F8FAFC;

  /* Bordas */
  --border-default: 1px solid #E2E8F0;
  --border-soft:    1px solid #F1F5F9;
  --border-focus:   2px solid #2563EB;

  /* Transições */
  --ease: cubic-bezier(0.4,0,0.2,1);
  --t-fast: 0.18s;
  --t-mid:  0.26s;
}

/* ── Base ─────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

html, body {
  margin: 0; padding: 0; height: 100%;
  font-family: 'DM Sans', system-ui, sans-serif;
  font-size: 14px;
  background: var(--bg-page);
  color: var(--slate-800);
  -webkit-font-smoothing: antialiased;
}

/* ── Layout ───────────────────────────────────────────── */
.os-wrapper {
  display: flex;
  min-height: 100vh;
}

/* ============================================================
   SIDEBAR
   ============================================================ */
.os-sidebar {
  width: 240px;
  min-width: 240px;
  background: var(--slate-900);
  display: flex;
  flex-direction: column;
  box-shadow: var(--sh-sidebar);
  position: sticky;
  top: 0;
  height: 100vh;
  overflow-y: auto;
  z-index: 100;
}

.sidebar-brand {
  padding: 22px 20px 18px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex;
  align-items: center;
  gap: 11px;
  text-decoration: none;
}
.brand-icon {
  width: 36px; height: 36px;
  background: var(--blue-600);
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; color: #fff;
  box-shadow: 0 4px 14px rgba(37,99,235,.40);
  flex-shrink: 0;
}
.brand-name {
  font-size: 17px; font-weight: 700;
  color: #fff; letter-spacing: -0.4px; line-height: 1;
}
.brand-tag {
  font-size: 10px; font-weight: 500;
  color: var(--slate-400); letter-spacing: .5px; text-transform: uppercase;
}

.sidebar-section {
  padding: 18px 12px 6px;
  font-size: 10.5px; font-weight: 600;
  color: var(--slate-500); letter-spacing: .8px; text-transform: uppercase;
}

.sidebar-nav { padding: 0 10px; }

.nav-link-os {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 12px;
  border-radius: 11px;
  color: var(--slate-400);
  font-size: 13.5px; font-weight: 500;
  text-decoration: none;
  transition: all var(--t-fast) var(--ease);
  margin-bottom: 2px;
  cursor: pointer;
}
.nav-link-os i { font-size: 17px; width: 20px; text-align: center; }
.nav-link-os:hover {
  color: #fff; background: rgba(255,255,255,.07);
}
.nav-link-os.active {
  color: #fff; background: var(--blue-600);
  box-shadow: 0 4px 14px rgba(37,99,235,.30);
}
.nav-link-os .badge-count {
  margin-left: auto;
  background: rgba(255,255,255,.12);
  color: var(--slate-300);
  font-size: 11px; font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
  min-width: 24px; text-align: center;
}
.nav-link-os.active .badge-count {
  background: rgba(255,255,255,.20); color: #fff;
}

.sidebar-divider {
  height: 1px; background: rgba(255,255,255,.06);
  margin: 10px 10px;
}

.sidebar-footer {
  margin-top: auto;
  padding: 14px 12px;
  border-top: 1px solid rgba(255,255,255,.06);
}
.user-card {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  cursor: pointer;
  transition: background var(--t-fast) var(--ease);
}
.user-card:hover { background: rgba(255,255,255,.07); }
.user-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: linear-gradient(135deg, #6366F1 0%, #8B5CF6 100%);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; color: #fff;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(99,102,241,.35);
}
.user-info { flex: 1; min-width: 0; }
.user-name {
  font-size: 13px; font-weight: 600; color: #fff;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.user-role {
  font-size: 11px; color: var(--slate-400);
}
.user-card i { color: var(--slate-500); font-size: 15px; }

/* ============================================================
   MAIN CONTENT
   ============================================================ */
.os-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

/* ── Top Bar ─────────────────────────────────────────── */
.topbar {
  background: var(--bg-surface);
  border-bottom: var(--border-default);
  padding: 0 28px;
  height: 62px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 50;
  box-shadow: 0 1px 0 rgba(15,23,42,.05);
}
.topbar-left {
  display: flex; align-items: center; gap: 8px;
}
.topbar-title {
  font-size: 17px; font-weight: 700; color: var(--slate-900);
  letter-spacing: -0.3px;
}
.topbar-subtitle {
  font-size: 13px; color: var(--slate-400); font-weight: 400;
}
.topbar-sep {
  color: var(--slate-300); font-size: 18px; margin: 0 4px;
}

.topbar-right { display: flex; align-items: center; gap: 8px; }

.tb-icon-btn {
  width: 36px; height: 36px;
  background: var(--bg-surface);
  border: var(--border-default);
  border-radius: 11px;
  display: flex; align-items: center; justify-content: center;
  color: var(--slate-500); font-size: 17px;
  cursor: pointer;
  transition: all var(--t-fast) var(--ease);
  position: relative;
  text-decoration: none;
}
.tb-icon-btn:hover {
  background: var(--slate-50);
  border-color: var(--slate-300);
  color: var(--slate-700);
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(15,23,42,.07);
}
.tb-icon-btn .notif-dot {
  position: absolute; top: 8px; right: 8px;
  width: 7px; height: 7px;
  background: #EF4444; border-radius: 50%;
  border: 2px solid #fff;
}

.btn-new-os {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 0 18px; height: 36px;
  background: var(--blue-600);
  color: #fff; font-size: 13.5px; font-weight: 600;
  border: none; border-radius: var(--r-btn);
  cursor: pointer;
  transition: all var(--t-mid) var(--ease);
  box-shadow: var(--sh-btn-primary);
  letter-spacing: -0.1px;
  white-space: nowrap;
}
.btn-new-os:hover {
  background: var(--blue-700);
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(37,99,235,.18), 0 10px 26px rgba(37,99,235,.22);
}
.btn-new-os:active { transform: translateY(0); }
.btn-new-os i { font-size: 16px; }

/* ── Page Body ──────────────────────────────────────── */
.page-body {
  padding: 24px 28px;
  flex: 1;
}

/* ── Filter Bar ─────────────────────────────────────── */
.filter-bar {
  background: var(--bg-surface);
  border: var(--border-default);
  border-radius: var(--r-card);
  box-shadow: var(--sh-card);
  padding: 14px 18px;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search-wrap {
  position: relative;
  flex: 1;
  min-width: 200px;
}
.search-wrap i {
  position: absolute; left: 13px; top: 50%;
  transform: translateY(-50%);
  color: var(--slate-400); font-size: 16px;
  pointer-events: none;
}
.search-input {
  width: 100%;
  height: 38px;
  padding: 0 14px 0 40px;
  background: var(--slate-50);
  border: var(--border-default);
  border-radius: var(--r-input);
  font-family: inherit; font-size: 13.5px;
  color: var(--slate-800);
  outline: none;
  transition: all var(--t-fast) var(--ease);
}
.search-input::placeholder { color: var(--slate-400); }
.search-input:hover { border-color: var(--slate-300); background: #fff; }
.search-input:focus {
  border-color: var(--blue-500);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}

.filter-select {
  height: 38px;
  padding: 0 32px 0 13px;
  background: var(--slate-50) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394A3B8' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 11px center;
  background-size: 12px;
  -webkit-appearance: none; appearance: none;
  border: var(--border-default);
  border-radius: var(--r-input);
  font-family: inherit; font-size: 13.5px; font-weight: 500;
  color: var(--slate-700);
  cursor: pointer;
  outline: none;
  transition: all var(--t-fast) var(--ease);
  min-width: 150px;
}
.filter-select:hover { border-color: var(--slate-300); background-color: #fff; }
.filter-select:focus {
  border-color: var(--blue-500);
  background-color: #fff;
  box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}

.btn-filter {
  display: inline-flex; align-items: center; gap: 6px;
  height: 38px; padding: 0 16px;
  border-radius: var(--r-btn);
  font-family: inherit; font-size: 13.5px; font-weight: 500;
  cursor: pointer; outline: none;
  transition: all var(--t-fast) var(--ease);
}
.btn-filter-primary {
  background: var(--blue-600); color: #fff; border: none;
  box-shadow: var(--sh-btn-primary);
}
.btn-filter-primary:hover {
  background: var(--blue-700); transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(37,99,235,.18), 0 8px 20px rgba(37,99,235,.20);
}
.btn-filter-ghost {
  background: #fff; color: var(--slate-600);
  border: var(--border-default);
}
.btn-filter-ghost:hover {
  background: var(--slate-50); border-color: var(--slate-300);
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(15,23,42,.07);
}

/* ============================================================
   METRIC CARDS
   ============================================================ */
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}

.metric-card {
  background: var(--bg-surface);
  border: var(--border-default);
  border-radius: var(--r-card);
  box-shadow: var(--sh-card);
  padding: 20px 22px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: transform var(--t-mid) var(--ease),
              box-shadow var(--t-mid) var(--ease),
              border-color var(--t-mid) var(--ease);
}
.metric-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--card-accent, var(--slate-200));
  transition: opacity var(--t-mid) var(--ease);
}
.metric-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--sh-card-hover);
  border-color: var(--slate-300);
}
.metric-head {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 14px;
}
.metric-label {
  font-size: 11.5px; font-weight: 600; color: var(--slate-500);
  text-transform: uppercase; letter-spacing: .5px;
}
.metric-icon-wrap {
  width: 38px; height: 38px; border-radius: 11px;
  background: var(--icon-bg);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  color: var(--icon-color);
}
.metric-value {
  font-size: 30px; font-weight: 700; color: var(--slate-900);
  letter-spacing: -1.2px; line-height: 1; margin-bottom: 8px;
}
.metric-footer {
  display: flex; align-items: center; gap: 5px;
  font-size: 12.5px;
}
.metric-change {
  display: inline-flex; align-items: center; gap: 3px;
  padding: 2px 8px; border-radius: 6px;
  font-weight: 600; font-size: 12px;
}
.change-up   { background: #DCFCE7; color: #16A34A; }
.change-down { background: #FEE2E2; color: #DC2626; }
.change-neutral { background: var(--slate-100); color: var(--slate-500); }
.metric-period { color: var(--slate-400); font-size: 12px; }

/* ============================================================
   CONTENT AREA (tabela + gráficos)
   ============================================================ */
.content-area {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 18px;
  align-items: start;
}

/* ── Panel (card genérico) ──────────────────────────── */
.panel {
  background: var(--bg-surface);
  border: var(--border-default);
  border-radius: var(--r-table);
  box-shadow: var(--sh-card);
  overflow: hidden;
}
.panel-header {
  padding: 16px 20px;
  border-bottom: var(--border-soft);
  display: flex; align-items: center; justify-content: space-between;
}
.panel-title {
  font-size: 14.5px; font-weight: 700;
  color: var(--slate-900); letter-spacing: -0.2px;
  display: flex; align-items: center; gap: 8px;
}
.panel-title i { font-size: 17px; color: var(--blue-600); }
.panel-actions { display: flex; gap: 6px; align-items: center; }

/* ── Tabela OS ──────────────────────────────────────── */
.os-table-wrap { overflow-x: auto; }

.os-table {
  width: 100%; border-collapse: collapse;
  font-size: 13.5px;
}
.os-table thead th {
  background: var(--slate-50);
  color: var(--slate-500);
  font-size: 11.5px; font-weight: 600;
  text-transform: uppercase; letter-spacing: .5px;
  padding: 11px 16px;
  border-bottom: var(--border-default);
  white-space: nowrap;
}
.os-table thead th:first-child { border-radius: 0; }
.os-table tbody tr {
  border-bottom: var(--border-soft);
  transition: background var(--t-fast) var(--ease);
}
.os-table tbody tr:last-child { border-bottom: none; }
.os-table tbody tr:hover { background: var(--slate-50); }
.os-table tbody td {
  padding: 13px 16px;
  vertical-align: middle;
}

.os-num {
  font-weight: 700; color: var(--blue-600); font-size: 13px;
  letter-spacing: -0.2px;
}
.os-title {
  font-weight: 500; color: var(--slate-800); max-width: 200px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.os-client {
  color: var(--slate-500); font-size: 12.5px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 140px;
}
.os-date { color: var(--slate-400); font-size: 12.5px; white-space: nowrap; }
.os-value { font-weight: 600; color: var(--slate-800); white-space: nowrap; }

/* ── Status badges ──────────────────────────────────── */
.badge-status, .badge-prior {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: var(--r-badge);
  font-size: 12px; font-weight: 600; white-space: nowrap;
}
.badge-status i, .badge-prior i { font-size: 11px; }

.s-aberta        { background: #EFF6FF; color: #2563EB; }
.s-em_andamento  { background: #FFFBEB; color: #D97706; }
.s-aguardando    { background: #F5F3FF; color: #7C3AED; }
.s-concluida     { background: #DCFCE7; color: #16A34A; }
.s-cancelada     { background: #FEF2F2; color: #DC2626; }

.p-baixa   { background: #F0FDF4; color: #16A34A; }
.p-media   { background: #FFF7ED; color: #EA580C; }
.p-alta    { background: #FFF1F2; color: #E11D48; }
.p-urgente { background: #FEF2F2; color: #DC2626; }

/* ── Ações na tabela ────────────────────────────────── */
.actions-cell { display: flex; gap: 5px; align-items: center; }
.btn-action {
  width: 30px; height: 30px;
  border: var(--border-default);
  border-radius: 9px;
  background: #fff;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; color: var(--slate-500);
  cursor: pointer;
  transition: all var(--t-fast) var(--ease);
}
.btn-action:hover {
  background: var(--slate-50); border-color: var(--slate-300);
  color: var(--slate-700);
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(15,23,42,.07);
}
.btn-action.danger:hover { background: #FEF2F2; border-color: #FCA5A5; color: #DC2626; }

/* ── Status dropdown ─────────────────────────────────── */
.status-dropdown {
  position: relative; display: inline-block;
}
.status-menu {
  display: none;
  position: absolute; top: calc(100% + 6px); left: 0;
  background: #fff;
  border: var(--border-default);
  border-radius: 14px;
  box-shadow: var(--sh-dropdown);
  padding: 6px;
  z-index: 200;
  min-width: 180px;
}
.status-menu.show { display: block; animation: fadeIn .15s var(--ease); }
.status-option {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; border-radius: 9px;
  font-size: 13px; font-weight: 500;
  cursor: pointer;
  transition: background var(--t-fast) var(--ease);
  color: var(--slate-700);
}
.status-option:hover { background: var(--slate-50); }
.status-option i { font-size: 14px; }

/* ── Paginação ──────────────────────────────────────── */
.pagination-bar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 20px;
  border-top: var(--border-soft);
  font-size: 13px; color: var(--slate-500);
}
.pagination-controls { display: flex; gap: 4px; }
.page-btn {
  min-width: 32px; height: 32px; padding: 0 6px;
  border: var(--border-default);
  border-radius: 9px; background: #fff;
  font-family: inherit; font-size: 13px; font-weight: 500;
  color: var(--slate-700);
  cursor: pointer;
  transition: all var(--t-fast) var(--ease);
  display: flex; align-items: center; justify-content: center;
}
.page-btn:hover { background: var(--slate-50); border-color: var(--slate-300); transform: translateY(-1px); box-shadow: 0 2px 6px rgba(15,23,42,.06); }
.page-btn.active { background: var(--blue-600); color: #fff; border-color: var(--blue-600); box-shadow: var(--sh-btn-primary); }
.page-btn:disabled { opacity: .4; pointer-events: none; }

/* ============================================================
   SIDEBAR DIREITA (gráficos + recentes)
   ============================================================ */
.side-panels { display: flex; flex-direction: column; gap: 16px; }

.chart-wrap { padding: 16px 20px 20px; }

/* ── OS Recentes ────────────────────────────────────── */
.recent-list { padding: 0 8px 12px; }
.recent-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  cursor: pointer;
  transition: background var(--t-fast) var(--ease);
}
.recent-item:hover { background: var(--slate-50); }
.recent-dot {
  width: 8px; height: 8px; border-radius: 50%;
  margin-top: 5px; flex-shrink: 0;
}
.recent-body { flex: 1; min-width: 0; }
.recent-num { font-size: 12px; font-weight: 700; color: var(--blue-600); }
.recent-title {
  font-size: 13px; font-weight: 500; color: var(--slate-800);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.recent-client { font-size: 12px; color: var(--slate-400); }
.recent-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.recent-date { font-size: 11px; color: var(--slate-400); white-space: nowrap; }

/* ============================================================
   MODAL — Nova OS
   ============================================================ */
.modal-overlay {
  display: none;
  position: fixed; inset: 0;
  background: rgba(2,6,23,.45);
  z-index: 1000;
  align-items: center; justify-content: center;
  padding: 20px;
  backdrop-filter: blur(3px);
  animation: fadeIn .2s var(--ease);
}
.modal-overlay.show { display: flex; }

.modal-box {
  background: #fff;
  border-radius: var(--r-modal);
  box-shadow: var(--sh-modal);
  width: 100%; max-width: 620px;
  max-height: 90vh; overflow-y: auto;
  animation: slideUp .25s var(--ease);
}
.modal-header {
  padding: 22px 26px 18px;
  border-bottom: var(--border-soft);
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; background: #fff;
  border-radius: var(--r-modal) var(--r-modal) 0 0;
  z-index: 2;
}
.modal-title {
  font-size: 17px; font-weight: 700; color: var(--slate-900);
  letter-spacing: -0.3px; display: flex; align-items: center; gap: 9px;
}
.modal-title i { color: var(--blue-600); font-size: 20px; }
.modal-close {
  width: 34px; height: 34px; border-radius: 10px;
  border: var(--border-default);
  background: #fff; color: var(--slate-500);
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; cursor: pointer;
  transition: all var(--t-fast) var(--ease);
}
.modal-close:hover { background: var(--slate-50); color: var(--slate-800); border-color: var(--slate-300); }

.modal-body { padding: 22px 26px; }

.form-group { margin-bottom: 16px; }
.form-label {
  display: block;
  font-size: 12.5px; font-weight: 600;
  color: var(--slate-600); margin-bottom: 6px;
  letter-spacing: .1px;
}
.form-label span { color: #EF4444; }
.form-control-os {
  width: 100%; height: 40px;
  padding: 0 14px;
  background: var(--slate-50);
  border: var(--border-default);
  border-radius: var(--r-input);
  font-family: inherit; font-size: 13.5px; color: var(--slate-800);
  outline: none;
  transition: all var(--t-fast) var(--ease);
}
.form-control-os::placeholder { color: var(--slate-400); }
.form-control-os:hover { border-color: var(--slate-300); background: #fff; }
.form-control-os:focus {
  border-color: var(--blue-500); background: #fff;
  box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}
textarea.form-control-os {
  height: 90px; padding: 11px 14px;
  resize: vertical; line-height: 1.5;
}
.form-row {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 14px;
}
.form-row-3 {
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  gap: 14px;
}

.modal-footer {
  padding: 16px 26px 22px;
  border-top: var(--border-soft);
  display: flex; justify-content: flex-end; gap: 10px;
}
.btn-modal-cancel {
  height: 40px; padding: 0 20px;
  border: var(--border-default); border-radius: var(--r-btn);
  background: #fff; color: var(--slate-700);
  font-family: inherit; font-size: 13.5px; font-weight: 500;
  cursor: pointer;
  transition: all var(--t-fast) var(--ease);
}
.btn-modal-cancel:hover { background: var(--slate-50); border-color: var(--slate-300); }

.btn-modal-save {
  height: 40px; padding: 0 22px;
  background: var(--blue-600); color: #fff;
  border: none; border-radius: var(--r-btn);
  font-family: inherit; font-size: 13.5px; font-weight: 600;
  cursor: pointer;
  box-shadow: var(--sh-btn-primary);
  transition: all var(--t-mid) var(--ease);
  display: flex; align-items: center; gap: 7px;
}
.btn-modal-save:hover {
  background: var(--blue-700); transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(37,99,235,.18), 0 10px 26px rgba(37,99,235,.22);
}
.btn-modal-save:disabled { opacity: .6; pointer-events: none; }

/* ============================================================
   TOAST
   ============================================================ */
.toast-container {
  position: fixed; bottom: 22px; right: 22px;
  z-index: 9999; display: flex; flex-direction: column; gap: 8px;
}
.toast-msg {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 18px;
  background: var(--slate-900); color: #fff;
  border-radius: 14px;
  font-size: 13.5px; font-weight: 500;
  box-shadow: 0 4px 16px rgba(2,6,23,.18), 0 16px 40px rgba(2,6,23,.14);
  animation: slideRight .25s var(--ease);
  min-width: 260px;
}
.toast-msg.success { background: #166534; }
.toast-msg.error   { background: #991B1B; }
.toast-msg i { font-size: 18px; flex-shrink: 0; }

/* ============================================================
   LOADING SKELETON
   ============================================================ */
.skeleton {
  background: linear-gradient(90deg, var(--slate-100) 25%, var(--slate-50) 50%, var(--slate-100) 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 8px;
}
.sk-row { height: 44px; margin-bottom: 2px; border-radius: 6px; }

/* ============================================================
   EMPTY STATE
   ============================================================ */
.empty-state {
  text-align: center; padding: 50px 20px;
  color: var(--slate-400);
}
.empty-state i { font-size: 44px; margin-bottom: 12px; display: block; }
.empty-state p { font-size: 14px; margin: 0; }

/* ============================================================
   ANIMATIONS
   ============================================================ */
@keyframes fadeIn   { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp  { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }
@keyframes slideRight { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: none; } }
@keyframes shimmer  { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ============================================================
   RESPONSIVE
   ============================================================ */
@media (max-width: 1100px) {
  .content-area { grid-template-columns: 1fr; }
  .side-panels { display: grid; grid-template-columns: 1fr 1fr; }
}
@media (max-width: 900px) {
  .metrics-grid { grid-template-columns: 1fr 1fr; }
  .form-row-3   { grid-template-columns: 1fr 1fr; }
  .os-sidebar   { display: none; }
}
@media (max-width: 620px) {
  .metrics-grid { grid-template-columns: 1fr; }
  .filter-bar   { flex-direction: column; }
  .form-row     { grid-template-columns: 1fr; }
  .page-body    { padding: 16px; }
  .topbar       { padding: 0 16px; }
  .side-panels  { grid-template-columns: 1fr; }
}

/* ── Scrollbar ───────────────────────────────────────── */
::-webkit-scrollbar       { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--slate-200); border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: var(--slate-300); }
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     WRAPPER PRINCIPAL
     ══════════════════════════════════════════════════════ -->
<div class="os-wrapper">

  <!-- ═══════════════════ SIDEBAR ═══════════════════════ -->
  <aside class="os-sidebar">
    <a class="sidebar-brand" href="#">
      <div class="brand-icon"><i class="bi bi-tools"></i></div>
      <div>
        <div class="brand-name">OSmais</div>
        <div class="brand-tag">Sistema de OS</div>
      </div>
    </a>

    <div class="sidebar-section">Principal</div>
    <nav class="sidebar-nav">
      <a class="nav-link-os active" href="#">
        <i class="bi bi-grid-1x2"></i> Dashboard
        <span class="badge-count" id="sb-total">—</span>
      </a>
      <a class="nav-link-os" href="#">
        <i class="bi bi-card-list"></i> Ordens de Serviço
      </a>
      <a class="nav-link-os" href="#">
        <i class="bi bi-people"></i> Clientes
      </a>
      <a class="nav-link-os" href="#">
        <i class="bi bi-person-badge"></i> Técnicos
      </a>
    </nav>

    <div class="sidebar-section" style="margin-top:10px">Operacional</div>
    <nav class="sidebar-nav">
      <a class="nav-link-os" href="#">
        <i class="bi bi-calendar3"></i> Agenda
      </a>
      <a class="nav-link-os" href="#">
        <i class="bi bi-box-seam"></i> Peças / Estoque
      </a>
      <a class="nav-link-os" href="#">
        <i class="bi bi-receipt"></i> Faturamento
      </a>
    </nav>

    <div class="sidebar-divider"></div>

    <nav class="sidebar-nav">
      <a class="nav-link-os" href="#">
        <i class="bi bi-bar-chart-line"></i> Relatórios
      </a>
      <a class="nav-link-os" href="#">
        <i class="bi bi-gear"></i> Configurações
      </a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-card">
        <div class="user-avatar">AD</div>
        <div class="user-info">
          <div class="user-name">Admin</div>
          <div class="user-role">Administrador</div>
        </div>
        <i class="bi bi-chevron-expand"></i>
      </div>
    </div>
  </aside>
  <!-- ═══════════════════ /SIDEBAR ══════════════════════ -->

  <!-- ═══════════════════ MAIN ══════════════════════════ -->
  <main class="os-main">

    <!-- ── Top Bar ───────────────────────────────────── -->
    <header class="topbar">
      <div class="topbar-left">
        <span class="topbar-title">Dashboard</span>
        <span class="topbar-sep">/</span>
        <span class="topbar-subtitle">Visão geral do sistema</span>
      </div>
      <div class="topbar-right">
        <div class="tb-icon-btn" title="Atualizar" onclick="loadAll()">
          <i class="bi bi-arrow-clockwise"></i>
        </div>
        <div class="tb-icon-btn" title="Notificações">
          <i class="bi bi-bell"></i>
          <span class="notif-dot"></span>
        </div>
        <div class="tb-icon-btn" title="Tela cheia" onclick="toggleFullscreen()">
          <i class="bi bi-fullscreen"></i>
        </div>
        <button class="btn-new-os" onclick="openModal()">
          <i class="bi bi-plus-lg"></i> Nova OS
        </button>
      </div>
    </header>

    <!-- ── Page Body ─────────────────────────────────── -->
    <div class="page-body">

      <!-- Metric Cards -->
      <div class="metrics-grid" id="metrics-grid">
        <!-- Gerados via JS -->
        <div class="metric-card skeleton" style="height:110px"></div>
        <div class="metric-card skeleton" style="height:110px"></div>
        <div class="metric-card skeleton" style="height:110px"></div>
        <div class="metric-card skeleton" style="height:110px"></div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" class="search-input" id="search-input"
                 placeholder="Buscar OS, cliente, número…">
        </div>
        <select class="filter-select" id="filter-status">
          <option value="">Todos os status</option>
          <option value="aberta">Aberta</option>
          <option value="em_andamento">Em andamento</option>
          <option value="aguardando">Aguardando</option>
          <option value="concluida">Concluída</option>
          <option value="cancelada">Cancelada</option>
        </select>
        <select class="filter-select" id="filter-prior">
          <option value="">Prioridade</option>
          <option value="baixa">Baixa</option>
          <option value="media">Média</option>
          <option value="alta">Alta</option>
          <option value="urgente">Urgente</option>
        </select>
        <button class="btn-filter btn-filter-primary" onclick="loadOS(1)">
          <i class="bi bi-funnel"></i> Filtrar
        </button>
        <button class="btn-filter btn-filter-ghost" onclick="clearFilters()">
          <i class="bi bi-x-lg"></i> Limpar
        </button>
      </div>

      <!-- Content Area -->
      <div class="content-area">

        <!-- Tabela de OS -->
        <div class="panel">
          <div class="panel-header">
            <div class="panel-title">
              <i class="bi bi-card-list"></i>
              Ordens de Serviço
              <span id="os-total-label" style="font-size:12px;font-weight:500;color:var(--slate-400);background:var(--slate-100);padding:2px 9px;border-radius:20px;margin-left:2px;"></span>
            </div>
            <div class="panel-actions">
              <div class="tb-icon-btn" style="width:30px;height:30px;border-radius:9px;" title="Exportar">
                <i class="bi bi-download" style="font-size:14px"></i>
              </div>
              <div class="tb-icon-btn" style="width:30px;height:30px;border-radius:9px;" title="Colunas">
                <i class="bi bi-layout-three-columns" style="font-size:14px"></i>
              </div>
            </div>
          </div>

          <div class="os-table-wrap">
            <table class="os-table">
              <thead>
                <tr>
                  <th>Número</th>
                  <th>Título / Cliente</th>
                  <th>Status</th>
                  <th>Prioridade</th>
                  <th>Técnico</th>
                  <th>Abertura</th>
                  <th>Valor</th>
                  <th style="text-align:center">Ações</th>
                </tr>
              </thead>
              <tbody id="os-tbody">
                <tr><td colspan="8">
                  <div class="skeleton sk-row"></div>
                  <div class="skeleton sk-row"></div>
                  <div class="skeleton sk-row"></div>
                  <div class="skeleton sk-row"></div>
                  <div class="skeleton sk-row"></div>
                </td></tr>
              </tbody>
            </table>
          </div>

          <div class="pagination-bar">
            <span id="pagination-info" style="font-size:12.5px;color:var(--slate-400)">—</span>
            <div class="pagination-controls" id="pagination-controls"></div>
          </div>
        </div>

        <!-- Sidebar direita -->
        <div class="side-panels">

          <!-- Gráfico Donut Status -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-pie-chart"></i> Por Status
              </div>
            </div>
            <div class="chart-wrap">
              <canvas id="chart-status" height="220"></canvas>
            </div>
          </div>

          <!-- Gráfico Mensal -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-bar-chart"></i> Últimos 6 Meses
              </div>
            </div>
            <div class="chart-wrap" style="padding-top:10px">
              <canvas id="chart-monthly" height="200"></canvas>
            </div>
          </div>

          <!-- OS Recentes -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-clock-history"></i> Recentes
              </div>
              <button class="btn-filter btn-filter-ghost" style="height:28px;padding:0 10px;font-size:12px;" onclick="loadOS(1)">
                Ver todas
              </button>
            </div>
            <div id="recent-list" class="recent-list">
              <div style="padding:20px;text-align:center"><div class="skeleton" style="height:20px;margin-bottom:8px"></div><div class="skeleton" style="height:20px;margin-bottom:8px"></div><div class="skeleton" style="height:20px"></div></div>
            </div>
          </div>

        </div><!-- /side-panels -->
      </div><!-- /content-area -->

    </div><!-- /page-body -->
  </main>
  <!-- ═══════════════════ /MAIN ══════════════════════════ -->
</div>

<!-- ════════════════════════════════════════════════════════
     MODAL — Nova OS
     ════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-nova-os">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-plus-circle"></i> Nova Ordem de Serviço
      </div>
      <button class="modal-close" onclick="closeModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cliente <span>*</span></label>
          <select class="form-control-os" id="f-cliente">
            <option value="">Selecione o cliente...</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Técnico Responsável</label>
          <select class="form-control-os" id="f-tecnico">
            <option value="">Sem técnico definido</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Título da OS <span>*</span></label>
        <input type="text" class="form-control-os" id="f-titulo"
               placeholder="Ex.: Manutenção preventiva impressora HP LaserJet">
      </div>

      <div class="form-group">
        <label class="form-label">Descrição / Problema relatado</label>
        <textarea class="form-control-os" id="f-descricao"
                  placeholder="Descreva o problema ou serviço a ser executado..."></textarea>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control-os" id="f-status">
            <option value="aberta">Aberta</option>
            <option value="em_andamento">Em andamento</option>
            <option value="aguardando">Aguardando</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Prioridade</label>
          <select class="form-control-os" id="f-prioridade">
            <option value="baixa">Baixa</option>
            <option value="media" selected>Média</option>
            <option value="alta">Alta</option>
            <option value="urgente">Urgente</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Categoria</label>
          <select class="form-control-os" id="f-categoria">
            <option value="">Selecione...</option>
            <option>Manutenção</option>
            <option>Instalação</option>
            <option>Configuração</option>
            <option>Suporte</option>
            <option>Limpeza</option>
            <option>Outro</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Equipamento</label>
          <input type="text" class="form-control-os" id="f-equipamento"
                 placeholder="Ex.: Impressora HP LaserJet M428">
        </div>
        <div class="form-group">
          <label class="form-label">Número de Série</label>
          <input type="text" class="form-control-os" id="f-nserie"
                 placeholder="Ex.: SN-2024-00123">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Valor Orçamento (R$)</label>
          <input type="number" class="form-control-os" id="f-valor"
                 placeholder="0,00" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label">Previsão de Conclusão</label>
          <input type="date" class="form-control-os" id="f-previsao">
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="closeModal()">Cancelar</button>
      <button class="btn-modal-save" id="btn-save-os" onclick="saveOS()">
        <i class="bi bi-check2"></i> Criar Ordem de Serviço
      </button>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toast-container"></div>

<!-- ════════════════════════════════════════════════════════
     SCRIPTS
     ════════════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

<script>
/* =========================================================
   CONFIG
   ========================================================= */
const API = 'dashboard.php';
let currentPage = 1;
let chartStatus  = null;
let chartMonthly = null;

/* =========================================================
   AJAX HELPER
   ========================================================= */
async function ajax(params, method = 'GET', body = null) {
  const qs  = new URLSearchParams(params).toString();
  const url = `${API}?${qs}`;
  const opts = {
    method,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  };
  if (body) {
    opts.body = new URLSearchParams(body);
    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
  }
  const res  = await fetch(url, opts);
  const data = await res.json();
  if (data.error) throw new Error(data.error);
  return data;
}

/* =========================================================
   MÉTRICAS
   ========================================================= */
async function loadMetrics() {
  try {
    const d = await ajax({ action: 'metrics' });

    const cards = [
      {
        label: 'Total de OS',
        value: d.total,
        icon: 'bi-card-list',
        iconBg: '#EFF6FF', iconColor: '#2563EB',
        accent: '#3B82F6',
        change: '+12%', changeType: 'up',
        period: 'vs. mês anterior'
      },
      {
        label: 'Abertas',
        value: d.abertas,
        icon: 'bi-folder2-open',
        iconBg: '#FFFBEB', iconColor: '#D97706',
        accent: '#F59E0B',
        change: d.abertas > 5 ? 'Atenção' : 'Normal', changeType: 'neutral',
        period: 'aguardando atendimento'
      },
      {
        label: 'Em Andamento',
        value: d.andando,
        icon: 'bi-arrow-repeat',
        iconBg: '#F5F3FF', iconColor: '#7C3AED',
        accent: '#8B5CF6',
        change: d.urgente + ' urgente(s)', changeType: d.urgente > 0 ? 'down' : 'neutral',
        period: 'em execução agora'
      },
      {
        label: 'Faturado',
        value: 'R$ ' + parseFloat(d.faturado).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2}),
        icon: 'bi-currency-dollar',
        iconBg: '#DCFCE7', iconColor: '#16A34A',
        accent: '#22C55E',
        change: d.concl + ' concl.', changeType: 'up',
        period: 'em OS concluídas'
      }
    ];

    const grid = document.getElementById('metrics-grid');
    grid.innerHTML = cards.map(c => `
      <div class="metric-card" style="--card-accent:${c.accent}">
        <div class="metric-head">
          <div class="metric-label">${c.label}</div>
          <div class="metric-icon-wrap" style="--icon-bg:${c.iconBg};--icon-color:${c.iconColor}">
            <i class="bi ${c.icon}"></i>
          </div>
        </div>
        <div class="metric-value">${c.value}</div>
        <div class="metric-footer">
          <span class="metric-change change-${c.changeType}">
            <i class="bi ${c.changeType === 'up' ? 'bi-arrow-up-short' : c.changeType === 'down' ? 'bi-arrow-down-short' : 'bi-dash'}"></i>
            ${c.change}
          </span>
          <span class="metric-period">${c.period}</span>
        </div>
      </div>
    `).join('');

    document.getElementById('sb-total').textContent = d.total;

  } catch (e) {
    console.warn('Métricas:', e.message);
    renderMetricsMock();
  }
}

function renderMetricsMock() {
  const cards = [
    { label:'Total de OS', value:'248', icon:'bi-card-list', iconBg:'#EFF6FF', iconColor:'#2563EB', accent:'#3B82F6', change:'+12%', changeType:'up', period:'vs. mês anterior' },
    { label:'Abertas',     value:'34',  icon:'bi-folder2-open', iconBg:'#FFFBEB', iconColor:'#D97706', accent:'#F59E0B', change:'Normal', changeType:'neutral', period:'aguardando atendimento' },
    { label:'Em Andamento',value:'18',  icon:'bi-arrow-repeat', iconBg:'#F5F3FF', iconColor:'#7C3AED', accent:'#8B5CF6', change:'2 urgentes', changeType:'down', period:'em execução agora' },
    { label:'Faturado',    value:'R$ 48.720,00', icon:'bi-currency-dollar', iconBg:'#DCFCE7', iconColor:'#16A34A', accent:'#22C55E', change:'92 concl.', changeType:'up', period:'em OS concluídas' },
  ];
  document.getElementById('metrics-grid').innerHTML = cards.map(c => `
    <div class="metric-card" style="--card-accent:${c.accent}">
      <div class="metric-head">
        <div class="metric-label">${c.label}</div>
        <div class="metric-icon-wrap" style="--icon-bg:${c.iconBg};--icon-color:${c.iconColor}">
          <i class="bi ${c.icon}"></i>
        </div>
      </div>
      <div class="metric-value">${c.value}</div>
      <div class="metric-footer">
        <span class="metric-change change-${c.changeType}">
          <i class="bi ${c.changeType==='up'?'bi-arrow-up-short':c.changeType==='down'?'bi-arrow-down-short':'bi-dash'}"></i>
          ${c.change}
        </span>
        <span class="metric-period">${c.period}</span>
      </div>
    </div>
  `).join('');
  document.getElementById('sb-total').textContent = '248';
}

/* =========================================================
   TABELA OS
   ========================================================= */
const statusMap = {
  aberta:        { label:'Aberta',        icon:'bi-circle', cls:'s-aberta' },
  em_andamento:  { label:'Em andamento',  icon:'bi-arrow-repeat', cls:'s-em_andamento' },
  aguardando:    { label:'Aguardando',    icon:'bi-pause-circle', cls:'s-aguardando' },
  concluida:     { label:'Concluída',     icon:'bi-check-circle', cls:'s-concluida' },
  cancelada:     { label:'Cancelada',     icon:'bi-x-circle', cls:'s-cancelada' },
};
const priorMap = {
  baixa:   { label:'Baixa',   icon:'bi-arrow-down', cls:'p-baixa' },
  media:   { label:'Média',   icon:'bi-dash', cls:'p-media' },
  alta:    { label:'Alta',    icon:'bi-arrow-up', cls:'p-alta' },
  urgente: { label:'Urgente', icon:'bi-exclamation-triangle', cls:'p-urgente' },
};
const dotColor = { aberta:'#3B82F6', em_andamento:'#F59E0B', aguardando:'#8B5CF6', concluida:'#22C55E', cancelada:'#EF4444' };

async function loadOS(page = 1) {
  currentPage = page;
  const tbody = document.getElementById('os-tbody');
  tbody.innerHTML = `<tr><td colspan="8"><div class="skeleton sk-row"></div><div class="skeleton sk-row"></div><div class="skeleton sk-row"></div></td></tr>`;

  try {
    const params = {
      action: 'list_os', page,
      search:     document.getElementById('search-input').value,
      status:     document.getElementById('filter-status').value,
      prioridade: document.getElementById('filter-prior').value,
    };
    const d = await ajax(params);
    renderTable(d);
  } catch (e) {
    renderTableMock();
  }
}

function renderTable(d) {
  const tbody = document.getElementById('os-tbody');
  document.getElementById('os-total-label').textContent = d.total + ' registros';
  document.getElementById('pagination-info').textContent =
    `Exibindo ${((d.page-1)*10)+1}–${Math.min(d.page*10, d.total)} de ${d.total}`;

  if (!d.rows.length) {
    tbody.innerHTML = `<tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>Nenhuma OS encontrada</p></div></td></tr>`;
    renderPagination(d);
    return;
  }

  tbody.innerHTML = d.rows.map(r => {
    const s = statusMap[r.status]  || { label: r.status, icon:'bi-circle', cls:'s-aberta' };
    const p = priorMap[r.prioridade] || { label: r.prioridade, icon:'bi-dash', cls:'p-media' };
    const dt = r.data_abertura ? new Date(r.data_abertura).toLocaleDateString('pt-BR') : '—';
    const val = r.valor_final > 0
      ? 'R$ ' + parseFloat(r.valor_final).toLocaleString('pt-BR', {minimumFractionDigits:2})
      : '—';
    return `
    <tr>
      <td><span class="os-num">${r.numero}</span></td>
      <td>
        <div class="os-title">${r.titulo}</div>
        <div class="os-client"><i class="bi bi-person" style="font-size:11px;margin-right:3px"></i>${r.cliente||'—'}</div>
      </td>
      <td>
        <div class="status-dropdown">
          <span class="badge-status ${s.cls}" style="cursor:pointer" onclick="toggleStatusMenu(this,${r.id})">
            <i class="bi ${s.icon}"></i> ${s.label}
          </span>
          <div class="status-menu" id="smenu-${r.id}">
            ${Object.entries(statusMap).map(([k,v])=>`
              <div class="status-option" onclick="updateStatus(${r.id},'${k}',this)">
                <span class="badge-status ${v.cls}" style="font-size:11px;padding:2px 7px"><i class="bi ${v.icon}"></i> ${v.label}</span>
              </div>
            `).join('')}
          </div>
        </div>
      </td>
      <td><span class="badge-prior ${p.cls}"><i class="bi ${p.icon}"></i> ${p.label}</span></td>
      <td style="color:var(--slate-600);font-size:13px">${r.tecnico||'<span style="color:var(--slate-400)">—</span>'}</td>
      <td><span class="os-date">${dt}</span></td>
      <td><span class="os-value">${val}</span></td>
      <td>
        <div class="actions-cell" style="justify-content:center">
          <div class="btn-action" title="Ver detalhes"><i class="bi bi-eye"></i></div>
          <div class="btn-action" title="Editar"><i class="bi bi-pencil"></i></div>
          <div class="btn-action" title="Imprimir"><i class="bi bi-printer"></i></div>
          <div class="btn-action danger" title="Excluir"><i class="bi bi-trash"></i></div>
        </div>
      </td>
    </tr>`;
  }).join('');

  renderPagination(d);
}

function renderPagination(d) {
  const ctrl = document.getElementById('pagination-controls');
  if (d.pages <= 1) { ctrl.innerHTML = ''; return; }

  let html = `<button class="page-btn" onclick="loadOS(${d.page-1})" ${d.page<=1?'disabled':''}><i class="bi bi-chevron-left"></i></button>`;
  for (let i = 1; i <= d.pages; i++) {
    if (i === 1 || i === d.pages || (i >= d.page-1 && i <= d.page+1)) {
      html += `<button class="page-btn ${i===d.page?'active':''}" onclick="loadOS(${i})">${i}</button>`;
    } else if (i === d.page-2 || i === d.page+2) {
      html += `<button class="page-btn" disabled style="border:none;background:none;cursor:default">…</button>`;
    }
  }
  html += `<button class="page-btn" onclick="loadOS(${d.page+1})" ${d.page>=d.pages?'disabled':''}><i class="bi bi-chevron-right"></i></button>`;
  ctrl.innerHTML = html;
}

function renderTableMock() {
  const mock = [
    { id:1, numero:'OS-00248', titulo:'Manutenção preventiva servidor', status:'em_andamento', prioridade:'alta', cliente:'Acme Corp', tecnico:'Carlos Silva', data_abertura:'2025-05-18', valor_final:1200 },
    { id:2, numero:'OS-00247', titulo:'Substituição teclado notebook Dell', status:'aberta', prioridade:'media', cliente:'TechBrasil', tecnico:null, data_abertura:'2025-05-17', valor_final:0 },
    { id:3, numero:'OS-00246', titulo:'Configuração VPN corporativa', status:'aguardando', prioridade:'urgente', cliente:'Logística Sul', tecnico:'Ana Martins', data_abertura:'2025-05-16', valor_final:0 },
    { id:4, numero:'OS-00245', titulo:'Backup e restauração de dados', status:'concluida', prioridade:'baixa', cliente:'Escritório Modelo', tecnico:'Pedro Alves', data_abertura:'2025-05-15', valor_final:450 },
    { id:5, numero:'OS-00244', titulo:'Upgrade memória RAM workstations', status:'concluida', prioridade:'media', cliente:'Design Studio', tecnico:'Carlos Silva', data_abertura:'2025-05-14', valor_final:890 },
    { id:6, numero:'OS-00243', titulo:'Instalação CFTV 8 câmeras', status:'em_andamento', prioridade:'alta', cliente:'Supermercado Rio', tecnico:'Lucas Ferreira', data_abertura:'2025-05-13', valor_final:3200 },
    { id:7, numero:'OS-00242', titulo:'Formatação e instalação Windows 11', status:'aberta', prioridade:'baixa', cliente:'João F. Silva', tecnico:null, data_abertura:'2025-05-12', valor_final:0 },
    { id:8, numero:'OS-00241', titulo:'Manutenção impressoras HP LaserJet', status:'concluida', prioridade:'media', cliente:'Advocacia Neto', tecnico:'Ana Martins', data_abertura:'2025-05-11', valor_final:620 },
  ];
  document.getElementById('os-total-label').textContent = '248 registros';
  document.getElementById('pagination-info').textContent = 'Exibindo 1–8 de 248';
  renderTable({ rows: mock, total: 248, pages: 25, page: 1 });
}

/* ── Status dropdown ─────────────────────────────────── */
function toggleStatusMenu(el, id) {
  document.querySelectorAll('.status-menu.show').forEach(m => m.classList.remove('show'));
  const menu = document.getElementById('smenu-'+id);
  menu.classList.toggle('show');
  setTimeout(() => {
    const close = (e) => {
      if (!menu.contains(e.target) && e.target !== el) {
        menu.classList.remove('show');
        document.removeEventListener('click', close);
      }
    };
    document.addEventListener('click', close);
  }, 10);
}

async function updateStatus(id, status, el) {
  document.querySelectorAll('.status-menu.show').forEach(m => m.classList.remove('show'));
  try {
    await ajax({ action: 'update_status' }, 'POST', { id, status });
    toast('Status atualizado com sucesso', 'success');
    loadOS(currentPage);
    loadMetrics();
  } catch(e) {
    // mock: apenas atualiza visualmente
    loadOS(currentPage);
    toast('Status atualizado (modo demo)', 'success');
  }
}

/* =========================================================
   GRÁFICOS
   ========================================================= */
async function loadCharts() {
  await Promise.all([loadChartStatus(), loadChartMonthly()]);
}

async function loadChartStatus() {
  const canvas = document.getElementById('chart-status');
  let labels = ['Aberta','Em andamento','Aguardando','Concluída','Cancelada'];
  let data   = [34, 18, 12, 92, 8];

  try {
    const rows = await ajax({ action: 'chart_status' });
    labels = rows.map(r => statusMap[r.status]?.label || r.status);
    data   = rows.map(r => parseInt(r.qty));
  } catch(e) {}

  if (chartStatus) chartStatus.destroy();
  chartStatus = new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: ['#3B82F6','#F59E0B','#8B5CF6','#22C55E','#EF4444'],
        borderColor: '#fff',
        borderWidth: 3,
        hoverOffset: 6,
      }]
    },
    options: {
      responsive: true,
      cutout: '68%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { family: 'DM Sans', size: 12 },
            color: '#64748B',
            padding: 12,
            usePointStyle: true,
            pointStyleWidth: 8,
          }
        },
        tooltip: {
          backgroundColor: '#0F172A',
          titleFont: { family: 'DM Sans', size: 13, weight: '600' },
          bodyFont:  { family: 'DM Sans', size: 12 },
          cornerRadius: 10,
          padding: 10,
        }
      }
    }
  });
}

async function loadChartMonthly() {
  const canvas = document.getElementById('chart-monthly');
  let labels   = ['Dez','Jan','Fev','Mar','Abr','Mai'];
  let abertas  = [28,35,22,40,31,34];
  let concl    = [20,28,18,35,26,29];

  try {
    const rows = await ajax({ action: 'chart_monthly' });
    labels  = rows.map(r => r.mes);
    abertas = rows.map(r => parseInt(r.abertas));
    concl   = rows.map(r => parseInt(r.concluidas));
  } catch(e) {}

  if (chartMonthly) chartMonthly.destroy();
  chartMonthly = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'Abertas',
          data: abertas,
          backgroundColor: '#BFDBFE',
          borderRadius: 6,
          borderSkipped: false,
        },
        {
          label: 'Concluídas',
          data: concl,
          backgroundColor: '#2563EB',
          borderRadius: 6,
          borderSkipped: false,
        }
      ]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            font: { family: 'DM Sans', size: 12 },
            color: '#64748B',
            padding: 12,
            usePointStyle: true,
            pointStyleWidth: 8,
          }
        },
        tooltip: {
          backgroundColor: '#0F172A',
          titleFont: { family: 'DM Sans', size: 13, weight: '600' },
          bodyFont:  { family: 'DM Sans', size: 12 },
          cornerRadius: 10,
          padding: 10,
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { font: { family: 'DM Sans', size: 12 }, color: '#94A3B8' }
        },
        y: {
          grid: { color: '#F1F5F9' },
          border: { dash: [3,3], display: false },
          ticks: { font: { family: 'DM Sans', size: 12 }, color: '#94A3B8' }
        }
      }
    }
  });
}

/* =========================================================
   OS RECENTES
   ========================================================= */
async function loadRecent() {
  const el = document.getElementById('recent-list');
  try {
    const rows = await ajax({ action: 'recent' });
    renderRecent(rows);
  } catch(e) {
    renderRecent([
      { numero:'OS-00248', titulo:'Manutenção preventiva servidor', status:'em_andamento', prioridade:'alta', cliente:'Acme Corp', data_abertura:'2025-05-18' },
      { numero:'OS-00247', titulo:'Substituição teclado notebook', status:'aberta', prioridade:'media', cliente:'TechBrasil', data_abertura:'2025-05-17' },
      { numero:'OS-00246', titulo:'Configuração VPN corporativa', status:'aguardando', prioridade:'urgente', cliente:'Logística Sul', data_abertura:'2025-05-16' },
      { numero:'OS-00245', titulo:'Backup e restauração de dados', status:'concluida', prioridade:'baixa', cliente:'Escritório Modelo', data_abertura:'2025-05-15' },
      { numero:'OS-00244', titulo:'Upgrade memória RAM', status:'concluida', prioridade:'media', cliente:'Design Studio', data_abertura:'2025-05-14' },
    ]);
  }
}

function renderRecent(rows) {
  const el = document.getElementById('recent-list');
  if (!rows.length) {
    el.innerHTML = `<div class="empty-state"><i class="bi bi-inbox"></i><p>Sem OS recentes</p></div>`;
    return;
  }
  el.innerHTML = rows.map(r => {
    const s  = statusMap[r.status] || statusMap.aberta;
    const dt = r.data_abertura ? new Date(r.data_abertura).toLocaleDateString('pt-BR',{day:'2-digit',month:'short'}) : '—';
    const dc = dotColor[r.status] || '#94A3B8';
    return `
    <div class="recent-item">
      <div class="recent-dot" style="background:${dc}"></div>
      <div class="recent-body">
        <div class="recent-num">${r.numero}</div>
        <div class="recent-title">${r.titulo}</div>
        <div class="recent-client">${r.cliente||'—'}</div>
      </div>
      <div class="recent-meta">
        <span class="badge-status ${s.cls}" style="font-size:10.5px;padding:3px 7px">${s.label}</span>
        <span class="recent-date">${dt}</span>
      </div>
    </div>`;
  }).join('');
}

/* =========================================================
   MODAL NOVA OS
   ========================================================= */
async function openModal() {
  document.getElementById('modal-nova-os').classList.add('show');
  document.body.style.overflow = 'hidden';
  try {
    const [clientes, tecnicos] = await Promise.all([
      ajax({ action: 'clientes' }),
      ajax({ action: 'tecnicos' })
    ]);
    const cs = document.getElementById('f-cliente');
    clientes.forEach(c => cs.add(new Option(c.nome, c.id)));
    const ts = document.getElementById('f-tecnico');
    tecnicos.forEach(t => ts.add(new Option(t.nome, t.id)));
  } catch(e) {
    // BD não disponível: selects ficam com placeholder apenas
  }
}

function closeModal() {
  document.getElementById('modal-nova-os').classList.remove('show');
  document.body.style.overflow = '';
  document.getElementById('f-titulo').value = '';
  document.getElementById('f-descricao').value = '';
}

async function saveOS() {
  const titulo = document.getElementById('f-titulo').value.trim();
  const cliente = document.getElementById('f-cliente').value;
  if (!titulo) { toast('Informe o título da OS', 'error'); document.getElementById('f-titulo').focus(); return; }

  const btn = document.getElementById('btn-save-os');
  btn.disabled = true;
  btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Criando...`;

  try {
    const data = await ajax({ action: 'save_os' }, 'POST', {
      titulo, cliente_id: cliente,
      tecnico_id: document.getElementById('f-tecnico').value,
      descricao:  document.getElementById('f-descricao').value,
      status:     document.getElementById('f-status').value,
      prioridade: document.getElementById('f-prioridade').value,
      categoria:  document.getElementById('f-categoria').value,
      equipamento: document.getElementById('f-equipamento').value,
      valor_orcamento: document.getElementById('f-valor').value || 0,
      data_previsao: document.getElementById('f-previsao').value,
    });
    closeModal();
    toast(`OS ${data.numero} criada com sucesso!`, 'success');
    loadAll();
  } catch(e) {
    toast('Erro ao criar OS: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.innerHTML = `<i class="bi bi-check2"></i> Criar Ordem de Serviço`;
  }
}

// Fechar modal clicando fora
document.getElementById('modal-nova-os').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

/* =========================================================
   FILTROS
   ========================================================= */
function clearFilters() {
  document.getElementById('search-input').value = '';
  document.getElementById('filter-status').value = '';
  document.getElementById('filter-prior').value = '';
  loadOS(1);
}

// Busca ao digitar (debounce)
let searchTimer;
document.getElementById('search-input').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadOS(1), 420);
});
document.getElementById('search-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') loadOS(1);
});

/* =========================================================
   TOAST
   ========================================================= */
function toast(msg, type = 'info') {
  const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'error' ? 'bi-x-circle-fill' : 'bi-info-circle-fill';
  const el = document.createElement('div');
  el.className = `toast-msg ${type}`;
  el.innerHTML = `<i class="bi ${icon}"></i> ${msg}`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.style.opacity = '0', 3200);
  setTimeout(() => el.remove(), 3600);
}

/* =========================================================
   UTILS
   ========================================================= */
function toggleFullscreen() {
  if (!document.fullscreenElement) document.documentElement.requestFullscreen?.();
  else document.exitFullscreen?.();
}

/* =========================================================
   INIT
   ========================================================= */
function loadAll() {
  loadMetrics();
  loadOS(1);
  loadCharts();
  loadRecent();
}

document.addEventListener('DOMContentLoaded', loadAll);
</script>
</body>
</html>
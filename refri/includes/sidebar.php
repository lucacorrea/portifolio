<!-- sidebar.php -->
<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo-area">
      <img src="/refri/assets/img/logo.png" alt="K.Yamaguchi" style="height: 36px;" />
      <span class="logo-text">K.Yamaguchi</span>
    </div>
  </div>
  <nav class="sidebar-nav">
    <a href="/refri/dashboard.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
      <i class="icon-dashboard"></i> Dashboard
    </a>
    <a href="/refri/tabelas.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'tabelas.php' ? 'active' : '' ?>">
      <i class="icon-table"></i> Ordens de Serviço
    </a>
    <a href="/refri/relatorios.php" class="nav-item <?= basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : '' ?>">
      <i class="icon-chart"></i> Relatórios
    </a>
    <!-- outros links -->
  </nav>
  <div class="sidebar-footer">
    <small>&copy; <span id="current-year"></span></small>
  </div>
</aside>
<div class="menu-overlay hidden" id="menu-overlay"></div>

<style>
/* Estilos inline apenas da sidebar (para encapsular, ideal mover para base.css) */
.sidebar {
  width: 240px;
  background: #FFFFFF;
  border-right: 1px solid #DDE3EA;
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  display: flex;
  flex-direction: column;
  z-index: 40;
  transition: transform 0.2s ease;
}

.sidebar-header {
  padding: 24px 20px 20px;
  border-bottom: 1px solid #E5E7EB;
}

.logo-area {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-text {
  font-weight: 700;
  font-size: 1.1rem;
  color: #111827;
}

.sidebar-nav {
  flex: 1;
  padding: 12px 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 500;
  color: #374151;
  transition: background-color 0.15s ease, color 0.15s ease;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  background: #F3F6F8;
}

.nav-item.active {
  background: #EAF5F3;
  color: #0F766E;
  border-left-color: #0F766E;
  font-weight: 600;
}

.icon-dashboard::before { content: "📊"; font-style: normal; margin-right: 4px; }
.icon-table::before { content: "📋"; font-style: normal; margin-right: 4px; }
.icon-chart::before { content: "📈"; font-style: normal; margin-right: 4px; }

.sidebar-footer {
  padding: 16px 20px;
  font-size: 0.8rem;
  color: #6B7280;
  border-top: 1px solid #E5E7EB;
}

/* Overlay mobile */
.menu-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.3);
  z-index: 35;
}
.hidden { display: none; }

@media (min-width: 769px) {
  .menu-overlay { display: none !important; }
}
</style>
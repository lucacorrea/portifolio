<?php $isDashboard = ($activePage ?? '') === 'dashboard'; ?>

<aside class="os-sidebar">
    <a class="sidebar-brand" href="dashboard.php">
      <div class="brand-icon"><i class="bi bi-tools"></i></div>
      <div>
        <div class="brand-name">OSmais</div>
        <div class="brand-tag">Sistema de OS</div>
      </div>
    </a>

    <div class="sidebar-section">Principal</div>
    <nav class="sidebar-nav">
      <a class="nav-link-os<?= $isDashboard ? ' active' : '' ?>" href="dashboard.php"<?= $isDashboard ? ' aria-current="page"' : '' ?>>
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

    <div class="sidebar-section sidebar-section-spaced">Operacional</div>
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

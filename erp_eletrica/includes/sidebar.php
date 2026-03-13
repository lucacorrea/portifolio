<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex align-items-center justify-content-center">
        <img src="logo_sistema_erp_eletrica.jpeg" alt="Centro do Eletricista" style="max-width: 100%; height: auto; max-height: 80px;">
    </div>
    
    <nav class="nav flex-column sidebar-menu">

        <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['vendedor', 'gerente'])): ?>
        <a href="index.php" class="nav-link <?= ($current_page == 'index.php' || $current_page == '') ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i> <span>Painel Comercial</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'gerente'])): ?>
        <a href="caixa.php" class="nav-link <?= $current_page == 'caixa.php' ? 'active' : '' ?>">
            <i class="fas fa-vault"></i> <span>Controle de Caixa</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'gerente'])): ?>
        <a href="vendas.php" class="nav-link <?= $current_page == 'vendas.php' ? 'active' : '' ?>">
            <i class="fas fa-cash-register"></i> <span>Balcão / Vendas</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'vendedor'])): ?>
        <a href="pre_vendas.php" class="nav-link <?= $current_page == 'pre_vendas.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i> <span>Pré-Venda / Orç.</span>
        </a>
        <?php endif; ?>
        
        <div class="px-3 mt-4 mb-2 text-uppercase text-muted opacity-50 fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Gestão de Materiais</div>
        
        <a href="estoque.php" class="nav-link <?= $current_page == 'estoque.php' ? 'active' : '' ?>">
            <i class="fas fa-boxes-stacked"></i> <span>Estoque / Materiais</span>
        </a>
        
        <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['vendedor', 'gerente'])): ?>
        <div class="px-3 mt-4 mb-2 text-uppercase text-muted opacity-50 fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Relacionamento</div>
        
        <a href="clientes.php" class="nav-link <?= $current_page == 'clientes.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> <span>Base de Clientes</span>
        </a>
        <a href="fornecedores.php" class="nav-link <?= $current_page == 'fornecedores.php' ? 'active' : '' ?>">
            <i class="fas fa-truck-fast"></i> <span>Fornecedores</span>
        </a>
        <a href="importar_automatico.php" class="nav-link <?= $current_page == 'importar_automatico.php' ? 'active' : '' ?>">
            <i class="fas fa-cloud-download-alt"></i> <span>Importação Automática</span>
        </a>
        
        <div class="px-3 mt-4 mb-2 text-uppercase text-muted opacity-50 fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Financeiro</div>
        
        <a href="financeiro.php" class="nav-link <?= $current_page == 'financeiro.php' ? 'active' : '' ?>">
            <i class="fas fa-wallet"></i> <span>Fluxo de Caixa</span>
        </a>

        <!-- Financial Reports Submenu -->
        <a class="nav-link collapsed" href="#" data-bs-toggle="collapse" data-bs-target="#reportsSubmenu">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Relatórios BI</span>
            <i class="fas fa-chevron-down ms-auto small opacity-50"></i>
        </a>
        <div class="collapse ps-3" id="reportsSubmenu">
            <nav class="nav flex-column">
                <a class="nav-link extra-small py-1" href="financeiro.php?action=dre">
                    <i class="fas fa-circle-notch me-1 extra-small"></i> DRE Mensal
                </a>
                <a class="nav-link extra-small py-1" href="financeiro.php?action=abcCurve">
                    <i class="fas fa-circle-notch me-1 extra-small"></i> Curva ABC Prod.
                </a>
                <a class="nav-link extra-small py-1" href="financeiro.php?action=delinquency">
                    <i class="fas fa-circle-notch me-1 extra-small"></i> Inadimplência
                </a>
            </nav>
        </div>
        <?php endif; ?>

        <?php if (in_array($_SESSION['usuario_nivel'] ?? '', ['admin'])): ?>
        <div class="px-3 mt-4 mb-2 text-uppercase text-muted opacity-50 fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Gestão Estratégica</div>
        
        <a href="custos.php" class="nav-link <?= $current_page == 'custos.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i> <span>Centro de Custos</span>
        </a>
        <a href="inteligencia.php" class="nav-link <?= $current_page == 'inteligencia.php' ? 'active' : '' ?>">
            <i class="fas fa-brain"></i> <span>Inteligência BI</span>
        </a>
        <?php endif; ?>

        <?php if (in_array($_SESSION['usuario_nivel'] ?? '', ['admin'])): ?>
        <div class="px-3 mt-4 mb-2 text-uppercase text-muted opacity-50 fw-bold" style="font-size: 0.65rem; letter-spacing: 1px;">Administração</div>
        
        <a href="filiais.php" class="nav-link <?= $current_page == 'filiais.php' ? 'active' : '' ?>">
            <i class="fas fa-landmark"></i> <span>Filiais / Lojas</span>
        </a>
        <a href="usuarios.php" class="nav-link <?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i> <span>Gestão de Equipe</span>
        </a>
        <a href="fiscal.php?action=settings" class="nav-link <?= ($current_page == 'fiscal.php' && ($_GET['action'] ?? '') == 'settings') ? 'active' : '' ?>">
            <i class="fas fa-server"></i> <span>Conectividade SEFAZ</span>
        </a>
        <a href="importar_automatico.php?action=config" class="nav-link <?= ($current_page == 'importar_automatico.php' && ($_GET['action'] ?? '') == 'config') ? 'active' : '' ?>">
            <i class="fas fa-shield-halved"></i> <span>Certificado Global A1</span>
        </a>
        <?php endif; ?>
        
        <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['vendedor', 'gerente'])): ?>
        <a href="configuracoes.php" class="nav-link mt-auto border-top border-secondary border-opacity-10 <?= $current_page == 'configuracoes.php' ? 'active' : '' ?>">
            <i class="fas fa-sliders-h"></i> <span>Ajustes Gerais</span>
        </a>
        <?php endif; ?>
        <a href="logout.php" class="nav-link text-danger">
            <i class="fas fa-power-off"></i> <span>Sair do Sistema</span>
        </a>
    </nav>
</aside>
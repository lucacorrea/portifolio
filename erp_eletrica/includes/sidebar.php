<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header d-flex align-items-center justify-content-center p-3">
        <img src="logo_sistema_erp_eletrica.png?v=<?= time() ?>" alt="Centro do Eletricista" style="max-width: 90%; height: auto; max-height: 55px; filter: drop-shadow(0 0 5px rgba(255,193,7,0.2));">
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
        <a href="vendidos.php" class="nav-link <?= $current_page == 'vendidos.php' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> <span>Histórico de Vendas</span>
        </a>
        <a href="fiado.php" class="nav-link <?= $current_page == 'fiado.php' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-dollar"></i> <span>Gestão de Fiados</span>
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
        <a href="estoque_baixo.php" class="nav-link <?= $current_page == 'estoque_baixo.php' ? 'active' : '' ?>">
            <i class="fas fa-triangle-exclamation"></i> <span>Estoque Baixo</span>
            <?php 
                $productModel = new \App\Models\Product();
                $s_filialId = $_SESSION['filial_id'] ?? null;
                $s_isMatriz = $_SESSION['is_matriz'] ?? false;
                $stats = $productModel->getStockStats(!$s_isMatriz ? $s_filialId : null);
                if ($stats['critical'] > 0): 
            ?>
                <span class="badge bg-danger ms-auto rounded-pill" style="font-size: 0.6rem;"><?= $stats['critical'] ?></span>
            <?php elseif ($stats['low'] > 0): ?>
                <span class="badge bg-warning ms-auto rounded-pill text-dark" style="font-size: 0.6rem;"><?= $stats['low'] ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        
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
        
        <a href="financeiro.php" class="nav-link <?= ($current_page == 'financeiro.php' && !isset($_GET['action'])) ? 'active' : '' ?>">
            <i class="fas fa-wallet"></i> <span>Fluxo de Caixa</span>
        </a>

        <!-- Financial Reports Submenu -->
        <?php 
            $is_bi_report = $current_page == 'financeiro.php' && in_array($_GET['action'] ?? '', ['dre', 'abcCurve', 'delinquency']);
        ?>
        <a class="nav-link <?= $is_bi_report ? '' : 'collapsed' ?>" href="#" data-bs-toggle="collapse" data-bs-target="#reportsSubmenu" aria-expanded="<?= $is_bi_report ? 'true' : 'false' ?>">
            <i class="fa-solid fa-chart-pie text-warning"></i>
            <span class="<?= $is_bi_report ? 'fw-bold text-white' : '' ?>">Relatórios BI</span>
            <i class="fas fa-chevron-down ms-auto small opacity-50"></i>
        </a>
        <div class="collapse ps-3 <?= $is_bi_report ? 'show' : '' ?>" id="reportsSubmenu">
            <nav class="nav flex-column border-start border-secondary border-opacity-25 ms-2">
                <a class="nav-link extra-small py-2 <?= ($_GET['action'] ?? '') == 'dre' ? 'active text-warning fw-bold' : 'text-white-50' ?>" href="financeiro.php?action=dre">
                    DRE Mensal
                </a>
                <a class="nav-link extra-small py-2 <?= ($_GET['action'] ?? '') == 'abcCurve' ? 'active text-warning fw-bold' : 'text-white-50' ?>" href="financeiro.php?action=abcCurve">
                    Curva ABC Prod.
                </a>
                <a class="nav-link extra-small py-2 <?= ($_GET['action'] ?? '') == 'delinquency' ? 'active text-warning fw-bold' : 'text-white-50' ?>" href="financeiro.php?action=delinquency">
                    Inadimplência
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
        
        <a href="configuracoes.php?tab=unidades#unidades" class="nav-link <?= ($current_page == 'configuracoes.php' && ($_GET['tab'] ?? '') == 'unidades') ? 'active' : '' ?>">
            <i class="fas fa-landmark"></i> <span>Filiais / Lojas</span>
        </a>
        <a href="usuarios.php" class="nav-link <?= $current_page == 'usuarios.php' ? 'active' : '' ?>">
            <i class="fas fa-user-shield"></i> <span>Gestão de Equipe</span>
        </a>
        <a href="fiscal.php?action=settings" class="nav-link <?= ($current_page == 'fiscal.php' && ($_GET['action'] ?? '') == 'settings') ? 'active' : '' ?>">
            <i class="fas fa-server"></i> <span>Conectividade SEFAZ</span>
        </a>
        <?php endif; ?>
        
        <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['vendedor', 'gerente'])): ?>
        <a href="configuracoes.php" class="nav-link mt-auto border-top border-secondary border-opacity-10 <?= ($current_page == 'configuracoes.php' && !isset($_GET['tab'])) ? 'active' : '' ?>">
            <i class="fas fa-sliders-h"></i> <span>Ajustes Gerais</span>
        </a>
        <?php endif; ?>
        <a href="logout.php" class="nav-link text-danger">
            <i class="fas fa-power-off"></i> <span>Sair do Sistema</span>
        </a>
    </nav>
</aside>
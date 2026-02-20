<aside class="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-bolt" style="font-size: 3rem; color: #3498db;"></i>
        <h2>ERP Elétrica</h2>
        <p style="font-size: 0.9rem; opacity: 0.8;">Sistema de Gestão</p>
    </div>
    
    <ul class="sidebar-menu">
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <a href="index.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'active' : ''; ?>">
            <a href="clientes.php">
                <i class="fas fa-users"></i> Clientes
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'produtos.php' ? 'active' : ''; ?>">
            <a href="produtos.php">
                <i class="fas fa-box"></i> Produtos
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'os.php' ? 'active' : ''; ?>">
            <a href="os.php">
                <i class="fas fa-clipboard-list"></i> Ordens de Serviço
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'estoque.php' ? 'active' : ''; ?>">
            <a href="estoque.php">
                <i class="fas fa-warehouse"></i> Estoque
            </a>
        </li>
        <li class="<?php echo basename($_SERVER['PHP_SELF']) == 'financeiro.php' ? 'active' : ''; ?>">
            <a href="financeiro.php">
                <i class="fas fa-dollar-sign"></i> Financeiro
            </a>
        </li>
        <li>
            <a href="relatorios.php">
                <i class="fas fa-chart-bar"></i> Relatórios
            </a>
        </li>
        <li>
            <a href="configuracoes.php">
                <i class="fas fa-cog"></i> Configurações
            </a>
        </li>
    </ul>
    
    <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: auto;">
        <p style="font-size: 0.8rem; opacity: 0.6; text-align: center;">
            &copy; 2024 ERP Elétrica<br>
            Versão 1.0
        </p>
    </div>
</aside>
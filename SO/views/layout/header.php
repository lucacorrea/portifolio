<header class="navbar no-print">
    <div class="container-xl">

        <div class="navbar-header">
            <a href="dashboard.php" class="navbar-brand">
                <img src="assets/img/prefeitura.jpg" alt="Prefeitura Municipal" style="height: 50px;">
            </a>

            <!-- BOTÃO HAMBURGUER -->
            <button class="menu-toggle" onclick="toggleMenu()">
                <i class="fas fa-bars"></i>
            </button>

            <div class="navbar-user">
                <div class="user-meta">
                    <div class="user-name"><?php echo $_SESSION['user_nome'] ?? $_SESSION['secretaria_nome']; ?></div>
                    <div class="user-role"><?php echo $_SESSION['nivel'] ?? 'SECRETARIA'; ?></div>
                </div>
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
            </div>
        </div>

        <!-- MENU -->
        <nav class="navbar-menu" id="navbarMenu">
            <ul class="nav-list">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="oficios_novo.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'oficios_novo.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i> Novo Ofício
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="oficios_lista.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'oficios_lista.php' ? 'active' : ''; ?>">
                            <i class="fas fa-folder-open"></i> Lista de Ofícios
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="aquisicoes_lista.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'aquisicoes_lista.php' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-bag"></i> Aquisições
                        </a>
                    </li>

                    <?php if ($_SESSION['nivel'] === 'ADMIN' || $_SESSION['nivel'] === 'SUPORTE'): ?>
                        <li class="nav-item">
                            <a href="relatorios.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? 'active' : ''; ?>">
                                <i class="fas fa-file-contract"></i> Relatórios
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['nivel'] === 'SUPORTE'): ?>
                        <li class="nav-item">
                            <a href="configuracoes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'configuracoes.php' ? 'active' : ''; ?>">
                                <i class="fas fa-tools"></i> Configurações
                            </a>
                        </li>
                    <?php endif; ?>

                <?php else: ?>

                    <li class="nav-item">
                        <a href="acompanhamento.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'acompanhamento.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tasks"></i> Acompanhamento
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="confirmar_entrega.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'confirmar_entrega.php' ? 'active' : ''; ?>">
                            <i class="fas fa-truck-loading"></i> Confirmar Entrega
                        </a>
                    </li>

                <?php endif; ?>

                <li class="nav-item ms-auto">
                    <a href="logout.php" class="nav-link" style="color: red;">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </li>

            </ul>
        </nav>

        <script>
            function toggleMenu() {
                const menu = document.getElementById('navbarMenu');
                menu.classList.toggle('active');
            }
        </script>

    </div>
</header>
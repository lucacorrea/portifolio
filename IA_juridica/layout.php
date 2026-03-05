<?php
/**
 * Main Layout Wrapper
 */

function getHeader($title = "Sistema de IA Jurídica") {
    $current_page = basename($_SERVER['PHP_SELF']);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $title; ?></title>
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    </head>
    <body>
        <div class="app-container">
            <aside class="sidebar">
                <div class="sidebar-logo">
                    <i class="fas fa-balance-scale"></i>
                    IA JURÍDICA
                </div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i> Novo Documento
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="historico.php" class="nav-link <?php echo $current_page == 'historico.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i> Histórico
                        </a>
                    </li>
                </ul>
                <div style="margin-top: auto; font-size: 0.8rem; opacity: 0.5;">
                    &copy; 2026 JuridicaAI
                </div>
            </aside>
            <main class="main-content">
    <?php
}

function getFooter() {
    ?>
            </main>
        </div>
    </body>
    </html>
    <?php
}
?>

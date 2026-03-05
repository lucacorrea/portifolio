<?php
declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
// Reutiliza o escapador básico se necessário
if (!function_exists('e')) {
    function e(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Painel da Distribuidora | Suporte</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" /><link rel="stylesheet" href="assets/css/lineicons.css" /><link rel="stylesheet" href="assets/css/main.css" />
    <style>
        .cardx { border: 1px solid rgba(148, 163, 184, .22); border-radius: 16px; background: #fff; overflow: hidden; margin-bottom: 24px; }
        .cardx .head { padding: 18px 20px; border-bottom: 1px solid rgba(148, 163, 184, .18); background: #f8fafc; }
        .cardx .body { padding: 20px; }
        .muted { font-size: 14px; color: #64748b; }
        .support-item { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 20px; }
        .support-icon { width: 48px; height: 48px; border-radius: 12px; background: rgba(54, 92, 245, 0.1); color: #365CF5; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0; }
        .support-text h6 { margin-bottom: 4px; font-weight: 700; color: #0f172a; }
        .support-text p { margin-bottom: 0; font-size: 14px; color: #64748b; }
    </style>
</head>
<body>
    <div id="preloader"><div class="spinner"></div></div>
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo"><a href="dashboard.php"><img src="assets/images/logo/logo.svg" alt="logo" /></a></div>
        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item"><a href="dashboard.php"><span class="icon"><i class="lni lni-grid-alt"></i></span><span class="text">Dashboard</span></a></li>
                <li class="nav-item">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config"><span class="icon"><i class="lni lni-cog"></i></span><span class="text">Configurações</span></a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>
                <li class="nav-item active"><a href="suporte.php"><span class="icon"><i class="lni lni-support"></i></span><span class="text">Suporte</span></a></li>
            </ul>
        </nav>
    </aside>
    <div class="overlay"></div>
    <main class="main-wrapper">
        <header class="header"><div class="container-fluid"><div class="row align-items-center"><div class="col-6"><button id="menu-toggle" class="main-btn primary-btn btn-sm"><i class="lni lni-menu"></i></button></div><div class="col-6 text-end"><span class="muted">Suporte</span></div></div></div></header>
        <section class="section"><div class="container-fluid p-4">
            <div class="cardx">
                <div class="head"><h5>Canais de Suporte</h5><p class="muted mb-0">Precisa de ajuda? Entre em contato conosco.</p></div>
                <div class="body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="support-item">
                                <div class="support-icon"><i class="lni lni-whatsapp"></i></div>
                                <div class="support-text"><h6>WhatsApp</h6><p>Suporte rápido via mensagens.</p><a href="https://wa.me/5500000000000" target="_blank" class="fw-bold text-primary">(00) 00000-0000</a></div>
                            </div>
                            <div class="support-item">
                                <div class="support-icon"><i class="lni lni-envelope"></i></div>
                                <div class="support-text"><h6>E-mail</h6><p>Para solicitações mais complexas.</p><a href="mailto:suporte@exemplo.com" class="fw-bold text-primary">suporte@exemplo.com</a></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="support-item">
                                <div class="support-icon"><i class="lni lni-phone"></i></div>
                                <div class="support-text"><h6>Telefone</h6><p>Atendimento em horário comercial.</p><span class="fw-bold text-primary">(00) 0000-0000</span></div>
                            </div>
                            <div class="support-item">
                                <div class="support-icon"><i class="lni lni-world"></i></div>
                                <div class="support-text"><h6>Base de Conhecimento</h6><p>Acesse nossos manuais e tutoriais.</p><a href="#" class="fw-bold text-primary">Acessar Documentação</a></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cardx">
                <div class="head"><h5>Horário de Atendimento</h5></div>
                <div class="body">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex justify-content-between mb-2"><span>Segunda a Sexta</span><span class="fw-bold">08:00 às 18:00</span></li>
                        <li class="d-flex justify-content-between mb-2"><span>Sábado</span><span class="fw-bold">08:00 às 12:00</span></li>
                        <li class="d-flex justify-content-between"><span>Domingos e Feriados</span><span class="text-danger fw-bold">Fechado</span></li>
                    </ul>
                </div>
            </div>
        </div></section>
    </main>
    <script src="assets/js/bootstrap.bundle.min.js"></script><script src="assets/js/main.js"></script>
</body></html>

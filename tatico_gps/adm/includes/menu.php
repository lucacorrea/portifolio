<?php
/**
 * includes/menu.php
 *
 * Uso:
 * antes do require, defina:
 * $paginaAtiva = 'dashboard';
 * require_once __DIR__ . '/includes/menu.php';
 */

if (!isset($paginaAtiva)) {
    $paginaAtiva = '';
}

function menuAtivo(string $paginaAtual, string $paginaMenu): string
{
    return $paginaAtual === $paginaMenu ? ' active' : '';
}
?>

<!-- Menu lateral -->
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="dashboard.php" class="app-brand-link">
            <span class="app-brand-logo demo">
                <span class="text-primary">
                    <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg"
                        xmlns:xlink="http://www.w3.org/1999/xlink">
                        <defs>
                            <path
                                d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z"
                                id="path-1"></path>
                        </defs>
                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <g transform="translate(-27.000000, -15.000000)">
                                <g transform="translate(27.000000, 15.000000)">
                                    <g transform="translate(0.000000, 8.000000)">
                                        <mask id="mask-2" fill="white">
                                            <use xlink:href="#path-1"></use>
                                        </mask>
                                        <use fill="currentColor" xlink:href="#path-1"></use>
                                    </g>
                                </g>
                            </g>
                        </g>
                    </svg>
                </span>
            </span>
            <span class="app-brand-text demo menu-text fw-bold ms-2">Tático GPS</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="bx bx-chevron-left d-block d-xl-none align-middle"></i>
        </a>
    </div>

    <div class="menu-divider mt-0"></div>
    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <li class="menu-item<?= menuAtivo($paginaAtiva, 'dashboard') ?>">
            <a href="dashboard.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div class="text-truncate">Painel Geral</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'clientes') ?>">
            <a href="clientes.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div class="text-truncate">Clientes</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'cobrancas') ?>">
            <a href="cobrancas.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-wallet"></i>
                <div class="text-truncate">Cobranças</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'pagamentos') ?>">
            <a href="pagamentos.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-credit-card"></i>
                <div class="text-truncate">Pagamentos</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'mensagens') ?>">
            <a href="mensagens.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-message-detail"></i>
                <div class="text-truncate">Mensagens</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'relatorios') ?>">
            <a href="relatorios.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                <div class="text-truncate">Relatórios</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'whatsapp') ?>">
            <a href="whatsapp.php" class="menu-link">
                <i class="menu-icon tf-icons bx bxl-whatsapp"></i>
                <div class="text-truncate">Conectar WhatsApp</div>
            </a>
        </li>

        <li class="menu-item<?= menuAtivo($paginaAtiva, 'configuracoes') ?>">
            <a href="configuracoes.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div class="text-truncate">Configurações</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="./php/auth/logout.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-power-off"></i>
                <div class="text-truncate">Sair</div>
            </a>
        </li>
    </ul>
</aside>
<!-- / Menu lateral -->
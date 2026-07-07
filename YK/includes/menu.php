<?php

declare(strict_types=1);

$activePage = $activePage ?? 'dashboard';

$companySettings = [];
try {
    $companySettings = $application->companySettings()->get();
} catch (Throwable) {
    $companySettings = [];
}

$companyName = trim((string) ($companySettings['nome_fantasia'] ?? ''));
if ($companyName === '') {
    $companyName = 'K. Yamaguchi';
}

$companyLogo = trim((string) ($companySettings['logo'] ?? ''));
if (
    $companyLogo !== ''
    && (
        str_contains($companyLogo, "\0")
        || $companyLogo !== strip_tags($companyLogo)
        || preg_match('/^\s*javascript:/i', $companyLogo)
    )
) {
    $companyLogo = '';
}

$navGroups = [
    'Principal' => [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'bi-grid-1x2',
            'href' => 'dashboard.php',
            'permission' => 'dashboard.visualizar',
        ],
        [
            'key' => 'ordens',
            'label' => 'Ordens de Serviço',
            'icon' => 'bi-clipboard2-check',
            'href' => 'ordens-servico.php',
            'permission' => 'os.visualizar',
        ],
        [
            'key' => 'orcamentos',
            'label' => 'Orçamentos',
            'icon' => 'bi-file-earmark-text',
            'href' => 'orcamentos.php',
            'permission' => 'orcamento.visualizar',
        ],
        [
            'key' => 'clientes',
            'label' => 'Clientes',
            'icon' => 'bi-people',
            'href' => 'clientes.php',
            'permission' => 'cliente.visualizar',
        ],
        [
            'key' => 'agenda',
            'label' => 'Agenda',
            'icon' => 'bi-calendar3',
            'href' => 'agenda.php',
            'permission' => 'agenda.visualizar',
        ],
        [
            'key' => 'painel-semanal',
            'label' => 'Serviços da Semana',
            'icon' => 'bi-calendar-week',
            'href' => 'painel-semanal.php',
            'permission' => 'painel_semanal.visualizar',
        ],
    ],

    'Operacional' => [
        [
            'key' => 'pecas',
            'label' => 'Produtos / Peças',
            'icon' => 'bi-box-seam',
            'href' => 'produtos.php',
            'permission' => 'produto.visualizar',
        ],
        [
            'key' => 'servicos',
            'label' => 'Serviços',
            'icon' => 'bi-tools',
            'href' => 'servicos.php',
            'permission' => 'servico.visualizar',
        ],
        [
            'key' => 'funcionarios',
            'label' => 'Funcionários',
            'icon' => 'bi-person-badge',
            'href' => 'funcionarios.php',
            'permission' => 'funcionario.visualizar',
        ],
    ],

    'Financeiro e Fiscal' => [
        [
            'key' => 'caixa',
            'label' => 'Caixa',
            'icon' => 'bi-cash-coin',
            'href' => 'caixa.php',
            'permission' => 'caixa.visualizar',
        ],
        [
            'key' => 'contas-receber',
            'label' => 'Contas a Receber',
            'icon' => 'bi-wallet2',
            'href' => 'contas-receber.php',
            'permission' => 'contas_receber.visualizar',
        ],
        [
            'key' => 'faturamento',
            'label' => 'Notas e Faturamento',
            'icon' => 'bi-receipt-cutoff',
            'href' => 'faturamento.php',
            'permissions_any' => [
                'nota_fiscal.visualizar',
                'recibo.visualizar',
                'boleto.visualizar',
            ],
        ],
        [
            'key' => 'recibos',
            'label' => 'Recibos e Boletos',
            'icon' => 'bi-journal-check',
            'href' => 'faturamento.php#recibos',
            'permissions_any' => [
                'recibo.visualizar',
                'boleto.visualizar',
            ],
        ],
    ],

    'Gestão' => [
        [
            'key' => 'relatorios',
            'label' => 'Relatórios',
            'icon' => 'bi-bar-chart-line',
            'href' => 'relatorios.php',
            'permissions_any' => [
                'relatorio.operacional',
                'relatorio.financeiro',
                'relatorio.estoque',
                'relatorio.produtividade',
                'relatorio.funcionarios',
            ],
        ],
        [
            'key' => 'configuracoes',
            'label' => 'Configurações',
            'icon' => 'bi-sliders',
            'href' => 'configuracoes.php',
            'permission' => 'configuracao.visualizar',
        ],
    ],

    'Administração' => [
        [
            'key' => 'usuarios',
            'label' => 'Usuários',
            'icon' => 'bi-person-gear',
            'href' => 'usuarios.php',
            'permission' => 'usuario.visualizar',
        ],
        [
            'key' => 'perfis-acesso',
            'label' => 'Perfis de Acesso',
            'icon' => 'bi-shield-lock',
            'href' => 'perfis-acesso.php',
            'permission' => 'perfil.visualizar',
        ],
    ],
];

$canSeeItem = static function (
    array $item
) use ($authorization): bool {
    if (isset($item['permission'])) {
        return $authorization->can(
            (string) $item['permission']
        );
    }

    if (
        isset($item['permissions_any'])
        && is_array($item['permissions_any'])
    ) {
        return $authorization->canAny(
            $item['permissions_any']
        );
    }

    return false;
};
?>

<aside
    class="os-sidebar"
    id="app-sidebar"
>
    <a
        class="sidebar-brand"
        href="dashboard.php"
        aria-label="<?= htmlspecialchars(
            $companyName,
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    >
        <div class="brand-icon">
            <?php if ($companyLogo !== ''): ?>
                <img
                    class="brand-logo-img"
                    src="<?= htmlspecialchars(
                        $companyLogo,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    alt="Logo <?= htmlspecialchars(
                        $companyName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
            <?php else: ?>
                <i class="bi bi-snow2"></i>
            <?php endif; ?>
        </div>

        <div>
            <div class="brand-name">
                <?= htmlspecialchars(
                    $companyName,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <div class="brand-tag">
                Gestão de Serviços
            </div>
        </div>
    </a>

    <?php foreach ($navGroups as $section => $items): ?>
        <?php
        $visibleItems = array_values(
            array_filter(
                $items,
                $canSeeItem
            )
        );
        ?>

        <?php if ($visibleItems === []): ?>
            <?php continue; ?>
        <?php endif; ?>

        <div class="sidebar-section">
            <?= htmlspecialchars(
                $section,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

        <nav
            class="sidebar-nav"
            aria-label="<?= htmlspecialchars(
                $section,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >
            <?php foreach ($visibleItems as $item): ?>
                <?php
                $isActive =
                    $activePage === $item['key']
                    || (
                        $activePage === 'faturamento'
                        && $item['key'] === 'recibos'
                    );
                ?>

                <a
                    class="nav-link-os<?= $isActive
                        ? ' active'
                        : '' ?>"
                    href="<?= htmlspecialchars(
                        $item['href'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    <?= $isActive
                        ? 'aria-current="page"'
                        : '' ?>
                >
                    <i
                        class="bi <?= htmlspecialchars(
                            $item['icon'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    ></i>

                    <span>
                        <?= htmlspecialchars(
                            $item['label'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endforeach; ?>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?= htmlspecialchars(
                    $currentUser->initials(),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

            <div class="user-info">
                <div class="user-name">
                    <?= htmlspecialchars(
                        $currentUser->name(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

                <div class="user-role">
                    <?= htmlspecialchars(
                        $currentUser->profileName(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</aside>

<div
    class="sidebar-backdrop"
    id="sidebar-backdrop"
    aria-hidden="true"
></div>

<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Security\Auth;
use App\Repositories\StoreRepository;
use App\Repositories\UserCompanyRepository;
use App\Services\PlatformAuthorizationService;
use App\Services\StoreAccessService;

Auth::requireLogin();
$user = Auth::user();
$platformAuth = new PlatformAuthorizationService();
$storeAccess = new StoreAccessService(new UserCompanyRepository(), new StoreRepository());
$isPlatformOwner = $platformAuth->isPlatformOwner((int)($user['id'] ?? 0));
$canManageFiliais = false;

if ($user) {
    $canManageFiliais = $storeAccess->canCreateFilial((int)$user['id'], (int)($user['empresa_id'] ?? 0));
}

$pageId = 'mais';
$pageTitle = 'Mais';
$activeMenu = 'mais';

require_once __DIR__ . '/layout/header.php';

$menuItems = [
    [
        'title' => 'Nova Venda',
        'description' => 'Abrir o PDV para iniciar uma venda.',
        'href' => 'nova-venda.php',
        'icon' => 'plus',
    ],
    [
        'title' => 'Histórico de Vendas',
        'description' => 'Consultar vendas realizadas e seus status.',
        'href' => 'historico-vendas.php',
        'icon' => 'history',
    ],
    [
        'title' => 'Relatórios',
        'description' => 'Acompanhar resultados, filtros e indicadores.',
        'href' => 'relatorios.php',
        'icon' => 'chart',
    ],
    [
        'title' => 'Clientes',
        'description' => 'Gerenciar cadastro e dados dos clientes.',
        'href' => 'clientes.php',
        'icon' => 'users',
    ],
    [
        'title' => 'Contas / Fiado',
        'description' => 'Ver contas em aberto e movimentações.',
        'href' => 'contas-clientes.php',
        'icon' => 'wallet',
    ],
    [
        'title' => 'Produtos',
        'description' => 'Acessar estoque, busca e cadastro de produtos.',
        'href' => 'produtos.php',
        'icon' => 'box',
    ],
    [
        'title' => 'Configurações',
        'description' => 'Ajustar parâmetros do sistema.',
        'href' => 'configuracoes.php',
        'icon' => 'settings',
    ],
    [
        'title' => 'Dashboard',
        'description' => 'Voltar para a visão inicial do sistema.',
        'href' => '../index.php',
        'icon' => 'home',
    ],
];

if ($isPlatformOwner) {
    array_splice($menuItems, 6, 0, [[
        'title' => 'Matrizes',
        'description' => 'Cadastre e gerencie as empresas principais da plataforma.',
        'href' => 'matrizes.php',
        'icon' => 'building',
    ]]);
}

if ($canManageFiliais) {
    array_splice($menuItems, 6, 0, [[
        'title' => 'Filiais',
        'description' => 'Crie, configure e acesse as filiais da matriz ativa.',
        'href' => 'lojas.php',
        'icon' => 'store',
    ]]);
}

function moreMenuIcon(string $icon): string
{
    return match ($icon) {
        'plus' => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'history' => '<path d="M5 5v5h5"/><path d="M6.2 15.8a7 7 0 1 0 .3-8.1"/><path d="M12 8v5l3 2"/>',
        'chart' => '<path d="M5 19V5"/><path d="M5 19h14"/><path d="M9 16v-5"/><path d="M13 16V8"/><path d="M17 16v-3"/>',
        'users' => '<path d="M16 11a4 4 0 1 0-8 0"/><path d="M4 20a8 8 0 0 1 16 0"/>',
        'wallet' => '<path d="M5 8h14v10H5z"/><path d="M7 11h10"/><path d="M8 15h4"/>',
        'box' => '<path d="m4 8 8-4 8 4-8 4z"/><path d="M4 8v8l8 4 8-4V8"/><path d="M12 12v8"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h9v18"/><path d="M15 8h3a2 2 0 0 1 2 2v11"/><path d="M8 7h3"/><path d="M8 11h3"/><path d="M8 15h3"/><path d="M3 21h18"/>',
        'store' => '<path d="M4 10h16"/><path d="M5 10l1-5h12l1 5"/><path d="M6 10v9h12v-9"/><path d="M9 19v-5h6v5"/><path d="M8 10v2"/><path d="M12 10v2"/><path d="M16 10v2"/>',
        'settings' => '<path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="M4 12h2"/><path d="M18 12h2"/><path d="M12 4v2"/><path d="M12 18v2"/>',
        default => '<path d="M4 11.5 12 5l8 6.5V20H4z"/>',
    };
}
?>

<style>
  .more-page {
    display: grid;
    gap: 16px;
    padding: 18px 16px 118px;
  }

  .more-hero {
    display: grid;
    gap: 6px;
    padding: 18px;
    color: #fff;
    background: linear-gradient(135deg, #1557a7 0%, #14866d 100%);
    border-radius: 24px;
    box-shadow: 0 16px 34px rgba(21, 87, 167, .18);
  }

  .more-hero .micro-label {
    margin: 0;
    color: rgba(255,255,255,.74);
  }

  .more-hero h1 {
    margin: 0;
    font-size: 25px;
    line-height: 1.08;
    letter-spacing: 0;
  }

  .more-hero p {
    margin: 0;
    max-width: 560px;
    color: rgba(255,255,255,.82);
    font-size: 13px;
    font-weight: 750;
    line-height: 1.45;
  }

  .more-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
  }

  .more-card {
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr) 20px;
    align-items: center;
    gap: 12px;
    min-height: 78px;
    padding: 14px;
    color: var(--ink);
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(29,55,95,.055);
  }

  .more-card-icon {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--blue);
    background: var(--blue-soft);
    border: 1px solid var(--blue-line);
    border-radius: 14px;
  }

  .more-card svg {
    width: 21px;
    height: 21px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .more-card h2 {
    margin: 0;
    font-size: 15px;
    line-height: 1.2;
    letter-spacing: 0;
  }

  .more-card p {
    margin: 4px 0 0;
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.35;
  }

  .more-card-arrow {
    color: var(--muted);
  }

  @media (min-width: 760px) {
    .more-page {
      padding: 24px 24px 64px;
    }

    .more-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>

<section class="more-page">
  <header class="more-hero">
    <p class="micro-label">Menu</p>
    <h1>Mais opções</h1>
    <p>Acesse os módulos que não ficam fixos na navegação principal do celular.</p>
  </header>

  <nav class="more-grid" aria-label="Mais opções do sistema">
    <?php foreach ($menuItems as $item): ?>
      <a class="more-card" href="<?= e((string)$item['href']) ?>">
        <span class="more-card-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><?= moreMenuIcon((string)$item['icon']) ?></svg>
        </span>
        <div class="more-card-copy">
          <h2><?= e((string)$item['title']) ?></h2>
          <p><?= e((string)$item['description']) ?></p>
        </div>
        <span class="more-card-arrow" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
        </span>
      </a>
    <?php endforeach; ?>
  </nav>
</section>

<?php require_once __DIR__ . '/layout/footer.php'; ?>

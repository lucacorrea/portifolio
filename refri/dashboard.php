<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0F766E">
  <title>Dashboard | K.Yamaguchi Service</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Tailwind CSS via CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#0F766E',
            'primary-hover': '#0B5F59',
            'blue-support': '#2563EB',
            success: '#15803D',
            warning: '#B45309',
            danger: '#B91C1C',
            'neutral-gray': '#374151',
          }
        }
      }
    }
  </script>
  <style>
    body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
  </style>
</head>
<body class="bg-[#F4F6F8] text-[#111827]">

<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-60 bg-white border-r border-[#DDE3EA] flex flex-col fixed inset-y-0 left-0 z-40 transition-transform duration-200 -translate-x-full md:translate-x-0" id="sidebar">
    <div class="p-5 border-b border-[#E5E7EB]">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 bg-[#EAF5F3] text-primary rounded-lg flex items-center justify-center font-bold text-sm">KY</div>
        <div>
          <strong class="block text-sm">K.Yamaguchi</strong>
          <span class="text-xs text-[#6B7280]">Service OS</span>
        </div>
      </div>
    </div>
    <nav class="flex-1 p-2 space-y-1">
      <a href="dashboard.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium bg-[#EAF5F3] text-primary border-l-4 border-primary">Dashboard</a>
      <a href="clientes.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Clientes</a>
      <a href="ordens-servico.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Ordens de Serviço</a>
      <a href="orcamentos.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Orçamentos</a>
      <a href="pecas.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Peças</a>
      <a href="tipos-servico.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Tipos de Serviço</a>
      <a href="relatorios.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Relatórios</a>
      <a href="notas-fiscais.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Notas Fiscais</a>
      <a href="configuracoes.php" class="flex items-center gap-3 px-4 py-2.5 rounded-md text-sm font-medium text-[#374151] hover:bg-[#F3F6F8] transition-colors duration-150 border-l-4 border-transparent">Configurações</a>
    </nav>
    <div class="p-4 border-t border-[#E5E7EB]">
      <span class="text-xs text-[#6B7280] uppercase tracking-wider">Atalho operacional</span>
      <strong class="block text-sm mt-1">Nova Ordem de Serviço</strong>
      <p class="text-xs text-[#6B7280] mt-1">Abra uma OS com cliente, equipamento, serviço e status técnico.</p>
      <a href="ordens-servico.php?action=new" class="block w-full text-center mt-3 px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary-hover transition-colors duration-150">+ Nova OS</a>
    </div>
    <div class="p-4 border-t border-[#E5E7EB] flex items-center gap-3">
      <div class="w-8 h-8 bg-[#EAF5F3] text-primary rounded-lg flex items-center justify-center font-bold text-xs">KY</div>
      <div>
        <strong class="block text-sm">Operador</strong>
        <span class="text-xs text-[#6B7280]">Administrador</span>
      </div>
    </div>
  </aside>
  <div class="fixed inset-0 bg-black/30 z-30 hidden" id="sidebarOverlay"></div>

  <!-- Main Content -->
  <main class="flex-1 md:ml-60 p-4 md:p-6 bg-[#F4F6F8] min-h-screen">
    <!-- Topbar -->
    <header class="h-16 bg-white border-b border-[#DDE3EA] flex items-center justify-between px-4 md:px-6 sticky top-0 z-20">
      <div class="flex items-center gap-4">
        <button id="menuToggle" class="md:hidden text-2xl">☰</button>
        <form class="relative">
          <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6B7280]">🔍</span>
          <input type="search" placeholder="Buscar cliente, OS, orçamento, peça ou nota fiscal..." class="pl-10 pr-4 py-2 w-64 md:w-80 border border-[#DDE3EA] rounded-md text-sm bg-[#F9FAFB] focus:bg-white focus:border-primary outline-none transition-colors duration-150">
        </form>
      </div>
      <div class="flex items-center gap-2">
        <button class="px-4 py-2 text-sm font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Atualizar</button>
        <button class="p-2 text-lg">🔔</button>
      </div>
    </header>

    <!-- Page Header -->
    <section class="mt-6 mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
      <div>
        <span class="text-xs font-semibold uppercase tracking-wider text-[#6B7280]">Visão operacional</span>
        <h1 class="text-2xl font-bold mt-1">Dashboard</h1>
        <p class="text-sm text-[#6B7280] mt-1">Acompanhe ordens de serviço, orçamentos, faturamento, estoque e alertas técnicos da K.Yamaguchi.</p>
      </div>
      <div class="flex gap-2 mt-4 md:mt-0">
        <a href="ordens-servico.php?action=new" class="px-4 py-2 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary-hover transition-colors duration-150">+ Nova OS</a>
        <a href="orcamentos.php?action=new" class="px-4 py-2 text-[#374151] border border-[#DDE3EA] rounded-md text-sm font-medium hover:bg-[#F8FAFC] transition-colors duration-150">Novo Orçamento</a>
      </div>
    </section>

    <!-- KPI Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="dashboardStats">
      <article class="bg-white border border-[#E5E7EB] rounded-lg p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-[#DBEAFE] text-[#1D4ED8] rounded-lg flex items-center justify-center text-xl font-bold">OS</div>
        <div>
          <span class="text-xs text-[#6B7280]">OS abertas</span>
          <strong class="block text-2xl font-bold">24</strong>
          <small class="text-xs text-[#6B7280]">+8 novas esta semana</small>
        </div>
      </article>
      <article class="bg-white border border-[#E5E7EB] rounded-lg p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-[#FEF3C7] text-[#B45309] rounded-lg flex items-center justify-center text-xl font-bold">EX</div>
        <div>
          <span class="text-xs text-[#6B7280]">Em execução</span>
          <strong class="block text-2xl font-bold">13</strong>
          <small class="text-xs text-[#6B7280]">5 técnicos em rota</small>
        </div>
      </article>
      <article class="bg-white border border-[#E5E7EB] rounded-lg p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-[#CCFBF1] text-primary rounded-lg flex items-center justify-center text-xl font-bold">OR</div>
        <div>
          <span class="text-xs text-[#6B7280]">Orçamentos pendentes</span>
          <strong class="block text-2xl font-bold">18</strong>
          <small class="text-xs text-[#6B7280]">R$ 12.480 em análise</small>
        </div>
      </article>
      <article class="bg-white border border-[#E5E7EB] rounded-lg p-5 flex items-center gap-4">
        <div class="w-12 h-12 bg-[#DCFCE7] text-success rounded-lg flex items-center justify-center text-xl font-bold">R$</div>
        <div>
          <span class="text-xs text-[#6B7280]">Faturamento do mês</span>
          <strong class="block text-2xl font-bold">R$ 38.920</strong>
          <small class="text-xs text-[#6B7280]">+12% vs mês anterior</small>
        </div>
      </article>
    </section>

    <!-- Charts Row -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
      <article class="bg-white border border-[#E5E7EB] rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-[#6B7280]">Status das OS</span>
            <h2 class="text-lg font-semibold">Ordens de serviço por status</h2>
          </div>
          <a href="ordens-servico.php" class="px-3 py-1.5 text-xs font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Ver OS</a>
        </div>
        <canvas id="osStatusChart" height="260"></canvas>
      </article>
      <article class="bg-white border border-[#E5E7EB] rounded-lg p-5">
        <div class="mb-4">
          <span class="text-xs font-semibold uppercase tracking-wider text-[#6B7280]">Financeiro</span>
          <h2 class="text-lg font-semibold">Faturamento mensal</h2>
        </div>
        <canvas id="revenueChart" height="220"></canvas>
      </article>
    </section>

    <!-- Recent Orders & Right Panel -->
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <article class="lg:col-span-2 bg-white border border-[#E5E7EB] rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
          <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-[#6B7280]">Operação recente</span>
            <h2 class="text-lg font-semibold">Últimas ordens de serviço</h2>
          </div>
          <a href="ordens-servico.php" class="px-3 py-1.5 text-xs font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Abrir lista</a>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-[#F8FAFC] border-b border-[#DDE3EA]">
                <th class="text-left p-3 font-semibold">OS</th>
                <th class="text-left p-3 font-semibold">Cliente</th>
                <th class="text-left p-3 font-semibold">Serviço</th>
                <th class="text-left p-3 font-semibold">Status</th>
                <th class="text-left p-3 font-semibold">Técnico</th>
                <th class="text-left p-3 font-semibold">Valor</th>
                <th class="text-left p-3 font-semibold">Ações</th>
              </tr>
            </thead>
            <tbody id="recentOrders">
              <tr class="border-b border-[#E5E7EB] hover:bg-[#F9FAFB] transition-colors duration-150">
                <td class="p-3"><span class="font-medium">OS-000123</span><br><span class="text-xs text-[#6B7280]">22/05/2026</span></td>
                <td class="p-3">Mercado São José</td>
                <td class="p-3">Manutenção Split</td>
                <td class="p-3"><span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#FEF3C7] text-[#B45309]">Em andamento</span></td>
                <td class="p-3">Carlos</td>
                <td class="p-3">R$ 280,00</td>
                <td class="p-3"><button class="px-3 py-1 text-xs font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Ver</button></td>
              </tr>
              <tr class="border-b border-[#E5E7EB] hover:bg-[#F9FAFB] transition-colors duration-150">
                <td class="p-3"><span class="font-medium">OS-000124</span><br><span class="text-xs text-[#6B7280]">23/05/2026</span></td>
                <td class="p-3">João Almeida</td>
                <td class="p-3">Higienização</td>
                <td class="p-3"><span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#EDE9FE] text-[#6D28D9]">Agendada</span></td>
                <td class="p-3">Paulo</td>
                <td class="p-3">R$ 150,00</td>
                <td class="p-3"><button class="px-3 py-1 text-xs font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Ver</button></td>
              </tr>
              <tr class="border-b border-[#E5E7EB] hover:bg-[#F9FAFB] transition-colors duration-150">
                <td class="p-3"><span class="font-medium">OS-000125</span><br><span class="text-xs text-[#6B7280]">24/05/2026</span></td>
                <td class="p-3">Clínica Vida Norte</td>
                <td class="p-3">Troca de peça</td>
                <td class="p-3"><span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#FFEDD5] text-[#C2410C]">Aguardando peça</span></td>
                <td class="p-3">Rafael</td>
                <td class="p-3">R$ 690,00</td>
                <td class="p-3"><button class="px-3 py-1 text-xs font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Ver</button></td>
              </tr>
              <tr class="hover:bg-[#F9FAFB] transition-colors duration-150">
                <td class="p-3"><span class="font-medium">OS-000126</span><br><span class="text-xs text-[#6B7280]">21/05/2026</span></td>
                <td class="p-3">Padaria Modelo</td>
                <td class="p-3">Câmara fria</td>
                <td class="p-3"><span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#DCFCE7] text-[#15803D]">Finalizada</span></td>
                <td class="p-3">Marcos</td>
                <td class="p-3">R$ 1.250,00</td>
                <td class="p-3"><button class="px-3 py-1 text-xs font-medium text-[#374151] border border-[#DDE3EA] rounded-md hover:bg-[#F8FAFC] transition-colors duration-150">Ver</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
      <aside class="bg-white border border-[#E5E7EB] rounded-lg p-5">
        <div class="mb-4">
          <span class="text-xs font-semibold uppercase tracking-wider text-[#6B7280]">Agenda</span>
          <h2 class="text-lg font-semibold">Atendimentos do dia</h2>
        </div>
        <div class="space-y-3" id="todaySchedule">
          <div class="flex items-center gap-3 p-2 hover:bg-[#F9FAFB] rounded-md transition-colors duration-150">
            <span class="text-xs font-mono text-[#6B7280] w-10">09:30</span>
            <div class="flex-1">
              <strong class="text-sm">Manutenção preventiva</strong>
              <span class="block text-xs text-[#6B7280]">Mercado São José</span>
            </div>
            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#EDE9FE] text-[#6D28D9]">Agendada</span>
          </div>
          <div class="flex items-center gap-3 p-2 hover:bg-[#F9FAFB] rounded-md transition-colors duration-150">
            <span class="text-xs font-mono text-[#6B7280] w-10">11:00</span>
            <div class="flex-1">
              <strong class="text-sm">Higienização Split</strong>
              <span class="block text-xs text-[#6B7280]">João Almeida</span>
            </div>
            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#EDE9FE] text-[#6D28D9]">Agendada</span>
          </div>
          <div class="flex items-center gap-3 p-2 hover:bg-[#F9FAFB] rounded-md transition-colors duration-150">
            <span class="text-xs font-mono text-[#6B7280] w-10">14:30</span>
            <div class="flex-1">
              <strong class="text-sm">Troca de peça</strong>
              <span class="block text-xs text-[#6B7280]">Clínica Vida Norte</span>
            </div>
            <span class="px-2 py-1 text-xs font-semibold rounded-md bg-[#FFEDD5] text-[#C2410C]">Aguardando peça</span>
          </div>
        </div>
        <div class="mt-6 pt-4 border-t border-[#E5E7EB]">
          <span class="text-xs font-semibold uppercase tracking-wider text-[#6B7280]">Atenção</span>
          <h2 class="text-lg font-semibold mb-3">Alertas importantes</h2>
          <div class="space-y-2" id="alertsList">
            <div class="p-3 border border-[#E5E7EB] rounded-md">
              <strong class="text-sm">Peças com estoque baixo</strong>
              <span class="block text-xs text-[#6B7280]">Capacitor 35µF abaixo do mínimo definido.</span>
            </div>
            <div class="p-3 border border-[#E5E7EB] rounded-md">
              <strong class="text-sm">Orçamentos próximos de expirar</strong>
              <span class="block text-xs text-[#6B7280]">2 propostas vencem nos próximos 3 dias.</span>
            </div>
            <div class="p-3 border border-[#E5E7EB] rounded-md">
              <strong class="text-sm">Notas pendentes</strong>
              <span class="block text-xs text-[#6B7280]">Existem 3 documentos fiscais aguardando ação.</span>
            </div>
          </div>
        </div>
      </aside>
    </section>
  </main>
</div>

<script src="assets/js/app.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
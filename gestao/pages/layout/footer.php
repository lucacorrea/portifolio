<?php
$prefix = $prefix ?? '../';
$activeMenu = $activeMenu ?? '';
?>
    </section>
    <nav class="bottom-nav" aria-label="Navegação principal">
      <a class="<?= $activeMenu === 'home' ? 'active' : '' ?>" href="<?= $prefix ?>index.php">
        <svg viewBox="0 0 24 24"><path d="M4 11.5 12 5l8 6.5V20H4z"/></svg>
        <span>Início</span>
      </a>
      <a class="<?= $activeMenu === 'historico' ? 'active' : '' ?>" href="<?= $prefix ?>pages/historico-vendas.php">
        <svg viewBox="0 0 24 24"><path d="M6 5h12v14H6z"/><path d="M9 9h6"/><path d="M9 13h4"/><path d="M9 17h6"/></svg>
        <span>Histórico</span>
      </a>
      <a class="center-action <?= $activeMenu === 'nova-venda' ? 'active' : '' ?>" href="<?= $prefix ?>pages/nova-venda.php" aria-label="Nova venda">
        <strong>+</strong>
      </a>
      <a class="<?= $activeMenu === 'produtos' ? 'active' : '' ?>" href="<?= $prefix ?>pages/produtos.php">
        <svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>
        <span>Produtos</span>
      </a>
      <a class="<?= $activeMenu === 'mais' ? 'active' : '' ?>" href="<?= $prefix ?>pages/mais.php">
        <svg viewBox="0 0 24 24"><path d="M12 5v.01"/><path d="M12 12v.01"/><path d="M12 19v.01"/></svg>
        <span>Mais</span>
      </a>
      <a class="side-nav-only <?= $activeMenu === 'clientes' ? 'active' : '' ?>" href="<?= $prefix ?>pages/clientes.php">
        <svg viewBox="0 0 24 24"><path d="M16 11a4 4 0 1 0-8 0"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
        <span>Clientes</span>
      </a>
      <a class="side-nav-only <?= $activeMenu === 'contas' ? 'active' : '' ?>" href="<?= $prefix ?>pages/contas-clientes.php">
        <svg viewBox="0 0 24 24"><path d="M5 8h14v10H5z"/><path d="M7 11h10"/><path d="M8 15h4"/></svg>
        <span>Fiado</span>
      </a>
      <a class="side-nav-only <?= $activeMenu === 'relatorios' ? 'active' : '' ?>" href="<?= $prefix ?>pages/relatorios.php">
        <svg viewBox="0 0 24 24"><path d="M5 19V5"/><path d="M5 19h14"/><path d="M9 16v-5"/><path d="M13 16V8"/><path d="M17 16v-3"/></svg>
        <span>Relatórios</span>
      </a>
    </nav>
    <div class="modal-backdrop" id="modalBackdrop" hidden>
      <section class="modal-card" id="modalCard" role="dialog" aria-modal="true"></section>
    </div>
    <div class="toast" id="toast"></div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="<?= $prefix ?>assets/js/app.js"></script>
  <script src="<?= $prefix ?>assets/js/pwa-install.js"></script>
</body>
</html>

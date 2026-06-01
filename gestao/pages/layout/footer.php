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
      <a class="<?= $activeMenu === 'vendas' ? 'active' : '' ?>" href="<?= $prefix ?>pages/nova-venda.php">
        <svg viewBox="0 0 24 24"><path d="M6 5h12v14H6z"/><path d="M9 9h6"/><path d="M9 13h4"/></svg>
        <span>Vendas</span>
      </a>
      <a class="center-action" href="<?= $prefix ?>pages/nova-venda.php" aria-label="Nova venda">
        <strong>+</strong>
      </a>
      <a class="<?= $activeMenu === 'produtos' ? 'active' : '' ?>" href="<?= $prefix ?>pages/produtos.php">
        <svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>
        <span>Produtos</span>
      </a>
      <a class="<?= $activeMenu === 'mais' ? 'active' : '' ?>" href="<?= $prefix ?>pages/configuracoes.php">
        <svg viewBox="0 0 24 24"><path d="M12 5v.01"/><path d="M12 12v.01"/><path d="M12 19v.01"/></svg>
        <span>Mais</span>
      </a>
    </nav>
    <div class="modal-backdrop" id="modalBackdrop" hidden>
      <section class="modal-card" id="modalCard" role="dialog" aria-modal="true"></section>
    </div>
    <div class="toast" id="toast"></div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
  <script src="<?= $prefix ?>assets/js/app.js"></script>
</body>
</html>

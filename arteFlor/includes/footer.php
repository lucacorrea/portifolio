</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <strong class="footer-brand">Arte&Flor</strong>
      <p>Flores, arranjos, vasos e presentes com compra finalizada dentro do sistema demonstrativo.</p>
    </div>
    <div>
      <strong>Catálogo</strong>
      <a href="<?= site_url('catalogo.php') ?>">Produtos</a>
      <a href="<?= site_url('carrinho.php') ?>">Carrinho</a>
      <a href="<?= site_url('checkout.php') ?>">Checkout</a>
    </div>
    <div>
      <strong>Atendimento</strong>
      <a href="<?= site_url('cliente.php') ?>">Consultar pedido</a>
      <a target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento da Arte&Flor.') ?>">Suporte por WhatsApp</a>
    </div>
  </div>
</footer>
<a class="whatsapp-float" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento da Arte&Flor.') ?>" aria-label="Atendimento por WhatsApp">Atendimento</a>
<div class="toast-root" data-toast-root aria-live="polite" aria-atomic="true"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
<script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
<script src="./js/resposive"></script>
</body>
</html>

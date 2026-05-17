</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-about">
      <a class="brand footer-logo" href="<?= site_url('index.php') ?>" aria-label="Arte&Flor - início">
        <span class="brand-mark" aria-hidden="true">A&F</span>
        <span class="brand-text">Arte<span>&</span>Flor</span>
      </a>
      <p>Flores, arranjos, vasos e presentes preparados com uma curadoria delicada para momentos especiais.</p>
    </div>
    <div>
      <strong>Comprar</strong>
      <a href="<?= site_url('catalogo.php') ?>">Catálogo</a>
      <a href="<?= site_url('carrinho.php') ?>">Carrinho</a>
      <a href="<?= site_url('checkout.php') ?>">Checkout</a>
    </div>
    <div>
      <strong>Atendimento</strong>
      <a href="<?= site_url('cliente.php') ?>">Consultar pedido</a>
      <a href="<?= site_url('blog.php') ?>">Dicas e cuidados</a>
      <a target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, preciso de atendimento da Arte&Flor.') ?>">Falar no WhatsApp</a>
    </div>
    <div>
      <strong>Demonstrativo</strong>
      <p>Projeto visual em PHP estatico para aprovacao da cliente. Sem banco, login real ou pagamento real.</p>
    </div>
  </div>
</footer>
<a class="whatsapp-float" target="_blank" rel="noopener" href="<?= whatsapp_url('Olá, vim pelo site da Arte&Flor.') ?>" aria-label="Falar com a Arte&Flor pelo WhatsApp">WhatsApp</a>
<div class="toast" data-toast role="status" aria-live="polite"></div>
<script src="<?= asset('js/app.js') ?>"></script>
<?php foreach (($pageScripts ?? []) as $script): ?>
  <script src="<?= asset($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>

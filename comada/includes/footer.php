<?php if (!$hideChrome): ?>
        </main>
    </div>
</div>
<nav class="mobile-nav" aria-label="Navegação principal">
    <a href="mesas.php"><svg viewBox="0 0 24 24"><path d="M5 9h14M7 9v10m10-10v10M4 5h16v4H4V5z"/></svg><span>Mesas</span></a>
    <a href="comandas.php"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6m-6 4h6"/></svg><span>Comandas</span></a>
    <a href="pedidos.php"><svg viewBox="0 0 24 24"><path d="M4 7h16l-1.5 12h-13L4 7zm3 0a5 5 0 0110 0"/></svg><span>Pedidos</span></a>
    <a href="perfil.php"><svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm-7 9a7 7 0 0114 0"/></svg><span>Perfil</span></a>
</nav>
<?php endif; ?>
<div class="toast" id="toast"></div>
<div class="modal" id="genericModal" aria-hidden="true">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-card">
        <button class="modal-close" data-modal-close>×</button>
        <div id="modalContent"></div>
    </div>
</div>
<script src="assets/js/app.js?v=1"></script>
</body>
</html>

<?php if (!$hideChrome): ?>
        </main>
    </div>
</div>
<nav class="mobile-nav">
    <a href="mesas.php">▤<span>Mesas</span></a>
    <a href="comandas.php">▧<span>Comandas</span></a>
    <a href="pedidos.php">◫<span>Pedidos</span></a>
    <a href="perfil.php">●<span>Perfil</span></a>
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

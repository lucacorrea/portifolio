<?php
$pageTitle = 'Área do cliente';
$activePage = 'cliente';
require_once __DIR__ . '/includes/header.php';
?>
<section class="page-header"><div class="container"><h1 class="section-title">Área do cliente</h1><p class="section-subtitle">Consulte pedidos, status de entrega e detalhes da compra.</p></div></section>
<section class="section"><div class="container grid-3"><div class="card"><span class="status">Pedido recebido</span><h3>Pedido #AF-1024</h3><p class="muted">Buquê Tons Pastel · Pix · Entrega</p></div><div class="card"><span class="status">Em preparo</span><h3>Pedido #AF-1025</h3><p class="muted">Arranjo Floral Premium · Presencial</p></div><div class="card"><span class="status">Finalizado</span><h3>Pedido #AF-1021</h3><p class="muted">Mini Buquê Delicado · Dinheiro</p></div></div></section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

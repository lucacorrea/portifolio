<?php $pageTitle='Comanda #153'; $currentPage='comandas'; include 'includes/header.php'; ?>

<div class="page-head">
    <div>
        <h1>Mesa 08 · Comanda #153</h1>
        <p>Atendimento em andamento.</p>
        <div class="comanda-context">
            <span class="context-chip">Cliente <strong>João Silva</strong></span>
            <span class="context-chip">Garçom <strong>Marcos Lima</strong></span>
            <span class="context-chip">Aberta às <strong>13:10</strong></span>
            <span class="pill success">Ativa</span>
        </div>
    </div>
    <div class="actions">
        <button class="btn btn-secondary" data-open-modal data-modal-title="Mais ações" data-modal-text="Transferir mesa, transferir item, juntar comandas, dividir comanda ou cancelar.">Mais ações</button>
        <a class="btn btn-success" href="fechar-conta.php">Fechar conta</a>
    </div>
</div>

<div class="comanda-layout">
    <section class="card comanda-summary">
        <div class="section-title"><h2>Itens da comanda</h2><span class="pill info">4 itens</span></div>
        <div class="comanda-items">
            <div class="comanda-item">
                <span class="item-qty">2×</span>
                <div class="item-copy"><strong>Heineken 600ml</strong><small>Bar · pedido pronto</small></div>
                <strong class="item-price">R$ 24,00</strong>
            </div>
            <div class="comanda-item">
                <span class="item-qty">1×</span>
                <div class="item-copy"><strong>Batata Frita</strong><small>Cozinha · em preparo</small></div>
                <strong class="item-price">R$ 28,00</strong>
            </div>
            <div class="comanda-item">
                <span class="item-qty">1×</span>
                <div class="item-copy"><strong>X-Bacon</strong><small>Sem cebola · em preparo</small></div>
                <strong class="item-price">R$ 32,00</strong>
            </div>

            <div class="totals">
                <div class="total-line"><span>Subtotal</span><strong>R$ 84,00</strong></div>
                <div class="total-line"><span>Taxa de serviço 10%</span><strong>R$ 8,40</strong></div>
                <div class="total-line grand"><span>Total</span><span>R$ 92,40</span></div>
            </div>

            <div class="actions" style="margin-top:15px">
                <button class="btn btn-primary" data-demo-action="Pedido enviado aos setores">Enviar pedido</button>
                <a class="btn btn-secondary" href="fechar-conta.php">Fechar conta</a>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="section-title">
            <div><h2>Adicionar itens</h2></div>
            <span class="pill neutral">Catálogo</span>
        </div>
        <div class="catalog-toolbar">
            <div class="filters">
                <input class="input search" placeholder="Buscar produto, bebida ou serviço" aria-label="Buscar produto">
                <div class="tabs">
                    <button class="tab active">Todos</button>
                    <button class="tab">Cervejas</button>
                    <button class="tab">Drinks</button>
                    <button class="tab">Porções</button>
                    <button class="tab">Lanches</button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="product-grid">
                <?php foreach($produtos as $p): ?>
                    <button class="product-card" data-product="<?= e($p['nome']) ?>" data-price="<?= number_format($p['preco'],2,',','.') ?>">
                        <span class="product-add">+</span>
                        <span class="product-category"><?= e($p['categoria']) ?></span>
                        <strong><?= e($p['nome']) ?></strong>
                        <small><?= e($p['setor']) ?> · disponível</small>
                        <b><?= money($p['preco']) ?></b>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>

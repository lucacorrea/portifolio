<?php $pageTitle='Mesas'; $currentPage='mesas'; include 'includes/header.php'; ?>

<div class="page-head">
    <div>
        <h1>Mesas e pontos de atendimento</h1>
        <p>Acompanhe ocupação, consumo e solicitações de conta em tempo real.</p>
    </div>
    <div class="actions">
        <a class="btn btn-secondary" href="mapa.php">Mapa visual</a>
        <a class="btn btn-primary" href="comanda.php">+ Nova comanda</a>
    </div>
</div>

<div class="mesa-summary">
    <div class="mesa-summary-card"><span class="mesa-summary-icon"><svg viewBox="0 0 24 24"><path d="M5 9h14M7 9v10m10-10v10M4 5h16v4H4V5z"/></svg></span><div><strong>38</strong><small>Total de mesas</small></div></div>
    <div class="mesa-summary-card"><span class="mesa-summary-icon" style="background:#eaf8f1;color:#16a36a"><svg viewBox="0 0 24 24"><path d="M5 12l4 4 10-10"/></svg></span><div><strong>12</strong><small>Livres agora</small></div></div>
    <div class="mesa-summary-card"><span class="mesa-summary-icon" style="background:#f0edff;color:#6f4df6"><svg viewBox="0 0 24 24"><path d="M12 3v18M7 8h8a3 3 0 010 6H9a3 3 0 000 6h8"/></svg></span><div><strong>21</strong><small>Em atendimento</small></div></div>
    <div class="mesa-summary-card"><span class="mesa-summary-icon" style="background:#fff5df;color:#e59a24"><svg viewBox="0 0 24 24"><path d="M12 7v5l3 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><div><strong>5</strong><small>Aguardando conta</small></div></div>
</div>

<div class="mesa-toolbar">
    <div class="filters">
        <input class="input search" placeholder="Buscar mesa ou cliente" aria-label="Buscar mesa ou cliente">
        <div class="tabs">
            <button class="tab active" data-tab-filter="Todos">Todos</button>
            <button class="tab" data-tab-filter="Livre">Livres</button>
            <button class="tab" data-tab-filter="Ocupada">Ocupadas</button>
            <button class="tab" data-tab-filter="Conta">Aguardando conta</button>
        </div>
    </div>
    <select class="select" aria-label="Ordenar mesas"><option>Ordenar por número</option><option>Maior consumo</option><option>Mais tempo aberta</option></select>
</div>

<div class="mesa-grid">
<?php foreach($mesas as $m): $cls=strtolower($m['status']); ?>
    <article class="card mesa-card <?= $cls ?>" data-status="<?= e($m['status']) ?>">
        <div class="mesa-top">
            <div><h3>Mesa <?= str_pad((string)$m['n'],2,'0',STR_PAD_LEFT) ?></h3></div>
            <?php if($m['tempo']): ?><span class="mesa-time">◷ <?= e($m['tempo']) ?></span><?php endif; ?>
        </div>

        <div class="mesa-meta">
            <span class="pill <?= $m['status']==='Livre'?'neutral':($m['status']==='Conta'?'warning':'info') ?>"><?= e($m['status']) ?></span>
        </div>

        <?php if($m['status']!=='Livre'): ?>
            <div class="mesa-client">
                <strong><?= e($m['cliente']) ?></strong>
                <small><?= $m['itens'] ?> itens lançados</small>
            </div>
            <div class="price"><?= money($m['total']) ?></div>
            <div class="mesa-actions">
                <a class="btn <?= $m['status']==='Conta'?'btn-success':'btn-secondary' ?> btn-sm" href="<?= $m['status']==='Conta'?'fechar-conta.php':'comanda.php' ?>"><?= $m['status']==='Conta'?'Fechar conta':'Abrir comanda' ?></a>
            </div>
        <?php else: ?>
            <div class="available">Disponível para atendimento</div>
            <div class="mesa-actions"><a class="btn btn-primary btn-sm" href="comanda.php">Abrir comanda</a></div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>

<?php include 'includes/footer.php'; ?>

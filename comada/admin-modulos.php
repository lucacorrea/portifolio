<?php $pageTitle='Módulos SaaS'; include 'includes/header.php'; ?>
<div class="page-head"><div><h1>Módulos</h1><p>Feature flags e recursos habilitáveis por plano.</p></div></div><div class="card card-body"><?php foreach(['Mesas e comandas','KDS / Produção','Caixa','Estoque','Relatórios','Auditoria','Delivery','Agendamento','Fiscal','API externa'] as $m): ?><div class="switch-row"><span><?= $m ?></span><span class="switch <?= in_array($m,['Delivery','Agendamento','Fiscal','API externa'])?'':'on' ?>"></span></div><?php endforeach; ?></div>
<?php include 'includes/footer.php'; ?>

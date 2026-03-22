<h1><?= $pageTitle ?></h1>
<ul>
<?php foreach($menuItems as $m): ?>
<li><?= $m['label'] ?></li>
<?php endforeach; ?>
</ul>

<div>
<?php foreach($cards as $c): ?>
<div><?= $c['titulo'] ?>: <?= $c['valor'] ?></div>
<?php endforeach; ?>
</div>

<?php require base_path('resources/views/'.$contentView.'.php'); ?>

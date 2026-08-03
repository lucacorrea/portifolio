<?php
use Sigesp\Core\{Auth, View};

$title = $title ?? 'Erro interno';
$isAuthenticated = Auth::check();
?>
<section class="empty-state" role="alert" aria-labelledby="error-title">
    <span class="empty-state__icon" aria-hidden="true">!</span>
    <h1 id="error-title">Não foi possível concluir esta solicitação</h1>
    <p>Ocorreu um erro inesperado. Tente novamente em alguns instantes.</p>
    <a class="button" href="<?= View::e(View::url($isAuthenticated ? '/dashboard' : '/login')) ?>">
        <?= $isAuthenticated ? 'Voltar ao painel' : 'Voltar ao acesso' ?>
    </a>
</section>

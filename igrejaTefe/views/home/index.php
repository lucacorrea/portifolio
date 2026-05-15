<section class="page-section">
    <div class="section-header">
        <div>
            <p class="eyebrow">Sistema financeiro</p>
            <h1>Gestão financeira para igrejas</h1>
        </div>
        <a class="button primary" href="<?= \App\Core\View::e(url('/login')) ?>">Acessar sistema</a>
    </div>

    <div class="status-grid">
        <article class="status-card">
            <span>Acesso seguro</span>
            <strong>Login por sessão</strong>
            <p>Usuários ativos acessam o painel com senha hash e sessão protegida.</p>
        </article>
        <article class="status-card">
            <span>Multi-igreja</span>
            <strong>Isolamento por igreja</strong>
            <p>As rotas privadas usam a igreja da sessão como base de autorização.</p>
        </article>
        <article class="status-card">
            <span>Painel financeiro</span>
            <strong>Dashboard protegido</strong>
            <p>Depois do login, o usuário entra no painel do MVP financeiro.</p>
        </article>
    </div>
</section>

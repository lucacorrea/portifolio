<?php
require_once __DIR__ . '/layout.php';
require_suporte();

render_layout_start(
    'usuarios',
    'Usuarios',
    'Controle de acessos e perfis do sistema.',
    'Usuarios normais acessam dashboard, cadastro e relatorios. O perfil suporte tambem acessa usuarios e configuracoes.'
);
?>

<section class="data-panel" data-page="users">
    <div class="panel-heading">
        <div>
            <h2>Usuarios do Sistema</h2>
            <p id="user-count-label">Carregando usuarios...</p>
        </div>
        <button class="btn primary" type="button" id="new-user">
            <i class="fa-solid fa-user-plus"></i>
            Novo usuario
        </button>
    </div>

    <div class="filter-bar compact">
        <label class="search-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" id="user-q" placeholder="Buscar usuario, login ou perfil">
        </label>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Login</th>
                    <th>Perfil</th>
                    <th>Ultimo acesso</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody id="user-table-body">
                <tr>
                    <td colspan="5" class="empty-row">Carregando usuarios...</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="user-pagination"></div>
</section>

<div class="modal-backdrop" id="user-modal" hidden>
    <section class="modal-box small">
        <header class="modal-header">
            <h2 id="user-modal-title">Novo Usuario</h2>
            <button class="icon-button" type="button" data-close-user-modal title="Fechar" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>
        <form id="user-form" class="modal-body">
            <input type="hidden" id="user-id">
            <label class="form-field">
                <span>Nome</span>
                <input type="text" id="user-nome" required maxlength="160">
            </label>
            <label class="form-field">
                <span>Login</span>
                <input type="text" id="user-login" required maxlength="80">
            </label>
            <label class="form-field">
                <span>Senha</span>
                <div class="password-field">
                    <input type="password" id="user-senha" maxlength="120" placeholder="Digite uma nova senha">
                    <button type="button" class="icon-button" data-toggle-password="user-senha" title="Mostrar senha" aria-label="Mostrar senha">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
                <small id="user-senha-help" class="field-help">Senha obrigatoria para novo usuario.</small>
            </label>
            <label class="form-field">
                <span>Perfil</span>
                <select id="user-perfil" required>
                    <option value="normal">Normal</option>
                    <option value="suporte">Suporte</option>
                </select>
            </label>
            <footer class="modal-actions">
                <button class="btn ghost" type="button" data-close-user-modal>Cancelar</button>
                <button class="btn primary" type="submit">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Salvar
                </button>
            </footer>
        </form>
    </section>
</div>

<?php render_layout_end(); ?>

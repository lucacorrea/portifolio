<!-- Actions Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-users-cog me-2"></i>Diretório de Colaboradores</h6>
        <button class="btn btn-primary fw-bold" onclick="openUserModal()">
            <i class="fas fa-user-plus me-2"></i>Novo Operador
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Colaborador</th>
                        <th>E-mail Corporativo</th>
                        <th>Unidade / Filial</th>
                        <th>Nível / Desc. Máx</th>
                        <th class="text-center">Status</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= $u['nome'] ?></div>
                                    <div class="text-muted extra-small">Visto em: <?= $u['last_login'] ? formatarDataHora($u['last_login']) : 'Nunca' ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="small"><?= $u['email'] ?></td>
                        <td>
                            <div class="small fw-bold text-primary">
                                <i class="fas fa-building me-1 opacity-50"></i>
                                <?= $u['filial_nome'] ?: '<span class="text-danger">SEM FILIAL</span>' ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-3">
                                <?= strtoupper($u['nivel']) ?>
                            </span>
                            <div class="extra-small text-muted mt-1">Limite Desc: <span class="fw-bold"><?= number_format($u['desconto_maximo'], 1) ?>%</span></div>
                        </td>
                        <td class="text-center">
                            <?php if($u['ativo']): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3">ATIVO</span>
                            <?php else: ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3">INATIVO</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light border" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" title="Editar Credenciais">
                                    <i class="fas fa-user-edit text-primary"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Bloquear Acesso">
                                    <i class="fas fa-ban"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($pagination && $pagination['pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-3">
        <nav aria-label="Navegação de usuários">
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <li class="page-item <?= $pagination['current'] <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['current'] - 1 ?>" aria-label="Anterior">
                        <i class="fas fa-chevron-left small"></i>
                    </a>
                </li>
                <?php 
                $start = max(1, $pagination['current'] - 2);
                $end = min($pagination['pages'], $start + 4);
                if ($end - $start < 4) $start = max(1, $end - 4);
                for($i = $start; $i <= $end; $i++): 
                ?>
                <li class="page-item <?= $i == $pagination['current'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $pagination['current'] >= $pagination['pages'] ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $pagination['current'] + 1 ?>" aria-label="Próximo">
                        <i class="fas fa-chevron-right small"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- User Modal -->
<div class="modal fade" id="modal-user" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="usuarios.php?action=save" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="user-modal-title">Gestão de Colaborador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="usuario_id" id="edit-user-id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Nome Completo</label>
                        <input type="text" name="nome" id="edit-user-nome" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">E-mail Corporativo</label>
                        <input type="email" name="email" id="edit-user-email" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Unidade de Lotação</label>
                        <select name="filial_id" id="edit-user-filial" class="form-select shadow-sm" required>
                            <option value="" disabled selected>Selecione a empresa...</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= $branch['id'] ?>"><?= $branch['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nível de Acesso</label>
                        <select name="nivel" id="edit-user-nivel" class="form-select shadow-sm" onchange="toggleAuthFields()">
                            <option value="vendedor">Vendedor</option>
                            <option value="tecnico">Técnico</option>
                            <option value="gerente">Gerente</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="ativo" id="edit-user-ativo" checked>
                            <label class="form-check-label small fw-bold" for="edit-user-ativo">Usuário Ativo</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-primary"><i class="fas fa-percentage me-1"></i> Desconto Máximo Permitido (%)</label>
                        <input type="number" step="0.1" name="desconto_maximo" id="edit-user-desconto" class="form-control shadow-sm" value="0.0">
                        <div class="extra-small text-muted">Aplica-se apenas ao nível Vendedor no PDV.</div>
                    </div>
                    <div id="auth-fields-section" style="display: none;" class="row g-3 px-0 mx-0">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-primary"><i class="fas fa-shield-alt me-1"></i> Tipo de Autorização</label>
                            <select name="auth_type" id="edit-user-auth-type" class="form-select shadow-sm" onchange="togglePinField()">
                                <option value="password">Senha de Login</option>
                                <option value="pin">PIN Numérico</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="pin-field-container" style="display: none;">
                            <label class="form-label small fw-bold text-primary">PIN de Autorização</label>
                            <input type="text" name="auth_pin" id="edit-user-auth-pin" class="form-control shadow-sm" placeholder="Ex: 1234">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Senha <span id="pwd-label" class="text-muted">(Obrigatória)</span></label>
                        <input type="password" name="senha" id="edit-user-senha" class="form-control shadow-sm">
                        <small class="text-muted extra-small">Deixe em branco para manter a senha atual ao editar.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Colaborador</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAuthFields() {
    const nivel = document.getElementById('edit-user-nivel').value;
    document.getElementById('auth-fields-section').style.display = (nivel === 'admin') ? 'flex' : 'none';
}

function togglePinField() {
    const type = document.getElementById('edit-user-auth-type').value;
    document.getElementById('pin-field-container').style.display = (type === 'pin') ? 'block' : 'none';
}

function openUserModal() {
    const modal = new bootstrap.Modal(document.getElementById('modal-user'));
    document.getElementById('user-modal-title').innerText = 'Novo Operador de Sistema';
    document.getElementById('edit-user-id').value = '';
    document.getElementById('edit-user-nome').value = '';
    document.getElementById('edit-user-email').value = '';
    document.getElementById('edit-user-filial').value = '';
    document.getElementById('edit-user-nivel').value = 'vendedor';
    document.getElementById('edit-user-ativo').checked = true;
    document.getElementById('edit-user-desconto').value = '0.0';
    document.getElementById('edit-user-auth-type').value = 'password';
    document.getElementById('edit-user-auth-pin').value = '';
    document.getElementById('edit-user-senha').required = true;
    document.getElementById('pwd-label').innerText = '(Obrigatória)';
    toggleAuthFields();
    togglePinField();
    modal.show();
}

function editUser(user) {
    const modal = new bootstrap.Modal(document.getElementById('modal-user'));
    document.getElementById('user-modal-title').innerText = 'Editar Colaborador';
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-user-nome').value = user.nome;
    document.getElementById('edit-user-email').value = user.email;
    document.getElementById('edit-user-filial').value = user.filial_id;
    document.getElementById('edit-user-nivel').value = user.nivel;
    document.getElementById('edit-user-ativo').checked = user.ativo == 1;
    document.getElementById('edit-user-desconto').value = user.desconto_maximo || '0.0';
    document.getElementById('edit-user-auth-type').value = user.auth_type || 'password';
    document.getElementById('edit-user-auth-pin').value = user.auth_pin || '';
    document.getElementById('edit-user-senha').required = false;
    document.getElementById('pwd-label').innerText = '(Opcional)';
    toggleAuthFields();
    togglePinField();
    modal.show();
}
</script>

<!-- Actions Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <form method="GET" class="d-flex gap-2 w-50">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" name="search" class="form-control border-start-0" placeholder="Buscar por nome, CPF/CNPJ ou email..." value="<?= htmlspecialchars($searchTerm ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-outline-primary fw-bold px-4">Filtrar</button>
        </form>
        <div>
            <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#clientModal">
                <i class="fas fa-user-plus me-2"></i>Novo Cliente
            </button>
        </div>
    </div>
</div>

<!-- Clients Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Nome / Razão Social</th>
                        <th class="ps-4">Cliente / Contato</th>
                        <th>CPF / CNPJ</th>
                        <th>Cidade / UF</th>
                        <th class="text-end">LTV (Acumulado)</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= $c['nome'] ?></div>
                                    <div class="text-muted extra-small">ID: #<?= $c['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="small"><i class="fas fa-phone me-1 text-muted"></i> <?= $c['telefone'] ?: '---' ?></div>
                            <div class="small"><i class="fas fa-envelope me-1 text-muted"></i> <?= $c['email'] ?: '---' ?></div>
                        </td>
                        <td class="small fw-bold text-muted"><?= $c['cpf_cnpj'] ?: '---' ?></td>
                        <td class="small">
                            <span class="text-muted"><?= $c['endereco'] ?: 'Endereço não informado' ?></span>
                        </td>
                        <td class="text-end fw-bold text-success">
                            <?php
                                $clientModel = new \App\Models\Client();
                                $ltv = $clientModel->getLTV($c['id']);
                                echo 'R$ ' . number_format($ltv, 2, ',', '.');
                            ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <a href="clientes.php?action=profile&id=<?= $c['id'] ?>" class="btn btn-light border text-primary" title="Perfil CRM (LTV)">
                                    <i class="fas fa-chart-line"></i>
                                </a>
                                <button class="btn btn-light border" onclick='editClient(<?= json_encode($c) ?>)' title="Ficha Cadastral">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($clients)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fas fa-users-slash fs-1 d-block mb-3 opacity-25"></i>
                            Nenhum cliente encontrado na base.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($pagination && $pagination['pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-3">
        <nav aria-label="Navegação de clientes">
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

<!-- Client Modal -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="clientes.php?action=save" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="clientModalTitle">Gestão de Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Nome Completo / Razão Social</label>
                        <input type="text" name="nome" id="edit_nome" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">CPF / CNPJ</label>
                        <input type="text" name="cpf_cnpj" id="edit_cpf_cnpj" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Telefone</label>
                        <input type="text" name="telefone" id="edit_telefone" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control shadow-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Endereço Completo</label>
                        <input type="text" name="endereco" id="edit_endereco" class="form-control shadow-sm">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Cliente</button>
            </div>
        </form>
    </div>
</div>

<script>
function editClient(client) {
    const modal = new bootstrap.Modal(document.getElementById('clientModal'));
    document.getElementById('edit_id').value = client.id;
    document.getElementById('edit_nome').value = client.nome;
    document.getElementById('edit_cpf_cnpj').value = client.cpf_cnpj;
    document.getElementById('edit_telefone').value = client.telefone;
    document.getElementById('edit_email').value = client.email;
    document.getElementById('edit_endereco').value = client.endereco;
    
    document.getElementById('clientModalTitle').innerText = 'Editar Cliente';
    modal.show();
}
</script>

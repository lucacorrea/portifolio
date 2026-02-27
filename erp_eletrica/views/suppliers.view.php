<!-- Actions Bar -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-truck me-2"></i>Diretório de Fornecedores</h6>
        <button class="btn btn-primary fw-bold" onclick="openSupplierModal()">
            <i class="fas fa-plus me-2"></i>Novo Fornecedor
        </button>
    </div>
</div>

<!-- Suppliers Table -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Empresa / Razão Social</th>
                        <th>CNPJ / Identificação</th>
                        <th>Contato Principal</th>
                        <th>Localização</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($suppliers as $s): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                    <i class="fas fa-industry"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?= $s['nome_fantasia'] ?></div>
                                    <div class="text-muted extra-small">ID: #<?= $s['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="small fw-bold text-muted"><?= $s['cnpj'] ?: '---' ?></td>
                        <td>
                            <div class="small"><i class="fas fa-phone me-1 text-muted"></i> <?= $s['telefone'] ?: '---' ?></div>
                            <div class="small"><i class="fas fa-envelope me-1 text-muted"></i> <?= $s['email'] ?: '---' ?></div>
                        </td>
                        <td class="small">
                            <span class="text-muted text-truncate d-inline-block" style="max-width: 200px;"><?= $s['endereco'] ?: '---' ?></span>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-light border" onclick='editSupplier(<?= json_encode($s) ?>)' title="Editar">
                                    <i class="fas fa-edit text-primary"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Inativar">
                                    <i class="fas fa-trash"></i>
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
    <?php if (isset($pagination) && $pagination['pages'] > 1): ?>
    <div class="card-footer bg-white border-top py-3">
        <nav aria-label="Navegação de fornecedores">
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

<!-- Supplier Modal -->
<div class="modal fade" id="modal-supplier" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" action="fornecedores.php?action=save" method="POST">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="sup-modal-title">Gestão de Fornecedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="id" id="sup-id">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label small fw-bold">Nome Fantasia / Empresa</label>
                        <input type="text" name="nome_fantasia" id="sup-nome" class="form-control shadow-sm" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">CNPJ</label>
                        <input type="text" name="cnpj" id="sup-cnpj" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Telefone</label>
                        <input type="text" name="telefone" id="sup-tel" class="form-control shadow-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">E-mail</label>
                        <input type="email" name="email" id="sup-email" class="form-control shadow-sm">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">Endereço Completo</label>
                        <textarea name="endereco" id="sup-end" class="form-control shadow-sm" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold">Salvar Parceiro</button>
            </div>
        </form>
    </div>
</div>

<script>
function openSupplierModal() {
    const modal = new bootstrap.Modal(document.getElementById('modal-supplier'));
    document.getElementById('sup-modal-title').innerText = 'Cadastrar Novo Parceiro';
    document.getElementById('sup-id').value = '';
    document.getElementById('sup-nome').value = '';
    document.getElementById('sup-cnpj').value = '';
    document.getElementById('sup-tel').value = '';
    document.getElementById('sup-email').value = '';
    document.getElementById('sup-end').value = '';
    modal.show();
}

function editSupplier(s) {
    const modal = new bootstrap.Modal(document.getElementById('modal-supplier'));
    document.getElementById('sup-modal-title').innerText = 'Editar Fornecedor';
    document.getElementById('sup-id').value = s.id;
    document.getElementById('sup-nome').value = s.nome_fantasia;
    document.getElementById('sup-cnpj').value = s.cnpj;
    document.getElementById('sup-tel').value = s.telefone;
    document.getElementById('sup-email').value = s.email;
    document.getElementById('sup-end').value = s.endereco;
    modal.show();
}
</script>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold text-dark"><i class="fas fa-user-shield me-2 text-primary"></i>Gestão de Permissões por Nível de Acesso</h6>
        <button type="submit" form="permissionsForm" class="btn btn-primary fw-bold px-4">
            <i class="fas fa-save me-2"></i>Salvar Alterações Globais
        </button>
    </div>
    <div class="card-body p-0">
        <form id="permissionsForm" action="master.php?action=savePermissions" method="POST">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4" width="250">Módulo / Funcionalidade</th>
                            <?php foreach ($niveis as $nivel): ?>
                            <th class="text-center text-uppercase small fw-bold"><?= $nivel ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $current_modulo = '';
                        foreach ($permissoes as $p): 
                            if ($current_modulo !== $p['modulo']): 
                                $current_modulo = $p['modulo'];
                        ?>
                        <tr class="table-light">
                            <td colspan="<?= count($niveis) + 1 ?>" class="ps-4 fw-bold text-primary small text-uppercase">
                                <i class="fas fa-folder-open me-2"></i>Módulo: <?= $current_modulo ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark small"><?= $p['acao'] ?></div>
                                <div class="text-muted extra-small"><?= $p['descricao'] ?></div>
                            </td>
                            <?php foreach ($niveis as $nivel): ?>
                            <td class="text-center">
                                <div class="form-check d-inline-block">
                                    <input class="form-check-input" type="checkbox" 
                                           name="perms[<?= $nivel ?>][]" 
                                           value="<?= $p['id'] ?>" 
                                           <?= in_array($p['id'], $mapping[$nivel] ?? []) ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="mt-4">
    <div class="alert alert-info border-0 shadow-sm small">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Nota de Arquiteto:</strong> O nível <code>master</code> possui permissões totais por padrão e não aparece nesta lista para evitar bloqueios acidentais do sistema.
    </div>
</div>

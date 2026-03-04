<!-- Global Actions -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-center py-3">
        <h6 class="mb-0 fw-bold text-secondary"><i class="fas fa-file-invoice me-2"></i>Histórico de Documentos Fiscais</h6>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary fw-bold shadow-sm" onclick="window.location.href='fiscal.php?action=settings'">
                <i class="fas fa-cog me-2"></i>Configurações SEFAZ
            </button>
            <button class="btn btn-primary fw-bold shadow-sm" onclick="location.reload()">
                <i class="fas fa-sync me-2"></i>Atualizar Status
            </button>
        </div>
    </div>
</div>

<!-- Invoice List -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Tipo</th>
                        <th>Chave de Acesso / Protocolo</th>
                        <th>Cliente / Valor</th>
                        <th class="text-center">Data Emissão</th>
                        <th class="text-center">Status SEFAZ</th>
                        <th class="text-end pe-4">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notes)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-receipt fs-1 d-block mb-3 opacity-25"></i>
                                Nenhuma nota fiscal emitida até o momento.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($notes as $nf): ?>
                    <tr>
                        <td class="ps-4">
                            <span class="badge <?= $nf['tipo'] == 'nfe' ? 'bg-primary' : 'bg-info' ?> text-uppercase">
                                <?= $nf['tipo'] ?>
                            </span>
                        </td>
                        <td>
                            <div class="fw-bold small text-muted"><?= chunk_split($nf['chave_acesso'], 4, ' ') ?></div>
                            <div class="extra-small text-primary fw-bold">Prot: <?= $nf['protocolo'] ?: '---' ?></div>
                        </td>
                        <td>
                            <div class="fw-bold"><?= $nf['cliente_nome'] ?: 'Consumidor Final' ?></div>
                            <div class="text-success small fw-bold"><?= formatarMoeda($nf['valor_total']) ?></div>
                        </td>
                        <td class="text-center">
                            <div class="small fw-bold"><?= date('d/m/Y', strtotime($nf['created_at'])) ?></div>
                            <div class="extra-small text-muted"><?= date('H:i', strtotime($nf['created_at'])) ?></div>
                        </td>
                        <td class="text-center">
                            <?php if ($nf['status'] == 'autorizada'): ?>
                                <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 border border-success border-opacity-25">AUTORIZADA</span>
                            <?php elseif ($nf['status'] == 'cancelada'): ?>
                                <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill px-3 border border-danger border-opacity-25">CANCELADA</span>
                            <?php else: ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning rounded-pill px-3 border border-warning border-opacity-25 text-uppercase"><?= $nf['status'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-4">
                            <div class="btn-group btn-group-sm shadow-sm">
                                <a href="fiscal.php?action=download_xml&id=<?= $nf['id'] ?>" class="btn btn-light border" title="Baixar XML">
                                    <i class="fas fa-code text-primary"></i>
                                </a>
                                <button class="btn btn-light border" title="Visualizar DANFE" onclick="alert('Visualizador de PDF em desenvolvimento')">
                                    <i class="fas fa-file-pdf text-danger"></i>
                                </button>
                                <button class="btn btn-light border text-danger" title="Cancelar Nota" onclick="alert('Solicitação de cancelamento enviada')">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="h4 mb-0 fw-bold text-primary">
                <i class="fas fa-cloud-download-alt me-2"></i>Importação Automática SEFAZ
            </h2>
            <p class="text-muted small mb-0">Notas Fiscais emitidas para o seu CNPJ via Certificado A1</p>
        </div>
        <div class="col-auto">
            <?php if (in_array($_SESSION['usuario_nivel'], ['master', 'admin'])): ?>
                <a href="importar_automatico.php?action=config" class="btn btn-outline-secondary fw-bold me-2">
                    <i class="fas fa-cog me-2"></i>CONFIGURAÇÕES GLOBAIS
                </a>
            <?php endif; ?>
            <button class="btn btn-primary fw-bold" onclick="sincronizarSefaz()">
                <i class="fas fa-sync-alt me-2"></i>ATUALIZAR NOTAS SEFAZ
            </button>
        </div>
    </div>

    <!-- Filtros e Status -->
    <div class="bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded p-3 mb-4 text-primary shadow-sm">
        <div class="d-flex align-items-center">
            <i class="fas fa-info-circle me-3 fa-2x"></i>
            <div>
                <h6 class="mb-1 fw-bold">Como funciona?</h6>
                <p class="mb-0 small text-light">O sistema consulta os servidores da SEFAZ Nacional em busca de notas destinadas ao seu CNPJ. 
                As notas aparecem aqui agrupadas por fornecedor para facilitar a entrada no estoque.</p>
            </div>
        </div>
    </div>

    <?php if (empty($fornecedores)): ?>
        <div class="card border-0 shadow-sm py-5 text-center">
            <div class="card-body">
                <i class="fas fa-file-invoice fa-4x opacity-25 mb-4"></i>
                <h5 class="text-muted">Nenhuma nota fiscal pendente encontrada.</h5>
                <p class="text-muted small">Clique em "Atualizar Notas SEFAZ" para buscar novos documentos.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="accordion border-0 shadow-sm" id="accordionFornecedores">
            <?php foreach ($fornecedores as $i => $f): ?>
                <div class="accordion-item border-0 mb-3 rounded shadow-sm">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white rounded" type="button" data-bs-toggle="collapse" data-bs-target="#forn_<?= $i ?>">
                            <div class="d-flex justify-content-between w-100 align-items-center me-3">
                                <div>
                                    <i class="fas fa-truck me-2 text-primary"></i>
                                    <?= htmlspecialchars($f['fornecedor_nome']) ?>
                                    <span class="badge bg-light text-dark ms-2 fw-normal"><?= $f['fornecedor_cnpj'] ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary rounded-pill me-2"><?= $f['total_notas'] ?> Nota(s)</span>
                                    <span class="text-muted small">Total: R$ <?= number_format($f['valor_acumulado'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="forn_<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#accordionFornecedores">
                        <div class="accordion-body p-0 border-top">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Número</th>
                                        <th>Emissão</th>
                                        <th>Valor Total</th>
                                        <th>Status Cache</th>
                                        <th class="text-end pe-4">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($f['notas'] as $nota): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?= $nota['numero_nota'] ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($nota['data_emissao'])) ?></td>
                                            <td class="fw-bold">R$ <?= number_format($nota['valor_total'], 2, ',', '.') ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark px-3 mt-1">PENDENTE</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-info fw-bold" onclick="manifestarNota(<?= $nota['id'] ?>)" title="Ciência da Operação">
                                                        <i class="fas fa-file-signature me-1"></i> MANIFESTAR
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary fw-bold" onclick="visualizarItens(<?= $nota['id'] ?>)">
                                                        <i class="fas fa-list me-1"></i> ITENS
                                                    </button>
                                                    <a href="importar_automatico.php?action=baixar_xml&id=<?= $nota['id'] ?>" class="btn btn-sm btn-outline-secondary fw-bold" title="Baixar XML">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Visualizar Itens -->
<div class="modal fade" id="modalItens" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-box-open me-2"></i>Produtos da Nota Fiscal</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="loaderItens" class="text-center py-5 d-none">
                    <span class="spinner-border text-primary" role="status"></span>
                    <p class="mt-3 text-muted">Lendo XML e extraindo produtos...</p>
                </div>
                <div id="errorItens" class="alert alert-danger m-3 d-none"></div>
                
                <table class="table table-hover mb-0 align-middle d-none" id="tableItens">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Cód. Forn.</th>
                            <th>Descrição / Nome</th>
                            <th>NCM</th>
                            <th>Qtd Com.</th>
                            <th>V. Unitário</th>
                            <th class="text-end pe-4">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyItens"></tbody>
                </table>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary border-0 fw-bold" data-bs-dismiss="modal">FECHAR</button>
                <button type="button" id="btnImportarTudo" class="btn btn-success fw-bold border-0 d-none">
                    <i class="fas fa-check-double me-2"></i>DAR ENTRADA NA NOTA COMPLETA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
async function sincronizarSefaz() {
    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=sincronizar');
        const result = await response.json();
        
        if (result.success) {
            alert(result.count > 0 ? `${result.count} novas notas localizadas e salvas!` : (result.message || 'Sincronização concluída sem novas notas.'));
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro de comunicação: ' + e.message);
    } finally {
        hideLoader();
    }
}

async function manifestarNota(id) {
    if (!confirm('Deseja realizar a Ciência da Operação para esta nota? Isso permitirá baixar os produtos na SEFAZ.')) return;
    showLoader();
    try {
        const response = await fetch(`importar_automatico.php?action=manifestar&id=${id}`);
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            // Sincronizar automaticamente após manifestar para tentar baixar o XML completo
            sincronizarSefaz();
        } else {
            alert('Erro ao manifestar: ' + result.error);
        }
    } catch (e) {
        alert('Erro de comunicação: ' + e.message);
    } finally {
        hideLoader();
    }
}

let activeNotaId = null;
let activeItems = [];

async function visualizarItens(id) {
    activeNotaId = id;
    const modalEl = document.getElementById('modalItens');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const loader = document.getElementById('loaderItens');
    const table = document.getElementById('tableItens');
    const error = document.getElementById('errorItens');
    const tbody = document.getElementById('tbodyItens');
    const btnTudo = document.getElementById('btnImportarTudo');

    modal.show();
    loader.classList.remove('d-none');
    table.classList.add('d-none');
    error.classList.add('d-none');
    btnTudo.classList.add('d-none');
    tbody.innerHTML = '';
    activeItems = [];

    try {
        const response = await fetch(`importar_automatico.php?action=visualizar_produtos&id=${id}`);
        const result = await response.json();

        if (result.success) {
            activeItems = result.produtos;
            result.produtos.forEach(p => {
                const row = `
                    <tr>
                        <td class="ps-4 small font-monospace">${p.codigo}</td>
                        <td class="fw-bold">${p.nome}</td>
                        <td class="small">${p.ncm}</td>
                        <td class="text-primary fw-bold">${p.qCom}</td>
                        <td>R$ ${p.vUnComFormatted}</td>
                        <td class="text-end pe-4">
                            <span class="badge bg-light text-muted">Aguardando Importação</span>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
            table.classList.remove('d-none');
            btnTudo.classList.remove('d-none');
        } else {
            error.innerText = result.error;
            error.classList.remove('d-none');
        }
    } catch (e) {
        error.innerText = 'Erro ao carregar itens: ' + e.message;
        error.classList.remove('d-none');
    } finally {
        loader.classList.add('d-none');
    }
}

document.getElementById('btnImportarTudo').onclick = async () => {
    if (!activeNotaId || activeItems.length === 0) return;
    
    if (!confirm(`Deseja realizar a entrada de ${activeItems.length} produtos no estoque?`)) return;

    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=processar_entrada', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nota_id: activeNotaId,
                items: activeItems
            })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Entrada de estoque realizada com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro ao processar entrada: ' + e.message);
    } finally {
        hideLoader();
    }
};
</script>

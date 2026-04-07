<div class="container-fluid py-4">
    <div class="row mb-4 align-items-end">
        <div class="col-lg-7">
            <h2 class="h3 mb-2 fw-bold text-primary d-flex align-items-center">
                <i class="fas fa-satellite-dish me-3 p-2 bg-primary bg-opacity-10 rounded-3"></i>Importação SEFAZ <span class="badge bg-warning text-dark ms-3 fs-6 px-3 py-2 rounded-pill">Certificado A1</span>
            </h2>
            <p class="text-muted mb-0 d-flex align-items-center">
                <i class="far fa-clock me-2"></i>Status da Rede: <span class="text-success fw-bold ms-1">Online</span> 
                <?php if (!empty($lastSync)): ?>
                    <span class="mx-2 opacity-25">|</span> Última Sincronização em <?= date('d/m/Y H:i', strtotime($lastSync)) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-auto">
            <?php if (in_array($_SESSION['usuario_nivel'], ['master', 'admin']) && ($_SESSION['is_matriz'] ?? false)): ?>
                <a href="importar_automatico.php?action=config" class="btn btn-outline-secondary fw-bold me-2">
                    <i class="fas fa-cog me-2"></i>CONFIGURAÇÕES GLOBAIS
                </a>
            <?php endif; ?>
            <div class="btn-group">
                <button class="btn btn-primary fw-bold" onclick="sincronizarSefaz()">
                    <i class="fas fa-sync-alt me-2"></i>ATUALIZAR NOTAS SEFAZ
                </button>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Opções</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                    <li>
                        <a class="dropdown-item fw-bold py-2" href="#" onclick="sincronizarSefaz(true)">
                            <i class="fas fa-search-plus me-2 text-primary"></i>BUSCA PROFUNDA (Últimos 90 dias)
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li class="px-3 py-1">
                        <small class="text-muted d-block" style="max-width: 200px; font-size: 0.7rem;">
                            <i class="fas fa-info-circle me-1"></i> Use a busca profunda se notar que existem notas faltando. A SEFAZ permite recuperar documentos de até 90 dias atrás.
                        </small>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Infobox Moderno -->
    <div class="card border-0 shadow-sm mb-4 overflow-hidden" style="border-left: 5px solid var(--primary-color) !important;">
        <div class="card-body p-3 bg-white">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3">
                    <i class="fas fa-shield-alt text-primary fa-lg"></i>
                </div>
                <div>
                    <h6 class="mb-1 fw-bold text-dark">Monitoramento de Notas Destinadas</h6>
                    <p class="mb-0 small text-muted">O sistema consulta os servidores da SEFAZ Nacional em busca de notas destinadas ao seu CNPJ nos <strong>últimos 90 dias</strong> (limite legal do governo).</p>
                </div>
                <div class="ms-auto d-none d-md-block text-end">
                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 rounded-pill px-3">Protocolo Homologado</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($fornecedores)): ?>
        <div class="card border-0 shadow-sm py-5 text-center bg-white rounded-4">
            <div class="card-body p-5">
                <div class="mb-4">
                    <div class="bg-light d-inline-flex p-4 rounded-circle mb-3">
                        <i class="fas fa-file-invoice fa-3x text-muted opacity-50"></i>
                    </div>
                </div>
                <h5 class="fw-bold text-dark">Tudo pronto por aqui!</h5>
                <p class="text-muted mb-4 mx-auto" style="max-width: 400px;">Nenhuma nota fiscal pendente foi encontrada. Clique no botão de atualização para buscar novos documentos na SEFAZ.</p>
                <button class="btn btn-primary px-5 rounded-pill fw-bold py-2 shadow-sm" onclick="sincronizarSefaz()">
                    <i class="fas fa-sync-alt me-2"></i>Sincronizar com SEFAZ Agora
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="accordion border-0" id="accordionFornecedores">
            <?php foreach ($fornecedores as $i => $f): ?>
                <div class="accordion-item border-0 mb-3 rounded-4 shadow-sm overflow-hidden bg-white">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed fw-bold bg-white py-3 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#forn_<?= $i ?>">
                            <div class="d-flex justify-content-between w-100 align-items-center me-3">
                                <div>
                                    <span class="p-2 bg-primary bg-opacity-10 rounded-3 me-3">
                                        <i class="fas fa-truck text-primary"></i>
                                    </span>
                                    <span class="text-dark"><?= htmlspecialchars($f['fornecedor_nome']) ?></span>
                                    <span class="ms-2 opacity-50 fw-normal small"><?= $f['fornecedor_cnpj'] ?></span>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill me-3 px-3"><?= $f['total_notas'] ?> Nota(s)</span>
                                    <span class="fw-bold text-dark me-2">R$ <?= number_format($f['valor_acumulado'], 2, ',', '.') ?></span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="forn_<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#accordionFornecedores">
                        <div class="accordion-body p-0 border-top bg-light bg-opacity-25">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background: rgba(43, 76, 125, 0.02);">
                                    <tr>
                                        <th class="ps-4 text-muted small text-uppercase fw-bold py-3" style="font-size: 0.75rem;">Número</th>
                                        <th class="text-muted small text-uppercase fw-bold py-3" style="font-size: 0.75rem;">Emissão</th>
                                        <th class="text-muted small text-uppercase fw-bold py-3" style="font-size: 0.75rem;">Valor Total</th>
                                        <th class="text-muted small text-uppercase fw-bold py-3" style="font-size: 0.75rem;">Status</th>
                                        <th class="text-end pe-4 text-muted small text-uppercase fw-bold py-3" style="font-size: 0.75rem;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($f['notas'] as $nota): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <span class="fw-bold text-dark">#<?= $nota['numero_nota'] ?></span>
                                            </td>
                                            <td><span class="text-muted small"><?= date('d/m/Y H:i', strtotime($nota['data_emissao'])) ?></span></td>
                                            <td><span class="fw-bold text-primary">R$ <?= number_format($nota['valor_total'], 2, ',', '.') ?></span></td>
                                            <td>
                                                <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2 rounded-pill fw-bold" style="font-size: 0.7rem;">
                                                    <i class="fas fa-history me-1"></i> PENDENTE
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                                                    <button class="btn btn-sm btn-white border-0 text-info fw-bold py-2" onclick="manifestarNota(<?= $nota['id'] ?>)" title="Ciência da Operação">
                                                        <i class="fas fa-file-signature me-1"></i> Manifestar
                                                    </button>
                                                    <button class="btn btn-sm btn-white border-0 text-primary fw-bold py-2" onclick="visualizarItens(<?= $nota['id'] ?>)">
                                                        <i class="fas fa-list me-1"></i> Itens
                                                    </button>
                                                    <a href="importar_automatico.php?action=baixar_xml&id=<?= $nota['id'] ?>" class="btn btn-sm btn-white border-0 text-muted py-2" title="Baixar XML">
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
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden rounded-4">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="fas fa-boxes me-3 p-2 bg-white bg-opacity-10 rounded-3"></i>Produtos da Nota Fiscal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light bg-opacity-25">
                <div id="loaderItens" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted fw-bold">Lendo XML e extraindo produtos...</p>
                </div>
                <div id="errorItens" class="alert alert-danger m-3 d-none border-0 shadow-sm"></div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle d-none bg-white" id="tableItens">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-muted small text-uppercase py-3">Cód. Forn.</th>
                                <th class="text-muted small text-uppercase py-3">Descrição / Nome</th>
                                <th class="text-muted small text-uppercase py-3">NCM</th>
                                <th class="text-muted small text-uppercase py-3">Qtd Com.</th>
                                <th class="text-muted small text-uppercase py-3">V. Unitário</th>
                                <th class="text-end pe-4 text-muted small text-uppercase py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItens"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white border-0 py-3 px-4 shadow-sm">
                <button type="button" class="btn btn-light fw-bold px-4" data-bs-dismiss="modal">FECHAR</button>
                <button type="button" id="btnImportarTudo" class="btn btn-success fw-bold px-5 rounded-pill shadow-sm d-none">
                    <i class="fas fa-check-double me-2"></i>DAR ENTRADA NA NOTA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
async function sincronizarSefaz(deep = false) {
    if (deep && !confirm('A Busca Profunda irá re-escanear as notas dos últimos 90 dias na SEFAZ. Deseja continuar?')) return;
    
    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=sincronizar' + (deep ? '&reset=1' : ''));
        const result = await response.json();
        
        if (result.success) {
            if (result.hasMore) {
                if (confirm(result.message + "\n\nDeseja continuar buscando o restante agora?")) {
                    sincronizarSefaz(false); // Continua do NSU atual
                    return;
                }
            } else {
                alert(result.message);
            }
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

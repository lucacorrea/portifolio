<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="h4 mb-1 fw-bold text-dark">NF-es recebidas da SEFAZ</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item small"><a href="dashboard.php" class="text-decoration-none text-muted">Fiscal</a></li>
                    <li class="breadcrumb-item small active text-muted" aria-current="page">NF-es recebidas da SEFAZ</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <div class="d-inline-flex gap-2">
                <?php if (in_array($_SESSION['usuario_nivel'], ['master', 'admin']) && ($_SESSION['is_matriz'] ?? false)): ?>
                    <a href="importar_automatico.php?action=config" class="btn btn-outline-secondary btn-sm fw-bold border-0 shadow-none">
                        <i class="fas fa-cog me-2"></i>CONFIGURAÇÕES GLOBAIS
                    </a>
                <?php endif; ?>
                <div class="btn-group shadow-sm">
                    <button class="btn btn-primary btn-sm fw-bold px-3 py-2" onclick="sincronizarSefaz()">
                        <i class="fas fa-sync-alt me-2"></i>ATUALIZAR NOTAS SEFAZ
                    </button>
                    <button type="button" class="btn btn-primary btn-sm dropdown-toggle dropdown-toggle-split border-start border-white border-opacity-25" data-bs-toggle="dropdown" aria-expanded="false"></button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
                        <li>
                            <a class="dropdown-item fw-bold py-2" href="#" onclick="sincronizarSefaz(true)">
                                <i class="fas fa-search-plus me-2 text-primary"></i>BUSCA PROFUNDA (90 Dias)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4 rounded-3 overflow-hidden">
        <div class="card-body p-3 bg-white">
            <form action="importar_automatico.php" method="GET" class="row g-3 align-items-center">
                <div class="col-lg-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-light-subtle"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-light-subtle bg-light" placeholder="Busque por fornecedor, número ou valor" value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>
                <div class="col-lg-2">
                    <select name="status" class="form-select form-select-sm border-light-subtle bg-light">
                        <option value="todas" <?= $filters['status'] == 'todas' ? 'selected' : '' ?>>Todos Lançamentos</option>
                        <option value="pendente" <?= $filters['status'] == 'pendente' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="importada" <?= $filters['status'] == 'importada' ? 'selected' : '' ?>>Lançadas</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-light-subtle small fw-bold">DE</span>
                        <input type="date" name="desde" class="form-control border-light-subtle bg-light" value="<?= htmlspecialchars($filters['desde']) ?>">
                    </div>
                </div>
                <div class="col-lg-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-light-subtle small fw-bold">ATÉ</span>
                        <input type="date" name="ate" class="form-control border-light-subtle bg-light" value="<?= htmlspecialchars($filters['ate']) ?>">
                    </div>
                </div>
                <div class="col-lg-2 d-flex gap-2">
                    <button type="submit" class="btn btn-secondary btn-sm fw-bold px-3">FILTRAR</button>
                    <a href="importar_automatico.php" class="btn btn-outline-light btn-sm fw-bold text-muted border-0"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela Principal -->
    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="mainNfeTable">
                    <thead class="bg-light bg-opacity-50">
                        <tr>
                            <th class="ps-4 py-3 text-muted extra-small text-uppercase fw-bold" style="width: 140px;">Data da emissão</th>
                            <th class="py-3 text-muted extra-small text-uppercase fw-bold">Fornecedor</th>
                            <th class="py-3 text-muted extra-small text-uppercase fw-bold" style="width: 120px;">Número</th>
                            <th class="py-3 text-muted extra-small text-uppercase fw-bold" style="width: 150px;">Valor</th>
                            <th class="py-3 text-muted extra-small text-uppercase fw-bold" style="width: 180px;">Manifestação</th>
                            <th class="py-3 text-muted extra-small text-uppercase fw-bold" style="width: 120px;">Lançar NF-e</th>
                            <th class="pe-4 py-3 text-center text-muted extra-small text-uppercase fw-bold" style="width: 80px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white border-top-0">
                        <?php if (empty($notas)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="opacity-50 mb-3">
                                        <i class="fas fa-file-invoice fa-4x text-muted"></i>
                                    </div>
                                    <h6 class="fw-bold text-muted">Nenhuma nota fiscal encontrada</h6>
                                    <p class="small text-muted mb-0">Tente ajustar seus filtros ou clique em "Sincronizar" para buscar novas notas.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notas as $nota): ?>
                                <tr class="border-bottom-0">
                                    <td class="ps-4">
                                        <span class="text-dark fw-medium small"><?= date('d/m/Y', strtotime($nota['data_emissao'])) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary small text-uppercase"><?= htmlspecialchars($nota['fornecedor_nome']) ?></div>
                                        <div class="extra-small text-muted"><?= $nota['fornecedor_cnpj'] ?></div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark font-monospace"><?= $nota['numero_nota'] ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-dark">R$ <?= number_format($nota['valor_total'], 2, ',', '.') ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                            $m_tipo = $nota['manifestacao_tipo'] ?? '';
                                            $m_label = 'Manifestar';
                                            $m_class = 'btn-outline-primary';
                                            $m_title = 'Clique para manifestar esta nota na SEFAZ';
                                            
                                            if ($m_tipo == '210200') { $m_label = 'Confirmada'; $m_class = 'btn-success'; }
                                            elseif ($m_tipo == '210210') { $m_label = 'Ciência'; $m_class = 'btn-info text-white'; }
                                            elseif ($m_tipo == '210220') { $m_label = 'Desconhecida'; $m_class = 'btn-danger'; }
                                            elseif ($m_tipo == '210240') { $m_label = 'Não Realizada'; $m_class = 'btn-warning'; }
                                        ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm <?= $m_class ?> fw-bold px-3 py-1 w-100 rounded-1 extra-small dropdown-toggle" 
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <?= $m_label ?>
                                            </button>
                                            <ul class="dropdown-menu shadow-lg border-0 extra-small">
                                                <li><a class="dropdown-item py-2 fw-bold text-success" href="#" onclick="manifestarNota(<?= $nota['id'] ?>, '210200')"><i class="fas fa-check-circle me-2"></i>Confirmar Operação</a></li>
                                                <li><a class="dropdown-item py-2 fw-bold text-info" href="#" onclick="manifestarNota(<?= $nota['id'] ?>, '210210')"><i class="fas fa-eye me-2"></i>Ciência da Operação</a></li>
                                                <li><a class="dropdown-item py-2 fw-bold text-warning" href="#" onclick="manifestarNota(<?= $nota['id'] ?>, '210240')"><i class="fas fa-times-circle me-2"></i>Operação Não Realizada</a></li>
                                                <li><a class="dropdown-item py-2 fw-bold text-danger" href="#" onclick="manifestarNota(<?= $nota['id'] ?>, '210220')"><i class="fas fa-question-circle me-2"></i>Desconhecimento da Operação</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($nota['status'] === 'importada'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 w-100 py-2 fw-bold">LANÇADA</span>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm fw-bold w-100 rounded-1 extra-small py-1" onclick="visualizarItens(<?= $nota['id'] ?>)">Lançar</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-link text-muted p-0 border-0" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 extra-small">
                                                <li><a class="dropdown-item py-2" href="importar_automatico.php?action=baixar_xml&id=<?= $nota['id'] ?>"><i class="fas fa-file-code me-2 text-muted"></i>Baixar XML</a></li>
                                                <li><a class="dropdown-item py-2" href="importar_automatico.php?action=baixar_danfe&id=<?= $nota['id'] ?>" target="_blank"><i class="fas fa-file-pdf me-2 text-muted"></i>Baixar DANFE</a></li>
                                                <li><a class="dropdown-item py-2" href="#" onclick="navigator.clipboard.writeText('<?= $nota['chave_acesso'] ?>'); alert('Chave copiada!')"><i class="fas fa-copy me-2 text-muted"></i>Copiar chave</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item py-2 text-primary fw-bold" href="https://www.nfe.fazenda.gov.br/portal/consultaRecaptcha.aspx?tipoConsulta=resumo" target="_blank">Manifestação Manual (Portal)</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination UI -->
            <?php if (isset($pagination) && $pagination['totalPages'] > 1): ?>
                <div class="card-footer bg-white border-top border-light p-3">
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <?php 
                                $p = $pagination['page'];
                                $tp = $pagination['totalPages'];
                                $params = $_GET;
                                
                                $buildUrl = function($pageNum) use ($params) {
                                    $params['page'] = $pageNum;
                                    return '?' . http_build_query($params);
                                };
                            ?>
                            
                            <li class="page-item <?= ($p <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link shadow-none" href="<?= $buildUrl(1) ?>" title="Primeira"><i class="fas fa-angle-double-left"></i></a>
                            </li>
                            <li class="page-item <?= ($p <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link shadow-none" href="<?= $buildUrl($p - 1) ?>" title="Anterior"><i class="fas fa-angle-left"></i></a>
                            </li>
                            
                            <?php 
                                $start = max(1, $p - 2);
                                $end = min($tp, $p + 2);
                                for ($i = $start; $i <= $end; $i++): 
                            ?>
                                <li class="page-item <?= ($i == $p) ? 'active' : '' ?>">
                                    <a class="page-link shadow-none" href="<?= $buildUrl($i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= ($p >= $tp) ? 'disabled' : '' ?>">
                                <a class="page-link shadow-none" href="<?= $buildUrl($p + 1) ?>" title="Próxima"><i class="fas fa-angle-right"></i></a>
                            </li>
                            <li class="page-item <?= ($p >= $tp) ? 'disabled' : '' ?>">
                                <a class="page-link shadow-none" href="<?= $buildUrl($tp) ?>" title="Última"><i class="fas fa-angle-double-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status da Rede (Sempre visível no rodapé ou similar) -->
    <div class="mt-4 d-flex justify-content-between align-items-center opacity-75">
        <div class="extra-small text-muted">
             <i class="far fa-clock me-1"></i> Última Sincronização: <?= !empty($lastSync) ? date('d/m/Y H:i', strtotime($lastSync)) : '---' ?>
        </div>
        <div class="extra-small">
            <span class="text-success fw-bold"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> Status Network: Online</span>
        </div>
    </div>
</div>

<!-- Modal Visualizar Itens -->
<div class="modal fade" id="modalItens" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden rounded-3">
            <div class="modal-header bg-erp-primary text-white border-0 py-3 shadow-sm">
                <h5 class="modal-title fw-bold d-flex align-items-center small">
                    <i class="fas fa-boxes me-3 p-2 bg-white bg-opacity-20 rounded-2 text-white"></i>Produtos da Nota Fiscal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="loaderItens" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted fw-bold small">Lendo XML e extraindo produtos...</p>
                </div>
                <div id="errorItens" class="alert alert-danger m-3 d-none border-0 shadow-sm extra-small"></div>
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle d-none bg-white small" id="tableItens">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-muted extra-small text-uppercase py-3">Cód. Forn.</th>
                                <th class="text-muted extra-small text-uppercase py-3">Descrição / Nome</th>
                                <th class="text-muted extra-small text-uppercase py-3">NCM</th>
                                <th class="text-muted extra-small text-uppercase py-3">Qtd Com.</th>
                                <th class="text-muted extra-small text-uppercase py-3">V. Unitário</th>
                                <th class="text-end pe-4 text-muted extra-small text-uppercase py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItens"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light bg-opacity-50 border-0 py-3 px-4 shadow-sm">
                <button type="button" class="btn btn-light btn-sm fw-bold px-4" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" id="btnImportarTudo" class="btn btn-success btn-sm fw-bold px-5 rounded-1 shadow-sm d-none">
                    <i class="fas fa-check-double me-2"></i>CONFIRMAR LANÇAMENTO NO ESTOQUE
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

async function manifestarNota(id, type = '210210') {
    const messages = {
        '210200': 'Deseja CONFIRMAR a operação desta nota?',
        '210210': 'Deseja realizar a CIÊNCIA da operação?',
        '210220': 'Deseja registrar o DESCONHECIMENTO desta operação?',
        '210240': 'Deseja registrar que esta operação NÃO FOI REALIZADA?'
    };

    if (!confirm(messages[type] || 'Deseja manifestar esta nota?')) return;

    showLoader();
    try {
        const response = await fetch(`importar_automatico.php?action=manifestar&id=${id}&type=${type}`);
        const result = await response.json();
        if (result.success) {
            alert(result.message);
            location.reload();
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
        
        if (!response.ok) {
            throw new Error(`Servidor retornou erro ${response.status}`);
        }

        const result = await response.json();

        if (result.success) {
            activeItems = result.produtos;
            if (activeItems.length === 0) {
                error.innerText = 'Esta nota não possui itens de produto.';
                error.classList.remove('d-none');
            } else {
                result.produtos.forEach(p => {
                    const row = `
                        <tr>
                            <td class="ps-4 extra-small font-monospace">${p.codigo}</td>
                            <td class="fw-bold small">${p.nome}</td>
                            <td class="extra-small">${p.ncm}</td>
                            <td class="text-primary fw-bold small">${p.qCom}</td>
                            <td class="small">R$ ${p.vUnComFormatted}</td>
                            <td class="text-end pe-4">
                                <span class="badge bg-light text-muted extra-small">Pendente</span>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
                table.classList.remove('d-none');
                btnTudo.classList.remove('d-none');
            }
        } else {
            error.innerText = result.error;
            error.classList.remove('d-none');
        }
    } catch (e) {
        error.innerText = 'Falha ao processar resposta: ' + e.message;
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

document.addEventListener('DOMContentLoaded', () => {
    const lastSyncStr = '<?= $lastSync ?? "" ?>';
    if (!lastSyncStr) {
        // Primeira vez: não sincroniza automaticamente, deixa o usuário clicar
        console.log('Nenhuma sincronização anterior. Aguardando ação manual.');
        return;
    }
    const lastSync = new Date(lastSyncStr.replace(' ', 'T'));
    const now = new Date();
    const diffMinutes = Math.floor((now - lastSync) / 1000 / 60);
    // Apenas se a última sync foi há mais de 2 horas (SEFAZ exige intervalo mínimo de 1h)
    if (diffMinutes >= 120) {
        console.log(`Auto-sync: última há ${diffMinutes} min. Sincronizando em background...`);
        // Sync silenciosa em background (sem alert, sem reload)
        fetch('importar_automatico.php?action=sincronizar')
            .then(r => r.json())
            .then(res => { if (res.count > 0) location.reload(); })
            .catch(() => {});
    }
});
</script>

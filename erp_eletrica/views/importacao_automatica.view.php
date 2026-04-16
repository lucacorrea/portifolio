<style>
    /* 🚀 Solução Definitiva para Dropdowns em Tabelas */
    .table-responsive {
        overflow: visible !important;
        min-height: 350px; /* Garante espaço para o menu mesmo com poucas linhas */
    }
    
    #mainNfeTable td {
        position: static !important; /* Permite que o menu flutue baseado na tabela e não na linha */
    }

    #mainNfeTable .dropdown-menu {
        z-index: 10000 !important;
        box-shadow: 0 1rem 3rem rgba(0,0,0,0.2) !important;
    }

    /* Ajuste para mobile para não quebrar o layout */
    @media (max-width: 991px) {
        .table-responsive {
            overflow-x: auto !important;
            min-height: 250px;
        }
    }
</style>

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
    
    <!-- Marcador de Progresso (Pedido pelo Usuário) -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm bg-light-subtle">
                <div class="card-body p-2 d-flex align-items-center gap-3">
                    <div class="bg-primary text-white p-2 rounded-circle" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-bookmark small"></i>
                    </div>
                    <div class="flex-grow-1">
                        <label class="small text-muted fw-bold d-block mb-0">Marcador de Progresso (Última Nota Conferida):</label>
                        <div class="input-group input-group-sm mt-1" style="max-width: 250px;">
                            <input type="text" id="userMarkerInput" class="form-control border-light-subtle" placeholder="Ex: 1234..." value="<?= htmlspecialchars($userMarker) ?>" onblur="saveNfeMarker(this.value)">
                            <button class="btn btn-outline-primary border-light-subtle px-2" type="button" onclick="saveNfeMarker(document.getElementById('userMarkerInput').value)">
                                <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-end d-none d-sm-block">
                        <small class="text-muted extra-small d-block">Use este campo para anotar onde parou na SEFAZ.</small>
                        <span id="markerStatus" class="badge bg-success-subtle text-success border border-success-subtle d-none">Salvo com sucesso!</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navegação por Abas -->
    <ul class="nav nav-tabs border-bottom-0 mb-3" id="importTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold text-uppercase small px-4 py-3 border-0 rounded-0" id="notas-tab" data-bs-toggle="tab" data-bs-target="#notas-pane" type="button" role="tab">
                <i class="fas fa-file-invoice me-2"></i>Notas Recebidas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-uppercase small px-4 py-3 border-0 rounded-0 position-relative" id="analise-tab" data-bs-toggle="tab" data-bs-target="#analise-pane" type="button" role="tab">
                <i class="fas fa-tasks me-2"></i>Itens em Análise
                <?php 
                    $db = \App\Config\Database::getInstance()->getConnection();
                    $pendAnalise = $db->query("SELECT COUNT(*) FROM nfe_importadas WHERE status = 'em_analise'")->fetchColumn();
                    if ($pendAnalise > 0): 
                ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="badgeAnalise">
                        <?= $pendAnalise ?>
                    </span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="importTabContent">
        <!-- ABA: NOTAS RECEBIDAS -->
        <div class="tab-pane fade show active" id="notas-pane" role="tabpanel" tabindex="0">
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
                                <option value="em_analise" <?= $filters['status'] == 'em_analise' ? 'selected' : '' ?>>Em Análise</option>
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
    <div class="card border-0 shadow-sm rounded-3">
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
                                                    type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bs-boundary="viewport">
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
                                        <?php elseif ($nota['status'] === 'em_analise'): ?>
                                            <button class="btn btn-warning btn-sm fw-bold w-100 rounded-1 extra-small py-1" onclick="abrirAnalise(<?= $nota['id'] ?>)">EM ANÁLISE</button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm fw-bold w-100 rounded-1 extra-small py-1" onclick="iniciarAnalise(<?= $nota['id'] ?>)">Lançar</button>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-center">
                                        <div class="dropdown">
                                            <button class="btn btn-link text-muted p-0 border-0" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport">
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

        <!-- ABA: ITENS EM ANÁLISE -->
        <div class="tab-pane fade" id="analise-pane" role="tabpanel" tabindex="0">
            <div id="analise-content">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-5 text-center">
                        <div class="opacity-25 mb-4"><i class="fas fa-search-location fa-5x"></i></div>
                        <h5 class="fw-bold text-muted">Nenhuma nota selecionada para análise</h5>
                        <p class="text-muted small">Clique em "Lançar" em uma nota pendente para iniciar a conferência dos itens.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status da Rede -->
    <div class="mt-4 d-flex justify-content-between align-items-center opacity-75">
        <div class="extra-small text-muted">
             <i class="far fa-clock me-1"></i> Última Sincronização: <?= !empty($lastSync) ? date('d/m/Y H:i', strtotime($lastSync)) : '---' ?>
        </div>
        <div class="extra-small">
            <span class="text-success fw-bold"><i class="fas fa-circle me-1" style="font-size: 0.5rem;"></i> Status Network: Online</span>
        </div>
    </div>
</div>

<!-- Modal Vínculo Manual -->
<div class="modal fade" id="modalVinculo" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 py-3">
                <h6 class="modal-title fw-bold"><i class="fas fa-link me-2 text-primary"></i>Vincular Produto ao Sistema</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="extra-small text-muted text-uppercase fw-bold mb-1">Produto do Fornecedor</label>
                    <div id="vinc-forn-nome" class="fw-bold border-bottom pb-2"></div>
                </div>

                <div class="mb-4">
                    <label class="extra-small text-muted text-uppercase fw-bold mb-2">Selecione o Produto correspondente no Estoque</label>
                    <select id="vinc-produto-select" class="form-select shadow-sm">
                        <!-- Preenchido via JS -->
                    </select>
                    <div class="extra-small text-muted mt-2">Dica: Se não encontrar, você pode cadastrar este item como novo produto.</div>
                </div>

                <div class="card bg-light border-0 rounded-3 mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold small mb-3"><i class="fas fa-code me-2"></i>Referência de Código Interno</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="update_code" id="radioCodeTemp" value="0" checked>
                            <label class="form-check-label small" for="radioCodeTemp">
                                <span class="fw-bold">Usar código do fornecedor apenas nesta nota</span><br>
                                <span class="text-muted extra-small">O cadastro oficial do produto não será alterado.</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="update_code" id="radioCodePerm" value="1">
                            <label class="form-check-label small" for="radioCodePerm">
                                <span class="fw-bold">Atualizar código interno permanentemente</span><br>
                                <span class="text-muted extra-small">O código atual do seu produto será substituído pelo código do fornecedor para sempre.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light btn-sm fw-bold px-4" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" id="btnConfirmarVinculo" class="btn btn-primary btn-sm fw-bold px-4">CONFIRMAR VÍNCULO</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Itens (Original / Resumo) -->
<div class="modal fade" id="modalItens" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0 py-3">
                <h5 class="modal-title fw-bold small">Produtos da Nota (Resumo)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="loaderItens" class="text-center py-5 d-none"><div class="spinner-border text-primary" role="status"></div></div>
                <div id="errorItens" class="alert alert-danger m-3 d-none border-0 shadow-sm extra-small"></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle d-none bg-white small" id="tableItens">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 text-muted extra-small py-3">Código</th>
                                <th class="text-muted extra-small py-3">Nome</th>
                                <th class="text-muted extra-small py-3">Qtd</th>
                                <th class="text-end pe-4 text-muted extra-small py-3">Valor</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItens"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function iniciarAnalise(id) {
    if (!confirm('O sistema iniciará a conferência desta nota. Fornecedor e itens serão mapeados para sua revisão. Continuar?')) return;
    
    showLoader();
    try {
        const response = await fetch(`importar_automatico.php?action=iniciar_analise&id=${id}`);
        const result = await response.json();
        if (result.success) {
            abrirAnalise(id);
        } else {
            alert('Erro ao iniciar análise: ' + result.error);
        }
    } catch (e) {
        alert('Erro: ' + e.message);
    } finally {
        hideLoader();
    }
}

async function abrirAnalise(id) {
    activeNotaId = id;
    const tabEl = document.querySelector('#analise-tab');
    const tab = new bootstrap.Tab(tabEl);
    tab.show();

    const container = document.getElementById('analise-content');
    container.innerHTML = `<div class="p-5 text-center"><div class="spinner-border text-primary"></div><p class="mt-2 small text-muted">Carregando itens para conferência...</p></div>`;

    try {
        const response = await fetch(`importar_automatico.php?action=listar_analise&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            renderAnaliseGrid(result.itens);
        } else {
            container.innerHTML = `<div class="alert alert-danger m-3">${result.error}</div>`;
        }
    } catch (e) {
        container.innerHTML = `<div class="alert alert-danger m-3">Erro de conexão.</div>`;
    }
}

function renderAnaliseGrid(itens) {
    const container = document.getElementById('analise-content');
    
    let html = `
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold small text-uppercase"><i class="fas fa-search me-2 text-primary"></i>Conferência de Itens</h6>
                <button class="btn btn-success btn-sm fw-bold" onclick="finalizarImportacaoAnalisada(${activeNotaId})">
                    <i class="fas fa-check-double me-2"></i>CONCLUIR ENTRADA NO ESTOQUE
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3 text-muted extra-small">Item do Fornecedor</th>
                            <th class="py-3 text-muted extra-small" style="width: 350px;">Vínculo no Sistema</th>
                            <th class="py-3 text-muted extra-small text-center">Quantidade</th>
                            <th class="py-3 text-muted extra-small text-center">V. Unitário</th>
                            <th class="pe-4 py-3 text-end text-muted extra-small">Ação</th>
                        </tr>
                    </thead>
                    <tbody>`;

    itens.forEach(item => {
        let statusBadge = '';
        let vincDesc = '';
        
        if (item.status === 'pendente') {
            statusBadge = '<span class="badge bg-danger">PENDENTE</span>';
            vincDesc = '<span class="text-danger small">Nenhum produto correspondente encontrado.</span>';
        } else {
            statusBadge = `<span class="badge bg-success">${item.status === 'vinculado' ? 'VINCULADO' : 'NOVO'}</span>`;
            vincDesc = `
                <div class="fw-bold text-dark">${item.sistema_nome}</div>
                <div class="extra-small text-muted">Cód Interno: ${item.sistema_codigo}</div>
            `;
        }

        html += `
            <tr>
                <td class="ps-4">
                    <div class="fw-bold">${item.nome_item}</div>
                    <div class="extra-small text-muted">Cód Forn: ${item.codigo_fornecedor} | NCM: ${item.ncm}</div>
                </td>
                <td>${vincDesc}</td>
                <td class="text-center fw-bold text-primary">${item.quantidade} <small class="text-muted fw-normal">${item.unidade}</small></td>
                <td class="text-center small">R$ ${parseFloat(item.valor_unitario).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                <td class="pe-4 text-end">
                    <button class="btn btn-outline-primary btn-sm extra-small fw-bold" onclick="abrirModalVinculo(${item.id}, '${item.nome_item.replace(/'/g, "\\'")}', ${item.produto_id})">
                        <i class="fas fa-link me-1"></i>${item.produto_id ? 'RE-VINCULAR' : 'VINCULAR'}
                    </button>
                    ${!item.produto_id ? `
                        <button class="btn btn-outline-success btn-sm extra-small fw-bold ms-1" onclick="cadastrarEVincular(${item.id})">
                            <i class="fas fa-plus me-1"></i>NOVO
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    });

    html += `</tbody></table></div></div>`;
    container.innerHTML = html;
}

let activeAnaliseItem = null;
async function abrirModalVinculo(analiseId, nomeForn, currentProdutoId) {
    activeAnaliseItem = analiseId;
    document.getElementById('vinc-forn-nome').innerText = nomeForn;
    const select = document.getElementById('vinc-produto-select');
    select.innerHTML = '<option value="">Carregando produtos...</option>';
    
    const modal = new bootstrap.Modal(document.getElementById('modalVinculo'));
    modal.show();

    // Carregar produtos para o select (limitado ou via busca se necessário, aqui usaremos o que já está na memória se possível ou faz fetch rápido)
    try {
        const res = await fetch('api/produtos_search.php?limit=200'); // Assumindo que existe ou usaremos o InventoryModel.all() via endpoint
        const produtos = await res.json();
        
        select.innerHTML = '<option value="">Selecione um produto...</option>';
        produtos.forEach(p => {
            select.innerHTML += `<option value="${p.id}" ${p.id == currentProdutoId ? 'selected' : ''}>${p.codigo} - ${p.nome}</option>`;
        });
    } catch(e) {
        select.innerHTML = '<option value="">Erro ao carregar lista.</option>';
    }
}

document.getElementById('btnConfirmarVinculo').onclick = async () => {
    const produtoId = document.getElementById('vinc-produto-select').value;
    const updateCode = document.querySelector('input[name="update_code"]:checked').value;

    if (!produtoId) { alert('Selecione um produto.'); return; }

    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=vincular_item', {
            method: 'POST',
            body: JSON.stringify({
                analise_id: activeAnaliseItem,
                produto_id: produtoId,
                update_code: updateCode == '1'
            })
        });
        const res = await response.json();
        if (res.success) {
            bootstrap.Modal.getInstance(document.getElementById('modalVinculo')).hide();
            abrirAnalise(activeNotaId);
        } else {
            alert(res.error);
        }
    } catch(e) { alert(e.message); }
    finally { hideLoader(); }
};

async function cadastrarEVincular(id) {
    if (!confirm('Deseja cadastrar este item como um NOVO produto no estoque?')) return;
    
    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=cadastrar_e_vincular', {
            method: 'POST',
            body: JSON.stringify({ analise_id: id })
        });
        const res = await response.json();
        if (res.success) {
            abrirAnalise(activeNotaId);
        } else {
            alert(res.error);
        }
    } catch(e) { alert(e.message); }
    finally { hideLoader(); }
}

async function finalizarImportacaoAnalisada(notaId) {
    if (!confirm('Deseja concluir o lançamento desta nota? Todos os itens vinculados serão adicionados ao estoque.')) return;

    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=finalizar_importacao', {
            method: 'POST',
            body: JSON.stringify({ nota_id: notaId })
        });
        const res = await response.json();
        if (res.success) {
            alert('Importação concluída com sucesso!');
            location.href = 'importar_automatico.php';
        } else {
            alert(res.error);
        }
    } catch(e) { alert(e.message); }
    finally { hideLoader(); }
}

async function visualizarItens(id) {
    activeNotaId = id;
    const modalEl = document.getElementById('modalItens');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const loader = document.getElementById('loaderItens');
    const table = document.getElementById('tableItens');
    const error = document.getElementById('errorItens');
    const tbody = document.getElementById('tbodyItens');

    modal.show();
    loader.classList.remove('d-none');
    table.classList.add('d-none');
    error.classList.add('d-none');
    tbody.innerHTML = '';

    try {
        const response = await fetch(`importar_automatico.php?action=visualizar_produtos&id=${id}`);
        const result = await response.json();

        if (result.success) {
            if (result.em_analise) {
                modal.hide();
                abrirAnalise(id);
                return;
            }
            result.produtos.forEach(p => {
                tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 extra-small font-monospace">${p.codigo}</td>
                        <td class="fw-bold small">${p.nome}</td>
                        <td class="small">${p.qCom}</td>
                        <td class="text-end pe-4 small">R$ ${p.vUnComFormatted}</td>
                    </tr>`;
            });
            table.classList.remove('d-none');
        } else {
            error.innerText = result.error;
            error.classList.remove('d-none');
        }
    } catch (e) {
        error.innerText = e.message;
        error.classList.remove('d-none');
    } finally {
        loader.classList.add('d-none');
    }
}

async function sincronizarSefaz(deep = false) {
    if (deep && !confirm('A Busca Profunda irá re-escanear as notas dos últimos 90 dias na SEFAZ. Deseja continuar?')) return;
    
    showLoader();
    try {
        const response = await fetch('importar_automatico.php?action=sincronizar' + (deep ? '&reset=1' : ''));
        const result = await response.json();
        
        if (result.success) {
            if (result.hasMore) {
                if (confirm(result.message + "\n\nDeseja continuar buscando o restante agora?")) {
                    sincronizarSefaz(false);
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

document.addEventListener('DOMContentLoaded', () => {
    const lastSyncStr = '<?= $lastSync ?? "" ?>';
    if (lastSyncStr) {
        const lastSync = new Date(lastSyncStr.replace(' ', 'T'));
        const now = new Date();
        const diffMinutes = Math.floor((now - lastSync) / 1000 / 60);
        if (diffMinutes >= 120) {
            fetch('importar_automatico.php?action=sincronizar')
                .then(r => r.json())
                .then(res => { if (res.count > 0) location.reload(); })
                .catch(() => {});
        }
    }
});

function saveNfeMarker(val) {
    const status = document.getElementById('markerStatus');
    fetch('importar_automatico.php?action=save_marker', {
        method: 'POST',
        body: JSON.stringify({ marker: val }),
        headers: { 'Content-Type': 'application/json' }
    }).then(r => r.json())
    .then(data => {
        if (data.success) {
            status.classList.remove('d-none');
            setTimeout(() => status.classList.add('d-none'), 3000);
        }
    });
}
</script>

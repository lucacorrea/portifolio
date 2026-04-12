<div class="container-fluid py-4">
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="this.parentElement.remove()"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-lg mb-4 d-flex align-items-center justify-content-between" role="alert" style="border-left: 5px solid #198754 !important;">
            <div>
                <i class="fas fa-check-circle me-2 fs-5"></i>
                <span class="fw-bold"><?= htmlspecialchars($_GET['success']) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (isset($_GET['print_id'])): ?>
                    <a href="caixa_imprimir.php?id=<?= (int)$_GET['print_id'] ?>" target="_blank" class="btn btn-dark btn-sm fw-bold px-3">
                        <i class="fas fa-print me-2"></i>Imprimir Resumo Agora
                    </a>
                    <script>
                        window.addEventListener('load', function() {
                            window.open('caixa_imprimir.php?id=<?= (int)$_GET['print_id'] ?>', '_blank');
                        });
                    </script>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="position: static; padding: 0.5rem;"></button>
            </div>
        </div>
    <?php endif; ?>


    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="fw-bold mb-0"><?= $pageTitle ?></h2>
            <p class="text-muted small mb-0">Gestão de abertura, fechamento e movimentações de caixa por operador.</p>
        </div>
        <div class="col-auto">
            <?php if (!$caixaAberto): ?>
                <button type="button" class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalAbrirCaixa">
                    <i class="fas fa-unlock me-2"></i>Abrir Caixa
                </button>
            <?php else: ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#modalMovimentacao">
                        <i class="fas fa-exchange-alt me-2"></i>Sangria / Suprimento
                    </button>
                    <button type="button" class="btn btn-danger fw-bold" data-bs-toggle="modal" data-bs-target="#modalFecharCaixa">
                        <i class="fas fa-lock me-2"></i>Fechar Caixa
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($caixaAberto): ?>
    <!-- Resumo do Caixa Aberto -->
    <div class="row row-cols-1 row-cols-md-5 g-3 mb-4">
        <div class="col">
            <div class="card border-primary border-0 h-100 bg-primary text-black shadow-lg" style="transform: scale(1.02);">
                <div class="card-body">
                    <div class="text-white-50 small fw-bold text-uppercase mb-2"><i class="fas fa-wallet me-2"></i>Saldo Gaveta</div>
                    <h3 class="mb-0 fw-bold text-white">
                        <?= formatarMoeda($caixaAberto['valor_abertura'] + $summary['dinheiro_em_gaveta']) ?>
                    </h3>
                    <div class="text-white-50 extra-small mt-2 fw-bold">Esperado Físico</div>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card border-0 h-100 shadow-sm bg-secondary text-white">
                <div class="card-body">
                    <div class="text-white-50 small fw-bold text-uppercase mb-2"><i class="fas fa-chart-line me-2"></i>Vendido (Total)</div>
                    <h4 class="mb-0 fw-bold text-white"><?= formatarMoeda($summary['total_bruto']) ?></h4>
                    <div class="text-white-50 extra-small mt-2 fw-bold">Base: Todos os tipos</div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card border-0 h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2"><i class="fas fa-money-bill-wave me-2"></i>Físico</div>
                    <h5 class="mb-0 fw-bold text-success">+ <?= formatarMoeda($summary['vendas_dinheiro'] + $summary['entradas_fiado_dinheiro']) ?></h5>
                    <div class="text-muted extra-small mt-2">Dinheiro + Sinal</div>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card border-0 h-100 shadow-sm bg-light">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2"><i class="fab fa-pix text-primary me-2"></i>Digitais</div>
                    <h5 class="mb-0 fw-bold text-primary">+ <?= formatarMoeda($summary['vendas_pix'] + $summary['vendas_cartao'] + $summary['vendas_boleto']) ?></h5>
                    <div class="text-muted extra-small mt-2">Pix, Cartões e Boleto</div>
                </div>
            </div>
        </div>
        
        <div class="col">
            <div class="card border-0 h-100 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small fw-bold text-uppercase mb-2"><i class="fas fa-arrow-up text-danger me-2"></i>Sangrias</div>
                    <h5 class="mb-0 fw-bold text-danger">- <?= formatarMoeda($summary['sangrias']) ?></h5>
                    <div class="text-muted extra-small mt-2">Retiradas do caixa</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Histórico de Caixas -->
    <div class="card border-0 ">
        <div class="card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-secondary">Histórico de Sessões</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Operador</th>
                            <th>Data/Hora Abertura</th>
                            <th>Data/Hora Fechamento</th>
                            <th>Status</th>
                            <th>Valor Abertura</th>
                            <th>Valor Fechamento</th>
                            <th class="pe-4 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($caixas as $c): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="fw-bold"><?= htmlspecialchars($c['operador_nome'] ?? 'Operador') ?></span>
                                <div class="text-muted small">
                                    <i class="fas fa-store-alt me-1 opacity-50"></i>
                                    <?= $c['filial_principal'] ? 'MATRIZ' : htmlspecialchars($c['filial_nome'] ?? 'Filial #' . $c['filial_id']) ?>
                                </div>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($c['data_abertura'])) ?></td>
                            <td><?= $c['data_fechamento'] ? date('d/m/Y H:i', strtotime($c['data_fechamento'])) : '-' ?></td>
                            <td>
                                <span class="badge bg-<?= $c['status'] == 'aberto' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $c['status'] == 'aberto' ? 'success' : 'secondary' ?> rounded-pill">
                                    <?= strtoupper($c['status']) ?>
                                </span>
                            </td>
                            <td><?= formatarMoeda($c['valor_abertura']) ?></td>
                            <td><?= $c['valor_fechamento'] ? formatarMoeda($c['valor_fechamento']) : '-' ?></td>
                            <td class="pe-4 text-end">
                                <a href="caixa.php?action=detalhes&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Ver detalhes"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination UI -->
        <?php if (isset($pagination) && $pagination['totalPages'] > 1): 
            $p = $pagination['page'];
            $tp = $pagination['totalPages'];
            $buildUrl = function($pageNum) {
                $params = $_GET;
                $params['page'] = $pageNum;
                return '?' . http_build_query($params);
            };
        ?>
        <div class="card-footer bg-white py-3 border-0 rounded-bottom d-flex align-items-center justify-content-between">
            <span class="text-muted small">
                Mostrando <strong><?= count($caixas) ?></strong> de <strong><?= $pagination['totalItems'] ?></strong> registros
            </span>
            <nav aria-label="Navegação da listagem">
                <ul class="pagination pagination-sm mb-0 shadow-sm">
                    <li class="page-item <?= ($p <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(1) ?>"><i class="fas fa-angle-double-left"></i></a>
                    </li>
                    <li class="page-item <?= ($p <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(max(1, $p - 1)) ?>"><i class="fas fa-angle-left"></i></a>
                    </li>
                    
                    <?php 
                        $start = max(1, $p - 2);
                        $end = min($tp, $p + 2);
                        for ($i = $start; $i <= $end; $i++): 
                    ?>
                        <li class="page-item <?= ($i == $p) ? 'active' : '' ?>">
                            <a class="page-link px-3 <?= ($i == $p) ? 'fw-bold' : '' ?>" href="<?= $buildUrl($i) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item <?= ($p >= $tp) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl(min($tp, $p + 1)) ?>"><i class="fas fa-angle-right"></i></a>
                    </li>
                    <li class="page-item <?= ($p >= $tp) ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $buildUrl($tp) ?>"><i class="fas fa-angle-double-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Abrir Caixa -->
<div class="modal fade" id="modalAbrirCaixa" tabindex="-1">
    <div class="modal-dialog">
        <form action="caixa.php?action=abrir" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Abrir Novo Caixa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle me-2"></i>Informe o valor inicial disponível no fundo de caixa.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Valor de Abertura (R$)</label>
                    <input type="number" step="0.01" value="0.00" name="valor_abertura" class="form-control form-control-lg text-center fw-bold" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary fw-bold">Abrir Caixa</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Movimentação -->
<?php if ($caixaAberto): ?>
<div class="modal fade" id="modalMovimentacao" tabindex="-1">
    <div class="modal-dialog">
        <form action="caixa.php?action=movimentar" method="POST" class="modal-content">
            <input type="hidden" name="caixa_id" value="<?= $caixaAberto['id'] ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Sangria ou Suprimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Tipo de Movimentação</label>
                    <select name="tipo" class="form-select" required>
                        <option value="suprimento">Suprimento (Entrada de valor)</option>
                        <option value="sangria">Sangria (Retirada de valor)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Valor (R$)</label>
                    <input type="number" step="0.01" name="valor" class="form-control form-control-lg text-center fw-bold" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Motivo / Descrição (Opcional)</label>
                    <input type="text" name="motivo" class="form-control" placeholder="Ex: Adição de troco, Retirada para almoço...">
                </div>

                <?php if (!in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'master'])): ?>
                <div class="card bg-light border-0 mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3 text-primary"><i class="fas fa-shield-alt me-2"></i>Autorização Administrativa</h6>
                        <p class="extra-small text-muted mb-3">Esta operação exige liberação de um Administrador.</p>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Código de Uso Único</label>
                            <input type="text" name="auth_code" class="form-control form-control-sm text-center fw-bold" placeholder="000000">
                            <div class="form-text extra-small text-center mt-1">Obtido na tela de login via "Gerar Código"</div>
                        </div>
                        
                        <div class="text-center my-2">
                             <span class="badge bg-secondary extra-small">OU</span>
                        </div>

                        <div class="mb-0">
                            <label class="form-label small fw-bold">Senha do Administrador</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="auth_password" class="form-control text-center" placeholder="••••••••">
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePasswordVisibility(this)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-warning fw-bold text-dark">Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Fechar Caixa -->
<div class="modal fade" id="modalFecharCaixa" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="caixa.php?action=fechar" method="POST" class="modal-content border-0 shadow-lg">
            <input type="hidden" name="caixa_id" value="<?= $caixaAberto['id'] ?>">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-bold"><i class="fas fa-lock me-2"></i>Fechamento de Caixa</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-12">
                        <div class="table-responsive rounded-3 border">
                            <table class="table table-hover align-middle mb-0" id="tableFechamento">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-3">TIPO PAGAMENTOS</th>
                                        <th class="text-center">SISTEMA</th>
                                        <th class="text-center">INFORMADO</th>
                                        <th class="pe-3 text-end">DIFERENÇA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $metodos = ['A PRAZO', 'CARTAO', 'DINHEIRO', 'PIX'];
                                    $breakdown = ($detailedSummary && is_array($detailedSummary['breakdown'])) ? $detailedSummary['breakdown'] : [];

                                    foreach ($metodos as $metodo): 
                                        $calculado = $breakdown[$metodo] ?? 0;
                                    ?>

                                    <tr>
                                        <td class="ps-3 fw-bold text-secondary"><?= $metodo ?></td>
                                        <td class="text-center text-muted">
                                            R$ <span class="calc-val" data-metodo="<?= $metodo ?>"><?= number_format($calculado, 2, ',', '.') ?></span>
                                            <input type="hidden" class="calc-raw" name="calculado[<?= $metodo ?>]" value="<?= $calculado ?>">
                                        </td>
                                        <td style="width: 150px;">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-white border-end-0">R$</span>
                                                <input type="number" step="0.01" value="0.00" name="breakdown[<?= $metodo ?>]" 
                                                       class="form-control form-control-sm informed-input text-end border-start-0 fw-bold" 
                                                       data-metodo="<?= $metodo ?>"
                                                       oninput="calcularDiferenca('<?= $metodo ?>')">
                                            </div>
                                        </td>
                                        <td class="pe-3 text-end fw-bold">
                                            <span class="diff-val text-muted" id="diff-<?= str_replace(' ', '-', $metodo) ?>">R$ 0,00</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light fw-bold">
                                    <tr>
                                        <td class="ps-3">TOTAIS:</td>
                                        <td class="text-center" id="total-sistema">R$ <?= number_format($detailedSummary['total_vendas'], 2, ',', '.') ?></td>
                                        <td class="text-center" id="total-informado">R$ 0,00</td>
                                        <td class="pe-3 text-end" id="total-diferenca">R$ 0,00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3"><i class="fas fa-cash-register me-2"></i>Resumo do Fluxo</h6>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Valor Abertura:</span>
                                    <span class="fw-bold"><?= formatarMoeda($caixaAberto['valor_abertura']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Suprimentos:</span>
                                    <span class="text-success small fw-bold">+ <?= formatarMoeda($detailedSummary['suprimento']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted small">Sangrias:</span>
                                    <span class="text-danger small fw-bold">- <?= formatarMoeda($detailedSummary['sangria']) ?></span>
                                </div>
                                <hr class="my-2">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold small">Saldo Final em Gaveta:</span>
                                    <span class="text-primary fw-bold" id="label-saldo-final">
                                        <?= formatarMoeda($caixaAberto['valor_abertura'] + $detailedSummary['saldo']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Observações / Justificativa</label>
                            <textarea name="justificativa" class="form-control form-control-sm" rows="4" placeholder="Alguma observação sobre as diferenças ou motivo do fechamento..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-link link-secondary text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#modalConfirmacaoRapida">Voltar</button>
                <div class="ms-auto">
                    <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-lg px-4 fw-bold shadow-sm">Confirmar Fechamento</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function calcularDiferenca(metodo) {
    const input = document.querySelector(`input[name="breakdown[${metodo}]"]`);
    const calcVal = parseFloat(document.querySelector(`input[name="calculado[${metodo}]"]`).value);
    const infVal = parseFloat(input.value) || 0;
    const diff = infVal - calcVal;
    
    const diffSpan = document.getElementById(`diff-${metodo.replace(/ /g, '-')}`);
    diffSpan.innerText = 'R$ ' + diff.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    
    if (diff > 0) {
        diffSpan.className = 'diff-val text-success';
    } else if (diff < 0) {
        diffSpan.className = 'diff-val text-danger';
    } else {
        diffSpan.className = 'diff-val text-muted';
    }
    
    atualizarTotaisFechamento();
}

function atualizarTotaisFechamento() {
    let totalInf = 0;
    document.querySelectorAll('.informed-input').forEach(input => {
        totalInf += parseFloat(input.value) || 0;
    });
    
    const totalCalc = <?= (float)$detailedSummary['total_vendas'] ?>;
    const totalDiff = totalInf - totalCalc;
    
    document.getElementById('total-informado').innerText = 'R$ ' + totalInf.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    
    const diffLabel = document.getElementById('total-diferenca');
    diffLabel.innerText = 'R$ ' + totalDiff.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    diffLabel.className = 'pe-3 text-end ' + (totalDiff >= 0 ? (totalDiff > 0 ? 'text-success' : 'text-muted') : 'text-danger');
}
</script>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation for Movement Modal (Sangria/Suprimento)
    const movementModal = document.getElementById('modalMovimentacao');
    if (movementModal) {
        const form = movementModal.querySelector('form');
        const btnSubmit = form.querySelector('button[type="submit"]');
        const valorInput = form.querySelector('input[name="valor"]');
        const motivoInput = form.querySelector('input[name="motivo"]');
        const authCode = form.querySelector('input[name="auth_code"]');
        const authPass = form.querySelector('input[name="auth_password"]');
        const isAdmin = <?= in_array($_SESSION['usuario_nivel'] ?? '', ['admin', 'master']) ? 'true' : 'false' ?>;

        function validateMovement() {
            let isValid = true;
            
            // 1. Basic fields (Valor is still required)
            if (!valorInput.value || parseFloat(valorInput.value) <= 0) isValid = false;

            // 2. Authorization (if not admin/master)
            if (!isAdmin) {
                const codeValue = authCode.value.trim();
                const passValue = authPass.value.trim();

                if (!codeValue && !passValue) {
                    isValid = false;
                    clearError();
                } else if (codeValue && codeValue.length === 6) {
                    // Check code validity via AJAX if it's 6 digits
                    validateCodeAjax(codeValue);
                    isValid = false; // Stay disabled until AJAX returns success
                } else if (passValue) {
                    isValid = true;
                    clearError();
                } else {
                    isValid = false;
                }
            }

            btnSubmit.disabled = !isValid;
            btnSubmit.style.opacity = isValid ? '1' : '0.5';
            btnSubmit.style.cursor = isValid ? 'pointer' : 'not-allowed';
        }

        let lastValidatedCode = '';
        async function validateCodeAjax(code) {
            if (code === lastValidatedCode) return;
            lastValidatedCode = code;

            const tipo = form.querySelector('select[name="tipo"]').value;
            
            try {
                const response = await fetch('caixa.php?action=validate_code', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: code, tipo: tipo })
                });
                const result = await response.json();

                if (result.success) {
                    showSuccess('Código Válido!');
                    btnSubmit.disabled = false;
                    btnSubmit.style.opacity = '1';
                    btnSubmit.style.cursor = 'pointer';
                } else {
                    showError(result.error);
                }
            } catch (e) {
                showError('Erro ao validar código.');
            }
        }

        function showError(msg) {
            let errDiv = form.querySelector('#auth-error');
            if (!errDiv) {
                errDiv = document.createElement('div');
                errDiv.id = 'auth-error';
                errDiv.className = 'text-danger extra-small mt-1 fw-bold text-center';
                authCode.parentNode.appendChild(errDiv);
            }
            errDiv.innerText = msg;
            errDiv.classList.remove('text-success');
            errDiv.classList.add('text-danger');
        }

        function showSuccess(msg) {
            showError(msg);
            const errDiv = form.querySelector('#auth-error');
            errDiv.classList.remove('text-danger');
            errDiv.classList.add('text-success');
        }

        function clearError() {
            const errDiv = form.querySelector('#auth-error');
            if (errDiv) errDiv.innerText = '';
        }

        form.addEventListener('input', validateMovement);
        form.querySelector('select[name="tipo"]').addEventListener('change', () => {
             lastValidatedCode = ''; // Forces re-validation if type changes
             validateMovement();
        });
        validateMovement(); // Initial check
    }
});
</script>

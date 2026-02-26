<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold text-secondary">Abertura de Chamado Técnico</h6>
    </div>
    <div class="card-body p-4">
        <form action="os.php?action=save" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            
            <div class="row g-4 mb-4">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Cliente / Requisitante</label>
                        <select name="cliente_id" class="form-select border-0 bg-light py-2" required>
                            <option value="">Selecione o cliente...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small text-uppercase">Descrição da Solicitação / Diagnóstico Inicial</label>
                        <textarea name="descricao" class="form-control border-0 bg-light" rows="6" required placeholder="Descreva detalhadamente o serviço..."></textarea>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Técnico Responsável</label>
                        <select name="tecnico_id" class="form-select border-0 bg-light py-2">
                            <option value="">Atribuir depois...</option>
                            <?php foreach ($tecnicos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] == $_SESSION['usuario_id'] ? 'selected' : '' ?>><?= $t['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-uppercase">Previsão de Conclusão</label>
                        <input type="date" name="data_previsao" class="form-control border-0 bg-light py-2" value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small text-uppercase">Prioridade</label>
                        <select name="prioridade" class="form-select border-0 bg-light py-2">
                            <option value="normal">Normal</option>
                            <option value="urgente">Urgente</option>
                            <option value="critico">Crítico (Parda Total)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="border-top pt-4 d-flex justify-content-end gap-2">
                <a href="os.php" class="btn btn-light px-4">Cancelar</a>
                <button type="submit" class="btn btn-primary px-5 shadow-sm">
                    <i class="fas fa-save me-2"></i>Registrar Ordem de Serviço
                </button>
            </div>
        </form>
    </div>
</div>

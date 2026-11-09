<div class="modal fade" id="modalEditarSolicitacao" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content pc-modal" id="formEditarSolicitacao" autocomplete="off">
      <div class="modal-header pc-modal-header">
        <div class="pc-modal-title-wrap">
          <span class="pc-modal-icon"><i class="bi bi-pencil-square"></i></span>
          <div>
            <small class="pc-modal-kicker">Editar solicitação</small>
            <h5 class="modal-title">Alterar dados da demanda</h5>
            <div class="pc-modal-subtitle" id="editarSolicitacaoDescricao">Atualize somente as informações necessárias.</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= pc_h($csrf) ?>">
        <input type="hidden" name="solicitacao_id" id="editarSolicitacaoId" value="0">
        <input type="hidden" name="solicitante_id" id="editarSolicitanteId" value="0">
        <input type="hidden" name="eh_inicial" id="editarSolicitacaoInicial" value="0">

        <div class="alert alert-info d-none mb-3" id="editarSolicitacaoInfo">
          <i class="bi bi-info-circle me-1"></i>
          A data da solicitação é independente da data original do cadastro da pessoa.
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-7">
            <label class="form-label" for="editarSolicitacaoAjuda">Tipo de ajuda</label>
            <select class="form-select" name="ajuda_tipo_id" id="editarSolicitacaoAjuda">
              <option value="">Sem tipo definido</option>
              <?php foreach ($ajudasTipos as $tipo): ?>
                <option value="<?= (int)$tipo['id'] ?>"><?= pc_h($tipo['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-5">
            <label class="form-label" for="editarSolicitacaoStatus">Status</label>
            <select class="form-select" name="status" id="editarSolicitacaoStatus" required>
              <option value="Aberto">Aberto</option>
              <option value="Em andamento">Em andamento</option>
              <option value="Concluído">Concluído</option>
              <option value="Cancelado">Cancelado</option>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label" for="editarSolicitacaoData">Data e hora da solicitação</label>
            <input
              class="form-control"
              type="datetime-local"
              name="data_solicitacao"
              id="editarSolicitacaoData"
              step="1"
              required
            >
            <div class="form-text">Esta alteração modifica somente a data da solicitação, não a data do cadastro do beneficiário.</div>
          </div>

          <div class="col-12">
            <label class="form-label" for="editarSolicitacaoResumo">Resumo da solicitação</label>
            <textarea
              class="form-control"
              name="resumo_caso"
              id="editarSolicitacaoResumo"
              rows="5"
              maxlength="3000"
              placeholder="Descreva a necessidade apresentada..."
            ></textarea>
          </div>
        </div>

        <div class="alert alert-danger d-none mt-3 mb-0" id="editarSolicitacaoErro" role="alert"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-check-lg me-1"></i> Salvar alterações
        </button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="modalNovaSolicitacao" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content pc-modal" id="formNovaSolicitacao">
      <div class="modal-header"><div><small class="pc-modal-kicker">Nova demanda</small><h5 class="modal-title">Registrar solicitação</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="csrf" value="<?= pc_h($csrf) ?>"><input type="hidden" name="solicitante_id" id="novaSolicitanteId">
        <div class="mb-3"><label class="form-label">Tipo de ajuda</label><select class="form-select" name="ajuda_tipo_id"><option value="">Selecione</option><?php foreach ($ajudasTipos as $tipo): ?><option value="<?= (int)$tipo['id'] ?>"><?= pc_h($tipo['nome']) ?></option><?php endforeach; ?></select></div>
        <div><label class="form-label">Resumo da solicitação</label><textarea class="form-control" name="resumo_caso" rows="4" maxlength="3000" placeholder="Descreva a necessidade apresentada..."></textarea></div>
        <div class="alert alert-danger d-none mt-3" id="novaSolicitacaoErro"></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar solicitação</button></div>
    </form>
  </div>
</div>

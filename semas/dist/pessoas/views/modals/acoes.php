<div class="modal fade" id="modalAcoesPessoa" tabindex="-1" aria-labelledby="modalAcoesPessoaTitulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content pc-modal pc-actions-modal">
      <div class="modal-header pc-modal-header">
        <div class="pc-modal-title-wrap">
          <span class="pc-modal-icon"><i class="bi bi-grid"></i></span>
          <div>
            <small class="pc-modal-kicker">Central de ações</small>
            <h5 class="modal-title" id="modalAcoesPessoaTitulo"><span id="acoesPessoaNome">Pessoa</span></h5>
            <div class="pc-modal-subtitle">Escolha o que deseja fazer com este cadastro.</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body">
        <div class="pc-action-person">
          <img id="acoesPessoaFoto" class="pc-action-person-photo" src="assets/images/user.png" alt="Foto da pessoa">
          <div>
            <strong id="acoesPessoaNomeResumo">Pessoa</strong>
            <span id="acoesPessoaResumo">Cadastro ANEXO selecionado</span>
          </div>
        </div>

        <div class="pc-action-grid <?= !empty($pcPermissions['isIntern']) ? 'pc-action-grid--three' : 'pc-action-grid--three' ?>">
          <button type="button" class="pc-action-card" data-pc-action="details">
            <i class="bi bi-person-vcard"></i>
            <strong>Visualizar cadastro</strong>
            <span>Dados pessoais, família, documentos e informações socioeconômicas.</span>
            <em><i class="bi bi-chevron-right"></i></em>
          </button>

          <button type="button" class="pc-action-card" data-pc-action="requests">
            <i class="bi bi-journal-text"></i>
            <strong>Ver solicitações</strong>
            <span>Consultar o histórico de solicitações desta pessoa.</span>
            <em><i class="bi bi-chevron-right"></i></em>
          </button>

          <?php if (!empty($pcPermissions['canCreateRequest'])): ?>
          <button type="button" class="pc-action-card" data-pc-action="new-request">
            <i class="bi bi-plus-circle"></i>
            <strong>Nova solicitação</strong>
            <span>Registrar uma nova demanda ou necessidade.</span>
            <em><i class="bi bi-chevron-right"></i></em>
          </button>
          <?php endif; ?>

          <?php if (!empty($pcPermissions['canEditPerson'])): ?>
          <a class="pc-action-card" id="acaoEditarPessoa" href="#">
            <i class="bi bi-pencil-square"></i>
            <strong>Editar cadastro</strong>
            <span>Atualizar as informações completas da pessoa.</span>
            <em><i class="bi bi-chevron-right"></i></em>
          </a>
          <?php endif; ?>

          <?php if (!empty($pcPermissions['canPrintSocio'])): ?>
          <a class="pc-action-card" id="acaoImprimirSocio" href="#" target="_blank" rel="noopener">
            <i class="bi bi-printer"></i>
            <strong>Folha socioeconômica</strong>
            <span>Abrir a folha completa em outra página para imprimir.</span>
            <em><i class="bi bi-box-arrow-up-right"></i></em>
          </a>
          <?php endif; ?>

          <?php if (empty($pcPermissions['isIntern'])): ?>
          <button type="button" class="pc-action-card" data-pc-action="benefits">
            <i class="bi bi-diagram-3"></i>
            <strong>Benefícios SIGAS</strong>
            <span>Consultar Primeiro Emprego, Comida na Mesa e integrações.</span>
            <em><i class="bi bi-chevron-right"></i></em>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="modal-footer pc-modal-footer-note">
        <?php if (!empty($pcPermissions['isIntern'])): ?>
          <span><i class="bi bi-shield-check"></i> Perfil Estagiário: acesso operacional somente para consultar, cadastrar e registrar novas solicitações.</span>
        <?php else: ?>
          <span><i class="bi bi-info-circle"></i> O cadastro não é alterado ao apenas visualizar informações.</span>
        <?php endif; ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

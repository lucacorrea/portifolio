<div class="modal fade" id="modalDetalhesPessoa" tabindex="-1" aria-labelledby="modalDetalhesPessoaTitulo" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content pc-modal pc-details-modal">
      <div class="modal-header pc-modal-header">
        <div class="pc-modal-title-wrap">
          <span class="pc-modal-icon"><i class="bi bi-person-vcard"></i></span>
          <div>
            <small class="pc-modal-kicker">Cadastro ANEXO</small>
            <h5 class="modal-title" id="modalDetalhesPessoaTitulo"><span id="detalheNome">Carregando...</span></h5>
            <div class="pc-modal-subtitle">Informações completas do cadastro selecionado.</div>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>

      <div class="modal-body pc-details-body">
        <div id="pcDetailLoading" class="pc-loading">
          <div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div>
          <span>Carregando dados...</span>
        </div>

        <div id="pcDetailContent" class="d-none">
          <div class="pc-detail-profile">
            <img id="detalheFoto" class="pc-detail-profile-photo" src="assets/images/user.png" alt="Foto da pessoa">
            <div class="pc-detail-profile-copy">
              <small>Beneficiário / solicitante</small>
              <strong id="detalhePerfilNome">Pessoa</strong>
              <span id="detalhePerfilResumo">—</span>
              <div class="pc-profile-chips" id="detalhePerfilChips"></div>
            </div>
          </div>

          <div class="pc-tabs-wrap">
            <ul class="nav nav-tabs pc-tabs" role="tablist">
              <li class="nav-item" role="presentation"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#pc-tab-dados" role="tab"><i class="bi bi-person"></i> Dados</button></li>
              <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pc-tab-socio" role="tab"><i class="bi bi-house-heart"></i> Socioeconômico</button></li>
              <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pc-tab-familia" role="tab"><i class="bi bi-people"></i> Família</button></li>
              <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pc-tab-solicitacoes" role="tab"><i class="bi bi-journal-text"></i> Solicitações</button></li>
              <li class="nav-item" role="presentation"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pc-tab-documentos" role="tab"><i class="bi bi-folder2-open"></i> Documentos</button></li>
              <?php if (empty($pcPermissions['isIntern'])): ?><li class="nav-item" role="presentation"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#pc-tab-sigas" role="tab"><i class="bi bi-diagram-3"></i> Benefícios SIGAS</button></li><?php endif; ?>
            </ul>
          </div>

          <div class="tab-content pc-tab-content">
            <div class="tab-pane fade show active" id="pc-tab-dados" role="tabpanel">
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-person-badge"></i></span><div><strong>Identificação</strong><small>Dados pessoais e do cadastro.</small></div></div>
                <div id="pcIdentityFields" class="pc-detail-grid"></div>
              </section>
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-geo-alt"></i></span><div><strong>Endereço</strong><small>Localização e referência residencial.</small></div></div>
                <div id="pcAddressFields" class="pc-detail-grid"></div>
              </section>
            </div>

            <div class="tab-pane fade" id="pc-tab-socio" role="tabpanel">
              <div class="pc-tab-toolbar">
                <div><strong>Dados socioeconômicos</strong><small>Benefícios informados no ANEXO, renda e condições habitacionais.</small></div>
                <?php if (!empty($pcPermissions['canPrintSocio'])): ?><a id="detalheImprimirSocio" class="btn btn-outline-primary btn-sm" href="#" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i> Imprimir folha</a><?php endif; ?>
              </div>
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-cash-stack"></i></span><div><strong>Grupos, benefícios e renda</strong><small>Informações declaradas no cadastro.</small></div></div>
                <div id="pcSocioFields" class="pc-detail-grid"></div>
              </section>
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-people"></i></span><div><strong>Composição familiar</strong><small>Totais informados para a residência.</small></div></div>
                <div id="pcHouseholdFields" class="pc-detail-grid"></div>
              </section>
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-house"></i></span><div><strong>Condições habitacionais</strong><small>Situação do imóvel e infraestrutura.</small></div></div>
                <div id="pcHousingFields" class="pc-detail-grid"></div>
              </section>
            </div>

            <div class="tab-pane fade" id="pc-tab-familia" role="tabpanel">
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-people-fill"></i></span><div><strong>Familiares</strong><small>Composição familiar cadastrada.</small></div></div>
                <div id="pcFamily"></div>
              </section>
              <section class="pc-detail-section">
                <div class="pc-section-title"><span><i class="bi bi-heart"></i></span><div><strong>Cônjuge</strong><small>Dados do cônjuge quando informados.</small></div></div>
                <div id="pcSpouseFields" class="pc-detail-grid"></div>
              </section>
            </div>

            <div class="tab-pane fade" id="pc-tab-solicitacoes" role="tabpanel">
              <div class="pc-tab-toolbar">
                <div><strong>Histórico de solicitações</strong><small><?= !empty($pcPermissions['canAssignBenefit']) ? 'Selecione a demanda correta para atribuir o benefício.' : 'Consulte as demandas já registradas para esta pessoa.' ?> A solicitação do cadastro não é repetida quando já existe no histórico.</small></div>
                <?php if (!empty($pcPermissions['canCreateRequest'])): ?><button type="button" class="btn btn-primary btn-sm" data-pc-detail-action="new-request"><i class="bi bi-plus-lg me-1"></i> Nova solicitação</button><?php endif; ?>
              </div>
              <div id="pcRequests"></div>
            </div>

            <div class="tab-pane fade" id="pc-tab-documentos" role="tabpanel">
              <div class="pc-tab-toolbar">
                <div><strong>Documentos anexados</strong><small>Arquivos vinculados ao cadastro.</small></div>
                <?php if (!empty($pcPermissions['canPrintSocio'])): ?><a id="detalheFolhaDocumentos" class="btn btn-outline-primary btn-sm" href="#" target="_blank" rel="noopener"><i class="bi bi-file-text me-1"></i> Folha socioeconômica</a><?php endif; ?>
              </div>
              <div id="pcDocuments"></div>
            </div>

            <?php if (empty($pcPermissions['isIntern'])): ?><div class="tab-pane fade" id="pc-tab-sigas" role="tabpanel"><div id="pcBenefits"></div></div><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="modal-footer pc-detail-footer">
        <?php if (!empty($pcPermissions['canEditPerson'])): ?><a id="detalheEditarCadastro" class="btn btn-outline-secondary" href="#"><i class="bi bi-pencil-square me-1"></i> Editar</a><?php endif; ?>
        <?php if (!empty($pcPermissions['canPrintSocio'])): ?><a id="detalheImprimirSocioFooter" class="btn btn-primary" href="#" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i> Imprimir socioeconômico</a><?php endif; ?>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-nova-os">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">
        <i class="bi bi-plus-circle"></i> Nova Ordem de Serviço
      </div>
      <button class="modal-close" onclick="closeModal()">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cliente <span>*</span></label>
          <select class="form-control-os" id="f-cliente">
            <option value="">Selecione o cliente...</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Técnico Responsável</label>
          <select class="form-control-os" id="f-tecnico">
            <option value="">Sem técnico definido</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Título da OS <span>*</span></label>
        <input type="text" class="form-control-os" id="f-titulo"
               placeholder="Ex.: Manutenção preventiva impressora HP LaserJet">
      </div>

      <div class="form-group">
        <label class="form-label">Descrição / Problema relatado</label>
        <textarea class="form-control-os" id="f-descricao"
                  placeholder="Descreva o problema ou serviço a ser executado..."></textarea>
      </div>

      <div class="form-row-3">
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control-os" id="f-status">
            <option value="aberta">Aberta</option>
            <option value="em_andamento">Em andamento</option>
            <option value="aguardando">Aguardando</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Prioridade</label>
          <select class="form-control-os" id="f-prioridade">
            <option value="baixa">Baixa</option>
            <option value="media" selected>Média</option>
            <option value="alta">Alta</option>
            <option value="urgente">Urgente</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Categoria</label>
          <select class="form-control-os" id="f-categoria">
            <option value="">Selecione...</option>
            <option>Manutenção</option>
            <option>Instalação</option>
            <option>Configuração</option>
            <option>Suporte</option>
            <option>Limpeza</option>
            <option>Outro</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Equipamento</label>
          <input type="text" class="form-control-os" id="f-equipamento"
                 placeholder="Ex.: Impressora HP LaserJet M428">
        </div>
        <div class="form-group">
          <label class="form-label">Número de Série</label>
          <input type="text" class="form-control-os" id="f-nserie"
                 placeholder="Ex.: SN-2024-00123">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Valor Orçamento (R$)</label>
          <input type="number" class="form-control-os" id="f-valor"
                 placeholder="0,00" min="0" step="0.01">
        </div>
        <div class="form-group">
          <label class="form-label">Previsão de Conclusão</label>
          <input type="date" class="form-control-os" id="f-previsao">
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn-modal-cancel" onclick="closeModal()">Cancelar</button>
      <button class="btn-modal-save" id="btn-save-os" onclick="saveOS()">
        <i class="bi bi-check2"></i> Criar Ordem de Serviço
      </button>
    </div>
  </div>
</div>

<div class="toast-container" id="toast-container"></div>

(function () {
  'use strict';

  const apiBase = 'api/';
  const state = {
    page: 1,
    perPage: 20,
    pessoas: [],
    pagination: { total: 0, paginas: 1 },
    selected: new Set(),
    selectAllFiltered: false,
    charts: {},
    csrf: document.getElementById('wpeCsrf')?.value || ''
  };

  const mensagemPadrao = `Ola, {NOME}. A Secretaria Municipal de Assistencia Social de Coari esta realizando uma atualizacao dos cadastros relacionados a area de emprego.

Atualmente, voce possui interesse em trabalhar em alguma destas areas?

1 - Servicos gerais
2 - Limpeza urbana / gari
3 - Outra profissao
4 - Nao tenho interesse no momento

Caso escolha a opcao 3, informe qual profissao ou area de trabalho voce procura.

Esta mensagem e apenas para atualizacao cadastral e nao representa garantia de contratacao ou disponibilidade imediata de vaga.

Caso nao queira receber novas mensagens de atualizacao cadastral, responda SAIR.`;

  const qs = (id) => document.getElementById(id);

  function payload() {
    return {
      di: qs('wpeDi')?.value || '',
      df: qs('wpeDf')?.value || '',
      mes: qs('wpeMes')?.value || '',
      ano: qs('wpeAno')?.value || '',
      beneficio_id: qs('wpeTipoEmprego')?.value || '',
      bairro_id: qs('wpeBairro')?.value || '',
      sexo: qs('wpeSexo')?.value || '',
      q: qs('wpeBusca')?.value || '',
      campanha_id: qs('wpeCampanhaFiltro')?.value || ''
    };
  }

  async function api(endpoint, data, method = 'POST') {
    const options = { method, headers: { Accept: 'application/json' } };
    if (method === 'POST') {
      options.headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(data || {});
    }
    const url = method === 'GET' && data ? `${apiBase}${endpoint}?${new URLSearchParams(data)}` : `${apiBase}${endpoint}`;
    const response = await fetch(url, options);
    const json = await response.json();
    if (!response.ok || !json.sucesso) {
      throw new Error(json.mensagem || 'Falha na requisicao.');
    }
    return json.dados || {};
  }

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
  }

  function setStatus(data) {
    const dot = qs('wpeStatusDot');
    const text = qs('wpeStatusText');
    const date = qs('wpeStatusDate');
    const status = data?.whatsapp || data;
    dot.className = 'wpe-status-dot me-2 ' + (status?.conectado ? 'online' : (status?.status === 'waiting_qr' || status?.status === 'connecting' ? 'waiting' : 'offline'));
    text.textContent = status?.conectado ? 'Conectado' : (status?.mensagem || 'Indisponível');
    date.textContent = 'Última verificação: ' + (status?.verificado_em || new Date().toLocaleString('pt-BR'));
  }

  function kpiCard(icon, label, value) {
    return `<div class="col-6 col-lg-3"><div class="wpe-kpi h-100"><div class="d-flex justify-content-between"><span class="label">${esc(label)}</span><i class="bi ${icon} icon"></i></div><div class="value mt-2">${esc(value)}</div></div></div>`;
  }

  function renderKpis(cards) {
    qs('wpeKpis').innerHTML = [
      kpiCard('bi-people', 'Pessoas filtradas', cards.pessoas_filtradas || 0),
      kpiCard('bi-telephone-check', 'Telefones válidos', cards.telefones_validos || 0),
      kpiCard('bi-telephone-x', 'Sem telefone válido', cards.sem_telefone_valido || 0),
      kpiCard('bi-person-dash', 'Não contatadas', cards.nao_contatadas || 0),
      kpiCard('bi-list-check', 'Mensagens na fila', cards.mensagens_fila || 0),
      kpiCard('bi-send-check', 'Mensagens enviadas', cards.mensagens_enviadas || 0),
      kpiCard('bi-reply', 'Pessoas responderam', cards.pessoas_responderam || 0),
      kpiCard('bi-pencil-square', 'Revisões pendentes', cards.revisoes_pendentes || 0),
      kpiCard('bi-file-earmark-check', 'Resumos atualizados', cards.resumos_atualizados || 0),
      kpiCard('bi-exclamation-triangle', 'Falhas de envio', cards.falhas_envio || 0),
      kpiCard('bi-slash-circle', 'Opt-out', cards.optout || 0),
      kpiCard('bi-briefcase', 'Profissões identificadas', cards.profissoes_identificadas || 0)
    ].join('');
  }

  function chart(id, type, labels, values, label) {
    if (state.charts[id]) state.charts[id].destroy();
    const ctx = qs(id);
    state.charts[id] = new Chart(ctx, {
      type,
      data: { labels, datasets: [{ label, data: values }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: type !== 'bar' } }, scales: type === 'doughnut' ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });
  }

  function renderCharts(data) {
    chart('wpeChartFunil', 'bar', (data.funil || []).map(x => x.etapa), (data.funil || []).map(x => x.total), 'Total');
    chart('wpeChartProfissoes', 'bar', (data.profissoes || []).map(x => x.nome), (data.profissoes || []).map(x => x.total), 'Profissões');
    chart('wpeChartPeriodo', 'bar', (data.periodo || []).map(x => x.dia), (data.periodo || []).map(x => Number(x.respostas || 0)), 'Respostas');
    chart('wpeChartConversas', 'doughnut', (data.conversas || []).map(x => x.status), (data.conversas || []).map(x => x.total), 'Conversas');
  }

  function renderPessoas(data) {
    state.pessoas = data.pessoas || [];
    state.pagination = data.paginacao || { paginas: 1, total: 0 };
    const body = qs('wpePessoasBody');
    if (!state.pessoas.length) {
      body.innerHTML = '<tr><td colspan="15" class="text-center text-muted">Nenhuma pessoa relacionada a Emprego encontrada.</td></tr>';
    } else {
      body.innerHTML = state.pessoas.map((p) => {
        const key = `${p.solicitante_id}:${p.solicitacao_id}`;
        return `<tr>
          <td><input class="form-check-input wpePessoaCheck" type="checkbox" value="${esc(key)}" ${state.selected.has(key) ? 'checked' : ''}></td>
          <td>${esc(p.nome)}</td>
          <td>${esc(p.cpf_mascarado)}</td>
          <td><span class="badge ${p.telefone_valido ? 'bg-success' : 'bg-danger'}">${esc(p.telefone_mascarado)}</span></td>
          <td>${esc(p.bairro)}</td>
          <td>${esc((p.data_referencia || '').slice(0, 10))}</td>
          <td><span class="badge bg-primary">${esc(p.tipo_caso)}</span></td>
          <td>${esc(p.trabalho || '-')}</td>
          <td><div class="wpe-text-truncate" title="${esc(p.resumo_caso || '')}">${esc(p.resumo_curto || '-')}</div></td>
          <td>${esc(p.profissao_identificada || '-')}</td>
          <td>${esc(p.campanha || '-')}</td>
          <td><span class="badge bg-light text-dark">${esc(p.status_mensagem || 'não_contatado')}</span></td>
          <td><div class="wpe-text-truncate">${esc(p.ultima_resposta_curta || '-')}</div></td>
          <td>${esc(p.situacao_atualizacao || '-')}</td>
          <td><button class="btn btn-sm btn-outline-primary wpeVerConversa" data-solicitante="${esc(p.solicitante_id)}" data-solicitacao="${esc(p.solicitacao_id)}">Ver conversa</button></td>
        </tr>`;
      }).join('');
    }
    qs('wpePageLabel').textContent = `Página ${state.pagination.pagina || state.page} de ${state.pagination.paginas || 1} (${state.pagination.total || 0})`;
    qs('wpePrev').disabled = state.page <= 1;
    qs('wpeNext').disabled = state.page >= (state.pagination.paginas || 1);
    updateSelection();
  }

  function updateSelection() {
    const text = state.selectAllFiltered
      ? `Todos os ${state.pagination.total || 0} resultados filtrados serão considerados.`
      : `${state.selected.size} pessoa(s) selecionada(s) nesta operação.`;
    qs('wpeSelecaoInfo').textContent = text;
  }

  async function loadIndicadores() {
    const data = await api('indicadores.php', payload(), 'POST');
    setStatus(data);
    renderKpis(data.cards || {});
    renderCharts(data);
  }

  async function loadPessoas() {
    const data = await api('listar-pessoas.php', { ...payload(), pagina: state.page, por_pagina: state.perPage }, 'POST');
    renderPessoas(data);
  }

  async function loadCampanhas() {
    const data = await api('campanhas.php', null, 'GET');
    const rows = data.campanhas || [];
    qs('wpeCampanhasBody').innerHTML = rows.length ? rows.map((c) => `<tr>
      <td>${esc(c.id)}</td><td>${esc(c.titulo)}</td><td><span class="badge bg-light text-dark">${esc(c.status)}</span></td>
      <td>${esc(c.destinatarios || 0)}</td><td>${esc(c.fila || 0)}</td><td>${esc(c.enviados || 0)}</td><td>${esc(c.falhas || 0)}</td>
      <td><button class="btn btn-sm btn-outline-success wpeProcessar" data-id="${esc(c.id)}">Processar lote</button></td>
    </tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted">Nenhuma campanha criada.</td></tr>';
    qs('wpeCampanhaFiltro').innerHTML = '<option value="">Todas</option>' + rows.map(c => `<option value="${esc(c.id)}">${esc(c.titulo)}</option>`).join('');
  }

  async function refreshAll() {
    await Promise.all([loadIndicadores(), loadPessoas(), loadCampanhas()]);
  }

  function openCampaignModal() {
    qs('wpeMensagemCampanha').value = mensagemPadrao;
    qs('wpeCampanhaResumo').textContent = state.selectAllFiltered
      ? `Todos os ${state.pagination.total || 0} resultados filtrados serão avaliados.`
      : `${state.selected.size || state.pessoas.length} pessoa(s) da página atual/seleção serão avaliadas. Telefones inválidos, opt-out e duplicados serão excluídos da fila.`;
    bootstrap.Modal.getOrCreateInstance(qs('wpeCampanhaModal')).show();
  }

  async function createCampaign() {
    const message = qs('wpeMensagemCampanha').value;
    if (!message.toLowerCase().includes('nao representa garantia') && !message.toLowerCase().includes('não representa garantia')) {
      alert('A mensagem precisa manter o aviso de que não existe garantia de contratação.');
      return;
    }
    const data = await api('criar-campanha.php', {
      csrf_token: state.csrf,
      titulo: qs('wpeTituloCampanha').value || 'Atualização profissional',
      mensagem_modelo: message,
      filtros: payload(),
      selecionar_todos: state.selectAllFiltered,
      selecionados: Array.from(state.selected)
    }, 'POST');
    alert(`Campanha criada. Válidos na fila: ${data.resumo?.validos || 0}`);
    bootstrap.Modal.getOrCreateInstance(qs('wpeCampanhaModal')).hide();
    await refreshAll();
  }

  async function processarCampanha(id) {
    const data = await api('processar-fila.php', { csrf_token: state.csrf, campanha_id: id, limite: 10 }, 'POST');
    alert(`Lote processado. Enviados: ${data.enviados || 0}. Falhas: ${data.falhas || 0}. Pendentes: ${data.pendentes || 0}.`);
    await refreshAll();
  }

  async function openConversa(solicitanteId, solicitacaoId) {
    const data = await api('buscar-conversa.php', { solicitante_id: solicitanteId, solicitacao_id: solicitacaoId }, 'POST');
    const pessoa = data.pessoa || {};
    qs('wpeConversaTitulo').textContent = `${pessoa.nome || 'Conversa'} - ${pessoa.cpf_mascarado || ''}`;
    const mensagens = (data.mensagens || []).map((m) => `<div class="wpe-message ${esc(m.direcao)}"><div class="small text-muted">${esc(m.direcao)} • ${esc(m.status)} • ${esc(m.criado_em || '')}</div>${esc(m.conteudo || '[sem texto]')}</div>`).join('');
    const atualizacoes = (data.atualizacoes || []).map((a) => `<div class="card border"><div class="card-body"><div class="fw-bold">${esc(a.categoria || 'Revisão')}</div><p class="mb-2">${esc(a.resumo_sugerido || '')}</p>${a.aplicado_em ? '<span class="badge bg-success">Aplicado</span>' : `<button class="btn btn-sm btn-primary wpeAplicarResumo" data-id="${esc(a.id)}">Aplicar resumo sugerido</button>`}</div></div>`).join('');
    qs('wpeConversaConteudo').innerHTML = `<div class="card border"><div class="card-body"><div><b>Telefone:</b> ${esc(pessoa.telefone_mascarado || '')}</div><div><b>Bairro:</b> ${esc(pessoa.bairro || '')}</div><div><b>Resumo atual:</b><br>${esc(pessoa.resumo_caso || '-')}</div></div></div><div class="d-grid gap-2">${mensagens || '<div class="text-muted">Sem mensagens registradas.</div>'}</div><h6>Atualizações sugeridas</h6>${atualizacoes || '<div class="text-muted">Sem atualização sugerida.</div>'}`;
    bootstrap.Modal.getOrCreateInstance(qs('wpeConversaModal')).show();
  }

  async function aplicarResumo(id) {
    if (!confirm('Aplicar esta atualização ao resumo da solicitação de Emprego?')) return;
    await api('atualizar-resumo.php', { csrf_token: state.csrf, atualizacao_id: id }, 'POST');
    alert('Resumo atualizado com histórico preservado.');
    await refreshAll();
  }

  function bind() {
    qs('current-year').textContent = String(new Date().getFullYear());
    qs('wpeBtnStatus').addEventListener('click', loadIndicadores);
    qs('wpeBtnNovaCampanha').addEventListener('click', openCampaignModal);
    qs('wpeConfirmarCampanha').addEventListener('click', createCampaign);
    qs('wpeBtnLimpar').addEventListener('click', () => { qs('wpeFiltros').reset(); state.page = 1; state.selected.clear(); state.selectAllFiltered = false; refreshAll(); });
    ['wpeMes', 'wpeAno', 'wpeTipoEmprego', 'wpeBairro', 'wpeSexo', 'wpeCampanhaFiltro'].forEach(id => qs(id).addEventListener('change', () => { state.page = 1; refreshAll(); }));
    qs('wpeBusca').addEventListener('input', () => { clearTimeout(window.__wpeSearch); window.__wpeSearch = setTimeout(() => { state.page = 1; refreshAll(); }, 300); });
    qs('wpePrev').addEventListener('click', () => { if (state.page > 1) { state.page--; loadPessoas(); } });
    qs('wpeNext').addEventListener('click', () => { if (state.page < (state.pagination.paginas || 1)) { state.page++; loadPessoas(); } });
    qs('wpeSelecionarPagina').addEventListener('click', () => { state.selectAllFiltered = false; state.pessoas.forEach(p => state.selected.add(`${p.solicitante_id}:${p.solicitacao_id}`)); renderPessoas({ pessoas: state.pessoas, paginacao: state.pagination }); });
    qs('wpeSelecionarTodos').addEventListener('click', () => { state.selected.clear(); state.selectAllFiltered = true; updateSelection(); });
    document.addEventListener('change', (event) => {
      if (!event.target.classList.contains('wpePessoaCheck')) return;
      state.selectAllFiltered = false;
      event.target.checked ? state.selected.add(event.target.value) : state.selected.delete(event.target.value);
      updateSelection();
    });
    document.addEventListener('click', (event) => {
      const conversa = event.target.closest('.wpeVerConversa');
      if (conversa) openConversa(conversa.dataset.solicitante, conversa.dataset.solicitacao);
      const proc = event.target.closest('.wpeProcessar');
      if (proc) processarCampanha(proc.dataset.id);
      const aplicar = event.target.closest('.wpeAplicarResumo');
      if (aplicar) aplicarResumo(aplicar.dataset.id);
    });

    const hash = window.location.hash;
    if (hash && document.querySelector(`[data-bs-target="${hash}"]`)) {
      bootstrap.Tab.getOrCreateInstance(document.querySelector(`[data-bs-target="${hash}"]`)).show();
    }

    document.querySelectorAll('[data-bs-toggle="pill"][data-bs-target]').forEach((button) => {
      button.addEventListener('shown.bs.tab', () => {
        const target = button.getAttribute('data-bs-target');
        if (target) history.replaceState(null, '', target);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    bind();
    refreshAll().catch((error) => {
      console.error(error);
      alert(error.message || 'Falha ao carregar a central.');
    });
  });
})();

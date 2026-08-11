
(function(){
'use strict';

const $=(s,r=document)=>r.querySelector(s);
const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));
const page=(location.pathname.split('/').pop()||'index.html').toLowerCase();
const pageKey=page.replace('.html','');
const esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

function toast(msg){
  let host=$('.demo-toast-host');
  if(!host){host=document.createElement('div');host.className='demo-toast-host';document.body.appendChild(host)}
  const el=document.createElement('div');el.className='demo-toast';el.textContent=msg;host.appendChild(el);
  setTimeout(()=>el.remove(),2600);
}

function ensureModal(){
  if($('#demoModal'))return;
  document.body.insertAdjacentHTML('beforeend',`
    <div class="demo-modal" id="demoModal" aria-hidden="true">
      <div class="demo-modal__backdrop" data-demo-close></div>
      <section class="demo-modal__dialog" id="demoDialog" role="dialog" aria-modal="true" aria-labelledby="demoModalTitle">
        <header class="demo-modal__head">
          <div><span class="demo-kicker" id="demoModalKicker">Demonstração</span><h2 id="demoModalTitle">Detalhes</h2><p id="demoModalSubtitle"></p></div>
          <button type="button" class="demo-modal__close" data-demo-close aria-label="Fechar">×</button>
        </header>
        <div class="demo-modal__body" id="demoModalBody"></div>
        <footer class="demo-modal__foot" id="demoModalFoot"></footer>
      </section>
    </div>`);
  $$('[data-demo-close]').forEach(x=>x.addEventListener('click',closeModal));
}
function openModal({title='Detalhes',kicker='Demonstração',subtitle='',body='',foot='',size=''}){
  ensureModal();
  $('#demoModalTitle').textContent=title;$('#demoModalKicker').textContent=kicker;$('#demoModalSubtitle').textContent=subtitle;
  $('#demoModalBody').innerHTML=body;$('#demoModalFoot').innerHTML=foot||`<button class="btn btn-secondary" data-demo-close>Fechar</button>`;
  $('#demoDialog').className='demo-modal__dialog '+size;
  $('#demoModal').classList.add('is-open');$('#demoModal').setAttribute('aria-hidden','false');document.body.classList.add('demo-modal-open');
  $$('[data-demo-close]').forEach(x=>x.addEventListener('click',closeModal));
  setTimeout(()=>$('.demo-modal__close')?.focus(),0);
}
function closeModal(){
  $('#demoModal')?.classList.remove('is-open');$('#demoModal')?.setAttribute('aria-hidden','true');document.body.classList.remove('demo-modal-open');
}
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal()});

function field(name,label,type='text',value='',full=false,opts=[]){
  const cls='demo-field'+(full?' full':'');
  if(type==='textarea')return `<label class="${cls}"><span>${esc(label)}</span><textarea name="${esc(name)}">${esc(value)}</textarea></label>`;
  if(type==='select')return `<label class="${cls}"><span>${esc(label)}</span><select name="${esc(name)}">${opts.map(o=>`<option ${String(value)===o?'selected':''}>${esc(o)}</option>`).join('')}</select></label>`;
  return `<label class="${cls}"><span>${esc(label)}</span><input name="${esc(name)}" type="${esc(type)}" value="${esc(value)}"></label>`;
}

const forms={
  pauta:{title:'Nova pauta',fields:[
    ['tema','Tema / título'],['secretaria','Secretaria','select','SEMSA',false,['SEMSA','SEMED','SEMAS','SEINFRA','GABINETE']],
    ['data','Data','date','2026-08-10'],['hora','Horário','time','09:00'],['local','Local'],['responsavel','Responsável'],
    ['equipe','Equipe escalada','textarea','Ana Lima - Jornalismo; João Silva - Fotografia',true],
    ['prioridade','Prioridade','select','Normal',false,['Normal','Alta']],['briefing','Briefing / objetivo','textarea','',true]
  ]},
  compromisso:{title:'Novo compromisso',fields:[
    ['tema','Tema'],['tipo','Tipo','select','Cobertura',false,['Pauta','Cobertura','Entrega','Publicação','Reunião']],
    ['data','Data','date','2026-08-10'],['hora','Horário','time','09:00'],['responsavel','Responsável'],['secretaria','Secretaria / origem'],
    ['local','Local'],['pauta','Pauta vinculada'],['quem_vai','Quem vai — Nome - Função; Nome - Função','textarea','Ana Lima - Responsável; João Silva - Fotógrafo',true],
    ['necessidades','Necessidades','textarea','Fotografia • Vídeo • Jornalismo',true],['observacoes','Briefing / observações','textarea','',true]
  ]},
  demanda:{title:'Nova demanda',fields:[
    ['titulo','Título'],['secretaria','Secretaria'],['solicitante','Solicitante'],['tipo','Tipo','select','Cobertura',false,['Cobertura','Arte','Campanha','Matéria','Vídeo']],
    ['prazo','Prazo','date','2026-08-11'],['prioridade','Prioridade','select','Normal',false,['Normal','Alta']],['descricao','Descrição','textarea','',true]
  ]},
  producao:{title:'Nova produção',fields:[
    ['titulo','Título'],['tipo','Tipo','select','Reel',false,['Reel','Vídeo','Galeria','Card','Spot']],['pauta','Pauta'],['responsavel','Responsável'],
    ['prazo','Prazo'],['versao','Versão','text','V1'],['observacoes','Orientações de edição','textarea','',true]
  ]},
  materia:{title:'Nova matéria',fields:[
    ['titulo','Título'],['pauta','Pauta'],['autor','Autor'],['canal','Canal','select','Portal',false,['Portal','Release','Interno']],
    ['texto','Texto / resumo','textarea','',true]
  ]},
  publicacao:{title:'Agendar publicação',fields:[
    ['conteudo','Conteúdo'],['canal','Canal','select','Instagram',false,['Instagram','Facebook','Site','YouTube']],['pauta','Pauta'],
    ['data','Data','date','2026-08-10'],['hora','Horário','time','18:00'],['responsavel','Responsável'],['legenda','Legenda / observações','textarea','',true]
  ]},
  profissional:{title:'Novo profissional',fields:[
    ['nome','Nome'],['funcao','Função'],['telefone','Telefone'],['email','E-mail','email'],['status','Status','select','Disponível',false,['Disponível','Em cobertura','Em produção','Em revisão']]
  ]},
  secretaria:{title:'Nova secretaria',fields:[
    ['sigla','Sigla'],['nome','Nome da secretaria'],['contato','Responsável de contato'],['telefone','Telefone'],['email','E-mail','email']
  ]},
  usuario:{title:'Novo usuário',fields:[
    ['nome','Nome'],['login','Login'],['perfil','Perfil','select','Leitor',false,['Administrador','Jornalismo','Produção','Social Media','Leitor']],
    ['setor','Setor / função'],['status','Status','select','Ativo',false,['Ativo','Bloqueado']]
  ]},
  upload:{title:'Upload de material',fields:[
    ['arquivo','Arquivo','file'],['classe','Classe','select','Original',false,['Original','Master','Proxy']],['pauta','Pauta vinculada','text','P-2026-0187'],
    ['assunto','Assunto'],['observacao','Observação','textarea','',true]
  ]}
};

function openForm(kind){
  const cfg=forms[kind];if(!cfg)return;
  openModal({
    title:cfg.title,kicker:'Cadastro',subtitle:'Protótipo front-end — os dados são simulados.',
    body:`<form class="demo-form" id="demoForm">${cfg.fields.map(f=>field(...f)).join('')}</form>
      ${kind==='upload'?'<div class="demo-alert" style="margin-top:14px">Na versão real, arquivos Originais serão enviados sem recompressão, com checksum SHA-256 e armazenamento protegido.</div>':''}`,
    foot:`<button class="btn btn-secondary" data-demo-close>Cancelar</button><button class="btn btn-primary" id="demoSaveForm">Salvar</button>`
  });
  $('#demoSaveForm').addEventListener('click',()=>{
    const data=Object.fromEntries(new FormData($('#demoForm')).entries());
    localStorage.setItem('secom_demo_last_'+kind,JSON.stringify({...data,createdAt:new Date().toISOString()}));
    closeModal();toast(cfg.title.replace(/^Nova |^Novo |^Agendar /,'')+' salvo(a) na demonstração.');
    addVisualRecord(kind,data);
  });
}
function addVisualRecord(kind,data){
  const table=$('table tbody');
  if(table && ['pauta','demanda','producao','materia','publicacao','profissional','secretaria','usuario'].includes(kind)){
    const tr=document.createElement('tr');tr.className='demo-new-record';
    const cells=Array.from(table.closest('table').querySelectorAll('thead th')).length;
    let primary=data.tema||data.titulo||data.nome||data.conteudo||data.sigla||'Novo registro';
    tr.innerHTML=`<td><strong>${esc(primary)}</strong><small>DEMO-${String(Date.now()).slice(-5)}</small></td>`+
      Array.from({length:Math.max(0,cells-2)},(_,i)=>`<td>${esc(Object.values(data)[i+1]||'—')}</td>`).join('')+
      `<td><span class="badge blue">Novo</span></td>`;
    table.prepend(tr);
  }
}

const eventDetails={
 '08:30 Briefing UBS':{type:'Pauta / Briefing',title:'Briefing UBS Grande Vitória',theme:'Alinhamento da cobertura da inauguração da UBS Grande Vitória',time:'08:30 às 08:50',resp:'Ana Lima',role:'Jornalista / Responsável pela pauta',local:'Sala da SECOM',sec:'SEMSA',pauta:'P-2026-0187',needs:'Briefing • Fotografia • Vídeo • Jornalismo',notes:'Repassar roteiro, autoridades previstas, pontos de captação e entrevistas prioritárias.',team:[['Ana Lima','Jornalista / Responsável'],['João Silva','Fotógrafo'],['Marcos Reis','Cinegrafista / Drone']]},
 '09:00 Inauguração UBS':{type:'Cobertura',title:'Inauguração da UBS Grande Vitória',theme:'Inauguração e entrega oficial da nova unidade de saúde',time:'09:00 às 11:00',resp:'Ana Lima',role:'Jornalista / Responsável pela cobertura',local:'UBS Grande Vitória',sec:'SEMSA',pauta:'P-2026-0187',needs:'Fotografia • Vídeo • Drone • Entrevistas • Matéria',notes:'Registrar autoridades, estrutura, moradores e entrevistas. Priorizar captação horizontal e vertical.',team:[['Ana Lima','Jornalista / Responsável'],['João Silva','Fotógrafo'],['Marcos Reis','Cinegrafista / Drone'],['Maria Souza','Social Media']]},
 '10:30 Kits escolares':{type:'Cobertura',title:'Entrega de kits escolares',theme:'Entrega de kits escolares aos alunos da rede municipal',time:'10:30 às 12:00',resp:'Pedro Lima',role:'Jornalista / Responsável',local:'Escola Municipal',sec:'SEMED',pauta:'P-2026-0188',needs:'Fotografia • Entrevistas • Matéria',notes:'Registrar entrega e entrevista breve com representante da SEMED.',team:[['Pedro Lima','Jornalista / Responsável'],['Luana Costa','Fotografia / Conteúdo']]},
 '13:30 Reel Feira':{type:'Entrega',title:'Prazo Reel Feira do Produtor',theme:'Finalização do Reel da Feira do Produtor',time:'Prazo final: 13:30',resp:'João Silva',role:'Editor / Responsável',local:'Sala de edição',sec:'SECOM',pauta:'P-2026-0184',needs:'Finalização • Legenda • Trilha',notes:'Entregar V3 pronta para aprovação.',team:[['João Silva','Editor / Responsável'],['Ana Lima','Revisão']]},
 '18:00 Agenda Prefeito':{type:'Publicação',title:'Publicar Agenda do Prefeito',theme:'Publicação da agenda institucional do dia',time:'18:00',resp:'Maria Souza',role:'Social Media',local:'Instagram + Facebook',sec:'Gabinete / SECOM',pauta:'PUB-AGENDA-0810',needs:'Texto • Card • Links',notes:'Conferir texto, marcações e arte final.',team:[['Maria Souza','Social Media / Responsável'],['Ana Lima','Revisão institucional']]},
 '08:00 Agosto Lilás':{type:'Pauta / Campanha',title:'Campanha Agosto Lilás',theme:'Ação institucional de conscientização',time:'08:00 às 10:30',resp:'Ana Lima',role:'Jornalista / Coordenação',local:'SEMAS',sec:'SEMAS',pauta:'P-2026-0190',needs:'Foto • Vídeo • Entrevistas • Matéria • Cards',notes:'Registrar abertura e falas técnicas.',team:[['Ana Lima','Jornalista / Responsável'],['Luana Costa','Designer / Foto'],['Marcos Reis','Vídeo']]}
};

function openEvent(text){
  const e=eventDetails[text]||{
    type:'Compromisso',title:text.replace(/^\d{2}:\d{2}\s*/,''),theme:'Compromisso institucional da agenda SECOM',time:(text.match(/^\d{2}:\d{2}/)||['A definir'])[0],resp:'Responsável da pauta',role:'Responsável',local:'A definir',sec:'SECOM',pauta:'A definir',needs:'Conforme briefing da pauta',notes:'Detalhes demonstrativos do compromisso.',team:[['Responsável da pauta','Responsável'],['Equipe escalada','Cobertura']]
  };
  openModal({
    title:e.title,kicker:e.type,subtitle:e.theme,size:'demo-lg',
    body:`<div class="demo-grid-4">
      <div class="demo-info"><small>Horário</small><strong>${esc(e.time)}</strong></div>
      <div class="demo-info"><small>Responsável</small><strong>${esc(e.resp)}</strong><span>${esc(e.role)}</span></div>
      <div class="demo-info"><small>Local</small><strong>${esc(e.local)}</strong></div>
      <div class="demo-info"><small>Secretaria / pauta</small><strong>${esc(e.sec)}</strong><span>${esc(e.pauta)}</span></div>
    </div>
    <div class="demo-section"><span class="demo-kicker">Equipe escalada</span><h3>Quem vai</h3><div class="demo-team">${e.team.map((t,i)=>`<div class="demo-person ${i===0?'is-responsible':''}"><div class="demo-person__avatar">${esc(t[0].split(/\s+/).map(x=>x[0]).join('').slice(0,2))}</div><div><strong>${esc(t[0])}</strong><span>${esc(t[1])}</span></div></div>`).join('')}</div></div>
    <div class="demo-section"><div class="demo-grid-2"><div class="demo-info"><small>Necessidades</small><strong>${esc(e.needs)}</strong></div><div class="demo-info"><small>Tema</small><strong>${esc(e.theme)}</strong></div></div><div class="demo-info" style="margin-top:9px"><small>Briefing / observações</small><strong>${esc(e.notes)}</strong></div></div>`,
    foot:`<button class="btn btn-secondary" data-demo-close>Fechar</button><button class="btn btn-secondary" onclick="location.href='pauta-detalhe.html'">Ver pauta</button><button class="btn btn-primary" id="demoEditEvent">Editar compromisso</button>`
  });
  $('#demoEditEvent').addEventListener('click',()=>openForm('compromisso'));
}

function rowContext(el){
  const row=el.closest('tr')||el.closest('.list-row')||el.closest('.media-card')||el.closest('.kanban-card')||el.closest('.report-card');
  if(!row)return {title:'Registro',text:''};
  const strong=row.querySelector('strong');
  return {title:(strong?.textContent||row.textContent||'Registro').trim().split('\n')[0],text:' '.join?row.textContent:''};
}
function contextBody(el){
  const row=el.closest('tr')||el.closest('.list-row')||el.closest('.media-card')||el.closest('.kanban-card')||el.closest('.report-card');
  if(!row)return '<div class="demo-alert">Detalhes do item selecionado na demonstração.</div>';
  if(row.matches('tr')){
    const cells=$$('td',row).map((td,i)=>`<div class="demo-info"><small>Campo ${i+1}</small><strong>${esc(td.textContent.trim())}</strong></div>`).join('');
    return `<div class="demo-grid-2">${cells}</div>`;
  }
  if(row.matches('.media-card')){
    const title=$('strong',row)?.textContent||'Arquivo';
    const meta=$('small',row)?.textContent||'';
    return `<div class="demo-alert">Este é um item do acervo institucional. Arquivos Originais não são sobrescritos.</div><div class="demo-grid-2" style="margin-top:12px"><div class="demo-info"><small>Arquivo</small><strong>${esc(title)}</strong></div><div class="demo-info"><small>Metadados</small><strong>${esc(meta)}</strong></div></div>`;
  }
  return `<div class="demo-info"><small>Registro</small><strong>${esc(row.textContent.replace(/\s+/g,' ').trim())}</strong></div>`;
}
function openContext(el,mode='Visualização'){
  const ctx=rowContext(el);
  openModal({
    title:ctx.title||mode,kicker:mode,body:contextBody(el),
    foot:`<button class="btn btn-secondary" data-demo-close>Fechar</button>${/editar|revisar/i.test(mode)?'<button class="btn btn-primary" id="demoConfirmAction">Salvar alterações</button>':''}`
  });
  $('#demoConfirmAction')?.addEventListener('click',()=>{closeModal();toast('Alterações salvas na demonstração.')});
}
function downloadCSV(){
  const table=$('table');if(!table){toast('Não há tabela nesta página para exportar.');return}
  const rows=$$('tr',table).map(tr=>$$('th,td',tr).map(c=>`"${c.textContent.trim().replaceAll('"','""')}"`).join(';')).join('\n');
  const blob=new Blob(['\ufeff'+rows],{type:'text/csv;charset=utf-8'}),url=URL.createObjectURL(blob),a=document.createElement('a');
  a.href=url;a.download='secom_'+pageKey+'_demo.csv';a.click();URL.revokeObjectURL(url);toast('CSV gerado para demonstração.');
}
function genericReport(card){
  const title=card.textContent.replace(/\s+/g,' ').trim().replace('Abrir relatório →','');
  openModal({title,kicker:'Relatório',size:'demo-lg',body:`<div class="demo-grid-4"><div class="demo-info"><small>Total</small><strong>89</strong></div><div class="demo-info"><small>Concluídos</small><strong>76</strong></div><div class="demo-info"><small>Pendentes</small><strong>13</strong></div><div class="demo-info"><small>Variação</small><strong>+12%</strong></div></div><div class="demo-alert" style="margin-top:14px">Relatório demonstrativo com filtros por período, secretaria, responsável e status. Na versão real os indicadores virão do banco de dados.</div>`,foot:'<button class="btn btn-secondary" data-demo-close>Fechar</button><button class="btn btn-primary" id="demoExportReport">Exportar CSV</button>'});
  $('#demoExportReport').addEventListener('click',downloadCSV);
}

function classifyCreate(text){
  const t=text.toLowerCase();
  if(t.includes('novo compromisso')||t==='+ cobertura')return 'compromisso';
  if(t.includes('nova pauta'))return 'pauta';
  if(t.includes('nova demanda'))return 'demanda';
  if(t.includes('nova produção'))return 'producao';
  if(t.includes('nova matéria'))return 'materia';
  if(t.includes('agendar publicação'))return 'publicacao';
  if(t.includes('profissional'))return 'profissional';
  if(t.includes('secretaria'))return 'secretaria';
  if(t.includes('novo usuário'))return 'usuario';
  if(t.includes('upload'))return 'upload';
  return null;
}

function enhanceAgenda(){
  if(pageKey!=='agenda')return;
  $$('.cal-event').forEach(ev=>{
    ev.classList.add('demo-clickable');ev.setAttribute('role','button');ev.setAttribute('tabindex','0');
    const open=()=>openEvent(ev.textContent.trim());
    ev.addEventListener('click',open);ev.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();open()}});
  });
  $$('.timeline-item').forEach(item=>{
    item.classList.add('demo-clickable');
    item.addEventListener('click',()=>{
      const time=item.querySelector('time')?.textContent.trim()||'';
      const title=item.querySelector('strong')?.textContent.trim()||'Compromisso';
      const match=Object.keys(eventDetails).find(k=>k.startsWith(time)&&eventDetails[k].title.toLowerCase().includes(title.toLowerCase().replace('prazo ','').replace('publicar ','')));
      openEvent(match||`${time} ${title}`);
    });
  });
}
function enhanceReports(){
  if(pageKey!=='relatorios')return;
  $$('.report-card').forEach(c=>{c.classList.add('demo-clickable');c.setAttribute('tabindex','0');c.addEventListener('click',()=>genericReport(c))});
}

document.addEventListener('click',e=>{
  const el=e.target.closest('button,a');
  if(!el)return;
  if(el.closest('.demo-modal'))return;
  const text=el.textContent.replace(/\s+/g,' ').trim();
  const kind=classifyCreate(text);
  if(kind){e.preventDefault();openForm(kind);return}
  if(/exportar|exportar csv/i.test(text)){e.preventDefault();downloadCSV();return}
  if(/verificar integridade/i.test(text)){e.preventDefault();openModal({title:'Verificação de integridade',kicker:'Preservação',body:'<div class="demo-grid-3"><div class="demo-info"><small>Arquivos verificados</small><strong>48.392</strong></div><div class="demo-info"><small>Checksums válidos</small><strong>100%</strong></div><div class="demo-info"><small>Falhas</small><strong>0</strong></div></div><div class="demo-alert" style="margin-top:14px">Demonstração concluída: nenhum problema de integridade identificado.</div>'});return}
  if(/salvar alterações|salvar perfil/i.test(text)){e.preventDefault();toast('Alterações salvas na demonstração.');return}
  if(/perfis e permissões/i.test(text)){e.preventDefault();openModal({title:'Perfis e permissões',kicker:'Controle de acesso',body:'<div class="demo-grid-2"><div class="demo-info"><small>Administrador</small><strong>Acesso total</strong></div><div class="demo-info"><small>Jornalismo</small><strong>Pautas, matérias e revisão</strong></div><div class="demo-info"><small>Produção</small><strong>Mídia, edição e versões</strong></div><div class="demo-info"><small>Social Media</small><strong>Publicações</strong></div></div>'});return}
  if(/histórico de backup/i.test(text)){e.preventDefault();openModal({title:'Histórico de backup',kicker:'Preservação',body:'<div class="list-stack"><div class="list-row"><div><strong>10/08/2026 • 02:00</strong><span>Backup concluído sem falhas</span></div><span class="badge green">OK</span></div><div class="list-row"><div><strong>09/08/2026 • 02:00</strong><span>Backup concluído sem falhas</span></div><span class="badge green">OK</span></div></div>'});return}
  if(/revisar/i.test(text)){e.preventDefault();openContext(el,'Revisar');return}
  if(/^editar$/i.test(text)){e.preventDefault();openContext(el,'Editar');return}
  if(/detalhes/i.test(text)){e.preventDefault();openContext(el,'Detalhes');return}
  if(/^ver$/i.test(text)){e.preventDefault();openContext(el,'Visualização');return}
  if(/preview|visualizar|assistir|ouvir|origem|usar/i.test(text)){e.preventDefault();openContext(el,'Visualização de mídia');return}
  if(/baixar|original$/i.test(text)){e.preventDefault();toast(/original/i.test(text)?'Download demonstrativo do arquivo Original — sem recompressão.':'Download demonstrativo iniciado.');return}
  if(/^link$/i.test(text)){e.preventDefault();openModal({title:'Publicação',kicker:'Link final',body:'<div class="demo-info"><small>URL demonstrativa</small><strong>https://coari.am.gov.br/comunicacao/publicacao-demo</strong></div>'});return}
  if(/abrir próxima/i.test(text)){e.preventDefault();const row=$('tbody tr')||$('.list-row');if(row)openContext(row.querySelector('a,button')||row,'Revisar');return}
});

function fixOldDemoAlerts(){
  $$('[data-demo]').forEach(el=>{
    el.removeAttribute('data-demo');
  });
}
function improveTopButtons(){
  // Não intercepta links de navegação reais como "Abrir" em Pautas.
  if(pageKey==='pautas'){
    $$('td a').forEach(a=>{ if(a.textContent.trim()==='Abrir' && a.getAttribute('href')) a.title='Abrir detalhes completos da pauta';});
  }
}
fixOldDemoAlerts();enhanceAgenda();enhanceReports();improveTopButtons();
})();

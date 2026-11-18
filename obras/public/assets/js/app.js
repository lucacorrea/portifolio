/* OBRAS360 — somente comportamento visual. Operações persistentes são feitas pelo PHP. */
(()=>{'use strict';
 const sidebar=document.getElementById('sidebar'),mask=document.querySelector('.sidebar-mask');
 const toggle=document.querySelector('[data-toggle-sidebar]');
 function closeSidebar(){sidebar?.classList.remove('open');mask?.classList.remove('open')}
 toggle?.addEventListener('click',()=>{sidebar?.classList.toggle('open');mask?.classList.toggle('open')});
 mask?.addEventListener('click',closeSidebar);
 document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});

 // Ícones reutilizados do mesmo arquivo SVG do PHP. Não usamos HTML de mensagens como código.
 const base=document.querySelector('meta[name="obras360-base"]')?.content||'';
 function makeIcon(name){
  const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
  svg.setAttribute('class','icon');svg.setAttribute('width','20');svg.setAttribute('height','20');
  svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('aria-hidden','true');
  const use=document.createElementNS('http://www.w3.org/2000/svg','use');
  use.setAttribute('href',base+'/assets/icons.svg#'+name);svg.append(use);return svg;
 }
 const toastRoot=document.getElementById('toast-root');
 const toastTitles={success:'Operação concluída',error:'Não foi possível concluir',warning:'Atenção',info:'Informação'};
 const closeToast=(item)=>{
  if(!item||item.dataset.closing==='1')return;
  item.dataset.closing='1';item.classList.add('is-hiding');
  if(window.matchMedia?.('(prefers-reduced-motion: reduce)').matches)item.remove();
  else window.setTimeout(()=>item.remove(),180);
 };
 function bindToast(item){
  item.querySelector('[data-close-toast]')?.addEventListener('click',()=>closeToast(item));
  const ms=Number(item.dataset.autoclose||0);
  if(ms>0)window.setTimeout(()=>closeToast(item),ms);
 }
 toastRoot?.querySelectorAll('[data-toast]').forEach(bindToast);
 function toast(message,tone='info'){
  if(!toastRoot)return;
  if(!Object.hasOwn(toastTitles,tone))tone='info';
  const item=document.createElement('div');item.className='obras-toast toast-'+tone;
  item.dataset.toast='';item.dataset.autoclose=tone==='error'?'0':'5000';
  item.setAttribute('role',tone==='error'?'alert':'status');
  const pict=document.createElement('span');pict.className='toast-icon';pict.append(makeIcon(tone==='error'||tone==='warning'?'alert':'check'));
  const copy=document.createElement('span');copy.className='toast-copy';
  const strong=document.createElement('strong');strong.textContent=toastTitles[tone];
  const txt=document.createElement('p');txt.textContent=message;
  copy.append(strong,txt);
  const dismiss=document.createElement('button');dismiss.type='button';dismiss.className='toast-close';
  dismiss.dataset.closeToast='';dismiss.title='Fechar';dismiss.setAttribute('aria-label','Fechar notificação');dismiss.append(makeIcon('x'));
  item.append(pict,copy,dismiss);toastRoot.append(item);bindToast(item);
 }

 // Confirmações semelhantes às janelas do protótipo, sem window.confirm.
 function confirmOperation(form,submitter){
  if(document.querySelector('.confirm-backdrop'))return;
  const previouslyFocused=document.activeElement;
  const overlay=document.createElement('div');overlay.className='confirm-backdrop';
  const dialog=document.createElement('section');dialog.className='confirm-dialog';
  dialog.setAttribute('role','dialog');dialog.setAttribute('aria-modal','true');
  dialog.setAttribute('aria-labelledby','confirm-title');dialog.setAttribute('aria-describedby','confirm-description');
  const symbol=document.createElement('span');symbol.className='confirm-symbol';symbol.append(makeIcon('alert'));
  const title=document.createElement('h2');title.id='confirm-title';title.textContent='Confirmar operação';
  const msg=document.createElement('p');msg.id='confirm-description';msg.textContent=form.dataset.confirm||'Deseja continuar?';
  const actions=document.createElement('div');actions.className='confirm-actions';
  const cancel=document.createElement('button');cancel.type='button';cancel.className='btn btn-outline';cancel.textContent='Cancelar';
  const ok=document.createElement('button');ok.type='button';ok.className='btn btn-primary';ok.textContent='Confirmar';
  actions.append(cancel,ok);dialog.append(symbol,title,msg,actions);overlay.append(dialog);document.body.append(overlay);
  function done(){document.removeEventListener('keydown',onKey);overlay.remove();previouslyFocused?.focus?.()}
  function onKey(e){
   if(e.key==='Escape'){e.preventDefault();done()}
   if(e.key==='Tab'){
    if(e.shiftKey&&document.activeElement===cancel){e.preventDefault();ok.focus()}
    else if(!e.shiftKey&&document.activeElement===ok){e.preventDefault();cancel.focus()}
   }
  }
  cancel.addEventListener('click',done);
  overlay.addEventListener('click',e=>{if(e.target===overlay)done()});
  ok.addEventListener('click',()=>{
   done();form.dataset.confirmed='1';
   if(submitter?.form===form)form.requestSubmit(submitter);
   else form.requestSubmit();
  });
  document.addEventListener('keydown',onKey);cancel.focus();
 }
 document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{
  if(form.dataset.confirmed==='1'){delete form.dataset.confirmed;return}
  e.preventDefault();confirmOperation(form,e.submitter);
 }));

 // Revelar/ocultar senha: login, perfil e senha provisória emitida pela administração.
 document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{
  const input=document.getElementById(button.dataset.passwordToggle);
  if(!input)return;
  const visible=input.type==='password';input.type=visible?'text':'password';
  const aria=visible?'Ocultar senha':'Mostrar senha';
  button.setAttribute('aria-pressed',String(visible));button.setAttribute('aria-label',aria);button.title=aria;
  const use=button.querySelector('use');
  if(use){const path=use.getAttribute('href')||'';use.setAttribute('href',path.replace(/#[^#]+$/,'#'+(visible?'eye-off':'eye')))}
 }));

 // A cópia usa a área de transferência; nenhum segredo é mostrado em janela nativa.
 async function copyToClipboard(value){
  if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(value);return true}
  const temp=document.createElement('textarea');temp.value=value;temp.readOnly=true;
  temp.style.position='fixed';temp.style.left='-9999px';document.body.append(temp);
  temp.select();let copied=false;
  try{copied=document.execCommand('copy')}catch{copied=false}
  temp.remove();return copied;
 }
 document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{
  const value=button.dataset.copy||'';
  try{
   if(await copyToClipboard(value))toast('Conteúdo copiado para a área de transferência.','success');
   else toast('Seu navegador bloqueou a cópia. Mostre o conteúdo e copie manualmente.','error');
  }catch{toast('Não foi possível copiar. Mostre o conteúdo e copie manualmente.','error')}
 }));
 document.querySelector('[data-print]')?.addEventListener('click',()=>window.print());
 document.querySelector('[data-use-geolocation]')?.addEventListener('click',()=>{
  const feedback=document.getElementById('geolocation-feedback');
  if(!navigator.geolocation){
   if(feedback)feedback.textContent='Localização não disponível neste navegador.';
   toast('Localização não disponível neste navegador.','error');return;
  }
  if(feedback)feedback.textContent='Aguardando permissão do dispositivo…';
  navigator.geolocation.getCurrentPosition(pos=>{
   const lat=document.querySelector('[data-coordinate="lat"]'),lng=document.querySelector('[data-coordinate="lng"]');
   if(lat&&lng){lat.value=pos.coords.latitude.toFixed(7);lng.value=pos.coords.longitude.toFixed(7)}
   if(feedback)feedback.textContent='Coordenadas preenchidas. Confira se correspondem ao local do registro.';
   toast('Coordenadas preenchidas. Confirme a posição do registro.','success');
  },err=>{
   if(feedback)feedback.textContent='Não foi possível obter a localização: '+err.message;
   toast('Não foi possível obter a localização: '+err.message,'error');
  },{enableHighAccuracy:true,timeout:12000,maximumAge:0});
 });
 const COLORS=['#169f93','#488bc6','#d6a54c','#8b74c4','#e18c86','#7ca8af','#a3cb85'];
 const draw=canvas=>{
  let labels=[],values=[];
  try{labels=JSON.parse(canvas.dataset.labels||'[]');values=JSON.parse(canvas.dataset.values||'[]').map(Number)}catch{return}
  const type=canvas.dataset.chart||'bars';
  const box=canvas.getBoundingClientRect(),width=Math.max(box.width,200),height=Math.max(box.height,170),dpr=Math.min(window.devicePixelRatio||1,2);
  canvas.width=Math.ceil(width*dpr);canvas.height=Math.ceil(height*dpr);
  const ctx=canvas.getContext('2d');if(!ctx)return;ctx.scale(dpr,dpr);ctx.clearRect(0,0,width,height);
  const valid=values.map(v=>Number.isFinite(v)?Math.max(0,v):0),sum=valid.reduce((a,b)=>a+b,0);
  if(!sum){ctx.font='14px system-ui';ctx.fillStyle='#94a8b4';ctx.textAlign='center';ctx.fillText('Sem dados para exibir',width/2,height/2);return;}
  if(type==='donut'){
   const cx=width/2,cy=height/2,r=Math.min(width,height)*.37,thickness=Math.max(17,Math.min(33,r*.33));let cursor=-Math.PI/2;
   ctx.lineWidth=thickness;ctx.lineCap='butt';valid.forEach((v,i)=>{
    const arc=Math.PI*2*v/sum;if(!arc)return;
    ctx.beginPath();ctx.strokeStyle=COLORS[i%COLORS.length];ctx.arc(cx,cy,r,cursor,cursor+arc);ctx.stroke();cursor+=arc;
   });
  } else {
   const longest=Math.max(...labels.map(x=>String(x).length),0);
   const labelSpace=width<420?100:Math.min(160,85+longest*2);
   const left=labelSpace,right=30,top=17,bottom=24,barArea=Math.max(60,width-left-right);
   const rows=Math.max(1,valid.length),step=Math.min(36,(height-top-bottom)/rows),barHeight=Math.max(9,Math.min(15,step*.46));
   const max=Math.max(1,...valid),space=(height-top-bottom)/rows;
   ctx.font='12px system-ui';ctx.textBaseline='middle';ctx.textAlign='right';
   valid.forEach((v,i)=>{
    const y=top+space*(i+.5),label=String(labels[i]??'');
    ctx.fillStyle='#75909f';ctx.fillText(label.length>25?label.slice(0,22)+'…':label,left-12,y,Math.max(55,left-15));
    ctx.fillStyle='#edf2f5';ctx.beginPath();ctx.roundRect(left,y-barHeight/2,barArea,barHeight,barHeight/2);ctx.fill();
    if(v>0){ctx.fillStyle=COLORS[i%COLORS.length];ctx.beginPath();ctx.roundRect(left,y-barHeight/2,Math.max(2,v/max*barArea),barHeight,barHeight/2);ctx.fill()}
    ctx.fillStyle='#3b5b6c';ctx.textAlign='left';ctx.fillText(String(Math.round(v)),left+4,y-barHeight-4);ctx.textAlign='right';
   });
  }
 };
 const charts=[...document.querySelectorAll('canvas[data-chart]')];
 charts.forEach(draw);
 if(typeof ResizeObserver!=='undefined'){
  const observer=new ResizeObserver(entries=>entries.forEach(entry=>draw(entry.target)));charts.forEach(c=>observer.observe(c));
 } else window.addEventListener('resize',()=>charts.forEach(draw));
})();

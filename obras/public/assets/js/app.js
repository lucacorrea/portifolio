/* OBRAS360 — somente comportamento visual. Operações persistentes são feitas pelo PHP. */
(()=>{'use strict';
 const sidebar=document.getElementById('sidebar'),mask=document.querySelector('.sidebar-mask');
 const toggle=document.querySelector('[data-toggle-sidebar]');
 function close(){sidebar?.classList.remove('open');mask?.classList.remove('open')}
 toggle?.addEventListener('click',()=>{sidebar?.classList.toggle('open');mask?.classList.toggle('open')});
 mask?.addEventListener('click',close);
 document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
 document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{
  if(!window.confirm(form.dataset.confirm||'Confirmar operação?'))e.preventDefault();
 }));
 document.querySelectorAll('[data-password-toggle]').forEach(button=>button.addEventListener('click',()=>{
  const input=document.getElementById(button.dataset.passwordToggle);
  if(input)input.type=input.type==='password'?'text':'password';
 }));
 document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{
  const value=button.dataset.copy;
  try {await navigator.clipboard.writeText(value);button.textContent='Copiado';}
  catch {window.prompt('Copie a credencial temporária:',value)}
 }));
 document.querySelector('[data-print]')?.addEventListener('click',()=>window.print());
 document.querySelector('[data-use-geolocation]')?.addEventListener('click',()=>{
  const feedback=document.getElementById('geolocation-feedback');
  if(!navigator.geolocation){if(feedback)feedback.textContent='Localização não disponível neste navegador.';return}
  if(feedback)feedback.textContent='Aguardando permissão do dispositivo…';
  navigator.geolocation.getCurrentPosition(pos=>{
   const lat=document.querySelector('[data-coordinate="lat"]'),lng=document.querySelector('[data-coordinate="lng"]');
   if(lat&&lng){lat.value=pos.coords.latitude.toFixed(7);lng.value=pos.coords.longitude.toFixed(7)}
   if(feedback)feedback.textContent='Coordenadas preenchidas. Confira se correspondem ao local do registro.';
  },err=>{if(feedback)feedback.textContent='Não foi possível obter a localização: '+err.message},
  {enableHighAccuracy:true,timeout:12000,maximumAge:0});
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

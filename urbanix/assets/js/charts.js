document.addEventListener('DOMContentLoaded', () => {
  if (typeof Chart === 'undefined') return;
  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.color = '#667085';
  const css = getComputedStyle(document.documentElement);
  const primary = css.getPropertyValue('--primary').trim() || '#0f6c5c';
  const accent = css.getPropertyValue('--accent').trim() || '#d7a84b';
  const info = css.getPropertyValue('--info').trim() || '#2767c5';
  const danger = css.getPropertyValue('--danger').trim() || '#c23a3a';
  const grid = '#edf0f4';

  const sales = document.getElementById('salesChart');
  if (sales) new Chart(sales,{type:'line',data:{labels:['Mar','Abr','Mai','Jun','Jul','Ago'],datasets:[{label:'Vendas',data:[620,780,710,960,1120,1280],borderColor:primary,backgroundColor:'rgba(15,108,92,.08)',fill:true,tension:.35,borderWidth:2.5,pointRadius:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:grid},ticks:{callback:v=>'R$ '+v+'k'}}}}});

  const availability = document.getElementById('availabilityChart');
  if (availability) new Chart(availability,{type:'doughnut',data:{labels:['Vendidos','Disponíveis','Reservados','Bloqueados'],datasets:[{data:[214,108,17,6],backgroundColor:[primary,info,accent,'#a6aebb'],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:'72%',plugins:{legend:{display:false}}}});

  const cash = document.getElementById('cashChart');
  if (cash) new Chart(cash,{type:'bar',data:{labels:['Mar','Abr','Mai','Jun','Jul','Ago'],datasets:[{label:'Receitas',data:[820,940,1020,910,1180,1320],backgroundColor:primary,borderRadius:6},{label:'Despesas',data:[540,610,650,690,760,830],backgroundColor:'#d5dce5',borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top',align:'end',labels:{boxWidth:10,usePointStyle:true}}},scales:{x:{grid:{display:false}},y:{grid:{color:grid},ticks:{callback:v=>'R$ '+v+'k'}}}}});

  const works = document.getElementById('worksChart');
  if (works) new Chart(works,{type:'bar',data:{labels:['Terraplanagem','Drenagem','Elétrica','Água','Pavimentação','Paisagismo'],datasets:[{data:[100,100,92,85,71,32],backgroundColor:[primary,primary,primary,primary,accent,info],borderRadius:7}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{min:0,max:100,grid:{color:grid},ticks:{callback:v=>v+'%'}},y:{grid:{display:false}}}}});

  const funnel = document.getElementById('funnelChart');
  if (funnel) new Chart(funnel,{type:'bar',data:{labels:['Leads','Contato','Visitas','Propostas','Reservas','Vendas'],datasets:[{data:[83,61,38,22,11,7],backgroundColor:[info,'#3d7dd8','#5d91d9',accent,'#d3a548',primary],borderRadius:7}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false}},y:{grid:{color:grid},beginAtZero:true}}}});
});

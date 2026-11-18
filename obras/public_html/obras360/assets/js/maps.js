/* Geovisualização Leaflet / OpenStreetMap. O mapa nunca cria coordenadas de obras automaticamente. */
(()=>{'use strict';
 function init(){
  const container=document.getElementById('obras-map');if(!container)return;
  if(typeof window.L==='undefined'){
   container.innerHTML='<div class="map-loading">A biblioteca do mapa não carregou. Confira a conexão com a internet. A lista de registros continua disponível à direita.</div>';
   return;
  }
  let points=[];try{points=JSON.parse(container.dataset.points||'[]')}catch{points=[]}
  const map=window.L.map(container,{zoomControl:true,scrollWheelZoom:false}).setView([-4.085,-63.14],12);
  const tile=container.dataset.tileUrl||'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  window.L.tileLayer(tile,{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'}).addTo(map);
  const markers=new Map(),bounds=[];
  for (const p of points){
   const lat=Number(p.lat),lng=Number(p.lng);
   if(!Number.isFinite(lat)||!Number.isFinite(lng)||Math.abs(lat)>90||Math.abs(lng)>180)continue;
   const color=p.module==='obras'?'#008c7d':'#3b75bf';
   const marker=window.L.circleMarker([lat,lng],{radius:9,color:'#fff',weight:2,fillColor:color,fillOpacity:1});
   const popup=document.createElement('div');popup.className='map-popup';
   const name=document.createElement('strong');name.textContent=p.title;popup.append(name);
   const loc=document.createElement('p');loc.textContent=(p.code||'')+' · '+(p.local||'')+' · '+(p.status||'');popup.append(loc);
   const link=document.createElement('a');link.textContent='Abrir ficha do registro';
   link.href=(document.querySelector('meta[name="obras360-base"]')?.content||'')+'/index.php?r='+encodeURIComponent(p.module)+'&action=show&id='+encodeURIComponent(p.id);popup.append(link);
   marker.bindPopup(popup);marker.addTo(map);markers.set(p.module+':'+p.id,{marker,point:p});bounds.push([lat,lng]);
  }
  if(bounds.length>1)map.fitBounds(bounds,{padding:[30,30],maxZoom:14});
  else if(bounds.length===1)map.setView(bounds[0],14);
  function applyFilters(){const active=new Set([...document.querySelectorAll('[data-map-filter]:checked')].map(e=>e.dataset.mapFilter));
   for(const {marker,point} of markers.values())active.has(point.module)?marker.addTo(map):map.removeLayer(marker);
  }
  document.querySelectorAll('[data-map-filter]').forEach(el=>el.addEventListener('change',applyFilters));
  document.querySelectorAll('[data-map-go]').forEach(button=>button.addEventListener('click',()=>{
   const item=markers.get(button.dataset.mapGo);if(!item)return;
   document.querySelector('[data-map-filter="'+item.point.module+'"]').checked=true;applyFilters();
   map.flyTo(item.marker.getLatLng(),15,{duration:.8});item.marker.openPopup();
   if(matchMedia('(max-width: 1020px)').matches)container.scrollIntoView({behavior:'smooth',block:'center'});
  }));
  setTimeout(()=>map.invalidateSize(),350);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();

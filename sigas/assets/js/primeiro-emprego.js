'use strict';

/* Dados e ações desta camada são demonstrativos; autorização e persistência devem ocorrer no PHP. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-pe-search]')?.addEventListener('input', event => {
        const term = event.target.value.trim().toLowerCase();
        const rows = [...document.querySelectorAll('[data-pe-row], .pe-desktop-table tbody tr')];
        let matches = 0;
        rows.forEach(row => { const visible = !term || row.textContent.toLowerCase().includes(term); row.hidden = !visible; if (visible) matches += 1; });
        document.querySelector('[data-pe-empty]')?.classList.toggle('show', matches === 0);
    });
    document.addEventListener('click', event => {
        const detail = event.target.closest('[data-pe-detail]');
        if (detail) { document.querySelector('[data-pe-detail-text]').textContent = `${detail.dataset.peDetail}: dados visuais para demonstração.`; bootstrap.Modal.getOrCreateInstance('#peDetailModal').show(); }
    });

    const form = document.querySelector('[data-pe-form]');
    if (form) {
        const steps = [...form.querySelectorAll('[data-pe-step]')]; let index = 0;
        const update = () => { steps.forEach((step, pos) => step.hidden = pos !== index); document.querySelectorAll('[data-pe-step-indicator]').forEach((item, pos) => item.classList.toggle('active', pos === index)); form.querySelector('[data-pe-prev]').disabled = index === 0; form.querySelector('[data-pe-next]').innerHTML = index === steps.length - 1 ? 'Finalizar<i class="bi bi-check-lg"></i>' : 'Próximo<i class="bi bi-arrow-right"></i>'; };
        form.querySelector('[data-pe-prev]').addEventListener('click', () => { index = Math.max(0, index - 1); update(); });
        form.querySelector('[data-pe-next]').addEventListener('click', () => { const fields = [...steps[index].querySelectorAll('[required]')]; const valid = fields.every(field => { const ok = field.value.trim() !== ''; field.classList.toggle('is-invalid', !ok); return ok; }); if (!valid) return; if (index === steps.length - 1) { window.SIGAS?.showToast('Cadastro demonstrativo revisado.'); return; } index += 1; update(); });
    }
    if (typeof Chart !== 'undefined') { const candidates = document.querySelector('#peCandidatesChart'); if (candidates) new Chart(candidates, { type:'line', data:{ labels:['Fev','Mar','Abr','Mai','Jun','Jul'], datasets:[{label:'Candidatos',data:[118,136,152,161,184,207],borderColor:'#176b85',backgroundColor:'rgba(23,107,133,.12)',fill:true,tension:.35}] }, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}} }); const areas = document.querySelector('#peAreasChart'); if (areas) new Chart(areas,{type:'doughnut',data:{labels:['Comércio','Administrativo','Serviços'],datasets:[{data:[38,29,19],backgroundColor:['#176b85','#4f9ab4','#a8cbd7']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}}); }
});

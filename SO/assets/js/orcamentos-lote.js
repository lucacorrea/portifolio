import { extractPdfTables, cents, lineTotal, canonical, money, decimalBR } from './orcamentos-table.js';

const config = JSON.parse(document.getElementById('batch-config').textContent);
const $ = id => document.getElementById(id);
const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const token = () => Array.from(crypto.getRandomValues(new Uint8Array(16)), n => n.toString(16).padStart(2, '0')).join('');
const documents = [];
const batchToken = token();
let selected = null, extracting = false, saving = false, previewVersion = 0;
const numericValue = value => /^\d+\.\d{1,2}$/.test(String(value ?? '')) ? String(value).replace('.', ',') : String(value ?? '');
function options(list, value) { return '<option value="">Selecione</option>' + list.map(s => `<option value="${s.id}" ${String(value) === String(s.id) ? 'selected' : ''}>${escape(s.nome)}${s.cnpj ? ' · ' + escape(s.cnpj) : ''}</option>`).join(''); }
function pending(doc) {
    const errors = [];
    if (['queued', 'reading', 'saving', 'done'].includes(doc.state)) return ['Processamento em andamento ou concluído.'];
    if (!doc.secretaria_id) errors.push('Selecione a secretaria.');
    if (!doc.fornecedor_id) errors.push('Selecione o fornecedor.');
    if (!doc.local.trim()) errors.push('Informe o local.');
    if (!doc.justificativa.trim()) errors.push('Informe a justificativa.');
    if (!doc.items.length) errors.push('Adicione os itens do orçamento.');
    let sum = 0;
    doc.items.forEach((item, i) => {
        const total = lineTotal(item.quantidade, item.valor_unitario);
        if (!item.produto.trim() || item.produto.length > 255 || !item.unidade.trim() || item.unidade.length > 20) errors.push(`Item ${i + 1}: confira descrição e unidade.`);
        if (!(cents(item.quantidade) > 0) || !(cents(item.valor_unitario) > 0) || total === null) errors.push(`Item ${i + 1}: confira quantidade e preço.`);
        if (total !== cents(item.valor_total)) errors.push(`Item ${i + 1}: quantidade × preço diverge do total.`);
        sum += total || 0;
    });
    if (cents(doc.total) !== sum || sum <= 0) errors.push('A soma dos itens precisa conferir com o total do documento.');
    if (!doc.reviewed) errors.push('Confirme a conferência de todas as páginas e itens.');
    return [...new Set(errors)];
}
function label(doc) {
    if (doc.state === 'done') return doc.result.numero_aq ? 'Aquisição gerada' : 'Enviado para aprovação';
    if (doc.state === 'reading') return 'Lendo tabela…';
    if (doc.state === 'queued') return 'Na fila';
    if (doc.state === 'saving') return 'Gravando…';
    if (doc.error) return 'Verificar erro';
    return pending(doc).length ? 'A conferir' : 'Pronto';
}
function update() {
    $('batch-workspace').hidden = !documents.length;
    $('batch-count').textContent = documents.length;
    $('batch-documents').innerHTML = documents.map((d, i) => `<button type="button" class="batch-document ${selected === d ? 'active' : ''}" data-document="${d.token}" aria-pressed="${selected === d}"><span class="batch-doc-number">${i + 1}</span><span><strong>${escape(d.file.name)}</strong><small>${escape(label(d))} · ${d.items.length} item(ns)</small></span></button>`).join('');
    const ready = documents.filter(d => pending(d).length === 0 && !d.error);
    const done = documents.filter(d => d.state === 'done').length;
    $('batch-summary').textContent = `${documents.length} orçamento(s) · ${ready.length} pronto(s) · ${done} concluído(s)`;
    $('batch-submit').disabled = saving || extracting || ready.length === 0 || config.disabled;
    $('batch-files').disabled = saving || config.disabled;
    if (selected && $('doc-pending')) {
        const errors = pending(selected);
        $('doc-pending').textContent = selected.state === 'done' ? label(selected) : (errors[0] || 'Conferência concluída. Orçamento pronto.');
        $('doc-pending').className = errors.length ? 'batch-pending' : 'batch-ok';
        $('doc-sum').textContent = money(selected.items.reduce((sum, i) => sum + (lineTotal(i.quantidade, i.valor_unitario) || 0), 0));
    }
}
function render() {
    if (!selected) { $('batch-editor').innerHTML = ''; update(); return; }
    const d = selected, busy = ['queued', 'reading', 'saving', 'done'].includes(d.state) || saving;
    const rows = d.items.map((item, i) => `<tr data-row="${i}" class="${item.revisar ? 'batch-uncertain' : ''}">
        <td><input aria-label="Referência do item ${i + 1}" data-item="codigo" maxlength="50" value="${escape(item.codigo)}"><small>Pág. ${item.pagina || '—'}</small></td>
        <td><textarea aria-label="Descrição do item ${i + 1}" data-item="produto" maxlength="255" rows="3">${escape(item.produto)}</textarea></td>
        <td><input aria-label="Unidade do item ${i + 1}" data-item="unidade" maxlength="20" placeholder="Selecionar" list="batch-units" value="${escape(item.unidade)}"></td>
        <td><input aria-label="Quantidade do item ${i + 1}" data-item="quantidade" inputmode="decimal" value="${escape(numericValue(item.quantidade))}"></td>
        <td><input aria-label="Preço unitário do item ${i + 1}" data-item="valor_unitario" inputmode="decimal" value="${escape(numericValue(item.valor_unitario))}"></td>
        <td><input aria-label="Total do item ${i + 1}" data-item="valor_total" inputmode="decimal" value="${escape(numericValue(item.valor_total))}"><button type="button" class="batch-text-button" data-calculate="${i}">Calcular</button></td>
        <td><button type="button" class="batch-text-button" data-remove-row="${i}" aria-label="Excluir item ${i + 1}">Excluir</button></td></tr>`).join('');
    $('batch-editor').innerHTML = `<div class="batch-editor-heading"><div><h2>${escape(d.file.name)}</h2><p>${escape(label(d))}</p></div><button type="button" class="btn btn-outline" id="doc-remove" ${busy ? 'disabled' : ''}>Remover do lote</button></div>
        ${d.error ? `<p class="batch-alert" role="alert">${escape(d.error)}</p>` : ''}
        <div class="batch-review-grid"><section class="batch-preview"><div class="batch-preview-toolbar"><button type="button" id="preview-prev" aria-label="Página anterior">‹</button><span id="preview-page">Página ${d.page} de ${d.pages || '?'}</span><button type="button" id="preview-next" aria-label="Próxima página">›</button></div>
        <p>Arraste sobre a tabela para ajustar a área de leitura.</p><div class="batch-canvas-wrap" id="preview-wrap"><canvas id="doc-preview" aria-label="Página do orçamento; use os campos de recorte abaixo como alternativa"></canvas><div id="doc-crop-box" hidden></div></div>
        <details><summary>Ajustar recorte por porcentagem</summary><div class="batch-crop-fields">${[['x', 'Esquerda'], ['y', 'Topo'], ['w', 'Largura'], ['h', 'Altura']].map(([key, name]) => `<label>${name}<input data-crop="${key}" type="number" min="0" max="100" value="${Math.round((d.crops[d.page]?.[key] ?? (['w', 'h'].includes(key) ? 1 : 0)) * 100)}" ${busy ? 'disabled' : ''}></label>`).join('')}</div></details>
        <div class="batch-preview-toolbar"><button type="button" class="btn btn-outline" id="doc-reextract" ${busy ? 'disabled' : ''}>Reler tabela</button><button type="button" class="batch-text-button" id="doc-clear-crop" ${busy ? 'disabled' : ''}>Página inteira</button></div></section>
        <section class="batch-fields"><fieldset ${busy ? 'disabled' : ''}>
        <label>Secretaria<select data-doc="secretaria_id">${options(config.secretarias, d.secretaria_id)}</select></label>
        <label>Fornecedor<select data-doc="fornecedor_id">${options(config.fornecedores, d.fornecedor_id)}</select></label>
        <label>Local da solicitação<input data-doc="local" maxlength="150" value="${escape(d.local)}"></label>
        <label>Justificativa<textarea data-doc="justificativa" maxlength="5000" rows="3">${escape(d.justificativa)}</textarea></label>
        <label>Total do orçamento (R$)<input data-doc="total" inputmode="decimal" value="${escape(numericValue(d.total))}" placeholder="Informe o total impresso no PDF"></label>
        </fieldset>${d.warnings.length ? `<details open class="batch-warnings"><summary>Pontos para conferir (${d.warnings.length})</summary><ul>${d.warnings.map(w => `<li>${escape(w)}</li>`).join('')}</ul></details>` : ''}</section></div>
        <fieldset ${busy ? 'disabled' : ''}><div class="batch-items-heading"><h3>Itens extraídos</h3><button type="button" class="btn btn-outline" id="doc-add-item" ${d.items.length >= 200 ? 'disabled' : ''}>Adicionar item</button></div>
        <div class="batch-table-scroll"><table class="batch-table"><thead><tr><th>Referência</th><th>Descrição</th><th>Unidade</th><th>Quantidade</th><th>Preço (R$)</th><th>Total (R$)</th><th>Ação</th></tr></thead><tbody>${rows}</tbody></table></div>
        <datalist id="batch-units"><option value="UN"><option value="SERV"><option value="M²"><option value="M³"><option value="M"><option value="KG"><option value="L"><option value="CX"></datalist>
        <div class="batch-check"><strong>Soma calculada: <span id="doc-sum"></span></strong><label><input type="checkbox" id="doc-reviewed" ${d.reviewed ? 'checked' : ''}> Conferi todas as páginas, itens, unidades, valores e fornecedor deste orçamento.</label><p id="doc-pending"></p></div></fieldset>`;
    if (d.state === 'done') $('batch-editor').insertAdjacentHTML('beforeend', resultHtml(d));
    bindEditor(d); update(); renderPreview(d);
}
function resultHtml(d) {
    const r = d.result;
    return `<p class="batch-ok">${r.reutilizado ? 'Orçamento já importado; registro recuperado.' : 'Documento gravado.'} ${escape(r.numero)} ${r.aquisicao_id ? `· <a href="aquisicoes_visualizar.php?id=${Number(r.aquisicao_id)}" target="_blank" rel="noopener">Aquisição ${escape(r.numero_aq)}</a>` : '· Aguarda aprovação'}</p>`;
}
function markChanged(d) { d.reviewed = false; d.error = ''; const check = $('doc-reviewed'); if (check) check.checked = false; update(); }
function bindEditor(d) {
    $('batch-editor').oninput = event => {
        const el = event.target;
        if (el.dataset.doc) { d[el.dataset.doc] = el.value; markChanged(d); }
        if (el.dataset.item) { d.items[Number(el.closest('[data-row]').dataset.row)][el.dataset.item] = el.value; markChanged(d); }
        if (el.dataset.crop) {
            const crop = d.crops[d.page] ||= { x: 0, y: 0, w: 1, h: 1 };
            crop[el.dataset.crop] = Math.max(0, Math.min(['x', 'y'].includes(el.dataset.crop) ? 0.98 : 1, Number(el.value) / 100));
            crop.w = Math.max(0.02, Math.min(crop.w, 1 - crop.x)); crop.h = Math.max(0.02, Math.min(crop.h, 1 - crop.y));
            markChanged(d); drawCrop(d);
        }
    };
    $('doc-reviewed').onchange = e => { d.reviewed = e.target.checked; if (d.reviewed) d.error = ''; update(); };
    $('doc-add-item').onclick = () => { if (d.items.length >= 200) return; d.items.push({ codigo: '', produto: '', unidade: '', quantidade: '', valor_unitario: '', valor_total: '' }); markChanged(d); render(); };
    $('batch-editor').onclick = event => {
        const remove = event.target.closest('[data-remove-row]'), calc = event.target.closest('[data-calculate]');
        if (remove) { d.items.splice(Number(remove.dataset.removeRow), 1); markChanged(d); render(); }
        if (calc) {
            const row = d.items[Number(calc.dataset.calculate)], total = lineTotal(row.quantidade, row.valor_unitario);
            if (total !== null) { row.valor_total = canonical(total); markChanged(d); render(); }
        }
    };
    $('doc-remove').onclick = () => { documents.splice(documents.indexOf(d), 1); selected = documents[0] || null; render(); };
    $('doc-reextract').onclick = () => { if (d.items.length && !window.confirm('Reler a tabela substituirá os itens deste orçamento. Continuar?')) return; d.state = 'queued'; d.reviewed = false; d.error = ''; render(); runQueue(); };
    $('doc-clear-crop').onclick = () => { delete d.crops[d.page]; markChanged(d); render(); };
    $('preview-prev').onclick = () => { if (d.page > 1) { d.page--; render(); } };
    $('preview-next').onclick = () => { if (d.page < d.pages) { d.page++; render(); } };
}
function drawCrop(d) {
    if (selected !== d || !$('doc-crop-box')) return;
    const box = $('doc-crop-box'), crop = d.crops[d.page];
    box.hidden = !crop;
    if (crop) Object.assign(box.style, { left: `${crop.x * 100}%`, top: `${crop.y * 100}%`, width: `${crop.w * 100}%`, height: `${crop.h * 100}%` });
}
async function renderPreview(d) {
    const version = ++previewVersion;
    let pdf;
    try {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/js/vendor/pdfjs/pdf.worker.min.js';
        pdf = await window.pdfjsLib.getDocument({ data: new Uint8Array(await d.file.arrayBuffer()), isEvalSupported: false }).promise;
        d.pages = pdf.numPages;
        if (version !== previewVersion) return;
        const page = await pdf.getPage(d.page), base = page.getViewport({ scale: 1 });
        const view = page.getViewport({ scale: Math.min(1.5, 1100 / Math.max(base.width, base.height)) });
        const canvas = $('doc-preview'); canvas.width = Math.ceil(view.width); canvas.height = Math.ceil(view.height);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport: view }).promise;
        if (version !== previewVersion) return;
        $('preview-page').textContent = `Página ${d.page} de ${d.pages}`; drawCrop(d);
        let start;
        const point = e => { const r = canvas.getBoundingClientRect(); return { x: Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)), y: Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) }; };
        canvas.onpointerdown = e => { if (saving || ['queued', 'reading', 'done'].includes(d.state)) return; start = point(e); canvas.setPointerCapture(e.pointerId); };
        canvas.onpointermove = e => { if (!start) return; const end = point(e); d.crops[d.page] = { x: Math.min(start.x, end.x), y: Math.min(start.y, end.y), w: Math.abs(end.x - start.x), h: Math.abs(end.y - start.y) }; drawCrop(d); };
        canvas.onpointerup = () => { if (!start) return; start = null; const crop = d.crops[d.page]; if (!crop || crop.w < 0.03 || crop.h < 0.03) delete d.crops[d.page]; markChanged(d); drawCrop(d); };
        canvas.onpointercancel = () => { start = null; };
    } catch (error) { if (version === previewVersion && $('preview-page')) $('preview-page').textContent = `Prévia indisponível: ${error.message}`; }
    finally { if (pdf) await pdf.destroy(); }
}
async function runQueue() {
    if (extracting) return;
    extracting = true;
    try {
        for (const d of documents) {
            if (d.state !== 'queued') continue;
            d.state = 'reading'; update(); if (selected === d) render();
            try {
                const result = await extractPdfTables(d.file, message => { $('batch-status').textContent = `${d.file.name}: ${message}`; }, d.crops);
                d.items = result.items; d.total = result.total; d.warnings = result.warnings; d.pages = result.pages; d.state = 'review';
                if (!d.items.length) d.error = 'Nenhum item identificado. Ajuste o recorte e releia, ou preencha a tabela manualmente.';
            } catch (error) { d.state = 'review'; d.error = error.message; }
            if (selected === d) render(); else update();
        }
    } finally { extracting = false; $('batch-status').textContent = 'Leitura concluída. Confira cada orçamento antes de gerar o lote.'; update(); }
}
async function addFiles(files) {
    if (saving || config.disabled) return;
    for (const file of files) {
        if (documents.length >= 20) { $('batch-status').textContent = 'Limite de 20 PDFs por lote.'; break; }
        if (!/\.pdf$/i.test(file.name) || file.size > 15 * 1024 * 1024 || file.size < 5) { $('batch-status').textContent = `${file.name}: envie um PDF de até 15 MB.`; continue; }
        const hash = Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await file.arrayBuffer())), n => n.toString(16).padStart(2, '0')).join('');
        if (documents.some(d => d.hash === hash)) { $('batch-status').textContent = `${file.name}: este arquivo já está no lote.`; continue; }
        const d = { token: token(), file, hash, state: 'queued', items: [], warnings: [], total: '', local: '', justificativa: '',
            secretaria_id: $('batch-secretaria').value, fornecedor_id: $('batch-fornecedor').value, reviewed: false, crops: {}, page: 1, pages: 0, error: '' };
        documents.push(d); if (!selected) selected = d;
    }
    render(); runQueue();
}
$('batch-files').onchange = e => { const files = [...e.target.files]; e.target.value = ''; addFiles(files); };
$('batch-drop').ondragover = e => { e.preventDefault(); $('batch-drop').classList.add('dragging'); };
$('batch-drop').ondragleave = () => $('batch-drop').classList.remove('dragging');
$('batch-drop').ondrop = e => { e.preventDefault(); $('batch-drop').classList.remove('dragging'); addFiles([...e.dataTransfer.files]); };
$('batch-documents').onclick = e => { const button = e.target.closest('[data-document]'); if (button) { selected = documents.find(d => d.token === button.dataset.document); render(); } };
$('batch-submit').onclick = () => {
    const ready = documents.filter(d => pending(d).length === 0 && !d.error);
    $('batch-confirm-summary').innerHTML = `<strong>${ready.length} orçamento(s) · ${money(ready.reduce((sum, d) => sum + cents(d.total), 0))}</strong><ul>${ready.map(d => `<li>${escape(d.file.name)} — ${escape(config.fornecedores.find(f => String(f.id) === String(d.fornecedor_id))?.nome)} — ${money(cents(d.total))}</li>`).join('')}</ul>`;
    $('batch-confirm').showModal();
};
$('batch-confirm').addEventListener('close', async () => {
    if ($('batch-confirm').returnValue !== 'confirm' || saving) return;
    const ready = documents.filter(d => pending(d).length === 0 && !d.error);
    saving = true; update(); render();
    try {
        for (const d of ready) {
            d.state = 'saving'; update(); if (selected === d) render();
            const data = { token: d.token, lote_token: batchToken, confirmado: true, modo: config.canApprove ? 'aprovar' : 'enviar',
                secretaria_id: Number(d.secretaria_id), fornecedor_id: Number(d.fornecedor_id), local: d.local, justificativa: d.justificativa,
                total_documento: decimalBR(d.total), itens: d.items.map(i => ({ codigo: i.codigo, produto: i.produto, unidade: i.unidade,
                    quantidade: decimalBR(i.quantidade), valor_unitario: decimalBR(i.valor_unitario), valor_total: decimalBR(i.valor_total) })) };
            const form = new FormData(); form.append('csrf_token', config.csrf); form.append('dados', JSON.stringify(data)); form.append('pdf', d.file);
            try {
                const response = await fetch('orcamentos_lote.php', { method: 'POST', body: form, credentials: 'same-origin', headers: { Accept: 'application/json' } });
                let body; try { body = await response.json(); } catch { throw new Error('O servidor não respondeu como esperado. Confira sua sessão e tente novamente.'); }
                if (!response.ok || !body.ok) throw new Error(body.error || 'Falha ao gravar orçamento.');
                d.result = body.resultado; d.state = 'done';
                $('batch-results').insertAdjacentHTML('beforeend', `<div><strong>${escape(d.file.name)}</strong>${resultHtml(d)}</div>`);
            } catch (error) { d.state = 'review'; d.error = error.message; d.reviewed = false; }
            if (selected === d) render(); else update();
        }
    } finally { saving = false; $('batch-status').textContent = 'Processamento encerrado. Confira os resultados; arquivos com erro podem ser corrigidos e reenviados.'; render(); }
});
window.addEventListener('beforeunload', event => { if (documents.some(d => d.state !== 'done')) { event.preventDefault(); event.returnValue = ''; } });
update();

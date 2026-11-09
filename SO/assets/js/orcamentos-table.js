// Extração por posições de células. Sem regras de fornecedor ou recortes fixos.
export const norm = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().replace(/[^A-Z0-9]/g, '');
export function decimalBR(value) {
    let text = String(value ?? '').trim().replace(/R\$/gi, '').replace(/\s/g, '');
    if (!text) return '';
    if (text.includes(',')) {
        if (!/^(?:\d{1,3}(?:\.\d{3})+|\d+),\d{1,2}$/.test(text)) return '';
        text = text.replace(/\./g, '').replace(',', '.');
    } else if (/^\d{1,3}(?:\.\d{3})+$/.test(text)) text = text.replace(/\./g, '');
    if (!/^\d+(?:\.\d{1,2})?$/.test(text)) return '';
    return text;
}
export function cents(value) {
    const text = decimalBR(value);
    if (!text) return null;
    const [whole, fraction = ''] = text.split('.');
    const n = Number(whole + fraction.padEnd(2, '0'));
    return Number.isSafeInteger(n) ? n : null;
}
export function lineTotal(qty, price) {
    const q = cents(qty), p = cents(price);
    if (q === null || p === null || !Number.isSafeInteger(q * p)) return null;
    return Math.floor((q * p + 50) / 100);
}
export const canonical = n => `${Math.floor(n / 100)}.${String(n % 100).padStart(2, '0')}`;
export const money = n => (n / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const cx = w => (w.bbox.x0 + w.bbox.x1) / 2;
const cy = w => (w.bbox.y0 + w.bbox.y1) / 2;
function groupLines(words) {
    const lines = [];
    for (const word of [...words].sort((a, b) => cy(a) - cy(b) || cx(a) - cx(b))) {
        let line = lines.find(l => Math.abs(l.y - cy(word)) < Math.max(8, (word.bbox.y1 - word.bbox.y0) * 0.55));
        if (!line) { line = { y: cy(word), words: [] }; lines.push(line); }
        line.words.push(word);
    }
    return lines.sort((a, b) => a.y - b.y).map(l => ({ ...l, words: l.words.sort((a, b) => cx(a) - cx(b)) }));
}
const join = words => groupLines(words).map(l => l.words.map(w => w.text).join(' ')).join(' ').replace(/\s+/g, ' ').trim();

export function verticalRules(pixels, startY, endY) {
    if (!pixels) return [];
    const { data, width, height } = pixels;
    const y0 = Math.max(0, Math.floor(startY)), y1 = Math.min(height, Math.ceil(endY));
    if (y1 - y0 < 30) return [];
    const hits = [];
    for (let x = 4; x < width - 4; x++) {
        let dark = 0, count = 0;
        for (let y = y0; y < y1; y += 3) {
            count++;
            for (let dx = -3; dx <= 3; dx++) {
                const p = (y * width + x + dx) * 4;
                if ((data[p] + data[p + 1] + data[p + 2]) / 3 < 195) { dark++; break; }
            }
        }
        if (dark / count > 0.72) hits.push(x);
    }
    const groups = [];
    for (const x of hits) {
        const last = groups.at(-1);
        if (last && x - last.at(-1) <= 3) last.push(x); else groups.push([x]);
    }
    return groups.filter(g => g.length <= 25).map(g => g[Math.floor(g.length / 2)]);
}

export function parseTableWords(words, pixels = null) {
    words = words.filter(w => w.text?.trim() && w.bbox && /[\p{L}\d]/u.test(w.text));
    const lines = groupLines(words);
    const description = words.find(w => /^(DESCRICAO|DISCRIMINACAO|ESPECIFICACAO|PRODUTO|PRODUTOS)$/.test(norm(w.text)));
    if (!description) throw new Error('Cabeçalho de descrição não localizado. Selecione somente a tabela e tente novamente.');
    const h = Math.max(14, description.bbox.y1 - description.bbox.y0);
    const headerWords = words.filter(w => Math.abs(cy(w) - cy(description)) <= h * 1.8);
    const qty = headerWords.find(w => /^(QUANT|QUANTIDADE|QTD|QTDE)$/.test(norm(w.text)));
    if (!qty) throw new Error('Coluna de quantidade não localizada. Ajuste o recorte da tabela.');
    const code = headerWords.find(w => /^(ITEM|ITENS|COD|CODIGO|ORDEM)$/.test(norm(w.text)));
    const price = headerWords.find(w => /^(UNIT|UNITARIO|UNITARIA)$/.test(norm(w.text)) && cx(w) > cx(qty));
    const total = headerWords.find(w => /^(TOTAL)$/.test(norm(w.text)) && cx(w) > cx(qty));
    const unit = headerWords.find(w => /^(UNID|UND|UNIDADE|UN)$/.test(norm(w.text)) && w !== price);
    const headerBottom = Math.max(...headerWords.filter(w => [description, qty, code, price, total, unit].includes(w)).map(w => w.bbox.y1));
    const footerLine = lines.find(l => l.y > headerBottom + h && /^(VALORTOTAL|TOTALGERAL|TOTALDOORCAMENTO|TOTALR?\d)/.test(norm(l.words.map(w => w.text).join(' '))));
    const endY = footerLine ? Math.min(...footerLine.words.map(w => w.bbox.y0)) : Math.max(...words.map(w => w.bbox.y1));
    const rules = verticalRules(pixels, headerBottom + h, endY);
    const headers = [['codigo', code], ['produto', description], ['unidade', unit], ['quantidade', qty], ['valor_unitario', price], ['valor_total', total]]
        .filter(([, w]) => w).sort((a, b) => cx(a[1]) - cx(b[1]));
    const boundaries = headers.slice(1).map(([key, w], i) => {
        const left = cx(headers[i][1]), right = cx(w);
        const candidates = rules.filter(x => x > left && x < right);
        return candidates.length ? candidates.reduce((a, b) => Math.abs(a - (left + right) / 2) < Math.abs(b - (left + right) / 2) ? a : b)
            : key === 'produto' ? headers[i][1].bbox.x1 + h * 0.5
                : Math.max(headers[i][1].bbox.x1 + 4, w.bbox.x0 - h * 0.5);
    });
    const leftRules = rules.filter(x => x < headers[0][1].bbox.x0);
    const rightRules = rules.filter(x => x > headers.at(-1)[1].bbox.x1);
    const leftEdge = leftRules.length ? leftRules.at(-1) : 0;
    const rightEdge = rightRules.length ? rightRules[0] : Infinity;
    const column = word => {
        const x = cx(word);
        if (x < leftEdge || x > rightEdge) return null;
        const index = boundaries.findIndex(b => x < b);
        return headers[index === -1 ? headers.length - 1 : index][0];
    };
    const body = words.filter(w => cy(w) > headerBottom + 3 && cy(w) < endY);
    const anchors = body.filter(w => column(w) === 'quantidade' && decimalBR(w.text) !== '').sort((a, b) => cy(a) - cy(b));
    if (!anchors.length) throw new Error('Não encontrei quantidades na tabela. Ajuste o recorte ou preencha os itens manualmente.');
    const warnings = [];
    if (!unit) warnings.push('A tabela não informa unidade. Selecione a unidade de cada item.');
    if (!price) warnings.push('A tabela não informa preço unitário. Preencha os preços.');
    if (!footerLine) warnings.push('Total geral não localizado. Informe o total do documento e confira se todas as linhas foram lidas.');
    if (rules.length < 3) warnings.push('Tabela sem divisórias nítidas. Confira a associação entre descrições e quantidades.');
    const items = anchors.map((anchor, i) => {
        const top = i ? (cy(anchors[i - 1]) + cy(anchor)) / 2 : headerBottom;
        const bottom = i < anchors.length - 1 ? (cy(anchor) + cy(anchors[i + 1])) / 2 : endY;
        const rowWords = body.filter(w => cy(w) >= top && cy(w) < bottom);
        const cells = Object.fromEntries(headers.map(([key]) => [key, join(rowWords.filter(w => column(w) === key))]));
        const suspicious = rowWords.some(w => typeof w.confidence === 'number' && w.confidence < 70);
        return { codigo: cells.codigo || '', produto: cells.produto || '', unidade: cells.unidade || '',
            quantidade: decimalBR(anchor.text), valor_unitario: decimalBR(cells.valor_unitario),
            valor_total: decimalBR(cells.valor_total), revisar: suspicious };
    });
    if (items.some(i => i.revisar)) warnings.push('Existem trechos com leitura duvidosa. Compare as descrições e números com o PDF.');
    const footerMoney = footerLine?.words.map(w => w.text).join(' ').match(/(?:R\$\s*)?\d[\d.]*,\d{2}/g);
    return { items, total: footerMoney ? decimalBR(footerMoney.at(-1)) : '', warnings };
}

export async function extractPdfTables(file, onProgress, crops = {}) {
    if (!window.pdfjsLib || !window.Tesseract) throw new Error('Leitor de PDF não carregou. Atualize a página.');
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/js/vendor/pdfjs/pdf.worker.min.js';
    const pdf = await window.pdfjsLib.getDocument({ data: new Uint8Array(await file.arrayBuffer()), isEvalSupported: false }).promise;
    let worker;
    const items = [], warnings = [], totals = [];
    try {
        if (pdf.numPages > 12) throw new Error('Este PDF tem mais de 12 páginas. Divida o documento antes de importar.');
        worker = await window.Tesseract.createWorker('por', 1, {
            workerPath: 'assets/js/vendor/tesseract/worker.min.js', corePath: 'assets/js/vendor/tesseract-core',
            langPath: 'assets/js/vendor/tessdata', gzip: true,
            logger: event => { if (event.status === 'recognizing text') onProgress(`Reconhecendo tabela: ${Math.round(event.progress * 100)}%`); }
        });
        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
            onProgress(`Lendo página ${pageNumber} de ${pdf.numPages}`);
            const page = await pdf.getPage(pageNumber);
            const initial = page.getViewport({ scale: 1 });
            const view = page.getViewport({ scale: Math.min(2.5, 2600 / Math.max(initial.width, initial.height)) });
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(view.width); canvas.height = Math.ceil(view.height);
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            await page.render({ canvasContext: ctx, viewport: view }).promise;
            let target = canvas;
            const crop = crops[pageNumber];
            if (crop) {
                target = document.createElement('canvas');
                target.width = Math.max(1, Math.round(canvas.width * crop.w)); target.height = Math.max(1, Math.round(canvas.height * crop.h));
                target.getContext('2d').drawImage(canvas, canvas.width * crop.x, canvas.height * crop.y, target.width, target.height, 0, 0, target.width, target.height);
            }
            const recognized = await worker.recognize(target);
            const words = recognized.data.words || [];
            try {
                const parsed = parseTableWords(words, target.getContext('2d').getImageData(0, 0, target.width, target.height));
                items.push(...parsed.items.map(item => ({ ...item, pagina: pageNumber })));
                warnings.push(...parsed.warnings.map(w => `Página ${pageNumber}: ${w}`));
                if (parsed.total) totals.push(parsed.total);
            } catch (error) { warnings.push(`Página ${pageNumber}: ${error.message}`); }
            target.width = 1; target.height = 1; canvas.width = 1; canvas.height = 1; page.cleanup();
        }
        if (items.length > 200) throw new Error('Foram detectados mais de 200 itens. Divida o orçamento.');
        // Não soma totais de páginas: eles podem ser subtotais ou repetição do total geral.
        return { items, total: totals.at(-1) || '', warnings: [...new Set(warnings)], pages: pdf.numPages };
    } finally { if (worker) await worker.terminate(); await pdf.destroy(); }
}

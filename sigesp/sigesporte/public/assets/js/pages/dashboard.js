function parseData(canvas) {
    for (const key of ['chart', 'modalidadesChart']) {
        if (!canvas.dataset[key]) continue;
        try { return JSON.parse(canvas.dataset[key]); } catch { return []; }
    }
    return [];
}

function drawBarChart(canvas, source) {
    const context = canvas.getContext('2d');
    const width = Math.max(canvas.parentElement?.clientWidth || 320, 280);
    const height = 260;
    const scale = window.devicePixelRatio || 1;
    canvas.width = width * scale;
    canvas.height = height * scale;
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    context.scale(scale, scale);
    context.clearRect(0, 0, width, height);
    if (source.length === 0) return;

    const values = source.map((item) => Number(item.total ?? item.value ?? 0));
    const maximum = Math.max(...values, 1);
    const gap = 12;
    const plotHeight = 195;
    const barWidth = Math.max(12, (width - 48 - gap * source.length) / source.length);
    context.font = '12px system-ui, sans-serif';
    context.textAlign = 'center';
    source.forEach((item, index) => {
        const x = 30 + index * (barWidth + gap);
        const barHeight = (values[index] / maximum) * plotHeight;
        context.fillStyle = '#16A37A';
        context.fillRect(x, 215 - barHeight, barWidth, barHeight);
        context.fillStyle = '#344054';
        context.fillText(String(values[index]), x + barWidth / 2, 205 - barHeight);
        const label = String(item.nome ?? item.label ?? '').slice(0, 12);
        context.fillText(label, x + barWidth / 2, 238);
    });
}

document.querySelectorAll('canvas[data-modalidades-chart], canvas[data-chart]').forEach((canvas) => {
    const source = parseData(canvas);
    canvas.setAttribute('role', 'img');
    if (!canvas.getAttribute('aria-label')) canvas.setAttribute('aria-label', 'Gráfico demonstrativo');
    const draw = () => drawBarChart(canvas, source);
    draw();
    if ('ResizeObserver' in window) new ResizeObserver(draw).observe(canvas.parentElement || canvas);
});


document.querySelectorAll('[data-page="relatorios"] [data-report-print]').forEach((button) => {
    button.dataset.demoAction = 'print';
});
document.querySelectorAll('[data-page="relatorios"] [data-report-export]').forEach((button) => {
    button.dataset.demoAction = `export-${button.dataset.reportExport || 'arquivo'}`;
});


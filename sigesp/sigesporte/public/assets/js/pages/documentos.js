document.addEventListener('sigesp:demo-action', (event) => {
    if (!document.body.matches('[data-page="documentos"]')) return;
    if (['approve', 'aprovar', 'reject', 'rejeitar'].includes(event.detail.action)) {
        event.detail.target?.setAttribute('aria-live', 'polite');
    }
});


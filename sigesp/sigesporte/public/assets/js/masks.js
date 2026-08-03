document.querySelectorAll('[data-cpf]').forEach((input) => {
    input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '').slice(0, 11)
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = button.previousElementSibling;
    if (!(input instanceof HTMLInputElement)) return;
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Ocultar' : 'Mostrar';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
    });
});

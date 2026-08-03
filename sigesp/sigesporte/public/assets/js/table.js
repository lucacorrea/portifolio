document.querySelectorAll('[data-select-all]').forEach(input => {
    input.addEventListener('change', () => {
        document.querySelectorAll('[data-row-select]').forEach(item => {
            item.checked = input.checked;
        });
    });
});

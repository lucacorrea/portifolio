const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const overlay = document.getElementById('sidebarOverlay');
const themeToggle = document.getElementById('themeToggle');
const themeLabel = document.getElementById('themeLabel');

function closeSidebar(){
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
}

menuToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('show');
});

overlay?.addEventListener('click', closeSidebar);

function applyTheme(theme){
    document.body.classList.toggle('dark', theme === 'dark');
    if (themeLabel) themeLabel.textContent = theme === 'dark' ? 'Modo escuro' : 'Modo claro';
    localStorage.setItem('bianka.theme', theme);
}

applyTheme(localStorage.getItem('bianka.theme') || 'light');

themeToggle?.addEventListener('click', () => {
    const current = document.body.classList.contains('dark') ? 'dark' : 'light';
    applyTheme(current === 'dark' ? 'light' : 'dark');
});

const stockModal = document.getElementById('stockModal');
const stockPieceId = document.getElementById('stockPieceId');
const stockPieceName = document.getElementById('stockPieceName');
const stockCurrent = document.getElementById('stockCurrent');

document.querySelectorAll('[data-stock-open]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!stockModal) return;
        if (stockPieceId) stockPieceId.value = button.dataset.id || '';
        if (stockPieceName) stockPieceName.textContent = button.dataset.name || 'Peça';
        if (stockCurrent) stockCurrent.textContent = button.dataset.stock || '-';
        stockModal.showModal();
    });
});

document.querySelectorAll('[data-modal-close]').forEach((button) => {
    button.addEventListener('click', () => {
        button.closest('dialog')?.close();
    });
});

document.querySelectorAll('dialog.app-modal').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
        const rect = dialog.getBoundingClientRect();
        const inside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom;
        if (!inside) dialog.close();
    });
});

const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const overlay = document.getElementById('sidebarOverlay');
const themeToggle = document.getElementById('themeToggle');
const themeLabel = document.getElementById('themeLabel');
const themeIcon = document.getElementById('themeIcon');

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
    const dark = theme === 'dark';
    document.body.classList.toggle('dark', dark);
    if (themeLabel) themeLabel.textContent = dark ? 'Modo escuro' : 'Modo claro';
    if (themeIcon) themeIcon.className = dark ? 'icon icon-moon' : 'icon icon-sun';
    localStorage.setItem('bianka-theme', theme);
}
applyTheme(localStorage.getItem('bianka-theme') || 'light');
themeToggle?.addEventListener('click', () => {
    applyTheme(document.body.classList.contains('dark') ? 'light' : 'dark');
});

document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
});

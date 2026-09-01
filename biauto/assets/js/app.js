
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

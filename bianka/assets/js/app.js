const sidebar = document.getElementById('sidebar');
const menuToggle = document.getElementById('menuToggle');
const overlay = document.getElementById('sidebarOverlay');

function closeSidebar(){
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
}
menuToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
    overlay?.classList.toggle('show');
});
overlay?.addEventListener('click', closeSidebar);

document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', (e) => {
        if (!confirm(btn.dataset.confirm)) e.preventDefault();
    });
});

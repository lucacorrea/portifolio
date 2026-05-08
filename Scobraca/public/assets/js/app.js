document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const backdrop = document.querySelector('[data-sidebar-close]');
    const search = document.querySelector('[data-table-search]');

    const closeSidebar = () => {
        if (sidebar) {
            sidebar.classList.remove('mobile-open');
        }

        if (backdrop) {
            backdrop.classList.remove('is-visible');
        }
    };

    const openSidebar = () => {
        if (sidebar) {
            sidebar.classList.add('mobile-open');
        }

        if (backdrop) {
            backdrop.classList.add('is-visible');
        }
    };

    if (toggle) {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();

            if (sidebar && sidebar.classList.contains('mobile-open')) {
                closeSidebar();
                return;
            }

            openSidebar();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    document.querySelectorAll('.sidebar a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });

    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();

            document.querySelectorAll('tbody tr').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });
    }
});

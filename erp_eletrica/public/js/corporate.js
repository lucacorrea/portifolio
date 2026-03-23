document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle for and desktop/mobile persistence
    const toggle = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;
    
    // Load persisted state
    if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth >= 992) {
        body.classList.add('sidebar-collapsed');
    }

    function toggleSidebar() {
        if (window.innerWidth < 992) {
            sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        } else {
            body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
        }
    }

    if (toggle) toggle.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', toggleSidebar);


    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Progress loader logic
    window.showLoader = function() {
        const loader = document.getElementById('globalLoader');
        if (loader) loader.style.display = 'flex';
    };

    window.hideLoader = function() {
        const loader = document.getElementById('globalLoader');
        if (loader) loader.style.display = 'none';
    };

    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Form submission enhancement (Add CSRF and Lock button)
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processando...';
            }
            showLoader();
        });
    });
});

// Global toggle password visibility
window.togglePasswordVisibility = function(button) {
    const inputGroup = button.closest('.input-group');
    if (!inputGroup) return;
    
    const input = inputGroup.querySelector('input');
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
};

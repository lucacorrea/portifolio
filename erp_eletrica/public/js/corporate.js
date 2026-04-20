document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle for desktop/mobile persistence
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const html = document.documentElement;

    function toggleSidebar() {
        if (window.innerWidth < 992) {
            // Mobile: slide in/out
            sidebar.classList.toggle('active');
            if (overlay) {
                overlay.classList.toggle('active');
                if (overlay.classList.contains('active')) {
                    overlay.style.display = 'block';
                    setTimeout(() => overlay.style.opacity = '1', 10);
                } else {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);
                }
            }
        } else {
            // Desktop: collapse/expand
            html.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', html.classList.contains('sidebar-collapsed'));
        }
    }

    if (toggle) toggle.addEventListener('click', toggleSidebar);

    // Auto-close sidebar on mobile when clicking a menu link
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                sidebar.classList.remove('active');
                if (overlay) {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.style.display = 'none', 300);
                    overlay.classList.remove('active');
                }
            }
        });
    });

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.style.opacity = '0';
            setTimeout(() => overlay.style.display = 'none', 300);
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

    // Auto-hide Top Flash Alerts ONLY (to avoid closing alerts inside modals or details)
    setTimeout(() => {
        const alerts = document.querySelectorAll('.content-body > .alert, .top-flash-alert');
        alerts.forEach(alert => {
            if (bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert)) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            }
        });
    }, 8000); // Increased to 8s for better readability

});


// Robust Zoom Modal (Bootstrap-based)
window.openLightbox = function(src) {
    if (!src || src.includes('fa-image') || src.includes('no-image')) return;

    const modalEl = document.getElementById('erp-image-zoom-modal');
    const modalImg = document.getElementById('erp-zoom-image-content');
    
    if (modalEl && modalImg) {
        modalImg.src = src;
        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }
};

// Attach click listener to zoom containers with improved robustness
document.addEventListener('click', (e) => {
    const zoomContainer = e.target.closest('.product-zoom-container');
    if (zoomContainer) {
        const img = zoomContainer.querySelector('img');
        if (img && img.src) {
            if (img.src.includes('fa-image') || img.src.includes('no-image')) return;
            openLightbox(img.src);
        }
    }
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

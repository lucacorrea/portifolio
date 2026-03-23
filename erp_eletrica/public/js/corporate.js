document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle for and desktop/mobile persistence
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const html = document.documentElement;

    function toggleSidebar() {
        if (window.innerWidth < 992) {
            sidebar.classList.toggle('active');
            if (overlay) overlay.classList.toggle('active');
        } else {
            html.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', html.classList.contains('sidebar-collapsed'));
        }
    }

    if (toggle) toggle.addEventListener('click', toggleSidebar);


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

});

// Robust Zoom Modal (Bootstrap-based)
window.openLightbox = function(src) {
    if (!src || src.includes('fa-image') || src.includes('no-image')) return;

    const modalEl = document.getElementById('erp-image-zoom-modal');
    const modalImg = document.getElementById('erp-zoom-image-content');
    
    if (modalEl && modalImg) {
        modalImg.src = src;
        const bsModal = new bootstrap.Modal(modalEl);
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

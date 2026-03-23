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

    // Lightbox Zoom Logic
    window.openLightbox = function(src) {
        if (!src || src.includes('fa-image') || src.includes('no-image')) return;

        let lightbox = document.getElementById('lightboxOverlay');
        if (!lightbox) {
            lightbox = document.createElement('div');
            lightbox.id = 'lightboxOverlay';
            lightbox.className = 'lightbox-overlay';
            lightbox.innerHTML = `
                <span class="lightbox-close">&times;</span>
                <img class="lightbox-content" id="lightboxImage">
            `;
            document.body.appendChild(lightbox);
            
            lightbox.addEventListener('click', (e) => {
                if (e.target.id === 'lightboxOverlay' || e.target.classList.contains('lightbox-close')) {
                    lightbox.classList.remove('active');
                }
            });
        }
        
        const img = document.getElementById('lightboxImage');
        img.src = src;
        
        // Brief delay to ensure display:flex is applied before opacity transition
        lightbox.style.display = 'flex';
        setTimeout(() => lightbox.classList.add('active'), 10);
    };

    // Attach click listener to zoom containers
    document.addEventListener('click', (e) => {
        const zoomContainer = e.target.closest('.product-zoom-container');
        if (zoomContainer) {
            const img = zoomContainer.querySelector('img');
            if (img && img.src) {
                openLightbox(img.src);
            }
        }
    });

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

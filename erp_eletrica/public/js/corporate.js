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

/**
 * ERP Global Notification System (Toasts)
 */
window.erpNotify = function(type, message) {
    const container = document.getElementById('erp-toast-container');
    if (!container) return;

    const id = 'toast-' + Date.now();
    const config = {
        success: { icon: 'fa-check-circle', class: 'bg-success' },
        danger:  { icon: 'fa-exclamation-circle', class: 'bg-danger' },
        warning: { icon: 'fa-exclamation-triangle', class: 'bg-warning text-dark' },
        info:    { icon: 'fa-info-circle', class: 'bg-info text-white' }
    };
    
    const style = config[type] || config.info;
    
    const html = `
        <div id="${id}" class="toast align-items-center text-white ${style.class} border-0 shadow-lg mb-2" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center">
                    <i class="fas ${style.icon} me-2 fs-5"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', html);
    const toastEl = document.getElementById(id);
    const bsToast = new bootstrap.Toast(toastEl, { delay: 6000 });
    bsToast.show();
    
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
};

/**
 * Robust fetch wrapper with automatic error handling
 */
window.erpFetch = async function(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    const mergedOptions = { ...defaultOptions, ...options };
    mergedOptions.headers = { ...defaultOptions.headers, ...options.headers };

    try {
        const response = await fetch(url, mergedOptions);
        const data = await response.json().catch(() => null);

        if (!response.ok) {
            const errorMsg = (data && data.message) ? data.message : `Erro no servidor (${response.status})`;
            erpNotify('danger', errorMsg);
            throw new Error(errorMsg);
        }

        if (data && data.success === false) {
            erpNotify('warning', data.message || 'Operação não concluída.');
        }

        return data;
    } catch (error) {
        if (!navigator.onLine) {
            erpNotify('danger', 'Você está offline. Verifique sua conexão.');
        } else if (error.name === 'AbortError') {
            console.warn('Requisição abortada');
        } else {
            console.error('Fetch error:', error);
            if (!error.message.includes('Erro no servidor')) {
                erpNotify('danger', 'Falha de comunicação com o servidor.');
            }
        }
        throw error;
    }
};

/**
 * ERP Global Currency Mask
 * Auto-formats inputs as currency (R$ xx,xx) right-to-left
 */
document.addEventListener('input', function(e) {
    const target = e.target;
    if (target.tagName !== 'INPUT') return;

    const name = (target.name || '').toLowerCase();
    const id = (target.id || '').toLowerCase();
    
    const isMoneyField = target.classList.contains('money') || 
                         target.dataset.mask === 'currency' ||
                         name.includes('preco') || name.includes('custo') || name === 'valor' ||
                         id.includes('preco') || id.includes('custo') || id === 'valor';

    if (isMoneyField && target.type !== 'hidden' && !target.classList.contains('no-mask')) {
        // Automatically change type to text to allow formatting characters
        if (target.type === 'number') {
            target.type = 'text';
        }

        let value = target.value;
        
        // Remove tudo que não é dígito
        value = value.replace(/\D/g, "");

        if (value === "") {
            target.value = "";
            return;
        }

        // Converte para decimal
        value = (parseInt(value, 10) / 100).toFixed(2);
        
        // Formata para o padrão Brasileiro
        value = value.replace(".", ",");
        value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");

        target.value = value;
    }
});

/**
 * ERP Global Submit Cleaner
 * Automatically removes the currency mask before the form is submitted
 * so the backend receives clean numbers (e.g., 1500.00 instead of 1.500,00).
 */
document.addEventListener('submit', function(e) {
    if (!e.target || e.target.tagName !== 'FORM') return;
    
    const form = e.target;
    // Encontra todos os inputs que possam ter a máscara de dinheiro dentro deste formulário
    const moneyInputs = form.querySelectorAll('input[type="text"]');
    
    moneyInputs.forEach(input => {
        const name = (input.name || '').toLowerCase();
        const id = (input.id || '').toLowerCase();
        
        const isMoneyField = input.classList.contains('money') || 
                             input.dataset.mask === 'currency' ||
                             name.includes('preco') || name.includes('custo') || name === 'valor' ||
                             id.includes('preco') || id.includes('custo') || id === 'valor';
                             
        if (isMoneyField && !input.classList.contains('no-mask') && input.value) {
            let value = input.value;
            // Verifica se o valor parece ter formatação brasileira com vírgula e opcionalmente pontos
            if (value.includes(',')) {
                // Remove R$ e espaços
                value = value.replace(/[R$\s]/g, '');
                // Remove pontos de milhares
                value = value.replace(/\./g, '');
                // Troca vírgula por ponto
                value = value.replace(/,/g, '.');
                // Atualiza o valor do input imediatamente antes do submit
                input.value = value;
            }
        }
    });
});

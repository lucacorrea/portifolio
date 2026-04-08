        </div> <!-- container-xl -->
    </main> <!-- page-body -->

    <footer class="no-print" style="
        padding: 1.5rem 0;
        font-size: 0.8rem;
        color: var(--text-muted);
        border-top: 1px solid var(--border-color);
        background: var(--white);
        margin-top: auto;
    ">
        <div class="container-xl">

            <!-- FLEX CONTAINER -->
            <div class="footer-content">
                
                <!-- ESQUERDA -->
                <div class="footer-left">
                    SGAO &copy; <?php echo date('Y'); ?> - Sistema de Gestão de Ofícios e Aquisições        </div>

                <!-- DIREITA -->
                <div class="footer-right">
                    Desenvolvido por <strong>Junior Praia, Lucas Correa e Luiz Frota.</strong>
                </div>

            </div>

        </div>
    </footer>

</div> <!-- page-wrapper -->

<style>
.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

/* TEXTO DIREITA */
.footer-right {
    text-align: right;
    font-size: 0.75rem;
    color: #999;
}

/* RESPONSIVO */
@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
    }

    .footer-right {
        text-align: center;
    }
}
</style>
<script>
document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-dropdown-toggle]');
    const isDropdownItem = event.target.closest('.dropdown-item');
    const activeDropdowns = document.querySelectorAll('.dropdown-menu.show');
    
    function closeDropdown(d) {
        d.classList.remove('show');
        d.classList.remove('dropup');
        d.style.position = '';
        d.style.top = '';
        d.style.left = '';
        d.style.right = '';
        d.style.bottom = '';
    }

    if (toggle) {
        event.preventDefault();
        const dropdown = toggle.parentElement.querySelector('.dropdown-menu');
        
        if (dropdown.classList.contains('show')) {
            closeDropdown(dropdown);
        } else {
            // Fecha outros
            activeDropdowns.forEach(closeDropdown);
            
            // Exibe invisivelmente para obter dimensões
            dropdown.style.display = 'block';
            dropdown.style.visibility = 'hidden';
            const dropRect = dropdown.getBoundingClientRect();
            dropdown.style.display = '';
            dropdown.style.visibility = '';

            // Posicionamento Inteligente fixo
            const rect = toggle.getBoundingClientRect();
            const winHeight = window.innerHeight;
            const spaceBelow = winHeight - rect.bottom;
            
            dropdown.style.position = 'fixed';
            dropdown.style.right = 'auto';
            dropdown.style.bottom = 'auto';
            
            // Se houver menos espaco que a altura do dropdown + margem, abre pra cima
            if (spaceBelow < dropRect.height + 20) {
                dropdown.classList.add('dropup');
                dropdown.style.top = (rect.top - dropRect.height - 5) + 'px';
            } else {
                dropdown.classList.remove('dropup');
                dropdown.style.top = (rect.bottom + 5) + 'px';
            }
            
            // Alinha pela direita do botao
            dropdown.style.left = (rect.right - dropRect.width) + 'px';
            
            dropdown.classList.add('show');
        }
    } else if (!isDropdownItem) {
        activeDropdowns.forEach(closeDropdown);
    }
});

// Fecha ao rolar a página ou wrap da tabela
window.addEventListener('scroll', function() {
    const openMenu = document.querySelector('.dropdown-menu.show');
    if (openMenu) {
        openMenu.classList.remove('show');
        openMenu.classList.remove('dropup');
        openMenu.style.position = '';
        openMenu.style.top = '';
        openMenu.style.left = '';
        openMenu.style.right = '';
        openMenu.style.bottom = '';
    }
}, true);
// Filtro Inteligente (Auto-submit sem recarregar a tela desnecessariamente perdendo foco)
document.addEventListener('DOMContentLoaded', function() {
    const filterForms = document.querySelectorAll('form.filtros-grid');
    filterForms.forEach(form => {
        // Esconder o botão "Filtrar" para um visual mais limpo, usando apenas a lógica automática
        const btnSubmit = form.querySelector('button[type="submit"]');
        if (btnSubmit) {
            btnSubmit.style.display = 'none';
        }

        let typingTimer;
        const doneTypingInterval = 800; // Tempo após parar de digitar (ms)

        form.querySelectorAll('input, select').forEach(element => {
            if (element.tagName === 'SELECT') {
                element.addEventListener('change', () => {
                    sessionStorage.setItem('lastFocusedFilter', element.name);
                    form.submit();
                });
            } else if (element.tagName === 'INPUT' && (element.type === 'text' || element.type === 'search')) {
                element.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => {
                        sessionStorage.setItem('lastFocusedFilter', element.name);
                        form.submit();
                    }, doneTypingInterval);
                });
                
                element.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(typingTimer);
                        sessionStorage.setItem('lastFocusedFilter', element.name);
                        // form will naturally submit on Enter
                    }
                });
            }
        });
        
        // Restaurar estado e foco do cursor após o reload da página
        const lastFocused = sessionStorage.getItem('lastFocusedFilter');
        if (lastFocused) {
            const elementToFocus = form.querySelector(`[name="${lastFocused}"]`);
            if (elementToFocus) {
                elementToFocus.focus();
                // Se for campo de terxo, colocar cursor no final do valor inserido
                if (elementToFocus.tagName === 'INPUT') {
                    const val = elementToFocus.value;
                    elementToFocus.value = '';
                    elementToFocus.value = val;
                }
            }
            sessionStorage.removeItem('lastFocusedFilter');
        }
    });
});
</script>

</body>
</html>
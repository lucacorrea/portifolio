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

<style>
/* =========================================================
   FILTROS DE BUSCA V2 - PADRÃO GLOBAL DO SO
   Carregado no footer para prevalecer sobre CSS local.
========================================================= */
.card.no-print:has(form.filtros-grid) {
    margin-bottom: 1.4rem !important;
    overflow: visible !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    background: #ffffff !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, .035) !important;
}

.card.no-print:has(form.filtros-grid) .card-body {
    padding: 16px 18px 18px !important;
}

.card.no-print:has(form.filtros-grid) .card-title {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    margin: 0 0 14px !important;
    color: #172033 !important;
    font-size: .94rem !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
}

.card.no-print:has(form.filtros-grid) .card-title i.fa-filter {
    width: 28px !important;
    height: 28px !important;
    margin: 0 !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    border-radius: 7px !important;
    background: #edf5ff !important;
    color: var(--primary) !important;
    font-size: .78rem !important;
}

form.filtros-grid {
    display: grid !important;
    grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
    gap: 11px 12px !important;
    align-items: end !important;
    margin: 0 !important;
}

form.filtros-grid > .form-group {
    min-width: 0 !important;
    margin: 0 !important;
}

/* 1ª linha: pesquisa e filtros principais */
form.filtros-grid .filtro-busca,
form.filtros-grid > .form-group:has([name="busca"]) {
    order: 10 !important;
    grid-column: span 4 !important;
}

form.filtros-grid .filtro-status,
form.filtros-grid > .form-group:has(select[name="status"]) {
    order: 20 !important;
    grid-column: span 2 !important;
}

form.filtros-grid .filtro-secretaria,
form.filtros-grid > .form-group:has([name="secretaria_id"]),
form.filtros-grid > .form-group:has([name="secretaria"]) {
    order: 30 !important;
    grid-column: span 3 !important;
}

form.filtros-grid .filtro-fornecedor,
form.filtros-grid > .form-group:has([name="fornecedor_id"]) {
    order: 40 !important;
    grid-column: span 3 !important;
}

/* 2ª linha: período, paginação, relatório e ações */
form.filtros-grid > .form-group:has([name="data_inicio"]) {
    order: 50 !important;
    grid-column: span 2 !important;
}

form.filtros-grid > .form-group:has([name="data_fim"]) {
    order: 60 !important;
    grid-column: span 2 !important;
}

form.filtros-grid .filtro-limite,
form.filtros-grid > .form-group:has([name="por_pagina"]) {
    order: 70 !important;
    grid-column: span 2 !important;
}

form.filtros-grid .filtro-tipo,
form.filtros-grid > .form-group:has([name="tipo_relatorio"]) {
    order: 80 !important;
    grid-column: span 2 !important;
}

form.filtros-grid .filtros-acoes {
    order: 90 !important;
    grid-column: span 6 !important;
    min-width: 0 !important;
    min-height: 40px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: 7px !important;
    flex-wrap: nowrap !important;
    margin: 0 !important;
}

form.filtros-grid:has([name="tipo_relatorio"]) .filtros-acoes {
    grid-column: span 4 !important;
}

/* SEFAZ / formulários mais simples */
form.filtros-grid:not(:has([name="fornecedor_id"])) > .form-group:has([name="busca"]) {
    grid-column: span 6 !important;
}

form.filtros-grid:not(:has([name="fornecedor_id"])) > .form-group:has([name="secretaria"]) {
    grid-column: span 6 !important;
}

form.filtros-grid:not(:has([name="fornecedor_id"])) > .form-group:has([name="data_inicio"]),
form.filtros-grid:not(:has([name="fornecedor_id"])) > .form-group:has([name="data_fim"]) {
    grid-column: span 3 !important;
}

form.filtros-grid:not(:has([name="fornecedor_id"])) > .form-group:has([name="por_pagina"]) {
    grid-column: span 2 !important;
}

form.filtros-grid:not(:has([name="fornecedor_id"])) .filtros-acoes {
    grid-column: span 4 !important;
}

form.filtros-grid .form-label,
form.filtros-grid label {
    display: block !important;
    margin: 0 0 5px !important;
    color: #334155 !important;
    font-size: .76rem !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
}

form.filtros-grid .form-control,
form.filtros-grid input:not([type="hidden"]),
form.filtros-grid select {
    width: 100% !important;
    height: 40px !important;
    min-height: 40px !important;
    padding: 0 11px !important;
    border: 1px solid #d7dee8 !important;
    border-radius: 8px !important;
    background-color: #fff !important;
    color: #172033 !important;
    font-size: .84rem !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
    transition: border-color .15s ease, box-shadow .15s ease !important;
}

form.filtros-grid input[name="busca"] {
    padding-left: 36px !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: 12px center !important;
    background-size: 15px 15px !important;
}

form.filtros-grid input::placeholder {
    color: #94a3b8 !important;
    opacity: 1 !important;
}

form.filtros-grid .form-control:focus,
form.filtros-grid input:not([type="hidden"]):focus,
form.filtros-grid select:focus {
    outline: none !important;
    border-color: #7aa7d8 !important;
    box-shadow: 0 0 0 3px rgba(0, 84, 166, .08) !important;
}

form.filtros-grid .filtros-acoes .btn,
form.filtros-grid .filtros-acoes .btn-sm,
form.filtros-grid .filtros-acoes .btn-outline,
form.filtros-grid .filtros-acoes .btn-limpar-filtros {
    width: auto !important;
    min-width: 40px !important;
    height: 40px !important;
    min-height: 40px !important;
    padding: 0 12px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    border-radius: 8px !important;
    font-size: .8rem !important;
    font-weight: 600 !important;
    line-height: 1 !important;
    white-space: nowrap !important;
}

form.filtros-grid .filtros-acoes > button[type="submit"]:first-of-type {
    border-color: var(--primary) !important;
    background: var(--primary) !important;
    color: #fff !important;
}

form.filtros-grid .filtros-acoes .btn-limpar-filtros {
    padding: 0 !important;
}

@media (max-width: 1100px) {
    form.filtros-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr)) !important;
    }

    form.filtros-grid .filtro-busca,
    form.filtros-grid > .form-group:has([name="busca"]) {
        grid-column: span 3 !important;
    }

    form.filtros-grid .filtro-status,
    form.filtros-grid > .form-group:has(select[name="status"]) {
        grid-column: span 3 !important;
    }

    form.filtros-grid .filtro-secretaria,
    form.filtros-grid > .form-group:has([name="secretaria_id"]),
    form.filtros-grid > .form-group:has([name="secretaria"]),
    form.filtros-grid .filtro-fornecedor,
    form.filtros-grid > .form-group:has([name="fornecedor_id"]) {
        grid-column: span 3 !important;
    }

    form.filtros-grid > .form-group:has([name="data_inicio"]),
    form.filtros-grid > .form-group:has([name="data_fim"]),
    form.filtros-grid .filtro-limite,
    form.filtros-grid > .form-group:has([name="por_pagina"]) {
        grid-column: span 2 !important;
    }

    form.filtros-grid .filtro-tipo,
    form.filtros-grid > .form-group:has([name="tipo_relatorio"]) {
        grid-column: span 2 !important;
    }

    form.filtros-grid .filtros-acoes,
    form.filtros-grid:has([name="tipo_relatorio"]) .filtros-acoes,
    form.filtros-grid:not(:has([name="fornecedor_id"])) .filtros-acoes {
        grid-column: span 6 !important;
        justify-content: flex-start !important;
    }
}

@media (max-width: 700px) {
    .card.no-print:has(form.filtros-grid) .card-body {
        padding: 14px !important;
    }

    form.filtros-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
        gap: 10px !important;
    }

    form.filtros-grid .filtro-busca,
    form.filtros-grid > .form-group:has([name="busca"]),
    form.filtros-grid .filtro-fornecedor,
    form.filtros-grid > .form-group:has([name="fornecedor_id"]),
    form.filtros-grid .filtros-acoes,
    form.filtros-grid:has([name="tipo_relatorio"]) .filtros-acoes,
    form.filtros-grid:not(:has([name="fornecedor_id"])) .filtros-acoes {
        grid-column: 1 / -1 !important;
    }

    form.filtros-grid .filtro-status,
    form.filtros-grid > .form-group:has(select[name="status"]),
    form.filtros-grid .filtro-secretaria,
    form.filtros-grid > .form-group:has([name="secretaria_id"]),
    form.filtros-grid > .form-group:has([name="secretaria"]),
    form.filtros-grid > .form-group:has([name="data_inicio"]),
    form.filtros-grid > .form-group:has([name="data_fim"]),
    form.filtros-grid .filtro-limite,
    form.filtros-grid > .form-group:has([name="por_pagina"]),
    form.filtros-grid .filtro-tipo,
    form.filtros-grid > .form-group:has([name="tipo_relatorio"]) {
        grid-column: span 1 !important;
    }

    form.filtros-grid .filtros-acoes {
        flex-wrap: wrap !important;
        justify-content: flex-start !important;
    }
}

@media (max-width: 480px) {
    form.filtros-grid {
        grid-template-columns: 1fr !important;
    }

    form.filtros-grid > .form-group,
    form.filtros-grid .filtro-busca,
    form.filtros-grid .filtro-status,
    form.filtros-grid .filtro-secretaria,
    form.filtros-grid .filtro-fornecedor,
    form.filtros-grid .filtro-data,
    form.filtros-grid .filtro-limite,
    form.filtros-grid .filtro-tipo,
    form.filtros-grid .filtros-acoes {
        grid-column: 1 / -1 !important;
    }

    form.filtros-grid .filtros-acoes {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
        width: 100% !important;
    }

    form.filtros-grid .filtros-acoes .btn,
    form.filtros-grid .filtros-acoes .btn-sm,
    form.filtros-grid .filtros-acoes .btn-outline {
        width: 100% !important;
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
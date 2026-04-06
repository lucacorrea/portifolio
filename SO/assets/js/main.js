// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // Adicionar novo item no formulário de ofício
    const addProductBtn = document.getElementById('add-product');
    const productsContainer = document.getElementById('products-container');

    if (addProductBtn && productsContainer) {
        addProductBtn.addEventListener('click', function() {
            const index = productsContainer.children.length;
            const itemDiv = document.createElement('div');
            itemDiv.className = 'form-grid product-item';
            itemDiv.style.display = 'grid';
            itemDiv.style.gridTemplateColumns = '2fr 1fr 1fr auto';
            itemDiv.style.gap = '15px';
            itemDiv.style.marginBottom = '10px';
            itemDiv.style.alignItems = 'end';

            itemDiv.innerHTML = `
                <div class="form-group">
                    <label class="form-label">Produto/Serviço</label>
                    <input type="text" name="produtos[${index}][nome]" class="form-control" required placeholder="Ex: Resma de Papel A4">
                </div>
                <div class="form-group">
                    <label class="form-label">Qtd</label>
                    <input type="number" step="0.01" name="produtos[${index}][qtd]" class="form-control" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Unidade</label>
                    <input type="text" name="produtos[${index}][unidade]" class="form-control" placeholder="UN" value="UN">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-danger remove-product"><i class="fas fa-trash"></i></button>
                </div>
            `;
            productsContainer.appendChild(itemDiv);

            // Adicionar evento para remover
            itemDiv.querySelector('.remove-product').addEventListener('click', function() {
                itemDiv.remove();
            });
        });
    }

    // Inicializar remoção para itens existentes (se houver)
    const removeBtns = document.querySelectorAll('.remove-product');
    removeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            btn.closest('.product-item').remove();
        });
    });

    // Filtros de Relatório Dinâmicos (opcional)
    const reportFilterForm = document.getElementById('report-filter-form');
    if (reportFilterForm) {
        // Implementar filtros se necessário
    }
});

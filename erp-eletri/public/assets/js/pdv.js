document.addEventListener('DOMContentLoaded', function () {
    let cart = [];
    const searchInput = document.getElementById('product-search');
    const searchResults = document.getElementById('search-results');
    const cartItemsContainer = document.getElementById('cart-items');
    const totalDisplay = document.getElementById('total-display');
    const btnFinalize = document.getElementById('btn-finalize');

    // Auto focus search
    searchInput.focus();

    // Event Listeners
    searchInput.addEventListener('input', debounce(handleSearch, 300));

    // Add Item to Cart
    window.addToCart = function (product) {
        const existingItem = cart.find(item => item.id === product.id);

        if (existingItem) {
            existingItem.quantity += 1;
            existingItem.subtotal = existingItem.quantity * existingItem.price;
        } else {
            cart.push({
                id: product.id,
                name: product.nome,
                code: product.codigo_interno,
                price: parseFloat(product.preco_venda),
                quantity: 1,
                subtotal: parseFloat(product.preco_venda)
            });
        }

        renderCart();
        searchInput.value = '';
        searchResults.style.display = 'none';
        searchInput.focus();
    };

    // Remove Item
    window.removeFromCart = function (index) {
        cart.splice(index, 1);
        renderCart();
    };

    // Update Quantity
    window.updateQuantity = function (index, newQty) {
        if (newQty <= 0) {
            removeFromCart(index);
            return;
        }
        cart[index].quantity = parseInt(newQty);
        cart[index].subtotal = cart[index].quantity * cart[index].price;
        renderCart();
    }

    // Finalize Sale
    btnFinalize.addEventListener('click', finalizeSale);

    // Keybindings
    document.addEventListener('keydown', function (e) {
        if (e.key === 'F2') {
            e.preventDefault();
            searchInput.focus();
        }
        if (e.key === 'F9') {
            e.preventDefault();
            finalizeSale();
        }
    });

    function handleSearch() {
        const term = searchInput.value;
        if (term.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        fetch(`${API_URL_SEARCH}&term=${encodeURIComponent(term)}`)
            .then(response => response.json())
            .then(data => {
                searchResults.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(product => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
                        item.innerHTML = `
                            <div>
                                <strong>${product.nome}</strong><br>
                                <small>${product.codigo_interno} - R$ ${parseFloat(product.preco_venda).toFixed(2)}</small>
                            </div>
                            <span class="badge bg-primary rounded-pill">+</span>
                        `;
                        item.onclick = (e) => {
                            e.preventDefault();
                            addToCart(product);
                        };
                        searchResults.appendChild(item);
                    });
                    searchResults.style.display = 'block';
                } else {
                    searchResults.style.display = 'none';
                }
            });
    }

    function renderCart() {
        cartItemsContainer.innerHTML = '';
        let total = 0;

        cart.forEach((item, index) => {
            total += item.subtotal;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <small class="d-block text-muted">${item.code}</small>
                    ${item.name}
                </td>
                <td class="text-center" style="width: 80px;">
                    <input type="number" class="form-control form-control-sm p-1 text-center" 
                           value="${item.quantity}" min="1" 
                           onchange="updateQuantity(${index}, this.value)">
                </td>
                <td class="text-end">R$ ${item.subtotal.toFixed(2)}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromCart(${index})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            cartItemsContainer.appendChild(tr);
        });

        // Calculate with discount
        const discount = parseFloat(document.getElementById('discount-input').value) || 0;
        const finalTotal = Math.max(0, total - discount);

        totalDisplay.innerText = finalTotal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // Update item count badge
        document.getElementById('cart-count').innerText = `${cart.length} itens`;
    }

    document.getElementById('discount-input').addEventListener('input', renderCart);

    function finalizeSale() {
        if (cart.length === 0) {
            alert('O carrinho está vazio!');
            return;
        }

        if (!confirm('Confirmar finalização da venda?')) return;

        const saleData = {
            cliente_id: document.getElementById('cliente-select').value,
            forma_pagamento: document.getElementById('payment-method').value,
            desconto: parseFloat(document.getElementById('discount-input').value) || 0,
            total: parseFloat(totalDisplay.innerText.replace('R$', '').replace('.', '').replace(',', '.').trim()), // Parser clean up
            items: cart.map(item => ({
                produto_id: item.id,
                quantidade: item.quantity,
                preco_unitario: item.price,
                subtotal: item.subtotal
            }))
        };

        // Re-calculate total strictly
        const subtotal = cart.reduce((acc, item) => acc + item.subtotal, 0);
        saleData.total = subtotal - saleData.desconto;

        fetch(API_URL_STORE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(saleData)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Venda realizada com sucesso! ID: ' + data.sale_id);
                    cart = [];
                    renderCart();
                    document.getElementById('discount-input').value = '0.00';
                } else {
                    alert('Erro ao finalizar venda: ' + (data.message || 'Desconhecido'));
                }
            })
            .catch(err => {
                alert('Erro de comunicação com o servidor.');
                console.error(err);
            });
    }

    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
});

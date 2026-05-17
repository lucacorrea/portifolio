const buttons = document.querySelectorAll('[data-filter]');
const search = document.querySelector('[data-search]');
const items = document.querySelectorAll('[data-product-item]');
let currentFilter = 'todos';

function applyFilters() {
  const term = (search?.value || '').toLowerCase().trim();
  items.forEach((item) => {
    const categoryMatch = currentFilter === 'todos' || item.dataset.category === currentFilter;
    const searchMatch = !term || item.dataset.name.includes(term);
    item.style.display = categoryMatch && searchMatch ? '' : 'none';
  });
}

buttons.forEach((button) => {
  button.addEventListener('click', () => {
    currentFilter = button.dataset.filter;
    buttons.forEach((btn) => btn.classList.remove('active'));
    button.classList.add('active');
    applyFilters();
  });
});

search?.addEventListener('input', applyFilters);

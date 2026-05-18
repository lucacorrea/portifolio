(() => {
  const buttons = document.querySelectorAll('[data-filter]');
  const search = document.querySelector('[data-search]');
  const items = document.querySelectorAll('[data-product-item]');
  const empty = document.querySelector('[data-empty-products]');
  let currentFilter = 'todos';

  const applyFilters = () => {
    const term = (search?.value || '').toLocaleLowerCase('pt-BR').trim();
    let visible = 0;

    items.forEach((item) => {
      const categoryMatch = currentFilter === 'todos' || item.dataset.category === currentFilter;
      const searchMatch = !term || (item.dataset.name || '').toLocaleLowerCase('pt-BR').includes(term);
      const shouldShow = categoryMatch && searchMatch;
      item.hidden = !shouldShow;
      if (shouldShow) visible += 1;
    });

    if (empty) empty.hidden = visible > 0;
  };

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      currentFilter = button.dataset.filter || 'todos';
      buttons.forEach((btn) => btn.classList.remove('active'));
      button.classList.add('active');
      applyFilters();
    });
  });

  search?.addEventListener('input', applyFilters);
  applyFilters();
})();

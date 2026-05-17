(function () {
  const normalize = (value) => String(value || '')
    .toLocaleLowerCase('pt-BR')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

  document.addEventListener('DOMContentLoaded', () => {
    const items = Array.from(document.querySelectorAll('[data-product-item]'));
    const search = document.querySelector('[data-search]');
    const count = document.querySelector('[data-result-count]');
    const empty = document.querySelector('[data-empty-products]');
    const filters = Array.from(document.querySelectorAll('[data-filter-group]'));

    if (!items.length) {
      return;
    }

    const state = {
      category: 'todos',
      availability: 'todos'
    };

    const applyFilters = () => {
      const term = normalize(search?.value || '');
      let visible = 0;

      items.forEach((item) => {
        const categoryMatch = state.category === 'todos' || item.dataset.category === state.category;
        const availabilityMatch = state.availability === 'todos' || item.dataset.availability === state.availability;
        const searchMatch = !term || normalize(item.dataset.name).includes(term);
        const shouldShow = categoryMatch && availabilityMatch && searchMatch;

        item.hidden = !shouldShow;
        if (shouldShow) {
          visible += 1;
        }
      });

      if (count) {
        count.textContent = `${visible} ${visible === 1 ? 'produto encontrado' : 'produtos encontrados'}`;
      }
      if (empty) {
        empty.hidden = visible > 0;
      }
    };

    filters.forEach((button) => {
      button.addEventListener('click', () => {
        const group = button.dataset.filterGroup;
        state[group] = button.dataset.filterValue;

        filters
          .filter((item) => item.dataset.filterGroup === group)
          .forEach((item) => item.classList.remove('active'));

        button.classList.add('active');
        applyFilters();
      });
    });

    search?.addEventListener('input', applyFilters);
    applyFilters();
  });
})();

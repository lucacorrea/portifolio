'use strict';

document.documentElement.classList.add('js-enabled');

const body = document.body;
const railToggle = document.querySelector('[data-rail-toggle]');
const searchOpenButtons = document.querySelectorAll('[data-search-open]');
const searchOverlay = document.querySelector('[data-search-overlay]');
const searchClose = document.querySelector('[data-search-close]');
const searchInput = document.querySelector('[data-search-input]');

const refreshIcons = () => {
    if (window.lucide) {
        window.lucide.createIcons();
    }
};

const openSearch = () => {
    if (!searchOverlay) return;
    searchOverlay.hidden = false;
    window.setTimeout(() => searchInput?.focus(), 20);
};

const closeSearch = () => {
    if (!searchOverlay) return;
    searchOverlay.hidden = true;
};

railToggle?.addEventListener('click', () => {
    body.classList.toggle('rail-open');
});

searchOpenButtons.forEach((button) => {
    button.addEventListener('click', openSearch);
});

searchClose?.addEventListener('click', closeSearch);

document.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        openSearch();
    }

    if (event.key === 'Escape') {
        closeSearch();
        body.classList.remove('rail-open');
    }
});

document.addEventListener('click', (event) => {
    if (!body.classList.contains('rail-open')) return;
    const rail = document.querySelector('[data-utility-rail]');
    if (rail?.contains(event.target) || railToggle?.contains(event.target)) return;
    body.classList.remove('rail-open');
});

refreshIcons();

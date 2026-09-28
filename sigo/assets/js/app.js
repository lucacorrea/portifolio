'use strict';

document.documentElement.classList.add('js-enabled');

const body = document.body;
const openButton = document.querySelector('[data-sidebar-open]');
const closeButtons = document.querySelectorAll('[data-sidebar-close]');

if (openButton) {
    openButton.addEventListener('click', () => {
        body.classList.add('sidebar-open');
    });
}

closeButtons.forEach((button) => {
    button.addEventListener('click', () => {
        body.classList.remove('sidebar-open');
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        body.classList.remove('sidebar-open');
    }

    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        document.querySelector('.quick-search input')?.focus();
    }
});

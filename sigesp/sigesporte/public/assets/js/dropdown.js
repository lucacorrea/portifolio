const dropdowns = [...document.querySelectorAll('[data-dropdown]')];

function parts(dropdown) {
    return {
        toggle: dropdown.querySelector('[data-dropdown-toggle]'),
        menu: dropdown.querySelector('[data-dropdown-menu]'),
    };
}

function menuItems(menu) {
    return [...menu.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')];
}

function closeDropdown(dropdown, restoreFocus = false) {
    const { toggle, menu } = parts(dropdown);
    if (!toggle || !menu || menu.hidden) return;
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (restoreFocus) toggle.focus();
}

function closeOthers(current) {
    dropdowns.forEach((dropdown) => {
        if (dropdown !== current) closeDropdown(dropdown);
    });
}

function openDropdown(dropdown, focusFirst = false) {
    const { toggle, menu } = parts(dropdown);
    if (!toggle || !menu) return;
    closeOthers(dropdown);
    menu.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    if (focusFirst) menuItems(menu)[0]?.focus();
}

dropdowns.forEach((dropdown, index) => {
    const { toggle, menu } = parts(dropdown);
    if (!toggle || !menu) return;
    if (!menu.id) menu.id = `dropdown-menu-${index + 1}`;
    toggle.setAttribute('aria-controls', menu.id);
    toggle.setAttribute('aria-haspopup', 'menu');
    menu.setAttribute('role', 'menu');
    menuItems(menu).forEach((item) => item.setAttribute('role', 'menuitem'));

    toggle.addEventListener('click', (event) => {
        event.stopPropagation();
        if (menu.hidden) openDropdown(dropdown);
        else closeDropdown(dropdown);
    });

    toggle.addEventListener('keydown', (event) => {
        if (!['ArrowDown', 'Enter', ' '].includes(event.key)) return;
        event.preventDefault();
        openDropdown(dropdown, true);
    });

    menu.addEventListener('keydown', (event) => {
        const items = menuItems(menu);
        const currentIndex = items.indexOf(document.activeElement);
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDropdown(dropdown, true);
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            items[(currentIndex + direction + items.length) % items.length]?.focus();
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            items[event.key === 'Home' ? 0 : items.length - 1]?.focus();
        }
    });
});

document.addEventListener('click', (event) => {
    dropdowns.forEach((dropdown) => {
        if (!dropdown.contains(event.target)) closeDropdown(dropdown);
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    dropdowns.forEach((dropdown) => closeDropdown(dropdown, dropdown.contains(document.activeElement)));
});

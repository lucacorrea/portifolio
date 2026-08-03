function setupTabs(tablist) {
    const root = tablist.closest('[data-tabs-root]') || tablist.parentElement;
    const tabs = [...tablist.querySelectorAll('[role="tab"][data-tab-target]')];
    if (!root || tabs.length === 0) return;

    const panelFor = (tab) => root.querySelector(`#${CSS.escape(tab.dataset.tabTarget)}`);
    const activate = (tab, moveFocus = true) => {
        tabs.forEach((item) => {
            const selected = item === tab;
            item.setAttribute('aria-selected', String(selected));
            item.tabIndex = selected ? 0 : -1;
            const panel = panelFor(item);
            if (panel) panel.hidden = !selected;
        });
        if (moveFocus) tab.focus();
    };

    tabs.forEach((tab) => {
        const panel = panelFor(tab);
        if (panel) {
            panel.setAttribute('role', 'tabpanel');
            panel.setAttribute('aria-labelledby', tab.id);
            panel.tabIndex = 0;
        }
        tab.addEventListener('click', () => activate(tab, false));
        tab.addEventListener('keydown', (event) => {
            const current = tabs.indexOf(tab);
            let next = null;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') next = (current + 1) % tabs.length;
            if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') next = (current - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next === null) return;
            event.preventDefault();
            activate(tabs[next]);
        });
    });

    activate(tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0], false);
}

document.querySelectorAll('[role="tablist"]').forEach(setupTabs);

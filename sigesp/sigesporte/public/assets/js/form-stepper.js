document.querySelectorAll('[data-stepper]').forEach((stepper, stepperIndex) => {
    const form = stepper.closest('form') || stepper.parentElement;
    const buttons = [...stepper.querySelectorAll('[data-step]')];
    const panels = [...form.querySelectorAll('[data-step-panel]')];

    const activate = (index, focus = false) => {
        buttons.forEach((button, buttonIndex) => {
            const active = buttonIndex === index;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-current', active ? 'step' : 'false');
            button.setAttribute('aria-expanded', String(active));
            if (focus && active) button.focus();
        });
        panels.forEach((panel, panelIndex) => { panel.hidden = panelIndex !== index; });
    };

    buttons.forEach((button, index) => {
        const panel = panels[index];
        if (panel) {
            if (!panel.id) panel.id = `step-${stepperIndex + 1}-panel-${index + 1}`;
            if (!button.id) button.id = `step-${stepperIndex + 1}-button-${index + 1}`;
            button.setAttribute('aria-controls', panel.id);
            panel.setAttribute('aria-labelledby', button.id);
        }
        button.addEventListener('click', () => activate(index));
        button.addEventListener('keydown', (event) => {
            if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1
                : (index + (event.key === 'ArrowRight' ? 1 : -1) + buttons.length) % buttons.length;
            activate(next, true);
        });
    });
    activate(0);
});

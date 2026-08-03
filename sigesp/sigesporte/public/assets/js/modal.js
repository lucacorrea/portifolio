const focusableSelector = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled])',
    'select:not([disabled])', 'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const modalState = new WeakMap();

function resolveModal(target) {
    if (target instanceof HTMLElement) return target;
    if (typeof target !== 'string' || target === '') return null;
    try {
        return document.querySelector(target.startsWith('#') ? target : `#${CSS.escape(target)}`);
    } catch {
        return null;
    }
}

function backgroundBranches(modal) {
    const branches = [];
    let branch = modal;
    while (branch?.parentElement && branch !== document.body) {
        [...branch.parentElement.children].forEach((sibling) => {
            if (sibling !== branch && !sibling.hasAttribute('data-toast-region')) branches.push(sibling);
        });
        branch = branch.parentElement;
    }
    return [...new Set(branches)];
}

function makeBackgroundInert(modal) {
    return backgroundBranches(modal).map((element) => {
        const previous = { element, inert: element.inert, ariaHidden: element.getAttribute('aria-hidden') };
        element.inert = true;
        element.setAttribute('aria-hidden', 'true');
        return previous;
    });
}

function restoreBackground(entries = []) {
    entries.forEach(({ element, inert, ariaHidden }) => {
        element.inert = inert;
        if (ariaHidden === null) element.removeAttribute('aria-hidden');
        else element.setAttribute('aria-hidden', ariaHidden);
    });
}

function focusableElements(modal) {
    return [...modal.querySelectorAll(focusableSelector)].filter((element) => !element.hidden && element.offsetParent !== null);
}

export function openModal(target, opener = document.activeElement) {
    const modal = resolveModal(target);
    if (!modal || !modal.hidden) return modal;
    modal.style.removeProperty('display');
    modal.hidden = false;
    document.body.classList.add('modal-open');
    modalState.set(modal, { opener: opener instanceof HTMLElement ? opener : null, background: makeBackgroundInert(modal) });
    (focusableElements(modal)[0] || modal.querySelector('[data-modal-panel]') || modal).focus();
    modal.dispatchEvent(new CustomEvent('sigesp:modal-open'));
    return modal;
}

export function closeModal(target, restoreFocus = true) {
    const modal = resolveModal(target);
    if (!modal || modal.hidden) return;
    const state = modalState.get(modal);
    modal.hidden = true;
    modal.style.display = 'none';
    restoreBackground(state?.background);
    modalState.delete(modal);
    if (!document.querySelector('.modal:not([hidden])')) document.body.classList.remove('modal-open');
    if (restoreFocus && state?.opener?.isConnected) state.opener.focus();
    modal.dispatchEvent(new CustomEvent('sigesp:modal-close'));
}

document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-modal-open]');
    if (opener) {
        event.preventDefault();
        openModal(opener.dataset.modalOpen, opener);
        return;
    }
    const closer = event.target.closest('[data-modal-close]');
    if (closer) closeModal(closer.closest('.modal'));
});

document.querySelectorAll('.modal[hidden]').forEach((modal) => { modal.style.display = 'none'; });

document.addEventListener('keydown', (event) => {
    const openModals = [...document.querySelectorAll('.modal:not([hidden])')];
    const modal = openModals.at(-1);
    if (!modal) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        closeModal(modal);
        return;
    }
    if (event.key !== 'Tab') return;
    const focusable = focusableElements(modal);
    if (focusable.length === 0) {
        event.preventDefault();
        modal.querySelector('[data-modal-panel]')?.focus();
        return;
    }
    const first = focusable[0];
    const last = focusable.at(-1);
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

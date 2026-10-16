/* SO - Modal único de ações para linhas de ofícios e aquisições. */
(function () {
    'use strict';

    function initListActions() {
        const modal = document.getElementById('so-list-actions-modal');
        if (!modal) return;

        const panel = modal.querySelector('.so-action-modal__panel');
        const title = modal.querySelector('#so-list-actions-title');
        const subtitle = modal.querySelector('#so-list-actions-subtitle');
        const links = modal.querySelector('[data-so-actions-container]');
        const closeButton = modal.querySelector('[data-so-action-close]');
        let lastRow = null;

        function isInteractive(target) {
            return !!target.closest('a,button,input,select,textarea,label,[contenteditable="true"],[data-no-row-action]');
        }

        function openActions(row) {
            const templateId = row.getAttribute('data-so-actions-template');
            const template = templateId ? document.getElementById(templateId) : null;
            if (!template || !links || !panel) return;

            links.replaceChildren(template.content.cloneNode(true));
            title.textContent = row.getAttribute('data-so-actions-title') || 'Ações disponíveis';
            subtitle.textContent = row.getAttribute('data-so-actions-subtitle') || 'Escolha uma opção para continuar.';
            lastRow = row;
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('so-list-modal-open');
            const firstLink = links.querySelector('a,button');
            (firstLink || closeButton).focus();
        }

        function closeActions(restoreFocus = true) {
            if (modal.hidden) return;
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('so-list-modal-open');
            if (restoreFocus && lastRow && lastRow.isConnected) lastRow.focus();
        }

        document.addEventListener('click', function (event) {
            const row = event.target.closest('[data-so-list-row]');
            if (!row || isInteractive(event.target)) return;
            openActions(row);
        });

        document.addEventListener('keydown', function (event) {
            if (modal.hidden) {
                const row = event.target.closest('[data-so-list-row]');
                if (row && event.target === row && (event.key === 'Enter' || event.key === ' ')) {
                    event.preventDefault();
                    openActions(row);
                }
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                closeActions();
            } else if (event.key === 'Tab') {
                const focusable = Array.from(panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled])'))
                    .filter(element => element.getClientRects().length > 0);
                if (focusable.length === 0) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal || event.target.closest('[data-so-action-close]')) {
                closeActions();
            } else if (event.target.closest('[data-delete-oficio]')) {
                // A confirmação de exclusão tem seu próprio modal protegido por senha.
                closeActions(false);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initListActions);
    } else {
        initListActions();
    }
})();

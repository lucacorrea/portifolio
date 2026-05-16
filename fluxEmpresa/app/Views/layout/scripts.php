<?php

defined('APP_PATH') || exit('Acesso direto negado.');
?>
<script>
    (() => {
        const body = document.body;
        const openButtons = document.querySelectorAll('[data-sidebar-toggle]');
        const closeButtons = document.querySelectorAll('[data-sidebar-close]');

        openButtons.forEach((button) => {
            button.addEventListener('click', () => {
                body.classList.add('sidebar-open');
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                body.classList.remove('sidebar-open');
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                body.classList.remove('sidebar-open');
            }
        });
    })();
</script>

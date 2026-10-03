(() => {
    'use strict';

    const menu = document.getElementById('landing-menu');
    const menuToggle = document.querySelector('[aria-controls="landing-menu"]');

    const closeMenu = () => {
        if (! menu || ! menuToggle) return;

        menu.classList.remove('is-open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Menyuni ochish');
    };

    if (menu && menuToggle) {
        menuToggle.addEventListener('click', () => {
            const open = menu.classList.toggle('is-open');
            menuToggle.setAttribute('aria-expanded', String(open));
            menuToggle.setAttribute('aria-label', open ? 'Menyuni yopish' : 'Menyuni ochish');
        });

        menu.querySelectorAll('a, button').forEach((item) => {
            item.addEventListener('click', closeMenu);
        });
    }

    document.querySelectorAll('[data-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.dialogOpen);

            if (! (dialog instanceof HTMLDialogElement)) return;

            dialog.showModal();
            document.body.classList.add('dialog-open');
        });
    });

    document.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('dialog')?.close();
        });
    });

    document.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });

        dialog.addEventListener('close', () => {
            document.body.classList.remove('dialog-open');
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });
})();

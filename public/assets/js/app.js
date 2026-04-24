document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-sidebar-shell]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    if (shell && toggle) {
        toggle.addEventListener('click', () => {
            shell.classList.toggle('sidebar-open');
        });

        document.addEventListener('click', (event) => {
            if (window.innerWidth > 920 || !shell.classList.contains('sidebar-open')) {
                return;
            }

            const insideSidebar = event.target.closest('#sidebar');
            const insideToggle = event.target.closest('[data-sidebar-toggle]');
            if (!insideSidebar && !insideToggle) {
                shell.classList.remove('sidebar-open');
            }
        });
    }

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach((element) => {
            element.style.transition = 'opacity .25s ease, transform .25s ease';
            element.style.opacity = '0';
            element.style.transform = 'translateY(-6px)';
            setTimeout(() => element.remove(), 250);
        });
    }, 4200);
});

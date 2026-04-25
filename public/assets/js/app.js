document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('[data-sidebar-shell]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    requestAnimationFrame(() => {
        document.body.classList.add('ui-ready');
    });

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

    const firstInvalidField = document.querySelector('.form-field.has-error input, .form-field.has-error select, .form-field.has-error textarea');
    if (firstInvalidField) {
        setTimeout(() => {
            firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalidField.focus({ preventScroll: true });
        }, 180);
    }

    document.querySelectorAll('form.stack-form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.classList.add('is-submitting');
        });
    });

    document.querySelectorAll('.form-field input, .form-field select, .form-field textarea').forEach((field) => {
        const wrapper = field.closest('.form-field');
        if (!wrapper) {
            return;
        }

        field.addEventListener('input', () => {
            wrapper.classList.remove('has-error');
            field.classList.remove('is-invalid');
        });

        field.addEventListener('change', () => {
            wrapper.classList.remove('has-error');
            field.classList.remove('is-invalid');
        });
    });

    document.querySelectorAll('[data-file-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const nameElement = document.getElementById(input.dataset.fileName || '');
            const previewElement = document.getElementById(input.dataset.filePreview || '');
            const previewImage = document.getElementById(input.dataset.fileImage || '');

            if (nameElement) {
                nameElement.textContent = file ? file.name : 'PNG, JPG, or WEBP up to 4 MB.';
            }

            if (!previewElement || !previewImage) {
                return;
            }

            if (!file) {
                previewElement.classList.remove('is-visible');
                previewImage.setAttribute('hidden', 'hidden');
                previewImage.removeAttribute('src');
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            previewImage.src = objectUrl;
            previewImage.removeAttribute('hidden');
            previewElement.classList.add('is-visible');
            previewImage.onload = () => URL.revokeObjectURL(objectUrl);
        });
    });

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach((element) => {
            element.style.transition = 'opacity .25s ease, transform .25s ease';
            element.style.opacity = '0';
            element.style.transform = 'translateY(-6px)';
            setTimeout(() => element.remove(), 250);
        });
    }, 4200);
});

document.addEventListener('DOMContentLoaded', () => {
    const loadingOverlay = document.querySelector('[data-loading-overlay]');
    const loadingText = loadingOverlay?.querySelector('[data-loading-text]');
    const shell = document.querySelector('[data-sidebar-shell]');
    const toggle = document.querySelector('[data-sidebar-toggle]');

    const showLoader = (message) => {
        if (!loadingOverlay) {
            return;
        }

        if (loadingText && message) {
            loadingText.textContent = message;
        }

        loadingOverlay.hidden = false;
        requestAnimationFrame(() => loadingOverlay.classList.add('is-visible'));
        document.body.classList.add('is-busy');
    };

    const hideLoader = () => {
        if (!loadingOverlay) {
            return;
        }

        loadingOverlay.classList.remove('is-visible');
        loadingOverlay.hidden = true;
        document.body.classList.remove('is-busy');
    };

    window.MhallyLoader = {
        show: showLoader,
        hide: hideLoader,
    };

    hideLoader();

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
            showLoader(form.dataset.loadingText || 'Saving your changes…');
        });
    });

    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const href = link.getAttribute('href') || '';
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || link.hasAttribute('download') || link.target === '_blank') {
                return;
            }

            showLoader(link.dataset.loadingText || 'Loading the next screen…');
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

    document.querySelectorAll('form[data-delete-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            const submitter = event.submitter;
            const scopeInput = form.querySelector('[data-delete-scope-input]');
            if (scopeInput && submitter?.dataset.deleteScope) {
                scopeInput.value = submitter.dataset.deleteScope;
            }

            const scope = submitter?.dataset.deleteScope || scopeInput?.value || 'selected';
            if (form.dataset.selectionForm === 'true' && scope === 'selected') {
                const selected = Array.from(document.querySelectorAll(`input[form="${form.id}"][data-row-select]:checked`));
                if (selected.length === 0) {
                    event.preventDefault();
                    window.alert(form.dataset.selectRequiredMessage || 'Select at least one row before deleting.');
                    return;
                }
            }

            const keyword = (submitter?.dataset.confirmKeyword || form.dataset.confirmKeyword || 'DELETE').trim().toUpperCase();
            const message = submitter?.dataset.confirmMessage || form.dataset.confirmMessage || `Type ${keyword} to confirm deletion.`;
            const errorMessage = submitter?.dataset.confirmError || form.dataset.confirmError || `Deletion cancelled. Type ${keyword} exactly to continue.`;
            const typed = window.prompt(message, '');

            if (typed === null) {
                event.preventDefault();
                return;
            }

            if (typed.trim().toUpperCase() !== keyword) {
                event.preventDefault();
                window.alert(errorMessage);
                return;
            }

            form.dataset.confirmed = 'true';
            showLoader(submitter?.dataset.loadingText || form.dataset.loadingText || 'Deleting records...');
        });
    });

    document.querySelectorAll('form[data-selection-form]').forEach((form) => {
        const rowSelectors = Array.from(document.querySelectorAll(`input[form="${form.id}"][data-row-select]`));
        const selectAllBoxes = Array.from(document.querySelectorAll(`[data-select-all][data-form-id="${form.id}"]`));
        const selectedCount = form.querySelector('[data-selected-count]');
        const selectedButton = form.querySelector('[data-selected-action]');
        const selectedCountTemplate = form.dataset.selectedCountTemplate || '__count__ selected';

        if (rowSelectors.length === 0) {
            return;
        }

        const updateSelectionState = () => {
            const checkedCount = rowSelectors.filter((input) => input.checked).length;
            const allChecked = checkedCount > 0 && checkedCount === rowSelectors.length;

            selectAllBoxes.forEach((checkbox) => {
                checkbox.checked = allChecked;
                checkbox.indeterminate = checkedCount > 0 && checkedCount < rowSelectors.length;
            });

            if (selectedCount) {
                selectedCount.textContent = selectedCountTemplate.replace('__count__', checkedCount.toString());
            }

            if (selectedButton) {
                selectedButton.disabled = checkedCount === 0;
            }
        };

        rowSelectors.forEach((input) => {
            input.addEventListener('change', updateSelectionState);
        });

        selectAllBoxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                rowSelectors.forEach((input) => {
                    input.checked = checkbox.checked;
                });
                updateSelectionState();
            });
        });

        updateSelectionState();
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

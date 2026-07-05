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

    const asyncPickers = Array.from(document.querySelectorAll('[data-async-picker]'));

    const closeAsyncPicker = (picker) => {
        const panel = picker.querySelector('[data-async-picker-panel]');
        const selectedBox = picker.querySelector('[data-async-picker-selected]');
        if (panel) {
            panel.hidden = true;
        }
        picker.classList.remove('is-open');
        picker.closest('.form-field')?.classList.remove('has-open-picker');
        if (selectedBox) {
            selectedBox.setAttribute('aria-expanded', 'false');
        }
    };

    asyncPickers.forEach((picker) => {
        const hiddenInput = picker.querySelector('input[type="hidden"]');
        const selectedBox = picker.querySelector('[data-async-picker-selected]');
        const panel = picker.querySelector('[data-async-picker-panel]');
        const searchInput = picker.querySelector('[data-async-picker-search]');
        const results = picker.querySelector('[data-async-picker-results]');
        const status = picker.querySelector('[data-async-picker-status]');
        const label = picker.querySelector('[data-async-picker-label]');
        const meta = picker.querySelector('[data-async-picker-meta]');
        const clearButton = picker.querySelector('[data-async-picker-clear]');

        if (!hiddenInput || !selectedBox || !panel || !searchInput || !results || !status || !label || !meta) {
            return;
        }

        const state = {
            endpoint: picker.dataset.endpoint || '',
            search: '',
            page: 1,
            hasMore: true,
            isLoading: false,
            hasLoaded: false,
            items: [],
            debounceId: null,
        };

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const renderStatus = (message) => {
            status.textContent = message;
        };

        const setSelection = (item) => {
            const emptyLabel = picker.dataset.emptyLabel || '';
            const emptyMeta = picker.dataset.emptyMeta || '';

            if (!item) {
                hiddenInput.value = '';
                label.textContent = emptyLabel;
                meta.textContent = emptyMeta;
                selectedBox.classList.remove('is-selected');
            } else {
                hiddenInput.value = String(item.id || '');
                label.textContent = item.label || emptyLabel;
                meta.textContent = item.meta || '';
                selectedBox.classList.add('is-selected');
            }

            const wrapper = hiddenInput.closest('.form-field');
            if (wrapper) {
                wrapper.classList.remove('has-error');
            }
            searchInput.classList.remove('is-invalid');
        };

        const renderItems = () => {
            if (!state.items.length) {
                results.innerHTML = `<div class="async-picker__empty">${picker.dataset.emptyText || ''}</div>`;
                return;
            }

            results.innerHTML = state.items.map((item) => {
                const selected = String(hiddenInput.value) === String(item.id);
                return `
                    <button type="button" class="async-picker__option ${selected ? 'is-selected' : ''}" data-option-id="${escapeHtml(item.id)}">
                        <strong>${escapeHtml(item.label || '')}</strong>
                        <small>${escapeHtml(item.meta || '')}</small>
                    </button>
                `;
            }).join('');
        };

        const loadItems = async (page, append = false) => {
            if (state.isLoading || !state.endpoint) {
                return;
            }

            state.isLoading = true;
            renderStatus(picker.dataset.loadingText || '');

            try {
                const url = new URL(state.endpoint, window.location.origin);
                url.searchParams.set('page', String(page));
                url.searchParams.set('limit', '25');
                if (state.search) {
                    url.searchParams.set('search', state.search);
                }

                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`Request failed with ${response.status}`);
                }

                const payload = await response.json();
                const items = Array.isArray(payload.items) ? payload.items : [];
                const pagination = payload.pagination || {};

                state.page = Number(pagination.page || page);
                state.hasMore = Boolean(pagination.has_more);
                state.items = append ? state.items.concat(items) : items;
                state.hasLoaded = true;

                renderItems();

                if (!state.items.length) {
                    renderStatus(picker.dataset.emptyText || '');
                } else if (state.hasMore) {
                    renderStatus(picker.dataset.scrollText || '');
                } else {
                    renderStatus('');
                }
            } catch (error) {
                renderStatus(picker.dataset.errorText || '');
            } finally {
                state.isLoading = false;
            }
        };

        const openPicker = () => {
            asyncPickers.forEach((item) => {
                if (item !== picker) {
                    closeAsyncPicker(item);
                }
            });

            panel.hidden = false;
            picker.classList.add('is-open');
            picker.closest('.form-field')?.classList.add('has-open-picker');
            selectedBox.setAttribute('aria-expanded', 'true');

            if (!state.hasLoaded) {
                loadItems(1);
            }
        };

        selectedBox.addEventListener('click', () => {
            if (picker.classList.contains('is-open')) {
                closeAsyncPicker(picker);
                return;
            }

            openPicker();
            window.setTimeout(() => searchInput.focus(), 0);
        });

        searchInput.addEventListener('focus', () => {
            openPicker();
        });

        clearButton?.addEventListener('click', () => {
            setSelection(null);
            searchInput.value = '';
            state.search = '';
            state.page = 1;
            state.hasMore = true;
            loadItems(1);
            searchInput.focus();
        });

        searchInput.addEventListener('input', () => {
            openPicker();
            window.clearTimeout(state.debounceId);
            state.debounceId = window.setTimeout(() => {
                state.search = searchInput.value.trim();
                state.page = 1;
                state.hasMore = true;
                loadItems(1);
            }, 250);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAsyncPicker(picker);
                searchInput.blur();
            }
        });

        results.addEventListener('scroll', () => {
            if (!state.hasMore || state.isLoading) {
                return;
            }

            if (results.scrollTop + results.clientHeight >= results.scrollHeight - 48) {
                loadItems(state.page + 1, true);
            }
        });

        results.addEventListener('click', (event) => {
            const option = event.target.closest('[data-option-id]');
            if (!option) {
                return;
            }

            const selected = state.items.find((item) => String(item.id) === String(option.dataset.optionId));
            if (!selected) {
                return;
            }

            setSelection(selected);
            renderItems();
            closeAsyncPicker(picker);
            searchInput.value = '';
            state.search = '';
        });
    });

    document.addEventListener('click', (event) => {
        asyncPickers.forEach((picker) => {
            if (!picker.contains(event.target)) {
                closeAsyncPicker(picker);
            }
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

    document.querySelectorAll('[data-mirror-selection]').forEach((container) => {
        const sourceSelector = container.dataset.mirrorSelection;
        const countElement = container.querySelector('[data-mirror-selection-count]');
        const actionElement = container.querySelector('[data-mirror-selection-action]');
        const template = container.dataset.mirrorSelectionTemplate || '__count__ selected';

        const updateMirrorState = () => {
            const checkedCount = document.querySelectorAll(`${sourceSelector}:checked`).length;

            if (countElement) {
                countElement.textContent = template.replace('__count__', checkedCount.toString());
            }

            if (actionElement) {
                actionElement.disabled = checkedCount === 0;
            }
        };

        document.addEventListener('change', (event) => {
            if (!event.target.matches) {
                return;
            }
            if (event.target.matches(sourceSelector) || event.target.matches('[data-select-all]')) {
                updateMirrorState();
            }
        });

        updateMirrorState();
    });

    document.querySelectorAll('form[data-copy-selection-into]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const sourceSelector = form.dataset.copySelectionInto;
            const checked = Array.from(document.querySelectorAll(`${sourceSelector}:checked`));

            if (checked.length === 0) {
                event.preventDefault();
                window.alert(form.dataset.selectRequiredMessage || 'Select at least one row first.');
                return;
            }

            form.querySelectorAll('[data-copied-selection]').forEach((node) => node.remove());
            checked.forEach((input) => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'selected_ids[]';
                hidden.value = input.value;
                hidden.setAttribute('data-copied-selection', 'true');
                form.appendChild(hidden);
            });

            form.classList.add('is-submitting');
            showLoader(form.dataset.loadingText || 'Saving your changes…');
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

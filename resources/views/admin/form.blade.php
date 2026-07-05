@extends('layouts.admin')

@php
    use App\Support\AdminUi;

    $isEdit = (bool) $item;
    $title = $isEdit
        ? __('panel.common.edit') . ' ' . AdminUi::singularTitle($module)
        : __('panel.common.add_new', ['title' => AdminUi::singularTitle($module)]);
    $requiredFields = $requiredFields ?? [];
    $asyncFieldConfigs = $asyncFieldConfigs ?? [];
    $selectedAsyncOptions = $selectedAsyncOptions ?? [];
    $fileFieldHelp = [
        'icon' => __('panel.common.file_help'),
        'emoji' => __('panel.common.file_help'),
        'catalog_products.image_url' => __('panel.common.file_help'),
        'offers.image_url' => __('panel.common.file_help'),
    ];
    $fieldIndex = 0;
@endphp

@section('page_action', $isEdit ? 'edit' : 'create')
@section('title', $title)

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', $module) }}">&larr; {{ __('panel.common.back_to', ['title' => $config['title']]) }}</a>
        </div>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $title }}</h3>
                    <p>{{ AdminUi::moduleFormHelp($module, $config['form_help'] ?? null) }}</p>
                </div>
            </div>

            @if(($config['notice_title'] ?? '') !== '')
                <div class="notice-box">
                    <strong>{{ $config['notice_title'] }}</strong>
                    <p>{{ $config['notice_body'] }}</p>
                </div>
            @endif

            @if($errors->any())
                <div class="validation-summary" role="alert">
                    <strong>{{ __('panel.common.validation_summary_title') }}</strong>
                    <p>{{ trans_choice('panel.common.validation_summary_body', $errors->count(), ['count' => $errors->count()]) }}</p>
                </div>
            @endif

            <form method="post" action="{{ $isEdit ? route('admin.module.update', [$module, $item->id]) : route('admin.module.store', $module) }}" class="stack-form" enctype="multipart/form-data" novalidate>
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                @foreach(array_chunk($config['fields'], 2, true) as $fieldGroup)
                    <div class="{{ count($fieldGroup) === 1 ? '' : 'two-field' }}">
                        @foreach($fieldGroup as $field => $type)
                            @php
                                $fieldIndex++;
                                $currentValue = old($field, data_get($item, $field));
                                $inputType = is_array($type) ? 'select' : $type;
                                $options = $fieldOptions[$field] ?? [];
                                $hasRelationOptions = array_key_exists($field, $fieldOptions ?? []);
                                $createModule = [
                                    'catalog_product_id' => 'catalog_products',
                                    'supplier_id' => 'suppliers',
                                    'supplier_product_id' => 'products',
                                    'category_id' => 'categories',
                                    'parent_id' => 'categories',
                                ][$field] ?? null;
                                $fieldId = $module . '_' . $field;
                                $hasError = $errors->has($field);
                                $fieldLabel = AdminUi::columnLabel($field);
                                $isRequired = (bool) ($requiredFields[$field] ?? false);
                                $currentMediaUrl = AdminUi::isImageReference($currentValue) ? AdminUi::mediaUrl($currentValue) : '';
                                $fileHelp = $fileFieldHelp[$module . '.' . $field] ?? $fileFieldHelp[$field] ?? 'PNG, JPG, or WEBP up to 4 MB.';
                                $inputClasses = trim($hasError ? 'is-invalid' : '');
                                $asyncConfig = $asyncFieldConfigs[$field] ?? null;
                                $selectedOption = $selectedAsyncOptions[$field] ?? null;
                                $selectedId = $currentValue !== null && $currentValue !== '' ? (string) $currentValue : '';
                                $selectedLabel = $selectedOption['label'] ?? ($selectedId !== '' ? __('panel.async.selected_value') : ($asyncConfig['empty_label'] ?? ''));
                                $selectedMeta = $selectedOption['meta'] ?? ($asyncConfig['empty_meta'] ?? '');
                            @endphp

                            <div class="form-field {{ $inputType === 'checkbox' ? 'is-checkbox' : '' }} {{ $hasError ? 'has-error' : '' }}" style="--stagger: {{ $fieldIndex }};">
                                @if($inputType === 'checkbox')
                                    <label class="check-row" for="{{ $fieldId }}">
                                        <input id="{{ $fieldId }}" type="checkbox" name="{{ $field }}" value="1" {{ (bool) $currentValue ? 'checked' : '' }} {{ $hasError ? 'aria-invalid=true' : '' }}>
                                        <span>{{ $fieldLabel }}@if($isRequired) <em>*</em>@endif</span>
                                    </label>
                                @else
                                    <label for="{{ $fieldId }}">
                                        <span>{{ $fieldLabel }}@if($isRequired) <em>*</em>@endif</span>
                                    </label>

                                    @if($asyncConfig)
                                        <div
                                            class="async-picker"
                                            data-async-picker
                                            data-endpoint="{{ $asyncConfig['endpoint'] }}"
                                            data-search-placeholder="{{ $asyncConfig['search_placeholder'] }}"
                                            data-empty-label="{{ $asyncConfig['empty_label'] }}"
                                            data-empty-meta="{{ $asyncConfig['empty_meta'] }}"
                                            data-loading-text="{{ __('panel.async.loading') }}"
                                            data-empty-text="{{ __('panel.async.no_results') }}"
                                            data-error-text="{{ __('panel.async.failed') }}"
                                            data-scroll-text="{{ __('panel.async.scroll_more') }}"
                                        >
                                            <input type="hidden" id="{{ $fieldId }}" name="{{ $field }}" value="{{ $selectedId }}">
                                            <button
                                                type="button"
                                                class="async-picker__selected {{ $selectedId !== '' ? 'is-selected' : '' }}"
                                                data-async-picker-selected
                                                aria-expanded="false"
                                            >
                                                <span class="async-picker__selected-copy">
                                                    <strong data-async-picker-label>{{ $selectedLabel }}</strong>
                                                    <small data-async-picker-meta>{{ $selectedMeta }}</small>
                                                </span>
                                                <span class="async-picker__toggle" aria-hidden="true">
                                                    <svg viewBox="0 0 20 20" fill="none" focusable="false">
                                                        <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </span>
                                            </button>
                                            <div class="async-picker__panel" data-async-picker-panel hidden>
                                                <div class="async-picker__search-row">
                                                    <input type="search" class="async-picker__search {{ $inputClasses }}" data-async-picker-search placeholder="{{ $asyncConfig['search_placeholder'] }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                                    <button type="button" class="ghost-button async-picker__clear" data-async-picker-clear>{{ __('panel.async.clear') }}</button>
                                                </div>
                                                <div class="async-picker__results" data-async-picker-results></div>
                                                <div class="async-picker__status" data-async-picker-status>{{ __('panel.async.search_prompt') }}</div>
                                            </div>
                                        </div>
                                    @elseif($hasRelationOptions)
                                        <select id="{{ $fieldId }}" name="{{ $field }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                            <option value="">{{ __('panel.common.select', ['field' => strtolower($fieldLabel)]) }}</option>
                                            @foreach($options as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" {{ (string) $currentValue === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                        @if(empty($options) && $createModule)
                                            <small class="field-help">{{ __('panel.common.no_relation_records', ['field' => strtolower($fieldLabel)]) }} <a href="{{ route('admin.module.create', $createModule) }}">{{ __('panel.common.create') }}</a>.</small>
                                        @endif
                                    @elseif(is_array($type))
                                        <select id="{{ $fieldId }}" name="{{ $field }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                            @foreach($type as $option)
                                                <option value="{{ $option }}" {{ (string) $currentValue === (string) $option ? 'selected' : '' }}>{{ AdminUi::statusLabel($option) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($type === 'textarea')
                                        <textarea id="{{ $fieldId }}" name="{{ $field }}" rows="4" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>{{ $currentValue }}</textarea>
                                    @elseif($type === 'datetime')
                                        <input id="{{ $fieldId }}" type="datetime-local" name="{{ $field }}" value="{{ $currentValue ? \Illuminate\Support\Carbon::parse($currentValue)->format('Y-m-d\TH:i') : '' }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                    @elseif($type === 'date')
                                        <input id="{{ $fieldId }}" type="date" name="{{ $field }}" value="{{ $currentValue ? \Illuminate\Support\Carbon::parse($currentValue)->format('Y-m-d') : '' }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                    @elseif($type === 'number')
                                        <input id="{{ $fieldId }}" type="number" step="any" name="{{ $field }}" value="{{ $currentValue }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                    @elseif($type === 'file')
                                        <div class="file-preview {{ $currentMediaUrl !== '' ? 'is-visible' : '' }}" id="{{ $fieldId }}_preview">
                                            <img id="{{ $fieldId }}_preview_image" src="{{ $currentMediaUrl }}" alt="{{ $fieldLabel }} preview" {{ $currentMediaUrl === '' ? 'hidden' : '' }}>
                                        </div>
                                        <input
                                            id="{{ $fieldId }}"
                                            type="file"
                                            name="{{ $field }}"
                                            accept="image/png,image/jpeg,image/webp"
                                            class="{{ $inputClasses }}"
                                            data-file-input
                                            data-file-preview="{{ $fieldId }}_preview"
                                            data-file-image="{{ $fieldId }}_preview_image"
                                            data-file-name="{{ $fieldId }}_file_name"
                                            {{ $hasError ? 'aria-invalid=true' : '' }}
                                        >
                                        <small class="file-meta" id="{{ $fieldId }}_file_name">{{ $currentMediaUrl !== '' ? __('panel.common.current_image_hint') : $fileHelp }}</small>
                                        @if($currentMediaUrl === '' && is_string($currentValue) && trim($currentValue) !== '')
                                            <small class="field-help">Current value: {{ $currentValue }}</small>
                                        @endif
                                    @else
                                        <input id="{{ $fieldId }}" type="{{ $type }}" name="{{ $field }}" value="{{ $currentValue }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                    @endif
                                @endif

                                @error($field)
                                    <small class="field-error">{{ $message }}</small>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="form-actions" style="--stagger: {{ $fieldIndex + 1 }};">
                    <a class="ghost-button" href="{{ route('admin.module.index', $module) }}">{{ __('panel.common.cancel') }}</a>
                    <button class="primary-button" type="submit">{{ $isEdit ? __('panel.common.update') : __('panel.common.create') }} {{ AdminUi::singularTitle($module) }}</button>
                </div>
            </form>
        </section>
    </section>
@endsection

@extends('layouts.admin')

@php
    use App\Support\AdminUi;

    $isEdit = (bool) $item;
    $title = ($isEdit ? 'Edit ' : 'Add New ') . AdminUi::singularTitle($module);
    $requiredFields = $requiredFields ?? [];
    $fileFieldHelp = [
        'icon' => 'Choose a clean icon image for this category.',
        'emoji' => 'Choose a small emoji-style image or sticker for this catalog item.',
        'catalog_products.image_url' => 'Choose the main product image used across the app.',
        'offers.image_url' => 'Choose the banner image used on the offer card.',
    ];
    $fieldIndex = 0;
@endphp

@section('page_action', $isEdit ? 'edit' : 'create')
@section('title', $title)

@section('content')
    <section class="module-screen narrow">
        <div class="screen-head">
            <a class="back-link" href="{{ route('admin.module.index', $module) }}">&larr; Back to {{ $config['title'] }}</a>
        </div>

        <section class="panel form-panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $config['form_help'] ?? 'Use the same buyer and supplier app flow while managing this record.' }}</p>
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
                    <strong>Please review the highlighted fields.</strong>
                    <p>{{ $errors->count() }} issue{{ $errors->count() === 1 ? '' : 's' }} need attention before this form can be saved.</p>
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
                                ][$field] ?? null;
                                $fieldId = $module . '_' . $field;
                                $hasError = $errors->has($field);
                                $fieldLabel = AdminUi::columnLabel($field);
                                $isRequired = (bool) ($requiredFields[$field] ?? false);
                                $currentMediaUrl = AdminUi::isImageReference($currentValue) ? AdminUi::mediaUrl($currentValue) : '';
                                $fileHelp = $fileFieldHelp[$module . '.' . $field] ?? $fileFieldHelp[$field] ?? 'PNG, JPG, or WEBP up to 4 MB.';
                                $inputClasses = trim($hasError ? 'is-invalid' : '');
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

                                    @if($hasRelationOptions)
                                        <select id="{{ $fieldId }}" name="{{ $field }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                            <option value="">Select {{ strtolower($fieldLabel) }}</option>
                                            @foreach($options as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" {{ (string) $currentValue === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                        @if(empty($options) && $createModule)
                                            <small class="field-help">No {{ strtolower($fieldLabel) }} records found. <a href="{{ route('admin.module.create', $createModule) }}">Create one first</a>.</small>
                                        @endif
                                    @elseif(is_array($type))
                                        <select id="{{ $fieldId }}" name="{{ $field }}" class="{{ $inputClasses }}" {{ $hasError ? 'aria-invalid=true' : '' }}>
                                            @foreach($type as $option)
                                                <option value="{{ $option }}" {{ (string) $currentValue === (string) $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
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
                                        <small class="file-meta" id="{{ $fieldId }}_file_name">{{ $currentMediaUrl !== '' ? 'Current image is shown above. Choose a new file to replace it.' : $fileHelp }}</small>
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
                    <a class="ghost-button" href="{{ route('admin.module.index', $module) }}">Cancel</a>
                    <button class="primary-button" type="submit">{{ $isEdit ? 'Update ' : 'Create ' }}{{ AdminUi::singularTitle($module) }}</button>
                </div>
            </form>
        </section>
    </section>
@endsection

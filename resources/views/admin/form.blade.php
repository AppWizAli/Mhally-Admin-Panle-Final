@extends('layouts.admin')

@php
    use App\Support\AdminUi;

    $isEdit = (bool) $item;
    $title = ($isEdit ? 'Edit ' : 'Add New ') . AdminUi::singularTitle($module);
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

            <form method="post" action="{{ $isEdit ? route('admin.module.update', [$module, $item->id]) : route('admin.module.store', $module) }}" class="stack-form" enctype="multipart/form-data">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                @foreach(array_chunk($config['fields'], 2, true) as $fieldGroup)
                    <div class="{{ count($fieldGroup) === 1 ? '' : 'two-field' }}">
                        @foreach($fieldGroup as $field => $type)
                            @php
                                $currentValue = old($field, data_get($item, $field));
                                $inputType = is_array($type) ? 'select' : $type;
                                $options = $fieldOptions[$field] ?? [];
                            @endphp
                            <label class="{{ $inputType === 'checkbox' ? 'check-row' : '' }}">
                                @if($inputType === 'checkbox')
                                    <input type="checkbox" name="{{ $field }}" value="1" {{ (bool) $currentValue ? 'checked' : '' }}>
                                    <span>{{ AdminUi::columnLabel($field) }}</span>
                                @else
                                    <span>{{ AdminUi::columnLabel($field) }}</span>
                                    @if(!empty($options))
                                        <select name="{{ $field }}">
                                            <option value="">Select {{ strtolower(AdminUi::columnLabel($field)) }}</option>
                                            @foreach($options as $optionValue => $optionLabel)
                                                <option value="{{ $optionValue }}" {{ (string) $currentValue === (string) $optionValue ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    @elseif(is_array($type))
                                        <select name="{{ $field }}">
                                            @foreach($type as $option)
                                                <option value="{{ $option }}" {{ (string) $currentValue === (string) $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($type === 'textarea')
                                        <textarea name="{{ $field }}" rows="4">{{ $currentValue }}</textarea>
                                    @elseif($type === 'datetime')
                                        <input type="datetime-local" name="{{ $field }}" value="{{ $currentValue ? \Illuminate\Support\Carbon::parse($currentValue)->format('Y-m-d\TH:i') : '' }}">
                                    @elseif($type === 'date')
                                        <input type="date" name="{{ $field }}" value="{{ $currentValue ? \Illuminate\Support\Carbon::parse($currentValue)->format('Y-m-d') : '' }}">
                                    @elseif($type === 'number')
                                        <input type="number" step="any" name="{{ $field }}" value="{{ $currentValue }}">
                                    @elseif($type === 'file')
                                        <input type="file" name="{{ $field }}" accept="image/png,image/jpeg,image/webp">
                                    @else
                                        <input type="{{ $type }}" name="{{ $field }}" value="{{ $currentValue }}">
                                    @endif
                                @endif
                            </label>
                        @endforeach
                    </div>
                @endforeach

                <div class="form-actions">
                    <a class="ghost-button" href="{{ route('admin.module.index', $module) }}">Cancel</a>
                    <button class="primary-button" type="submit">{{ $isEdit ? 'Update ' : 'Create ' }}{{ AdminUi::singularTitle($module) }}</button>
                </div>
            </form>
        </section>
    </section>
@endsection

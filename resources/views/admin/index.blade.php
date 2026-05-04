@extends('layouts.admin')

@php
    use App\Support\AdminUi;

    $statusField = $config['fields']['status'] ?? null;
    $statusOptions = is_array($statusField) ? $statusField : [];
    $canCreate = $config['creatable'] ?? !in_array($module, ['orders', 'chats', 'referral_claims', 'referral_codes', 'devices', 'otp_requests'], true);
    $canDelete = $config['deletable'] ?? !in_array($module, ['orders', 'chats', 'referral_claims', 'referral_codes', 'devices', 'otp_requests'], true);
    $canBulkDelete = $canDelete && $items->count() > 0;
@endphp

@section('title', $config['title'])

@section('content')
    <section class="module-screen">
        @if($module === 'orders' && !empty($summaryCards))
            <div class="tab-stats">
                @foreach($summaryCards as $key => $value)
                    @php $statusKey = $key === 'all' ? '' : $key; @endphp
                    <a class="tab-stat {{ $status === $statusKey ? 'is-active' : '' }}" href="{{ route('admin.module.index', ['module' => $module, 'status' => $statusKey]) }}">
                        <strong>{{ number_format((int) $value) }}</strong>
                        <span>{{ $key === 'all' ? __('panel.common.all') : AdminUi::statusLabel($key) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="screen-toolbar">
            <form method="get" class="toolbar-filters {{ count($statusOptions) <= 3 ? 'short' : '' }}">
                <div class="search-input">
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('panel.common.search') }} {{ $config['title'] }}...">
                </div>
                @if($module === 'offers')
                    <input type="text" name="city" value="{{ $city }}" placeholder="{{ AdminUi::columnLabel('city') }}">
                @endif
                @if($statusOptions)
                    <select name="status">
                        <option value="">{{ __('panel.common.all_status') }}</option>
                        @foreach($statusOptions as $option)
                            <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>{{ AdminUi::statusLabel($option) }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="ghost-button" type="submit">{{ $statusOptions || $module === 'offers' ? __('panel.common.filters') : __('panel.common.search') }}</button>
            </form>
            @if($module === 'products')
                <a class="ghost-button" href="{{ route('admin.products.bulk') }}">{{ __('panel.common.bulk_upload') }}</a>
            @endif
            @if($module === 'catalog_products')
                <a class="ghost-button" href="{{ route('admin.catalog-products.bulk') }}">{{ __('panel.common.bulk_catalog_upload') }}</a>
            @endif
            @if($canCreate)
                <a class="primary-button" href="{{ route('admin.module.create', $module) }}">{{ __('panel.common.add', ['title' => AdminUi::singularTitle($module)]) }}</a>
            @endif
        </div>

        <section class="panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $config['title'] }}</h3>
                    <p>{{ __('panel.common.records_found', ['count' => number_format($items->total())]) }}</p>
                </div>
            </div>
            @if($canBulkDelete)
                <form
                    id="bulk-delete-form"
                    method="post"
                    action="{{ route('admin.module.bulk-destroy', $module) }}"
                    class="bulk-actions"
                    data-delete-confirm
                    data-selection-form="true"
                    data-select-required-message="{{ __('panel.messages.bulk_delete_no_items') }}"
                    data-selected-count-template="{{ __('panel.common.selected_count', ['count' => '__count__']) }}"
                >
                    @csrf
                    <input type="hidden" name="delete_scope" value="selected" data-delete-scope-input>
                    <input type="hidden" name="search" value="{{ $search }}">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <input type="hidden" name="city" value="{{ $city }}">

                    <div class="bulk-actions__meta">
                        <label class="check-pill bulk-select" for="bulk-delete-toggle">
                            <input type="checkbox" id="bulk-delete-toggle" data-select-all data-form-id="bulk-delete-form">
                            <span>{{ __('panel.common.select_all_page') }}</span>
                        </label>
                        <strong data-selected-count>{{ __('panel.common.selected_count', ['count' => 0]) }}</strong>
                        <small>{{ __('panel.common.bulk_delete_help') }}</small>
                    </div>

                    <div class="bulk-actions__buttons">
                        <button
                            type="submit"
                            class="danger-button"
                            data-delete-scope="selected"
                            data-selected-action
                            data-confirm-message="{{ __('panel.common.delete_selected_prompt', ['title' => AdminUi::moduleTitle($module)]) }}"
                            data-confirm-error="{{ __('panel.common.delete_prompt_error') }}"
                            data-loading-text="{{ __('panel.common.deleting_selected') }}"
                            disabled
                        >
                            {{ __('panel.common.delete_selected') }}
                        </button>
                        <button
                            type="submit"
                            class="ghost-button danger-outline"
                            data-delete-scope="filtered"
                            data-confirm-message="{{ __('panel.common.delete_all_results_prompt', ['count' => number_format($items->total()), 'title' => AdminUi::moduleTitle($module)]) }}"
                            data-confirm-error="{{ __('panel.common.delete_prompt_error') }}"
                            data-loading-text="{{ __('panel.common.deleting_all_results') }}"
                        >
                            {{ __('panel.common.delete_all_results', ['count' => number_format($items->total())]) }}
                        </button>
                    </div>
                </form>
            @endif
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        @if($canBulkDelete)
                            <th class="selection-cell">
                                <input type="checkbox" data-select-all data-form-id="bulk-delete-form" aria-label="{{ __('panel.common.select_all_page') }}">
                            </th>
                        @endif
                        @foreach($config['list'] as $column)
                            <th>{{ AdminUi::columnLabel($column) }}</th>
                        @endforeach
                        <th>{{ __('panel.common.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            @if($canBulkDelete)
                                <td class="selection-cell">
                                    <input
                                        type="checkbox"
                                        name="selected_ids[]"
                                        value="{{ $item->id }}"
                                        form="bulk-delete-form"
                                        data-row-select
                                        aria-label="{{ __('panel.common.select_record') }}"
                                    >
                                </td>
                            @endif
                            @foreach($config['list'] as $column)
                                @php $value = data_get($item, $column); @endphp
                                <td>
                                    @if($column === 'status')
                                        <span class="status-chip {{ AdminUi::statusBadgeClass($value) }}">{{ AdminUi::statusLabel($value) }}</span>
                                    @elseif($loop->first)
                                        <div class="table-title">
                                            <strong>{{ AdminUi::primaryCell($module, $item, $column) }}</strong>
                                            <small>{{ AdminUi::secondaryCell($module, $item) }}</small>
                                        </div>
                                    @else
                                        {{ AdminUi::formatTableValue($column, $value) }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <div class="row-actions">
                                    <a class="inline-link" href="{{ route('admin.module.show', [$module, $item->id]) }}">{{ __('panel.common.view') }}</a>
                                    @if($config['editable'] ?? true)
                                        <a class="inline-link" href="{{ route('admin.module.edit', [$module, $item->id]) }}">{{ __('panel.common.edit') }}</a>
                                    @endif
                                    @if($canDelete)
                                        <form
                                            method="post"
                                            action="{{ route('admin.module.destroy', [$module, $item->id]) }}"
                                            data-delete-confirm
                                            data-confirm-message="{{ __('panel.common.delete_single_prompt', ['item' => AdminUi::singularTitle($module)]) }}"
                                            data-confirm-error="{{ __('panel.common.delete_prompt_error') }}"
                                            data-loading-text="{{ __('panel.common.deleting_item') }}"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-link danger" type="submit">{{ __('panel.common.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($config['list']) + 1 + ($canBulkDelete ? 1 : 0) }}" class="subtle">{{ __('panel.common.no_records') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($items->hasPages())
                <div class="section-space">
                    {{ $items->links() }}
                </div>
            @endif
        </section>
    </section>
@endsection

@extends('layouts.admin')

@php
    use App\Support\AdminUi;
    use Illuminate\Support\Facades\Schema;

    $statusField = $config['fields']['status'] ?? null;
    $statusOptions = is_array($statusField) ? $statusField : [];
    $canCreate = $config['creatable'] ?? !in_array($module, ['orders', 'chats', 'referral_claims', 'referral_codes', 'devices', 'otp_requests'], true);
    $canDelete = $config['deletable'] ?? !in_array($module, ['orders', 'chats', 'referral_claims', 'referral_codes', 'devices', 'otp_requests'], true);
    $canBulkDelete = $canDelete && $items->count() > 0;
    $canBulkAssignParent = $module === 'categories' && $items->count() > 0 && Schema::hasColumn('categories', 'parent_id');
    $parentId = $parentId ?? 0;
    $parentCategory = $parentCategory ?? null;
    $showAllSubcategories = $showAllSubcategories ?? false;
    $categoryViewCounts = $categoryViewCounts ?? [];
    $isCategoryDrilldown = $module === 'categories' && $parentId > 0 && $parentCategory;
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

        @if($module === 'categories' && $parentId === 0 && !empty($categoryViewCounts))
            <div class="tab-stats">
                <a class="tab-stat {{ !$showAllSubcategories ? 'is-active' : '' }}" href="{{ route('admin.module.index', ['module' => 'categories']) }}">
                    <strong>{{ number_format($categoryViewCounts['main']) }}</strong>
                    <span>{{ __('panel.common.category_main_categories') }}</span>
                </a>
                <a class="tab-stat {{ $showAllSubcategories ? 'is-active' : '' }}" href="{{ route('admin.module.index', ['module' => 'categories', 'view' => 'subcategories']) }}">
                    <strong>{{ number_format($categoryViewCounts['subcategories']) }}</strong>
                    <span>{{ __('panel.common.category_all_subcategories') }}</span>
                </a>
            </div>
        @endif

        <div class="screen-toolbar">
            <form method="get" class="toolbar-filters {{ count($statusOptions) <= 3 ? 'short' : '' }}">
                @if($isCategoryDrilldown)
                    <input type="hidden" name="parent_id" value="{{ $parentId }}">
                @elseif($showAllSubcategories)
                    <input type="hidden" name="view" value="subcategories">
                @endif
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
                    @if($isCategoryDrilldown)
                        <a class="back-link" href="{{ route('admin.module.index', 'categories') }}">&larr; {{ __('panel.common.category_back_to_all') }}</a>
                        <h3>{{ __('panel.common.category_subcategories_of', ['name' => $parentCategory->name]) }}</h3>
                    @elseif($showAllSubcategories)
                        <h3>{{ __('panel.common.category_all_subcategories') }}</h3>
                    @else
                        <h3>{{ $config['title'] }}</h3>
                    @endif
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
                    <input type="hidden" name="parent_id" value="{{ $parentId }}">
                    <input type="hidden" name="view" value="{{ $showAllSubcategories ? 'subcategories' : '' }}">

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
            @if($canBulkAssignParent)
                <form
                    id="bulk-assign-parent-form"
                    method="post"
                    action="{{ route('admin.categories.bulk-assign-parent') }}"
                    class="bulk-actions"
                    data-copy-selection-into="input[data-row-select]"
                    data-select-required-message="{{ __('panel.messages.category_bulk_parent_no_items') }}"
                    data-mirror-selection="input[data-row-select]"
                    data-mirror-selection-template="{{ __('panel.common.selected_count', ['count' => '__count__']) }}"
                    data-loading-text="{{ __('panel.common.assign_parent_loading') }}"
                >
                    @csrf
                    <input type="hidden" name="redirect_parent_id" value="{{ $parentId }}">
                    <input type="hidden" name="redirect_view" value="{{ $showAllSubcategories ? 'subcategories' : '' }}">
                    <div class="bulk-actions__meta">
                        <strong data-mirror-selection-count>{{ __('panel.common.selected_count', ['count' => 0]) }}</strong>
                        <small>{{ __('panel.common.assign_parent_help') }}</small>
                    </div>

                    <div class="bulk-actions__buttons">
                        <div
                            class="async-picker"
                            data-async-picker
                            data-endpoint="{{ route('admin.async.categories') }}"
                            data-search-placeholder="{{ __('panel.async.category_search_placeholder') }}"
                            data-empty-label="{{ __('panel.async.category_empty_label') }}"
                            data-empty-meta="{{ __('panel.async.category_empty_meta') }}"
                            data-loading-text="{{ __('panel.async.loading') }}"
                            data-empty-text="{{ __('panel.async.no_results') }}"
                            data-error-text="{{ __('panel.async.failed') }}"
                            data-scroll-text="{{ __('panel.async.scroll_more') }}"
                        >
                            <input type="hidden" id="bulk_assign_parent_id" name="parent_id" value="">
                            <button
                                type="button"
                                class="async-picker__selected"
                                data-async-picker-selected
                                aria-expanded="false"
                            >
                                <span class="async-picker__selected-copy">
                                    <strong data-async-picker-label>{{ __('panel.async.category_empty_label') }}</strong>
                                    <small data-async-picker-meta>{{ __('panel.async.category_empty_meta') }}</small>
                                </span>
                                <span class="async-picker__toggle" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="none" focusable="false">
                                        <path d="M5 7.5 10 12.5 15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </span>
                            </button>
                            <div class="async-picker__panel" data-async-picker-panel hidden>
                                <div class="async-picker__search-row">
                                    <input type="search" class="async-picker__search" data-async-picker-search placeholder="{{ __('panel.async.category_search_placeholder') }}">
                                    <button type="button" class="ghost-button async-picker__clear" data-async-picker-clear>{{ __('panel.async.clear') }}</button>
                                </div>
                                <div class="async-picker__results" data-async-picker-results></div>
                                <div class="async-picker__status" data-async-picker-status>{{ __('panel.async.search_prompt') }}</div>
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="primary-button"
                            data-mirror-selection-action
                            disabled
                        >
                            {{ __('panel.common.assign_parent_submit') }}
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
                                    @elseif($module === 'categories' && $column === 'subcategory_count')
                                        @if((int) $value > 0)
                                            <a class="inline-link" href="{{ route('admin.module.index', ['module' => 'categories', 'parent_id' => $item->id]) }}" title="{{ __('panel.common.category_view_subcategories') }}">
                                                {{ number_format((int) $value) }}
                                            </a>
                                        @else
                                            {{ AdminUi::formatTableValue($column, $value) }}
                                        @endif
                                    @elseif($module === 'categories' && $column === 'parent_name' && !empty($value) && !empty($item->parent_id))
                                        <a class="inline-link" href="{{ route('admin.module.index', ['module' => 'categories', 'parent_id' => $item->parent_id]) }}">
                                            {{ $value }}
                                        </a>
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

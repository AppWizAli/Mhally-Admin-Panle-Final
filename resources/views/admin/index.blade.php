@extends('layouts.admin')

@php
    use App\Support\AdminUi;

    $statusField = $config['fields']['status'] ?? null;
    $statusOptions = is_array($statusField) ? $statusField : [];
    $canCreate = $config['creatable'] ?? !in_array($module, ['orders', 'chats', 'referral_claims', 'referral_codes', 'devices', 'otp_requests'], true);
    $canDelete = $config['deletable'] ?? !in_array($module, ['orders', 'chats', 'referral_claims', 'referral_codes', 'devices', 'otp_requests'], true);
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
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        @foreach($config['list'] as $column)
                            <th>{{ AdminUi::columnLabel($column) }}</th>
                        @endforeach
                        <th>{{ __('panel.common.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
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
                                        <form method="post" action="{{ route('admin.module.destroy', [$module, $item->id]) }}" onsubmit="return confirm('{{ __('panel.common.delete_confirm', ['item' => AdminUi::singularTitle($module)]) }}');">
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
                            <td colspan="{{ count($config['list']) + 1 }}" class="subtle">{{ __('panel.common.no_records') }}</td>
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

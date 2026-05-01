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
                        <span>{{ ucfirst($key) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="screen-toolbar">
            <form method="get" class="toolbar-filters {{ count($statusOptions) <= 3 ? 'short' : '' }}">
                <div class="search-input">
                    <input type="search" name="search" value="{{ $search }}" placeholder="Search {{ strtolower($config['title']) }}...">
                </div>
                @if($module === 'offers')
                    <input type="text" name="city" value="{{ $city }}" placeholder="City">
                @endif
                @if($statusOptions)
                    <select name="status">
                        <option value="">All Status</option>
                        @foreach($statusOptions as $option)
                            <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                @endif
                <button class="ghost-button" type="submit">{{ $statusOptions || $module === 'offers' ? 'Filters' : 'Search' }}</button>
            </form>
            @if($module === 'products')
                <a class="ghost-button" href="{{ route('admin.products.bulk') }}">Bulk Upload</a>
            @endif
            @if($module === 'catalog_products')
                <a class="ghost-button" href="{{ route('admin.catalog-products.bulk') }}">Bulk Catalog Upload</a>
            @endif
            @if($canCreate)
                <a class="primary-button" href="{{ route('admin.module.create', $module) }}">Add {{ AdminUi::singularTitle($module) }}</a>
            @endif
        </div>

        <section class="panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ $config['title'] }}</h3>
                    <p>{{ number_format($items->total()) }} records found for the selected filters.</p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        @foreach($config['list'] as $column)
                            <th>{{ AdminUi::columnLabel($column) }}</th>
                        @endforeach
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            @foreach($config['list'] as $column)
                                @php $value = data_get($item, $column); @endphp
                                <td>
                                    @if($column === 'status')
                                        <span class="status-chip {{ AdminUi::statusBadgeClass($value) }}">{{ ucfirst((string) $value) }}</span>
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
                                    <a class="inline-link" href="{{ route('admin.module.show', [$module, $item->id]) }}">View</a>
                                    @if($config['editable'] ?? true)
                                        <a class="inline-link" href="{{ route('admin.module.edit', [$module, $item->id]) }}">Edit</a>
                                    @endif
                                    @if($canDelete)
                                        <form method="post" action="{{ route('admin.module.destroy', [$module, $item->id]) }}" onsubmit="return confirm('Delete this {{ strtolower(AdminUi::singularTitle($module)) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-link danger" type="submit">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($config['list']) + 1 }}" class="subtle">No records found.</td>
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

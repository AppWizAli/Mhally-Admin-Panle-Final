@extends('layouts.admin')

@php
    use App\Support\AdminUi;
@endphp

@section('title', __('panel.pages.dashboard.title'))

@section('content')
    <section class="metrics-grid dashboard-stats">
        <article class="summary-card">
            <div class="summary-card__icon blue"></div>
            <div>
                <strong>{{ number_format((int) ($counts['products'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.total_products') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon green"></div>
            <div>
                <strong>{{ number_format((int) ($counts['suppliers'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.active_suppliers') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon amber"></div>
            <div>
                <strong>{{ number_format((int) ($counts['orders'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.total_orders') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon purple"></div>
            <div>
                <strong>{{ AdminUi::money((float) ($counts['sales_total'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.total_marketplace_sales') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon purple"></div>
            <div>
                <strong>{{ AdminUi::money((float) ($counts['revenue'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.cleared_seller_sales') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon amber"></div>
            <div>
                <strong>{{ AdminUi::money((float) ($counts['pending_sales'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.being_cleared_sales') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon green"></div>
            <div>
                <strong>{{ AdminUi::money((float) ($counts['commission'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.cleared_admin_commission') }}</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon amber"></div>
            <div>
                <strong>{{ AdminUi::money((float) ($counts['pending_commission'] ?? 0)) }}</strong>
                <span>{{ __('panel.dashboard.being_cleared_commission') }}</span>
            </div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ __('panel.dashboard.recent_orders_title') }}</h3>
                    <p>{{ __('panel.dashboard.recent_orders_subtitle') }}</p>
                </div>
                <a class="inline-link" href="{{ route('admin.module.index', 'orders') }}">{{ __('panel.common.view_all') }}</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>{{ \App\Support\AdminUi::columnLabel('order_number') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('store_name') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('supplier_name') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('total_amount') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('admin_commission_amount') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('commission_status') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('status') }}</th>
                        <th>{{ \App\Support\AdminUi::columnLabel('order_date') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td><a class="inline-link" href="{{ route('admin.module.show', ['orders', $order->id]) }}">{{ $order->order_number }}</a></td>
                            <td>{{ $order->store_name }}</td>
                            <td>{{ $order->business_name }}</td>
                            <td>{{ AdminUi::money($order->total_amount) }}</td>
                            <td>{{ AdminUi::money($order->admin_commission_amount ?? 0) }}</td>
                            <td>{{ AdminUi::statusLabel($order->commission_status) }}</td>
                            <td><span class="status-chip {{ AdminUi::statusBadgeClass($order->status) }}">{{ AdminUi::statusLabel($order->status) }}</span></td>
                            <td>{{ AdminUi::shortDate($order->order_date) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="subtle">{{ __('panel.dashboard.no_orders') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="panel slim-panel">
            <div class="page-block__header">
                <div>
                    <h3>{{ __('panel.dashboard.low_stock_title') }}</h3>
                    <p>{{ __('panel.dashboard.low_stock_subtitle') }}</p>
                </div>
            </div>
            <div class="alert-list">
                @forelse($lowStock as $item)
                    <div class="alert-row">
                        <div>
                            <strong>{{ $item->product_name }}</strong>
                            <small>{{ $item->business_name ?: __('panel.dashboard.unassigned') }}</small>
                        </div>
                        <div class="alert-row__meta">
                            <span>{{ __('panel.dashboard.stock_label', ['count' => $item->stock_quantity]) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="alert-row">
                        <div>
                            <strong>{{ __('panel.dashboard.no_low_stock') }}</strong>
                            <small>{{ __('panel.dashboard.inventory_healthy') }}</small>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection

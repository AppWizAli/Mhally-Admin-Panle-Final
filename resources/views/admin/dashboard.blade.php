@extends('layouts.admin')

@php
    use App\Support\AdminUi;
@endphp

@section('title', 'Operations Dashboard')

@section('content')
    <section class="metrics-grid dashboard-stats">
        <article class="summary-card">
            <div class="summary-card__icon blue"></div>
            <div>
                <strong>{{ number_format((int) ($counts['products'] ?? 0)) }}</strong>
                <span>Total Products</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon green"></div>
            <div>
                <strong>{{ number_format((int) ($counts['suppliers'] ?? 0)) }}</strong>
                <span>Active Suppliers</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon amber"></div>
            <div>
                <strong>{{ number_format((int) ($counts['orders'] ?? 0)) }}</strong>
                <span>Total Orders</span>
            </div>
        </article>
        <article class="summary-card">
            <div class="summary-card__icon purple"></div>
            <div>
                <strong>{{ AdminUi::money((float) ($counts['revenue'] ?? 0)) }}</strong>
                <span>Revenue</span>
            </div>
        </article>
    </section>

    <section class="dashboard-grid">
        <article class="panel">
            <div class="page-block__header">
                <div>
                    <h3>Recent Orders</h3>
                    <p>Latest marketplace activity from the buyer and supplier flow.</p>
                </div>
                <a class="inline-link" href="{{ route('admin.module.index', 'orders') }}">View All</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Supplier</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentOrders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->store_name }}</td>
                            <td>{{ $order->business_name }}</td>
                            <td>{{ AdminUi::money($order->total_amount) }}</td>
                            <td><span class="status-chip {{ AdminUi::statusBadgeClass($order->status) }}">{{ ucfirst((string) $order->status) }}</span></td>
                            <td>{{ AdminUi::shortDate($order->order_date) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="subtle">No orders yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="panel slim-panel">
            <div class="page-block__header">
                <div>
                    <h3>Low Stock Alert</h3>
                    <p>Products that need supplier or admin attention.</p>
                </div>
            </div>
            <div class="alert-list">
                @forelse($lowStock as $item)
                    <div class="alert-row">
                        <div>
                            <strong>{{ $item->product_name }}</strong>
                            <small>{{ $item->business_name ?: 'Unassigned' }}</small>
                        </div>
                        <div class="alert-row__meta">
                            <span>Stock: {{ $item->stock_quantity }}</span>
                        </div>
                    </div>
                @empty
                    <div class="alert-row">
                        <div>
                            <strong>No low stock products</strong>
                            <small>Inventory looks healthy.</small>
                        </div>
                    </div>
                @endforelse
            </div>
        </article>
    </section>
@endsection

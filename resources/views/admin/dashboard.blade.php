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

    <section class="panel hierarchy-panel">
        <div class="page-block__header">
            <div>
                <h3>{{ __('panel.nav.categories') }} / {{ __('panel.nav.catalog_products') }} / {{ __('panel.nav.products') }}</h3>
                <p>Browse the marketplace structure by category, then inspect the catalog products and their live supplier products.</p>
            </div>
            <div class="hierarchy-summary">
                <span class="count-chip">{{ number_format((int) ($catalogHierarchy['category_total'] ?? 0)) }} categories</span>
                <span class="count-chip">{{ number_format((int) ($catalogHierarchy['catalog_total'] ?? 0)) }} catalogs</span>
            </div>
        </div>

        <div class="hierarchy-list">
            @forelse(($catalogHierarchy['categories'] ?? []) as $category)
                <details class="hierarchy-category" @if($loop->first) open @endif>
                    <summary class="hierarchy-category__summary">
                        <div class="hierarchy-category__title">
                            <strong>{{ $category->name }}</strong>
                            <small>{{ AdminUi::displayValue($category->description) }}</small>
                        </div>
                        <div class="hierarchy-category__meta">
                            <span class="count-chip">{{ number_format((int) ($category->catalog_count ?? 0)) }} catalogs</span>
                            <span class="count-chip">{{ number_format((int) ($category->listing_count ?? 0)) }} products</span>
                            <span class="status-chip {{ AdminUi::statusBadgeClass($category->status) }}">{{ AdminUi::statusLabel($category->status) }}</span>
                        </div>
                    </summary>

                    <div class="hierarchy-category__body">
                        <div class="hierarchy-catalog-grid">
                            @forelse($category->catalogs as $catalog)
                                <article class="hierarchy-catalog">
                                    <div class="hierarchy-catalog__head">
                                        <div>
                                            <strong>{{ $catalog->name }}</strong>
                                            <small>{{ trim(($catalog->packaging ?: '') . ' ' . ($catalog->unit_type ?: '')) }}</small>
                                        </div>
                                        <span class="status-chip {{ AdminUi::statusBadgeClass($catalog->status) }}">{{ AdminUi::statusLabel($catalog->status) }}</span>
                                    </div>

                                    <div class="hierarchy-catalog__meta">
                                        <span>{{ number_format((int) ($catalog->product_count ?? 0)) }} products</span>
                                        <span>{{ AdminUi::displayValue($catalog->category_name ?? $category->name) }}</span>
                                    </div>

                                    <div class="hierarchy-product-list">
                                        @forelse($catalog->products as $product)
                                            <div class="hierarchy-product-row">
                                                <div>
                                                    <strong>{{ $product->supplier_name ?: ($product->supplier_owner_name ?: __('panel.common.not_available')) }}</strong>
                                                    <small>{{ AdminUi::money($product->price) }} &middot; {{ number_format((int) ($product->stock_quantity ?? 0)) }} stock</small>
                                                </div>
                                                <span class="status-chip {{ AdminUi::statusBadgeClass($product->status) }}">{{ AdminUi::statusLabel($product->status) }}</span>
                                            </div>
                                        @empty
                                            <div class="hierarchy-empty">No supplier products are linked to this catalog yet.</div>
                                        @endforelse
                                    </div>

                                    <div class="hierarchy-actions">
                                        <a class="inline-link" href="{{ route('admin.module.index', ['module' => 'catalog_products', 'search' => $category->name]) }}">Open all catalogs</a>
                                        <a class="inline-link" href="{{ route('admin.module.show', ['catalog_products', $catalog->id]) }}">View catalog</a>
                                        <a class="inline-link" href="{{ route('admin.module.index', ['module' => 'products', 'search' => $catalog->name]) }}">View products</a>
                                    </div>
                                </article>
                            @empty
                                <div class="hierarchy-empty">This category currently has no catalog products in the dashboard preview.</div>
                            @endforelse
                        </div>
                    </div>
                </details>
            @empty
                <div class="hierarchy-empty">No categories were found in the marketplace database.</div>
            @endforelse
        </div>
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

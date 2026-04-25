<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class AdminUi
{
    public static function navItems(): array
    {
        return [
            'dashboard' => ['label' => 'Dashboard', 'icon' => 'grid', 'route' => route('admin.dashboard')],
            'catalog_products' => ['label' => 'Catalog', 'icon' => 'layers', 'route' => route('admin.module.index', 'catalog_products')],
            'products' => ['label' => 'Products', 'icon' => 'box', 'route' => route('admin.module.index', 'products')],
            'suppliers' => ['label' => 'Suppliers', 'icon' => 'truck', 'route' => route('admin.module.index', 'suppliers')],
            'buyers' => ['label' => 'Buyers', 'icon' => 'users', 'route' => route('admin.module.index', 'buyers')],
            'offers' => ['label' => 'Offers', 'icon' => 'box', 'route' => route('admin.module.index', 'offers')],
            'notifications' => ['label' => 'Notifications', 'icon' => 'message', 'route' => route('admin.module.index', 'notifications')],
            'referral_claims' => ['label' => 'Referrals', 'icon' => 'users', 'route' => route('admin.module.index', 'referral_claims')],
            'categories' => ['label' => 'Categories', 'icon' => 'layers', 'route' => route('admin.module.index', 'categories')],
            'orders' => ['label' => 'Orders', 'icon' => 'clipboard', 'route' => route('admin.module.index', 'orders')],
            'chats' => ['label' => 'Chats', 'icon' => 'message', 'route' => route('admin.module.index', 'chats')],
            'settings' => ['label' => 'Settings', 'icon' => 'settings', 'route' => route('admin.module.index', 'settings')],
        ];
    }

    public static function pageMeta(?string $module = null, string $action = 'list'): array
    {
        $map = [
            'dashboard' => ['title' => 'Operations Dashboard', 'subtitle' => 'Control buyers, suppliers, products, orders, and app-ready APIs from one responsive panel.'],
            'products' => ['title' => 'Product Listings', 'subtitle' => 'Manage catalog products and supplier-specific listings used by both buyer and supplier flows.'],
            'catalog_products' => ['title' => 'Catalog Products', 'subtitle' => 'Create the master products that supplier listings connect to, such as rice, oil, snacks, or cleaning items.'],
            'suppliers' => ['title' => 'Supplier Network', 'subtitle' => 'Review supplier onboarding, store details, business rules, and operational status.'],
            'buyers' => ['title' => 'Buyer Accounts', 'subtitle' => 'Maintain retailer profiles, contact details, status, and order history access.'],
            'offers' => ['title' => 'Offers & Campaigns', 'subtitle' => 'Create promotions that appear in the buyer app home experience and supplier search flows.'],
            'notifications' => ['title' => 'Buyer Notifications', 'subtitle' => 'Compose announcements for all buyers, specific cities, or individual buyer accounts.'],
            'referral_claims' => ['title' => 'Referral Program', 'subtitle' => 'Configure referral rewards and review which buyers are inviting new stores to the marketplace.'],
            'categories' => ['title' => 'Categories', 'subtitle' => 'Organize the marketplace catalog with icons, sort order, and visibility status.'],
            'orders' => ['title' => 'Orders & Fulfillment', 'subtitle' => 'Track marketplace orders, line items, payment status, and delivery progress.'],
            'chats' => ['title' => 'Messages Monitor', 'subtitle' => 'View buyer-supplier conversation threads and recent message activity.'],
            'settings' => ['title' => 'Settings & Profile', 'subtitle' => 'Update admin profile, system preferences, public app configuration, and API notes.'],
        ];

        $key = $module ?: 'dashboard';
        $meta = $map[$key] ?? ['title' => ucwords(str_replace('_', ' ', $key)), 'subtitle' => 'Manage marketplace records.'];

        if ($key !== 'dashboard' && $action !== 'list') {
            $prefix = ['create' => 'Add ', 'edit' => 'Edit ', 'show' => 'View '][$action] ?? '';
            $meta['title'] = $prefix . self::singularTitle($key);
        }

        return $meta;
    }

    public static function singularTitle(string $module): string
    {
        return match ($module) {
            'products' => 'Product',
            'catalog_products' => 'Catalog Product',
            'suppliers' => 'Supplier',
            'buyers' => 'Buyer',
            'offers' => 'Offer',
            'notifications' => 'Notification',
            'referral_claims', 'referral_codes' => 'Referral',
            'categories' => 'Category',
            'orders' => 'Order',
            'chats' => 'Chat',
            'settings' => 'Setting',
            default => rtrim(ucwords(str_replace('_', ' ', $module)), 's'),
        };
    }

    public static function iconSvg(string $name): string
    {
        $icons = [
            'grid' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zm10 0h6v6h-6zM4 14h6v6H4zm10 0h6v6h-6z"/></svg>',
            'box' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 3 7v10l9 5 9-5V7zm0 2.2 6.72 3.73L12 11.66 5.28 7.93zM5 9.64l6 3.33v6.41l-6-3.33zm14 0v6.41l-6 3.33v-6.41z"/></svg>',
            'truck' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h11v8h2.5l2.2 2.7V18H17a2.5 2.5 0 1 1-5 0H9a2.5 2.5 0 1 1-5 0H3zm13 3v3h3.1L17.4 8z"/></svg>',
            'users' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm-8 1a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.67 0-8 1.34-8 4v2h10v-2c0-1.24.5-2.33 1.33-3.24A13.56 13.56 0 0 0 8 14zm8 0c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>',
            'layers' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 10 5-10 5L2 7zm-8 9 8 4 8-4 2 1-10 5L2 12zm0 5 8 4 8-4 2 1-10 5L2 17z"/></svg>',
            'clipboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 2h6a2 2 0 0 1 2 2h3v18H4V4h3a2 2 0 0 1 2-2zm0 4H6v14h12V6h-3v1H9zm1-2v1h4V4z"/></svg>',
            'message' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H8l-4 3v-3H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg>',
            'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m19.14 12.94.04-.94-.04-.94 2.03-1.58a.49.49 0 0 0 .12-.64l-1.92-3.32a.49.49 0 0 0-.6-.22l-2.39.96a7.14 7.14 0 0 0-1.63-.94L14.5 2.7a.49.49 0 0 0-.49-.4h-4a.49.49 0 0 0-.49.4l-.36 2.62c-.58.22-1.12.53-1.63.94l-2.39-.96a.49.49 0 0 0-.6.22L2.62 8.84a.49.49 0 0 0 .12.64l2.03 1.58-.04.94.04.94-2.03 1.58a.49.49 0 0 0-.12.64l1.92 3.32a.49.49 0 0 0 .6.22l2.39-.96c.5.41 1.05.72 1.63.94l.36 2.62a.49.49 0 0 0 .49.4h4a.49.49 0 0 0 .49-.4l.36-2.62c.58-.22 1.12-.53 1.63-.94l2.39.96a.49.49 0 0 0 .6-.22l1.92-3.32a.49.49 0 0 0-.12-.64zM12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5z"/></svg>',
            'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>',
        ];

        return $icons[$name] ?? $icons['grid'];
    }

    public static function statusBadgeClass(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'active', 'delivered', 'verified', 'approved', 'paid', 'confirmed', 'completed' => 'success',
            'pending', 'processing', 'draft', 'sent' => 'warning',
            'suspended', 'cancelled', 'rejected', 'inactive', 'out_of_stock', 'expired', 'blocked' => 'danger',
            'shipped', 'in_transit' => 'info',
            default => 'neutral',
        };
    }

    public static function money($value, string $currency = 'PKR'): string
    {
        return $currency . ' ' . number_format((float) $value, 2);
    }

    public static function shortDate($value): string
    {
        if (!$value) {
            return 'N/A';
        }
        return Carbon::parse($value)->format('d M Y');
    }

    public static function longDate($value): string
    {
        if (!$value) {
            return 'N/A';
        }
        return Carbon::parse($value)->format('F d, Y');
    }

    public static function displayValue($value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }
        if (is_numeric($value) && strlen((string) $value) > 12) {
            return (string) $value;
        }
        return (string) $value;
    }

    public static function mediaUrl($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '/')) {
            return $value;
        }

        return '/' . ltrim($value, '/');
    }

    public static function isImageReference($value): bool
    {
        $url = self::mediaUrl($value);
        if ($url === '') {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return (bool) preg_match('/\.(png|jpe?g|webp|gif)$/i', (string) $path);
    }

    public static function columnLabel(string $column): string
    {
        return match ($column) {
            'order_number' => 'Order ID',
            'catalog_product_id' => 'Catalog Product',
            'supplier_id' => 'Supplier',
            'supplier_product_id' => 'Supplier Product',
            'category_id' => 'Category',
            'icon' => 'Icon Image',
            'emoji' => 'Emoji Image',
            'image_url' => 'Image',
            'catalog_name', 'product_name' => 'Product',
            'business_name', 'supplier_name' => 'Supplier',
            'store_name', 'buyer_name' => str_contains($column, 'store') ? 'Store' : 'Buyer',
            'item_count' => 'Products',
            'total_amount', 'minimum_order_amount' => 'Amount',
            'order_date' => 'Order Date',
            'delivery_date' => 'Delivery Date',
            'last_message_at' => 'Updated',
            'referrer_store_name' => 'Referrer',
            'referred_store_name' => 'Referred Buyer',
            default => ucwords(str_replace('_', ' ', $column)),
        };
    }

    public static function formatTableValue(string $column, $value): string
    {
        if (str_contains($column, 'amount') || str_contains($column, 'price') || str_contains($column, 'spend') || str_contains($column, 'reward')) {
            return self::money($value);
        }

        if (str_contains($column, '_at') || str_contains($column, '_date')) {
            return self::shortDate($value);
        }

        if (is_bool($value) || in_array((string) $value, ['0', '1'], true) && str_starts_with($column, 'is_')) {
            return (int) $value === 1 ? 'Yes' : 'No';
        }

        if ($column === 'item_count') {
            return number_format((int) $value) . ' items';
        }

        return self::displayValue($value);
    }

    public static function primaryCell(string $module, object $item, string $fallbackColumn): string
    {
        return match ($module) {
            'products' => (string) ($item->catalog_name ?? $item->{$fallbackColumn} ?? ''),
            'suppliers' => (string) ($item->business_name ?? ''),
            'buyers' => (string) ($item->store_name ?? ''),
            'offers' => (string) ($item->title ?? ''),
            'notifications' => (string) ($item->title ?? ''),
            'categories' => trim((self::isImageReference($item->icon ?? null) ? '' : (string) ($item->icon ?? '')) . ' ' . (string) ($item->name ?? '')),
            'orders' => (string) ($item->order_number ?? ''),
            'chats' => (string) ($item->store_name ?? ''),
            'referral_claims' => (string) ($item->referrer_store_name ?? $item->referral_code ?? ''),
            default => self::displayValue($item->{$fallbackColumn} ?? null),
        };
    }

    public static function secondaryCell(string $module, object $item): string
    {
        return match ($module) {
            'products' => trim((string) ($item->packaging ?? '') . ' / ' . (string) ($item->unit_type ?? ''), ' /'),
            'suppliers' => (string) ($item->business_license_number ?? $item->owner_name ?? ''),
            'buyers' => (string) ($item->buyer_name ?? ''),
            'offers' => (string) ($item->description ?: 'No description'),
            'notifications' => (string) ($item->message ?? ''),
            'categories' => (string) ($item->slug ?? ''),
            'orders' => (string) ($item->store_name ?? ''),
            'chats' => (string) ($item->business_name ?? ''),
            'referral_claims' => (string) ($item->referred_store_name ?? ''),
            default => '',
        };
    }
}

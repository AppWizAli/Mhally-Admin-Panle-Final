<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddMarketplacePerformanceIndexes extends Migration
{
    public function up(): void
    {
        $this->addIndex('supplier_products', 'idx_supplier_products_supplier_status_stock', ['supplier_id', 'status', 'stock_quantity']);
        $this->addIndex('supplier_products', 'idx_supplier_products_catalog_supplier', ['catalog_product_id', 'supplier_id']);
        $this->addIndex('catalog_products', 'idx_catalog_products_status_category_name', ['status', 'category_id', 'name']);
        $this->addIndex('orders', 'idx_orders_supplier_date', ['supplier_id', 'order_date']);
        $this->addIndex('orders', 'idx_orders_buyer_date', ['buyer_id', 'order_date']);
        $this->addIndex('chat_threads', 'idx_chat_threads_supplier_last_message', ['supplier_id', 'last_message_at']);
        $this->addIndex('chat_threads', 'idx_chat_threads_buyer_last_message', ['buyer_id', 'last_message_at']);
        $this->addIndex('app_notifications', 'idx_app_notifications_status_target_created', ['status', 'target_type', 'target_value', 'created_at']);
        $this->addIndex('offers', 'idx_offers_active_lookup', ['supplier_id', 'supplier_product_id', 'catalog_product_id', 'status']);
    }

    public function down(): void
    {
        $this->dropIndex('supplier_products', 'idx_supplier_products_supplier_status_stock');
        $this->dropIndex('supplier_products', 'idx_supplier_products_catalog_supplier');
        $this->dropIndex('catalog_products', 'idx_catalog_products_status_category_name');
        $this->dropIndex('orders', 'idx_orders_supplier_date');
        $this->dropIndex('orders', 'idx_orders_buyer_date');
        $this->dropIndex('chat_threads', 'idx_chat_threads_supplier_last_message');
        $this->dropIndex('chat_threads', 'idx_chat_threads_buyer_last_message');
        $this->dropIndex('app_notifications', 'idx_app_notifications_status_target_created');
        $this->dropIndex('offers', 'idx_offers_active_lookup');
    }

    private function addIndex(string $table, string $index, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        $quotedColumns = implode('`, `', $columns);
        DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$quotedColumns}`)");
    }

    private function dropIndex(string $table, string $index): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
    }

    private function indexExists(string $table, string $index): bool
    {
        return !empty(DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index]));
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'ft_catalog_products_search';

    public function up(): void
    {
        if (!Schema::hasTable('catalog_products') || $this->indexExists()) {
            return;
        }

        // A FULLTEXT index turns "search across lacs/millions of catalog
        // products" from a full table scan (what `LIKE '%term%'` requires)
        // into an inverted-index lookup, so it stays fast as the catalog
        // grows. Falls back silently to LIKE search (handled in the
        // controller) if the storage engine doesn't support it.
        try {
            DB::statement(
                'ALTER TABLE `catalog_products` ADD FULLTEXT `' . self::INDEX_NAME . '` (`name`, `packaging`, `unit_type`)'
            );
        } catch (Throwable $exception) {
            Log::warning('Could not create FULLTEXT index on catalog_products; falling back to LIKE search.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('catalog_products') && $this->indexExists()) {
            DB::statement('ALTER TABLE `catalog_products` DROP INDEX `' . self::INDEX_NAME . '`');
        }
    }

    private function indexExists(): bool
    {
        return !empty(DB::select('SHOW INDEX FROM `catalog_products` WHERE Key_name = ?', [self::INDEX_NAME]));
    }
};

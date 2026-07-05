<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->unsignedInteger('parent_id')->nullable()->after('id');
                $table->index('parent_id', 'idx_categories_parent_id');
                $table->foreign('parent_id', 'fk_categories_parent_id')
                    ->references('id')->on('categories')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign('fk_categories_parent_id');
                $table->dropIndex('idx_categories_parent_id');
                $table->dropColumn('parent_id');
            });
        }
    }
};

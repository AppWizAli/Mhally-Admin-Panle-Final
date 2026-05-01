<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'status_reason')) {
                    $table->string('status_reason', 255)->nullable()->after('status');
                }
                if (!Schema::hasColumn('orders', 'admin_commission_percentage')) {
                    $table->decimal('admin_commission_percentage', 5, 2)->default(0)->after('total_amount');
                }
                if (!Schema::hasColumn('orders', 'admin_commission_amount')) {
                    $table->decimal('admin_commission_amount', 12, 2)->default(0)->after('admin_commission_percentage');
                }
                if (!Schema::hasColumn('orders', 'seller_confirmed_at')) {
                    $table->dateTime('seller_confirmed_at')->nullable()->after('delivery_date');
                }
                if (!Schema::hasColumn('orders', 'completed_at')) {
                    $table->dateTime('completed_at')->nullable()->after('seller_confirmed_at');
                }
            });
        }

        $this->setting('default_currency', 'PKR', 'public', 'Default currency shown in buyer and supplier apps');
        $this->setting('admin_commission_percentage', '0', 'system', 'Admin commission percentage per completed order');

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status_reason')) {
            DB::table('orders')
                ->where('status', 'pending')
                ->whereNull('status_reason')
                ->update(['status_reason' => 'Waiting for supplier confirmation.']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['completed_at', 'seller_confirmed_at', 'admin_commission_amount', 'admin_commission_percentage', 'status_reason'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('setting_key', ['default_currency', 'admin_commission_percentage'])->delete();
        }
    }

    private function setting(string $key, string $value, string $group, string $label): void
    {
        if (!Schema::hasTable('settings') || DB::table('settings')->where('setting_key', $key)->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_group' => $group,
            'label' => $label,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

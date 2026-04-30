<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateSupplierDevicesAndFirebaseSetting extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('supplier_devices')) {
            Schema::create('supplier_devices', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('supplier_id');
                $table->string('firebase_token');
                $table->string('platform', 40)->default('android');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique(['supplier_id', 'firebase_token'], 'uniq_supplier_device');
                $table->index('firebase_token', 'idx_supplier_device_token');
            });
        }

        if (Schema::hasTable('settings') && !DB::table('settings')->where('setting_key', 'firebase_server_key')->exists()) {
            DB::table('settings')->insert([
                'setting_key' => 'firebase_server_key',
                'setting_value' => '',
                'setting_group' => 'system',
                'label' => 'Firebase Cloud Messaging server key',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_devices');
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('setting_key', 'firebase_server_key')->delete();
        }
    }
}

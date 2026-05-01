<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class CreateMuhalliMarketplaceTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->increments('id');
                $table->string('full_name', 120);
                $table->string('email', 180)->unique();
                $table->string('phone', 40)->nullable();
                $table->string('location', 120)->nullable();
                $table->string('role', 80)->default('Super Admin');
                $table->text('bio')->nullable();
                $table->string('password_hash');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (!Schema::hasTable('buyers')) {
            Schema::create('buyers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('store_name', 150);
                $table->string('buyer_name', 120);
                $table->string('email', 180)->unique();
                $table->string('phone', 40)->nullable();
                $table->string('city', 120)->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 11, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('password_hash');
                $table->string('preferred_language', 10)->default('en');
                $table->string('status', 30)->default('active');
                $table->date('member_since');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }
        $this->ensureColumn('buyers', 'latitude', fn (Blueprint $table) => $table->decimal('latitude', 11, 8)->nullable()->after('address'));
        $this->ensureColumn('buyers', 'longitude', fn (Blueprint $table) => $table->decimal('longitude', 11, 8)->nullable()->after('latitude'));

        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('business_name', 160);
                $table->string('owner_name', 120);
                $table->string('email', 180)->unique();
                $table->string('phone', 40)->nullable();
                $table->string('city', 120)->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 11, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->string('business_license_number', 120)->nullable();
                $table->string('password_hash');
                $table->integer('minimum_order_quantity')->default(1);
                $table->decimal('minimum_order_amount', 12, 2)->default(0);
                $table->string('delivery_time', 80)->nullable();
                $table->string('payment_terms', 80)->nullable();
                $table->text('description')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('status', 30)->default('pending');
                $table->boolean('is_verified')->default(false);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }
        $this->ensureColumn('suppliers', 'latitude', fn (Blueprint $table) => $table->decimal('latitude', 11, 8)->nullable()->after('address'));
        $this->ensureColumn('suppliers', 'longitude', fn (Blueprint $table) => $table->decimal('longitude', 11, 8)->nullable()->after('latitude'));

        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('icon', 32)->nullable();
                $table->text('description')->nullable();
                $table->string('accent_color', 20)->default('#2f6bff');
                $table->integer('sort_order')->default(0);
                $table->string('status', 30)->default('active');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (!Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('category_id');
                $table->string('name', 180);
                $table->string('slug', 190)->unique();
                $table->string('emoji', 16)->nullable();
                $table->text('description')->nullable();
                $table->string('packaging', 150)->nullable();
                $table->string('unit_type', 80)->nullable();
                $table->string('image_url')->nullable();
                $table->string('status', 30)->default('active');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('catalog_product_id');
                $table->unsignedInteger('supplier_id')->nullable();
                $table->string('sku', 90);
                $table->decimal('price', 12, 2)->default(0);
                $table->integer('stock_quantity')->default(0);
                $table->integer('min_order_qty')->default(1);
                $table->decimal('min_order_amount', 12, 2)->default(0);
                $table->string('delivery_time', 80)->nullable();
                $table->string('status', 30)->default('active');
                $table->boolean('is_featured')->default(false);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->foreign('catalog_product_id')->references('id')->on('catalog_products')->cascadeOnDelete();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->increments('id');
                $table->string('order_number', 60)->unique();
                $table->unsignedInteger('buyer_id');
                $table->unsignedInteger('supplier_id');
                $table->string('status', 30)->default('pending');
                $table->string('status_reason', 255)->nullable();
                $table->string('payment_status', 30)->default('pending');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('delivery_fee', 12, 2)->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->decimal('admin_commission_percentage', 5, 2)->default(0);
                $table->decimal('admin_commission_amount', 12, 2)->default(0);
                $table->text('notes')->nullable();
                $table->date('order_date');
                $table->date('delivery_date')->nullable();
                $table->dateTime('seller_confirmed_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->foreign('buyer_id')->references('id')->on('buyers')->cascadeOnDelete();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('order_id');
                $table->unsignedInteger('supplier_product_id')->nullable();
                $table->string('product_name', 180);
                $table->string('unit_label', 80)->nullable();
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('line_total', 12, 2)->default(0);
                $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
                $table->foreign('supplier_product_id')->references('id')->on('supplier_products')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('chat_threads')) {
            Schema::create('chat_threads', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('buyer_id');
                $table->unsignedInteger('supplier_id');
                $table->string('subject', 180)->nullable();
                $table->text('last_message')->nullable();
                $table->dateTime('last_message_at');
                $table->integer('buyer_unread_count')->default(0);
                $table->integer('supplier_unread_count')->default(0);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->foreign('buyer_id')->references('id')->on('buyers')->cascadeOnDelete();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('thread_id');
                $table->string('sender_type', 30);
                $table->string('sender_name', 120);
                $table->text('message_body');
                $table->string('message_type', 30)->default('text');
                $table->dateTime('created_at');
                $table->foreign('thread_id')->references('id')->on('chat_threads')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('setting_key', 120)->unique();
                $table->text('setting_value')->nullable();
                $table->string('setting_group', 40)->default('system');
                $table->string('label', 160);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
            });
        }

        if (!Schema::hasTable('api_tokens')) {
            Schema::create('api_tokens', function (Blueprint $table) {
                $table->increments('id');
                $table->string('user_type', 30);
                $table->unsignedInteger('user_id');
                $table->string('token', 128)->unique();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('created_at');
            });
        }

        if (!Schema::hasTable('otp_requests')) {
            Schema::create('otp_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->string('user_role', 30);
                $table->string('purpose', 30);
                $table->string('phone', 40);
                $table->string('channel', 30)->default('sms');
                $table->string('provider', 60)->default('demo');
                $table->string('code_hash');
                $table->longText('payload_json')->nullable();
                $table->longText('delivery_response')->nullable();
                $table->string('status', 30)->default('pending');
                $table->dateTime('expires_at');
                $table->dateTime('verified_at')->nullable();
                $table->dateTime('consumed_at')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['user_role', 'purpose', 'phone', 'status'], 'idx_otp_lookup');
                $table->index('expires_at', 'idx_otp_expiry');
            });
        }

        if (!Schema::hasTable('offers')) {
            Schema::create('offers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 190);
                $table->text('description')->nullable();
                $table->string('badge_label', 80)->nullable();
                $table->string('discount_label', 80)->nullable();
                $table->string('image_url')->nullable();
                $table->unsignedInteger('supplier_id')->nullable();
                $table->unsignedInteger('supplier_product_id')->nullable();
                $table->unsignedInteger('catalog_product_id')->nullable();
                $table->decimal('offer_price', 10, 2)->nullable();
                $table->unsignedInteger('maximum_quantity')->nullable();
                $table->string('city', 120)->nullable();
                $table->string('status', 30)->default('active');
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index('status', 'idx_offer_status');
                $table->index('city', 'idx_offer_city');
            });
        }
        $this->ensureColumn('offers', 'supplier_product_id', fn (Blueprint $table) => $table->unsignedInteger('supplier_product_id')->nullable()->after('supplier_id'));
        $this->ensureColumn('offers', 'offer_price', fn (Blueprint $table) => $table->decimal('offer_price', 10, 2)->nullable()->after('catalog_product_id'));
        $this->ensureColumn('offers', 'maximum_quantity', fn (Blueprint $table) => $table->unsignedInteger('maximum_quantity')->nullable()->after('offer_price'));

        if (!Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title', 190);
                $table->text('message');
                $table->string('target_type', 30)->default('all');
                $table->string('target_value', 120)->nullable();
                $table->string('link_type', 40)->nullable();
                $table->string('link_value', 190)->nullable();
                $table->string('status', 30)->default('active');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->index(['target_type', 'target_value'], 'idx_notification_target');
                $table->index('status', 'idx_notification_status');
            });
        }

        if (!Schema::hasTable('buyer_devices')) {
            Schema::create('buyer_devices', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('buyer_id');
                $table->string('firebase_token');
                $table->string('platform', 40)->default('android');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique(['buyer_id', 'firebase_token'], 'uniq_buyer_device');
                $table->index('firebase_token', 'idx_device_token');
            });
        }

        if (!Schema::hasTable('buyer_referral_codes')) {
            Schema::create('buyer_referral_codes', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('buyer_id');
                $table->string('referral_code', 40);
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique('buyer_id', 'uniq_buyer_referral_buyer');
                $table->unique('referral_code', 'uniq_buyer_referral_code');
            });
        }

        if (!Schema::hasTable('buyer_referral_claims')) {
            Schema::create('buyer_referral_claims', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('referrer_buyer_id');
                $table->unsignedInteger('referred_buyer_id');
                $table->string('referral_code', 40);
                $table->string('used_by_phone', 40)->nullable();
                $table->decimal('reward_amount', 10, 2)->default(0);
                $table->decimal('referee_reward_amount', 10, 2)->default(0);
                $table->string('status', 30)->default('completed');
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->unique('referred_buyer_id', 'uniq_referred_buyer');
                $table->index('referral_code', 'idx_referral_code');
                $table->index('referrer_buyer_id', 'idx_referrer_buyer');
            });
        }

        $this->seedDefaults();
    }

    public function down(): void
    {
        foreach ([
            'buyer_referral_claims',
            'buyer_referral_codes',
            'buyer_devices',
            'app_notifications',
            'offers',
            'otp_requests',
            'api_tokens',
            'settings',
            'chat_messages',
            'chat_threads',
            'order_items',
            'orders',
            'supplier_products',
            'catalog_products',
            'categories',
            'suppliers',
            'buyers',
            'admins',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function ensureColumn(string $table, string $column, callable $definition): void
    {
        if (Schema::hasTable($table) && !Schema::hasColumn($table, $column)) {
            Schema::table($table, $definition);
        }
    }

    private function seedDefaults(): void
    {
        if (Schema::hasTable('admins') && DB::table('admins')->count() === 0) {
            DB::table('admins')->insert([
                'full_name' => 'Admin User',
                'email' => 'admin@muhalli.test',
                'phone' => '+92 300 5550100',
                'location' => 'Karachi, Pakistan',
                'role' => 'Super Admin',
                'bio' => 'Controls buyer, supplier, order, and marketplace operations.',
                'password_hash' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $settings = [
            'support_whatsapp' => ['+92 300 7000000', 'public', 'Support WhatsApp number'],
            'support_whatsapp_message' => ['Hello Muhalli support, I need help with my buyer account.', 'public', 'Support WhatsApp default message'],
            'map_default_city' => ['Karachi', 'public', 'Default map city'],
            'default_currency' => ['PKR', 'public', 'Default currency shown in buyer and supplier apps'],
            'referral_enabled' => ['1', 'public', 'Referral program enabled'],
            'referral_reward_amount' => ['20', 'public', 'Referrer reward amount'],
            'referral_referee_reward_amount' => ['10', 'public', 'Referred buyer reward amount'],
            'otp_provider' => ['brqsms', 'system', 'OTP provider'],
            'otp_api_url' => ['https://dash.brqsms.com/api/http/sms/send', 'system', 'OTP API URL'],
            'otp_api_token' => ['', 'system', 'OTP API token'],
            'otp_delivery_channel' => ['sms', 'system', 'OTP delivery channel'],
            'otp_sender_id' => ['Muhalli', 'system', 'OTP sender ID'],
            'otp_expiry_minutes' => ['10', 'system', 'OTP expiry minutes'],
            'otp_message_template' => ['Your Muhalli verification code is {{CODE}}. It expires in {{MINUTES}} minutes.', 'system', 'OTP message template'],
            'admin_commission_percentage' => ['0', 'system', 'Admin commission percentage per completed order'],
        ];

        foreach ($settings as $key => [$value, $group, $label]) {
            if (DB::table('settings')->where('setting_key', $key)->exists()) {
                continue;
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
    }
}

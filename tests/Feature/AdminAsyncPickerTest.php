<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminAsyncPickerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTables();
        $this->seedBaseRecords();
    }

    public function test_async_routes_require_admin_session(): void
    {
        $this->get(route('admin.async.catalog-products'))
            ->assertRedirect(route('login'));
    }

    public function test_async_catalog_endpoint_returns_paginated_search_results(): void
    {
        DB::table('catalog_products')->insert([
            [
                'id' => 2,
                'category_id' => 1,
                'name' => 'Pepsi 1.5L',
                'slug' => 'pepsi-15l',
                'packaging' => '1.5L bottle',
                'unit_type' => 'Bottle',
                'status' => 'active',
            ],
            [
                'id' => 3,
                'category_id' => 1,
                'name' => 'Pepsi 330ml',
                'slug' => 'pepsi-330ml',
                'packaging' => '330ml can',
                'unit_type' => 'Can',
                'status' => 'active',
            ],
        ]);

        $response = $this->withSession($this->adminSession())
            ->getJson(route('admin.async.catalog-products', [
                'search' => 'Pepsi',
                'page' => 1,
                'limit' => 1,
            ]));

        $response
            ->assertOk()
            ->assertJsonPath('pagination.page', 1)
            ->assertJsonPath('pagination.limit', 1)
            ->assertJsonPath('pagination.has_more', true)
            ->assertJsonCount(1, 'items');
    }

    public function test_async_supplier_endpoint_returns_matching_results(): void
    {
        DB::table('suppliers')->insert([
            'id' => 2,
            'business_name' => 'Fresh Trade',
            'owner_name' => 'Ahmed',
            'email' => 'fresh@example.com',
            'phone' => '03001234567',
            'city' => 'Karachi',
            'status' => 'active',
        ]);

        $response = $this->withSession($this->adminSession())
            ->getJson(route('admin.async.suppliers', ['search' => 'Fresh']));

        $response
            ->assertOk()
            ->assertJsonPath('items.0.label', 'Fresh Trade')
            ->assertJsonPath('pagination.page', 1);
    }

    public function test_product_edit_form_renders_async_selected_values(): void
    {
        DB::table('catalog_products')->insert([
            'id' => 2,
            'category_id' => 1,
            'name' => 'Pepsi 1.5L',
            'slug' => 'pepsi-15l',
            'packaging' => '1.5L bottle',
            'unit_type' => 'Bottle',
            'status' => 'active',
        ]);

        DB::table('suppliers')->insert([
            'id' => 2,
            'business_name' => 'Fresh Trade',
            'owner_name' => 'Ahmed',
            'email' => 'fresh@example.com',
            'phone' => '03001234567',
            'city' => 'Karachi',
            'status' => 'active',
        ]);

        DB::table('supplier_products')->insert([
            'id' => 10,
            'catalog_product_id' => 2,
            'supplier_id' => 2,
            'sku' => 'SKU-PEPSI',
            'price' => 120,
            'stock_quantity' => 10,
            'min_order_qty' => 1,
            'min_order_amount' => 0,
            'delivery_time' => '24 hours',
            'status' => 'active',
            'is_featured' => 0,
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.module.edit', ['module' => 'products', 'id' => 10]));

        $response
            ->assertOk()
            ->assertSee('data-async-picker')
            ->assertSee('Pepsi 1.5L')
            ->assertSee('Fresh Trade');
    }

    public function test_offer_edit_form_renders_async_selected_values(): void
    {
        DB::table('catalog_products')->insert([
            'id' => 2,
            'category_id' => 1,
            'name' => 'Pepsi 1.5L',
            'slug' => 'pepsi-15l',
            'packaging' => '1.5L bottle',
            'unit_type' => 'Bottle',
            'status' => 'active',
        ]);

        DB::table('suppliers')->insert([
            'id' => 2,
            'business_name' => 'Fresh Trade',
            'owner_name' => 'Ahmed',
            'email' => 'fresh@example.com',
            'phone' => '03001234567',
            'city' => 'Karachi',
            'status' => 'active',
        ]);

        DB::table('supplier_products')->insert([
            'id' => 10,
            'catalog_product_id' => 2,
            'supplier_id' => 2,
            'sku' => 'SKU-PEPSI',
            'price' => 120,
            'stock_quantity' => 10,
            'min_order_qty' => 1,
            'min_order_amount' => 0,
            'delivery_time' => '24 hours',
            'status' => 'active',
            'is_featured' => 0,
        ]);

        DB::table('offers')->insert([
            'id' => 11,
            'title' => 'Pepsi Deal',
            'description' => 'Offer',
            'supplier_id' => 2,
            'supplier_product_id' => 10,
            'catalog_product_id' => 2,
            'offer_price' => 100,
            'maximum_quantity' => 5,
            'city' => 'Karachi',
            'status' => 'active',
        ]);

        $response = $this->withSession($this->adminSession())
            ->get(route('admin.module.edit', ['module' => 'offers', 'id' => 11]));

        $response
            ->assertOk()
            ->assertSee('data-async-picker')
            ->assertSee('Pepsi Deal')
            ->assertSee('Fresh Trade');
    }

    public function test_product_store_accepts_valid_ids_and_rejects_invalid_ids(): void
    {
        DB::table('catalog_products')->insert([
            'id' => 2,
            'category_id' => 1,
            'name' => 'Pepsi 1.5L',
            'slug' => 'pepsi-15l',
            'packaging' => '1.5L bottle',
            'unit_type' => 'Bottle',
            'status' => 'active',
        ]);

        DB::table('suppliers')->insert([
            'id' => 2,
            'business_name' => 'Fresh Trade',
            'owner_name' => 'Ahmed',
            'email' => 'fresh@example.com',
            'phone' => '03001234567',
            'city' => 'Karachi',
            'status' => 'active',
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.module.store', 'products'), [
                'catalog_product_id' => 2,
                'supplier_id' => 2,
                'sku' => 'SKU-OK',
                'price' => 120,
                'stock_quantity' => 10,
                'min_order_qty' => 1,
                'min_order_amount' => 0,
                'delivery_time' => '24 hours',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.module.index', 'products'));

        $this->assertDatabaseHas('supplier_products', [
            'catalog_product_id' => 2,
            'supplier_id' => 2,
            'sku' => 'SKU-OK',
        ]);

        $this->withSession($this->adminSession())
            ->from(route('admin.module.create', 'products'))
            ->post(route('admin.module.store', 'products'), [
                'catalog_product_id' => 9999,
                'supplier_id' => 9999,
                'sku' => 'SKU-BAD',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.module.create', 'products'))
            ->assertSessionHasErrors(['catalog_product_id', 'supplier_id']);
    }

    public function test_offer_store_accepts_valid_ids_and_rejects_invalid_ids(): void
    {
        DB::table('catalog_products')->insert([
            'id' => 2,
            'category_id' => 1,
            'name' => 'Pepsi 1.5L',
            'slug' => 'pepsi-15l',
            'packaging' => '1.5L bottle',
            'unit_type' => 'Bottle',
            'status' => 'active',
        ]);

        DB::table('suppliers')->insert([
            'id' => 2,
            'business_name' => 'Fresh Trade',
            'owner_name' => 'Ahmed',
            'email' => 'fresh@example.com',
            'phone' => '03001234567',
            'city' => 'Karachi',
            'status' => 'active',
        ]);

        DB::table('supplier_products')->insert([
            'id' => 10,
            'catalog_product_id' => 2,
            'supplier_id' => 2,
            'sku' => 'SKU-PEPSI',
            'price' => 120,
            'stock_quantity' => 10,
            'min_order_qty' => 1,
            'min_order_amount' => 0,
            'delivery_time' => '24 hours',
            'status' => 'active',
            'is_featured' => 0,
        ]);

        $this->withSession($this->adminSession())
            ->post(route('admin.module.store', 'offers'), [
                'title' => 'Pepsi Deal',
                'supplier_id' => 2,
                'supplier_product_id' => 10,
                'catalog_product_id' => 2,
                'offer_price' => 100,
                'maximum_quantity' => 5,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.module.index', 'offers'));

        $this->assertDatabaseHas('offers', [
            'title' => 'Pepsi Deal',
            'supplier_id' => 2,
            'supplier_product_id' => 10,
            'catalog_product_id' => 2,
        ]);

        $this->withSession($this->adminSession())
            ->from(route('admin.module.create', 'offers'))
            ->post(route('admin.module.store', 'offers'), [
                'title' => 'Invalid Deal',
                'supplier_id' => 9999,
                'supplier_product_id' => 9999,
                'catalog_product_id' => 9999,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.module.create', 'offers'))
            ->assertSessionHasErrors(['supplier_id', 'supplier_product_id', 'catalog_product_id']);
    }

    private function adminSession(): array
    {
        return [
            'admin_user' => [
                'id' => 1,
                'full_name' => 'Admin User',
                'email' => 'admin@example.com',
                'role' => 'Super Admin',
            ],
        ];
    }

    private function createTables(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('password_hash');
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('packaging')->nullable();
            $table->string('unit_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('owner_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->integer('minimum_order_quantity')->nullable();
            $table->decimal('minimum_order_amount', 10, 2)->nullable();
            $table->string('delivery_time')->nullable();
            $table->string('status')->nullable();
            $table->integer('is_verified')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('catalog_product_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('sku')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('stock_quantity')->nullable();
            $table->integer('min_order_qty')->nullable();
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->string('delivery_time')->nullable();
            $table->string('status')->nullable();
            $table->integer('is_featured')->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('badge_label')->nullable();
            $table->string('discount_label')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('supplier_product_id')->nullable();
            $table->unsignedBigInteger('catalog_product_id')->nullable();
            $table->decimal('offer_price', 10, 2)->nullable();
            $table->integer('maximum_quantity')->nullable();
            $table->string('city')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    private function seedBaseRecords(): void
    {
        DB::table('admins')->insert([
            'id' => 1,
            'full_name' => 'Admin User',
            'email' => 'admin@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'Super Admin',
        ]);

        DB::table('categories')->insert([
            'id' => 1,
            'name' => 'Beverages',
            'slug' => 'beverages',
            'status' => 'active',
        ]);
    }
}

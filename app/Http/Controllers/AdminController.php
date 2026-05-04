<?php

namespace App\Http\Controllers;

use App\Support\AdminUi;
use App\Support\CatalogProductBulkImporter;
use App\Support\ProductBulkImporter;
use App\Support\PushNotifications;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class AdminController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $admin = DB::table('admins')->where('email', $credentials['email'])->first();
        if (!$admin || !Hash::check($credentials['password'], $admin->password_hash)) {
            return back()->withErrors(['email' => __('panel.login.invalid_credentials')])->withInput();
        }

        $request->session()->put('admin_user', [
            'id' => $admin->id,
            'full_name' => $admin->full_name,
            'email' => $admin->email,
            'role' => $admin->role,
        ]);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('admin_user');
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function dashboard()
    {
        $openOrderStatuses = ['pending', 'processing', 'shipped'];
        $hasCommissionAmount = Schema::hasColumn('orders', 'admin_commission_amount');

        $counts = [
            'buyers' => $this->count('buyers', 'active'),
            'suppliers' => $this->count('suppliers', 'active'),
            'products' => $this->count('supplier_products', 'active'),
            'orders' => DB::table('orders')->count(),
            'sales_total' => (float) DB::table('orders')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount'),
            'revenue' => (float) DB::table('orders')
                ->where('status', 'delivered')
                ->sum('total_amount'),
            'pending_sales' => (float) DB::table('orders')
                ->whereIn('status', $openOrderStatuses)
                ->sum('total_amount'),
            'commission' => $hasCommissionAmount
                ? (float) DB::table('orders')->where('status', 'delivered')->sum('admin_commission_amount')
                : 0.0,
            'pending_commission' => $hasCommissionAmount
                ? (float) DB::table('orders')->whereIn('status', $openOrderStatuses)->sum('admin_commission_amount')
                : 0.0,
            'commission_total' => $hasCommissionAmount
                ? (float) DB::table('orders')->where('status', '!=', 'cancelled')->sum('admin_commission_amount')
                : 0.0,
            'pending_suppliers' => $this->count('suppliers', 'pending'),
        ];

        $recentOrderColumns = [
            'o.id',
            'o.order_number',
            'o.status',
            'o.total_amount',
            'o.order_date',
            'b.store_name',
            's.business_name',
        ];
        if (Schema::hasColumn('orders', 'status_reason')) {
            $recentOrderColumns[] = 'o.status_reason';
        }
        if (Schema::hasColumn('orders', 'admin_commission_amount')) {
            $recentOrderColumns[] = 'o.admin_commission_amount';
        }
        if (Schema::hasColumn('orders', 'admin_commission_percentage')) {
            $recentOrderColumns[] = 'o.admin_commission_percentage';
        }

        $recentOrders = DB::table('orders as o')
            ->join('buyers as b', 'b.id', '=', 'o.buyer_id')
            ->join('suppliers as s', 's.id', '=', 'o.supplier_id')
            ->select($recentOrderColumns)
            ->selectRaw('CASE WHEN o.status = "delivered" THEN "Cleared" WHEN o.status = "cancelled" THEN "Cancelled" ELSE "Being cleared" END AS commission_status')
            ->orderByDesc('o.order_date')
            ->orderByDesc('o.id')
            ->limit(6)
            ->get();

        $lowStock = DB::table('supplier_products as sp')
            ->join('catalog_products as cp', 'cp.id', '=', 'sp.catalog_product_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->select([
                'sp.id',
                'cp.name as product_name',
                'sp.stock_quantity',
                'sp.min_order_qty',
                's.business_name',
            ])
            ->where('sp.stock_quantity', '<=', 10)
            ->orderBy('sp.stock_quantity')
            ->orderBy('cp.name')
            ->limit(6)
            ->get();

        return view('admin.dashboard', compact('counts', 'recentOrders', 'lowStock'));
    }

    public function index(string $module, Request $request)
    {
        $config = $this->module($module);
        if ($module === 'settings') {
            return $this->settingsIndex($config);
        }
        if ($module === 'referral_claims') {
            return $this->referralIndex($config);
        }

        [$orderColumn, $direction] = $config['order'];
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $city = trim((string) $request->query('city', ''));

        $query = $this->moduleIndexQuery($module, $search, $status, $city);
        $items = $query->orderBy($orderColumn, $direction)->paginate(20)->withQueryString();
        $summaryCards = $module === 'orders' ? $this->orderSummaryCards() : [];

        return view('admin.index', compact('module', 'config', 'items', 'search', 'status', 'city', 'summaryCards'));
    }

    public function show(string $module, int $id)
    {
        $config = $this->module($module);
        $item = $this->findItem($module, $id);
        abort_if(!$item, 404);

        if ($module === 'chats') {
            $messages = DB::table('chat_messages')
                ->where('thread_id', $id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            return view('admin.chat-show', compact('module', 'config', 'item', 'messages'));
        }

        $subtitle = $this->showSubtitle($module, $item);
        $related = $this->relatedBlocks($module, $item);

        return view('admin.show', compact('module', 'config', 'item', 'subtitle', 'related'));
    }

    public function create(string $module)
    {
        $config = $this->module($module);
        $item = null;
        $fieldOptions = $this->fieldOptions($module);
        $requiredFields = $this->requiredFieldMap($this->validationRules($module, $config));

        return view('admin.form', compact('module', 'config', 'item', 'fieldOptions', 'requiredFields'));
    }

    public function edit(string $module, int $id)
    {
        $config = $this->module($module);
        $item = DB::table($config['table'])->where('id', $id)->first();
        abort_if(!$item, 404);
        $fieldOptions = $this->fieldOptions($module);
        $requiredFields = $this->requiredFieldMap($this->validationRules($module, $config, $id));

        return view('admin.form', compact('module', 'config', 'item', 'fieldOptions', 'requiredFields'));
    }

    public function store(string $module, Request $request)
    {
        $config = $this->module($module);
        $this->validateModuleRequest($module, $config, $request);

        $data = $this->payload($module, $config, $request);
        $data = $this->applyModuleDefaults($module, $config['table'], $data);
        if (Schema::hasColumn($config['table'], 'created_at')) {
            $data['created_at'] = now();
        }
        if (Schema::hasColumn($config['table'], 'updated_at')) {
            $data['updated_at'] = now();
        }

        try {
            $insertedId = DB::table($config['table'])->insertGetId($data);
        } catch (Throwable $e) {
            return $this->saveFailed($module, $config, $data, $e);
        }

        if ($module === 'notifications' && ($data['status'] ?? '') === 'active') {
            PushNotifications::dispatchAppNotification((int) $insertedId);
        }

        return redirect()->route('admin.module.index', $module)->with('status', __('panel.messages.created', ['title' => AdminUi::moduleTitle($module)]));
    }

    public function update(string $module, int $id, Request $request)
    {
        $config = $this->module($module);
        $item = DB::table($config['table'])->where('id', $id)->first();
        abort_if(!$item, 404);
        $this->validateModuleRequest($module, $config, $request, $id);

        $data = $this->payload($module, $config, $request, $item);
        if (Schema::hasColumn($config['table'], 'updated_at')) {
            $data['updated_at'] = now();
        }

        try {
            DB::table($config['table'])->where('id', $id)->update($data);
        } catch (Throwable $e) {
            return $this->saveFailed($module, $config, $data, $e);
        }

        if ($module === 'notifications' && ($data['status'] ?? '') === 'active') {
            PushNotifications::dispatchAppNotification($id);
        }

        return redirect()->route('admin.module.index', $module)->with('status', __('panel.messages.updated', ['title' => AdminUi::moduleTitle($module)]));
    }

    public function bulkProductsForm()
    {
        $config = $this->module('products');
        $suppliers = DB::table('suppliers')
            ->orderBy('business_name')
            ->pluck('business_name', 'id')
            ->all();
        $summary = session('bulk_summary');

        return view('admin.product-bulk', compact('config', 'suppliers', 'summary'));
    }

    public function bulkProductsUpload(Request $request)
    {
        $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
            'inventory_file' => ['required', 'file', 'max:10240'],
        ], [
            'supplier_id.required' => 'Select the supplier this inventory belongs to.',
            'inventory_file.required' => 'Choose a CSV or XLSX inventory file.',
        ]);

        $file = $request->file('inventory_file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            return back()->withInput()->withErrors(['inventory_file' => 'Upload a CSV or XLSX file.']);
        }

        try {
            $summary = ProductBulkImporter::importFile($file->getRealPath(), (int) $request->input('supplier_id'));
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['inventory_file' => $exception->getMessage()]);
        }

        $message = $summary['error_count'] > 0
            ? 'Bulk upload finished with row errors. Review the report below.'
            : 'Bulk upload completed and products are active.';

        return redirect()
            ->route('admin.products.bulk')
            ->with('status', $message)
            ->with('bulk_summary', $summary);
    }

    public function bulkProductsTemplate()
    {
        $lines = [
            ProductBulkImporter::templateHeaders(),
            ['Pepsi 1.5L', 'Beverages', '120', '100', '2', '50'],
        ];
        $csv = '';
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(fn ($value) => '"' . str_replace('"', '""', $value) . '"', $line)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="muhalli-product-upload-template.csv"',
        ]);
    }

    public function bulkCatalogProductsForm()
    {
        $config = $this->module('catalog_products');
        $summary = session('bulk_summary');

        return view('admin.catalog-product-bulk', compact('config', 'summary'));
    }

    public function bulkCatalogProductsUpload(Request $request)
    {
        $request->validate([
            'catalog_file' => ['required', 'file', 'max:10240'],
        ], [
            'catalog_file.required' => 'Choose a CSV or XLSX catalog file.',
        ]);

        $file = $request->file('catalog_file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            return back()->withInput()->withErrors(['catalog_file' => 'Upload a CSV or XLSX file.']);
        }

        try {
            $summary = CatalogProductBulkImporter::importFile($file->getRealPath());
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['catalog_file' => $exception->getMessage()]);
        }

        $message = $summary['error_count'] > 0
            ? 'Catalog bulk upload finished with row errors. Review the report below.'
            : 'Catalog bulk upload completed and products are active.';

        return redirect()
            ->route('admin.catalog-products.bulk')
            ->with('status', $message)
            ->with('bulk_summary', $summary);
    }

    public function bulkCatalogProductsTemplate()
    {
        $lines = [
            CatalogProductBulkImporter::templateHeaders(),
            ['Pepsi 1.5L', 'Beverages', '', 'Carbonated drink bottle', '1.5L bottle', 'unit', '', 'active'],
        ];
        $csv = '';
        foreach ($lines as $line) {
            $csv .= implode(',', array_map(fn ($value) => '"' . str_replace('"', '""', $value) . '"', $line)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="muhalli-catalog-product-template.csv"',
        ]);
    }

    public function destroy(string $module, int $id)
    {
        $config = $this->module($module);
        DB::table($config['table'])->where('id', $id)->delete();

        return redirect()->route('admin.module.index', $module)->with('status', __('panel.messages.deleted', ['title' => $config['title']]));
    }

    public function saveProfile(Request $request)
    {
        $user = (array) $request->session()->get('admin_user', []);
        abort_if(empty($user['id']), 403);

        $payload = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'location' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string'],
        ]);

        $payload['updated_at'] = now();

        DB::table('admins')->where('id', $user['id'])->update($payload);

        $request->session()->put('admin_user', [
            'id' => $user['id'],
            'full_name' => $payload['full_name'],
            'email' => $payload['email'],
            'role' => $payload['role'] ?: ($user['role'] ?? 'Super Admin'),
        ]);

        return back()->with('status', __('panel.messages.profile_updated'));
    }

    public function savePassword(Request $request)
    {
        $user = (array) $request->session()->get('admin_user', []);
        abort_if(empty($user['id']), 403);

        $payload = $request->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'string', 'min:6'],
            'confirm_password' => ['required', 'same:new_password'],
        ]);

        $admin = DB::table('admins')->where('id', $user['id'])->first();
        if (!$admin || !Hash::check($payload['current_password'], $admin->password_hash)) {
            return back()->withErrors(['current_password' => __('panel.messages.current_password_incorrect')]);
        }

        DB::table('admins')->where('id', $user['id'])->update([
            'password_hash' => Hash::make($payload['new_password']),
            'updated_at' => now(),
        ]);

        return back()->with('status', __('panel.messages.password_updated'));
    }

    public function saveSettings(Request $request)
    {
        foreach ($request->except(['_token']) as $key => $value) {
            if (!str_starts_with((string) $key, 'setting_')) {
                continue;
            }

            $settingKey = substr((string) $key, 8);
            DB::table('settings')
                ->where('setting_key', $settingKey)
                ->update([
                    'setting_value' => is_string($value) ? trim($value) : $value,
                    'updated_at' => now(),
                ]);
        }

        return back()->with('status', __('panel.messages.settings_saved'));
    }

    public function saveCommissionSettings(Request $request)
    {
        $payload = $request->validate([
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_apply_scope' => ['required', Rule::in(['new_only', 'all_orders'])],
        ]);

        $percentage = round((float) $payload['commission_percentage'], 2);
        $scope = (string) $payload['commission_apply_scope'];

        $storedValue = rtrim(rtrim(number_format($percentage, 2, '.', ''), '0'), '.');
        $existingCommissionSetting = DB::table('settings')
            ->where('setting_key', 'admin_commission_percentage')
            ->exists();

        if ($existingCommissionSetting) {
            DB::table('settings')
                ->where('setting_key', 'admin_commission_percentage')
                ->update([
                    'setting_value' => $storedValue,
                    'setting_group' => 'system',
                    'label' => 'Admin commission percentage per completed order',
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('settings')->insert([
                'setting_key' => 'admin_commission_percentage',
                'setting_value' => $storedValue,
                'setting_group' => 'system',
                'label' => 'Admin commission percentage per completed order',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (
            $scope === 'all_orders'
            && Schema::hasTable('orders')
            && Schema::hasColumn('orders', 'admin_commission_percentage')
            && Schema::hasColumn('orders', 'admin_commission_amount')
        ) {
            $rate = number_format($percentage / 100, 6, '.', '');
            DB::table('orders')->update([
                'admin_commission_percentage' => $percentage,
                'admin_commission_amount' => DB::raw('ROUND(COALESCE(total_amount, 0) * ' . $rate . ', 2)'),
                'updated_at' => now(),
            ]);
        }

        $message = $scope === 'all_orders'
            ? __('panel.messages.commission_saved_all')
            : __('panel.messages.commission_saved_new');

        return back()->with('status', $message);
    }

    public function sendChatMessage(int $id, Request $request)
    {
        $thread = DB::table('chat_threads')->where('id', $id)->first();
        abort_if(!$thread, 404);

        $message = trim((string) $request->input('message_body', ''));
        if ($message === '') {
            return back()->withErrors(['message_body' => __('panel.messages.message_required')]);
        }

        $adminUser = (array) $request->session()->get('admin_user', []);

        DB::table('chat_messages')->insert([
            'thread_id' => $id,
            'sender_type' => 'admin',
            'sender_name' => $adminUser['full_name'] ?? 'Admin',
            'message_body' => $message,
            'message_type' => 'text',
            'created_at' => now(),
        ]);

        DB::table('chat_threads')->where('id', $id)->update([
            'last_message' => $message,
            'last_message_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.module.show', ['module' => 'chats', 'id' => $id])->with('status', __('panel.messages.message_sent'));
    }

    private function payload(string $module, array $config, Request $request, ?object $existingItem = null): array
    {
        $data = [];
        $table = $config['table'];

        foreach ($config['fields'] as $field => $type) {
            if (!Schema::hasColumn($table, $field)) {
                continue;
            }

            if ($type === 'checkbox') {
                $data[$field] = $request->boolean($field) ? 1 : 0;
                continue;
            }

            if ($type === 'file') {
                $data[$field] = $this->storeUploadedImage($request, $module, $field, data_get($existingItem, $field));
                continue;
            }

            if ($type === 'number') {
                $value = $request->input($field);
                $data[$field] = $value === null || $value === '' ? null : $value;
                continue;
            }

            if ($type === 'datetime') {
                $value = $request->input($field);
                $data[$field] = $value ? str_replace('T', ' ', $value) . (strlen($value) === 16 ? ':00' : '') : null;
                continue;
            }

            $value = $request->input($field);
            $data[$field] = is_string($value) ? trim($value) : $value;
        }

        if (isset($data['name']) && Schema::hasColumn($table, 'slug') && empty($data['slug'])) {
            $data['slug'] = $this->uniqueSlug($table, Str::slug($data['name']));
        }

        return $data;
    }

    private function validateModuleRequest(string $module, array $config, Request $request, ?int $id = null): void
    {
        $request->validate(
            $this->validationRules($module, $config, $id),
            $this->validationMessages(),
            $this->validationAttributes($config)
        );
    }

    private function validationRules(string $module, array $config, ?int $id = null): array
    {
        $table = $config['table'];
        $rules = [];

        $add = function (string $field, array $fieldRules) use (&$rules, $table): void {
            if (Schema::hasColumn($table, $field)) {
                $rules[$field] = $fieldRules;
            }
        };

        match ($module) {
            'buyers' => [
                $add('store_name', ['required', 'string', 'max:150']),
                $add('buyer_name', ['required', 'string', 'max:120']),
                $add('email', ['required', 'email', 'max:180', Rule::unique($table, 'email')->ignore($id)]),
                $add('phone', ['nullable', 'string', 'max:40']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['active', 'inactive', 'blocked'])]),
            ],
            'suppliers' => [
                $add('business_name', ['required', 'string', 'max:160']),
                $add('owner_name', ['required', 'string', 'max:120']),
                $add('email', ['required', 'email', 'max:180', Rule::unique($table, 'email')->ignore($id)]),
                $add('phone', ['nullable', 'string', 'max:40']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['pending', 'active', 'suspended'])]),
            ],
            'products' => [
                $add('catalog_product_id', ['required', 'integer', Rule::exists('catalog_products', 'id')]),
                $add('supplier_id', ['nullable', 'integer', Rule::exists('suppliers', 'id')]),
                $add('price', ['nullable', 'numeric', 'min:0']),
                $add('stock_quantity', ['nullable', 'integer', 'min:0']),
                $add('min_order_qty', ['nullable', 'integer', 'min:1']),
                $add('min_order_amount', ['nullable', 'numeric', 'min:0']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['active', 'draft', 'archived'])]),
            ],
            'offers' => [
                $add('title', ['required', 'string', 'max:190']),
                $add('image_url', ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']),
                $add('supplier_id', ['nullable', 'integer', Rule::exists('suppliers', 'id')]),
                $add('supplier_product_id', ['nullable', 'integer', Rule::exists('supplier_products', 'id')]),
                $add('catalog_product_id', ['nullable', 'integer', Rule::exists('catalog_products', 'id')]),
                $add('offer_price', ['nullable', 'numeric', 'min:0']),
                $add('maximum_quantity', ['nullable', 'integer', 'min:1']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['active', 'draft', 'expired'])]),
            ],
            'catalog_products' => [
                $add('category_id', ['required', 'integer', Rule::exists('categories', 'id')]),
                $add('name', ['required', 'string', 'max:180']),
                $add('emoji', ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']),
                $add('image_url', ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['active', 'draft', 'archived'])]),
            ],
            'categories' => [
                $add('name', ['required', 'string', 'max:120']),
                $add('icon', ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['active', 'draft', 'archived'])]),
            ],
            'notifications' => [
                $add('title', ['required', 'string', 'max:190']),
                $add('message', ['required', 'string']),
                $add('status', ['required', Rule::in($config['fields']['status'] ?? ['active', 'draft', 'archived'])]),
            ],
            default => null,
        };

        return $rules;
    }

    private function requiredFieldMap(array $rules): array
    {
        $requiredFields = [];

        foreach ($rules as $field => $fieldRules) {
            if (in_array('required', $fieldRules, true)) {
                $requiredFields[$field] = true;
            }
        }

        return $requiredFields;
    }

    private function validationMessages(): array
    {
        return [
            'catalog_product_id.required' => 'Select a catalog product before saving.',
            'catalog_product_id.exists' => 'The selected catalog product does not exist.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'supplier_product_id.exists' => 'The selected supplier product does not exist.',
            'category_id.required' => 'Select a category before saving.',
            'category_id.exists' => 'The selected category does not exist.',
            'email.unique' => 'This email already exists.',
            'icon.image' => 'Choose a valid icon image file.',
            'icon.mimes' => 'The icon image must be a JPG, PNG, or WEBP file.',
            'emoji.image' => 'Choose a valid emoji image file.',
            'emoji.mimes' => 'The emoji image must be a JPG, PNG, or WEBP file.',
            'image_url.image' => 'Choose a valid image file.',
            'image_url.mimes' => 'The image must be a JPG, PNG, or WEBP file.',
            'image_url.max' => 'The image must be 4 MB or smaller.',
        ];
    }

    private function validationAttributes(array $config): array
    {
        $attributes = [];

        foreach (array_keys($config['fields']) as $field) {
            $attributes[$field] = strtolower(AdminUi::columnLabel($field));
        }

        return $attributes;
    }

    private function applyModuleDefaults(string $module, string $table, array $data): array
    {
        if ($module === 'buyers') {
            $this->setDefault($table, $data, 'password_hash', fn () => Hash::make(Str::random(32)));
            $this->setDefault($table, $data, 'member_since', fn () => now()->toDateString());
            $this->setDefault($table, $data, 'preferred_language', 'en');
            $this->setDefault($table, $data, 'status', 'active');
        }

        if ($module === 'suppliers') {
            $this->setDefault($table, $data, 'password_hash', fn () => Hash::make(Str::random(32)));
            $this->setDefault($table, $data, 'minimum_order_quantity', 1);
            $this->setDefault($table, $data, 'minimum_order_amount', 0);
            $this->setDefault($table, $data, 'status', 'pending');
            $this->setDefault($table, $data, 'is_verified', 0);
        }

        if ($module === 'products') {
            $this->setDefault($table, $data, 'sku', fn () => 'SKU-' . strtoupper(Str::random(8)));
            $this->setDefault($table, $data, 'price', 0);
            $this->setDefault($table, $data, 'stock_quantity', 0);
            $this->setDefault($table, $data, 'min_order_qty', 1);
            $this->setDefault($table, $data, 'min_order_amount', 0);
            $this->setDefault($table, $data, 'status', 'active');
            $this->setDefault($table, $data, 'is_featured', 0);
        }

        if ($module === 'offers') {
            $this->setDefault($table, $data, 'status', 'active');
        }

        if ($module === 'categories') {
            $this->setDefault($table, $data, 'accent_color', '#2f6bff');
            $this->setDefault($table, $data, 'sort_order', 0);
            $this->setDefault($table, $data, 'status', 'active');
        }

        if ($module === 'catalog_products') {
            $this->setDefault($table, $data, 'status', 'active');
        }

        return $data;
    }

    private function setDefault(string $table, array &$data, string $field, $value): void
    {
        if (!Schema::hasColumn($table, $field)) {
            return;
        }

        if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
            return;
        }

        $data[$field] = is_callable($value) ? $value() : $value;
    }

    private function uniqueSlug(string $table, string $slug): string
    {
        $baseSlug = $slug ?: Str::random(8);
        $candidate = $baseSlug;
        $counter = 2;

        while (DB::table($table)->where('slug', $candidate)->exists()) {
            $candidate = $baseSlug . '-' . $counter++;
        }

        return $candidate;
    }

    private function storeUploadedImage(Request $request, string $module, string $field, $existingValue = null): ?string
    {
        if (!$request->hasFile($field)) {
            return $existingValue ? (string) $existingValue : null;
        }

        $file = $request->file($field);
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $existingValue ? (string) $existingValue : null;
        }

        [$directory, $maxPathLength] = $this->uploadTarget($module, $field);
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $extension = match ($extension) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };

        $path = $this->uniqueUploadPath($directory, $extension, $maxPathLength);
        $absoluteDirectory = public_path(trim($directory, '/'));
        if (!is_dir($absoluteDirectory)) {
            mkdir($absoluteDirectory, 0775, true);
        }

        $file->move($absoluteDirectory, basename($path));

        return $path;
    }

    private function uploadTarget(string $module, string $field): array
    {
        return match ([$module, $field]) {
            ['categories', 'icon'] => ['u/i', 32],
            ['catalog_products', 'emoji'] => ['u/e', 16],
            ['catalog_products', 'image_url'] => ['u/p', 255],
            ['offers', 'image_url'] => ['u/o', 255],
            default => ['u/m', 255],
        };
    }

    private function uniqueUploadPath(string $directory, string $extension, int $maxPathLength): string
    {
        $prefix = '/' . trim($directory, '/') . '/';
        $maxBaseLength = $maxPathLength - strlen($prefix) - strlen($extension) - 1;
        $baseLength = min(16, max(4, $maxBaseLength));

        do {
            $filename = Str::lower(Str::random($baseLength)) . '.' . $extension;
            $path = $prefix . $filename;

            if (strlen($path) > $maxPathLength) {
                $baseLength--;

                if ($baseLength < 4) {
                    throw new \RuntimeException('Unable to create a valid upload path for ' . $directory . '.');
                }
            }
        } while (strlen($path) > $maxPathLength || file_exists(public_path(ltrim($path, '/'))));

        return $path;
    }

    private function saveFailed(string $module, array $config, array $data, Throwable $e)
    {
        $errorId = (string) Str::uuid();

        Log::error('Admin record save failed', [
            'error_id' => $errorId,
            'module' => $module,
            'table' => $config['table'],
            'data_keys' => array_keys($data),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return back()
            ->withInput()
            ->withErrors(['save' => $this->saveErrorMessage($config['title'], $e, $errorId)]);
    }

    private function saveErrorMessage(string $title, Throwable $e, string $errorId): string
    {
        $message = $e instanceof QueryException ? $e->getMessage() : '';

        if (str_contains($message, 'Duplicate entry')) {
            return $title . ' could not be saved because one value already exists. Error ID: ' . $errorId;
        }

        if (str_contains($message, 'foreign key constraint') || str_contains($message, 'Cannot add or update a child row')) {
            return $title . ' could not be saved because a selected related record was not found. Error ID: ' . $errorId;
        }

        return $title . ' could not be saved because of a server/database issue. Error ID: ' . $errorId;
    }

    private function fieldOptions(string $module): array
    {
        $options = [];

        if (in_array($module, ['catalog_products'], true) && Schema::hasTable('categories')) {
            $options['category_id'] = DB::table('categories')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        if (in_array($module, ['products', 'offers'], true) && Schema::hasTable('catalog_products')) {
            $options['catalog_product_id'] = DB::table('catalog_products')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        if (in_array($module, ['products', 'offers'], true) && Schema::hasTable('suppliers')) {
            $options['supplier_id'] = DB::table('suppliers')
                ->orderBy('business_name')
                ->pluck('business_name', 'id')
                ->all();
        }

        if ($module === 'offers' && Schema::hasTable('supplier_products')) {
            $options['supplier_product_id'] = DB::table('supplier_products as sp')
                ->leftJoin('catalog_products as cp', 'cp.id', '=', 'sp.catalog_product_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->select('sp.id', 'cp.name as product_name', 's.business_name', 'sp.price')
                ->orderBy('cp.name')
                ->get()
                ->mapWithKeys(function ($product) {
                    $label = trim(($product->product_name ?: 'Product #' . $product->id) . ' - ' . ($product->business_name ?: 'No supplier'), ' -');

                    return [$product->id => $label . ' (' . AdminUi::money((float) $product->price) . ')'];
                })
                ->all();
        }

        return $options;
    }

    private function settingsIndex(array $config)
    {
        $adminId = (int) data_get(session('admin_user', []), 'id', 0);
        $admin = $adminId > 0 ? DB::table('admins')->where('id', $adminId)->first() : null;
        $publicSettings = DB::table('settings')->where('setting_group', 'public')->orderBy('id')->get();
        $systemSettings = DB::table('settings')
            ->where('setting_group', 'system')
            ->where('setting_key', '!=', 'admin_commission_percentage')
            ->orderBy('id')
            ->get();
        $commissionPercentage = (string) (DB::table('settings')
            ->where('setting_key', 'admin_commission_percentage')
            ->value('setting_value') ?? '0');
        $multilineSettingKeys = [
            'buyer_min_order_message',
            'supplier_onboarding_message',
            'support_whatsapp_message',
            'marketplace_notice',
            'otp_message_template',
        ];
        $booleanSettingKeys = ['referral_enabled'];

        return view('admin.settings', compact('config', 'admin', 'publicSettings', 'systemSettings', 'multilineSettingKeys', 'booleanSettingKeys', 'commissionPercentage'));
    }

    private function referralIndex(array $config)
    {
        $settings = DB::table('settings')
            ->where('setting_group', 'public')
            ->where('setting_key', 'like', 'referral_%')
            ->orderBy('id')
            ->get();

        $codes = DB::table('buyer_referral_codes as rc')
            ->join('buyers as b', 'b.id', '=', 'rc.buyer_id')
            ->select([
                'rc.referral_code',
                'b.store_name',
                'b.buyer_name',
                'b.city',
                'rc.updated_at',
            ])
            ->orderByDesc('rc.updated_at')
            ->limit(100)
            ->get();

        $claims = DB::table('buyer_referral_claims as c')
            ->join('buyers as rb', 'rb.id', '=', 'c.referrer_buyer_id')
            ->join('buyers as nb', 'nb.id', '=', 'c.referred_buyer_id')
            ->select([
                'c.*',
                'rb.store_name as referrer_store_name',
                'nb.store_name as referred_store_name',
            ])
            ->orderByDesc('c.created_at')
            ->limit(100)
            ->get();

        return view('admin.referrals', compact('config', 'settings', 'codes', 'claims'));
    }

    private function module(string $module): array
    {
        $modules = config('muhalli.modules');
        abort_unless(isset($modules[$module]), 404);

        $config = $modules[$module];
        $config['title'] = AdminUi::moduleTitle($module);
        if (isset($config['form_help'])) {
            $config['form_help'] = AdminUi::moduleFormHelp($module, (string) $config['form_help']);
        }

        return $config;
    }

    private function count(string $table, ?string $status = null): int
    {
        $query = DB::table($table);
        if ($status !== null && Schema::hasColumn($table, 'status')) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    private function moduleIndexQuery(string $module, string $search = '', string $status = '', string $city = '')
    {
        return match ($module) {
            'categories' => $this->categoryQuery($search, $status),
            'suppliers' => $this->supplierQuery($search, $status, $city),
            'buyers' => $this->buyerQuery($search, $status),
            'products' => $this->productQuery($search, $status, $city),
            'offers' => $this->offerQuery($search, $status, $city),
            'notifications' => $this->notificationQuery($search, $status),
            'orders' => $this->orderQuery($search, $status),
            'referral_claims' => $this->referralClaimQuery($search, $status),
            'referral_codes' => $this->referralCodeQuery($search),
            'chats' => $this->chatQuery($search),
            'catalog_products' => $this->catalogQuery($search, $status),
            'devices' => $this->deviceQuery($search),
            'otp_requests' => $this->otpRequestQuery($search, $status),
            'settings' => $this->settingQuery($search),
            default => $this->genericQuery($module, $search, $status),
        };
    }

    private function categoryQuery(string $search = '', string $status = '')
    {
        $query = DB::table('categories as c')
            ->select('c.*')
            ->selectSub(
                DB::table('catalog_products as cp')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('cp.category_id', 'c.id'),
                'catalog_count'
            )
            ->selectSub(
                DB::table('supplier_products as sp')
                    ->join('catalog_products as cp2', 'cp2.id', '=', 'sp.catalog_product_id')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('cp2.category_id', 'c.id'),
                'listing_count'
            );

        $this->applySearch($query, ['c.name', 'c.description', 'c.slug'], $search);
        if ($status !== '') {
            $query->where('c.status', $status);
        }

        return $query;
    }

    private function supplierQuery(string $search = '', string $status = '', string $city = '')
    {
        $query = DB::table('suppliers as s')
            ->select('s.*')
            ->selectSub(
                DB::table('supplier_products as sp')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('sp.supplier_id', 's.id'),
                'product_count'
            )
            ->selectSub(
                DB::table('orders as o')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('o.supplier_id', 's.id'),
                'order_count'
            )
            ->selectSub(
                DB::table('orders as o')
                    ->selectRaw('COALESCE(SUM(CASE WHEN o.status = "delivered" THEN o.total_amount ELSE 0 END), 0)')
                    ->whereColumn('o.supplier_id', 's.id'),
                'revenue_total'
            )
            ->selectSub(
                DB::table('supplier_products as sp')
                    ->selectRaw('MIN(sp.price)')
                    ->whereColumn('sp.supplier_id', 's.id')
                    ->where('sp.status', 'active'),
                'lowest_price'
            );

        $this->applySearch($query, ['s.business_name', 's.owner_name', 's.email', 's.phone', 's.city'], $search);
        if ($status !== '') {
            $query->where('s.status', $status);
        }
        if ($city !== '') {
            $query->where('s.city', $city);
        }

        return $query;
    }

    private function buyerQuery(string $search = '', string $status = '')
    {
        $query = DB::table('buyers as b')
            ->select('b.*')
            ->selectSub(
                DB::table('orders as o')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('o.buyer_id', 'b.id'),
                'order_count'
            )
            ->selectSub(
                DB::table('orders as o')
                    ->selectRaw('COALESCE(SUM(CASE WHEN o.status != "cancelled" THEN o.total_amount ELSE 0 END), 0)')
                    ->whereColumn('o.buyer_id', 'b.id'),
                'spend_total'
            );

        $this->applySearch($query, ['b.store_name', 'b.buyer_name', 'b.email', 'b.phone', 'b.city'], $search);
        if ($status !== '') {
            $query->where('b.status', $status);
        }

        return $query;
    }

    private function productQuery(string $search = '', string $status = '', string $city = '')
    {
        $query = DB::table('supplier_products as sp')
            ->join('catalog_products as cp', 'cp.id', '=', 'sp.catalog_product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'cp.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->select([
                'sp.*',
                'cp.id as catalog_id',
                'cp.name as catalog_name',
                'cp.slug',
                'cp.emoji',
                'cp.description',
                'cp.packaging',
                'cp.unit_type',
                'cp.image_url',
                'c.name as category_name',
                's.business_name as supplier_name',
                's.city as supplier_city',
                's.minimum_order_amount as supplier_minimum_order_amount',
                's.minimum_order_quantity as supplier_minimum_order_quantity',
            ]);

        $this->applySearch($query, ['cp.name', 'cp.packaging', 'cp.unit_type', 'c.name', 's.business_name', 's.city', 'sp.sku'], $search);
        if ($status !== '') {
            $query->where('sp.status', $status);
        }
        if ($city !== '') {
            $query->where('s.city', $city);
        }

        return $query;
    }

    private function offerQuery(string $search = '', string $status = '', string $city = '')
    {
        $query = DB::table('offers as o')
            ->leftJoin('suppliers as s', 's.id', '=', 'o.supplier_id')
            ->leftJoin('catalog_products as cp', 'cp.id', '=', 'o.catalog_product_id')
            ->select([
                'o.*',
                's.business_name as supplier_name',
                'cp.name as product_name',
            ]);

        $this->applySearch($query, ['o.title', 'o.description', 's.business_name', 'cp.name'], $search);
        if ($status !== '') {
            $query->where('o.status', $status);
        }
        if ($city !== '') {
            $query->where('o.city', $city);
        }

        return $query;
    }

    private function notificationQuery(string $search = '', string $status = '')
    {
        $query = DB::table('app_notifications as n')->select('n.*');
        $this->applySearch($query, ['n.title', 'n.message', 'n.target_value'], $search);
        if ($status !== '') {
            $query->where('n.status', $status);
        }

        return $query;
    }

    private function orderQuery(string $search = '', string $status = '')
    {
        $query = DB::table('orders as o')
            ->join('buyers as b', 'b.id', '=', 'o.buyer_id')
            ->join('suppliers as s', 's.id', '=', 'o.supplier_id')
            ->select([
                'o.*',
                'b.store_name',
                'b.buyer_name',
                's.business_name',
            ])
            ->selectSub(
                DB::table('order_items as oi')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('oi.order_id', 'o.id'),
                'item_count'
            )
            ->selectRaw('CASE WHEN o.status = "delivered" THEN "Cleared" WHEN o.status = "cancelled" THEN "Cancelled" ELSE "Being cleared" END AS commission_status');

        $this->applySearch($query, ['o.order_number', 'b.store_name', 's.business_name'], $search);
        if ($status !== '') {
            $query->where('o.status', $status);
        }

        return $query;
    }

    private function referralClaimQuery(string $search = '', string $status = '')
    {
        $query = DB::table('buyer_referral_claims as c')
            ->join('buyers as rb', 'rb.id', '=', 'c.referrer_buyer_id')
            ->join('buyers as nb', 'nb.id', '=', 'c.referred_buyer_id')
            ->select([
                'c.*',
                'rb.store_name as referrer_store_name',
                'nb.store_name as referred_store_name',
            ]);

        $this->applySearch($query, ['c.referral_code', 'c.used_by_phone', 'rb.store_name', 'nb.store_name'], $search);
        if ($status !== '') {
            $query->where('c.status', $status);
        }

        return $query;
    }

    private function referralCodeQuery(string $search = '')
    {
        $query = DB::table('buyer_referral_codes as rc')
            ->join('buyers as b', 'b.id', '=', 'rc.buyer_id')
            ->select([
                'rc.*',
                'b.store_name',
                'b.buyer_name',
                'b.city',
            ]);

        $this->applySearch($query, ['rc.referral_code', 'b.store_name', 'b.buyer_name', 'b.city'], $search);

        return $query;
    }

    private function chatQuery(string $search = '')
    {
        $query = DB::table('chat_threads as t')
            ->join('buyers as b', 'b.id', '=', 't.buyer_id')
            ->join('suppliers as s', 's.id', '=', 't.supplier_id')
            ->select([
                't.*',
                'b.store_name',
                'b.buyer_name',
                's.business_name',
                's.owner_name',
            ])
            ->selectSub(
                DB::table('chat_messages as m')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('m.thread_id', 't.id'),
                'message_count'
            );

        $this->applySearch($query, ['b.store_name', 's.business_name', 't.subject', 't.last_message'], $search);

        return $query;
    }

    private function catalogQuery(string $search = '', string $status = '')
    {
        $query = DB::table('catalog_products as cp')
            ->leftJoin('categories as c', 'c.id', '=', 'cp.category_id')
            ->select([
                'cp.*',
                'c.name as category_name',
            ]);

        $this->applySearch($query, ['cp.name', 'cp.packaging', 'cp.unit_type', 'c.name'], $search);
        if ($status !== '') {
            $query->where('cp.status', $status);
        }

        return $query;
    }

    private function deviceQuery(string $search = '')
    {
        $query = DB::table('buyer_devices as d')
            ->leftJoin('buyers as b', 'b.id', '=', 'd.buyer_id')
            ->select([
                'd.*',
                'b.store_name',
            ]);

        $this->applySearch($query, ['d.firebase_token', 'd.platform', 'b.store_name'], $search);

        return $query;
    }

    private function otpRequestQuery(string $search = '', string $status = '')
    {
        $query = DB::table('otp_requests as o')->select('o.*');
        $this->applySearch($query, ['o.user_role', 'o.purpose', 'o.phone', 'o.provider'], $search);
        if ($status !== '') {
            $query->where('o.status', $status);
        }

        return $query;
    }

    private function settingQuery(string $search = '')
    {
        $query = DB::table('settings as s')->select('s.*');
        $this->applySearch($query, ['s.setting_key', 's.setting_value', 's.setting_group', 's.label'], $search);

        return $query;
    }

    private function genericQuery(string $module, string $search = '', string $status = '')
    {
        $config = $this->module($module);
        $query = DB::table($config['table']);

        if ($search !== '') {
            $this->applySearch($query, $config['list'], $search);
        }
        if ($status !== '' && Schema::hasColumn($config['table'], 'status')) {
            $query->where('status', $status);
        }

        return $query;
    }

    private function findItem(string $module, int $id): ?object
    {
        return match ($module) {
            'categories' => $this->categoryQuery()->where('c.id', $id)->first(),
            'suppliers' => $this->supplierQuery()->where('s.id', $id)->first(),
            'buyers' => $this->buyerQuery()->where('b.id', $id)->first(),
            'products' => $this->productQuery()->where('sp.id', $id)->first(),
            'offers' => $this->offerQuery()->where('o.id', $id)->first(),
            'notifications' => $this->notificationQuery()->where('n.id', $id)->first(),
            'orders' => DB::table('orders as o')
                ->join('buyers as b', 'b.id', '=', 'o.buyer_id')
                ->join('suppliers as s', 's.id', '=', 'o.supplier_id')
                ->select([
                    'o.*',
                    'b.store_name',
                    'b.buyer_name',
                    'b.phone as buyer_phone',
                    'b.city as buyer_city',
                    's.business_name',
                    's.owner_name',
                    's.phone as supplier_phone',
                ])
                ->where('o.id', $id)
                ->first(),
            'referral_claims' => $this->referralClaimQuery()->where('c.id', $id)->first(),
            'referral_codes' => $this->referralCodeQuery()->where('rc.id', $id)->first(),
            'chats' => DB::table('chat_threads as t')
                ->join('buyers as b', 'b.id', '=', 't.buyer_id')
                ->join('suppliers as s', 's.id', '=', 't.supplier_id')
                ->select([
                    't.*',
                    'b.store_name',
                    'b.buyer_name',
                    's.business_name',
                    's.owner_name',
                ])
                ->where('t.id', $id)
                ->first(),
            'catalog_products' => $this->catalogQuery()->where('cp.id', $id)->first(),
            'devices' => $this->deviceQuery()->where('d.id', $id)->first(),
            'otp_requests' => $this->otpRequestQuery()->where('o.id', $id)->first(),
            'settings' => $this->settingQuery()->where('s.id', $id)->first(),
            default => DB::table($this->module($module)['table'])->where('id', $id)->first(),
        };
    }

    private function relatedBlocks(string $module, object $item): array
    {
        $blocks = [];

        if ($module === 'suppliers') {
            $hasCommissionAmount = Schema::hasColumn('orders', 'admin_commission_amount');
            $hasStatusReason = Schema::hasColumn('orders', 'status_reason');
            $clearedCommissionSql = $hasCommissionAmount
                ? 'COALESCE(SUM(CASE WHEN status = "delivered" THEN admin_commission_amount ELSE 0 END), 0)'
                : '0';
            $pendingCommissionSql = $hasCommissionAmount
                ? 'COALESCE(SUM(CASE WHEN status IN ("pending", "processing", "shipped") THEN admin_commission_amount ELSE 0 END), 0)'
                : '0';

            $activity = DB::table('orders')
                ->selectRaw('COUNT(*) AS total_orders')
                ->selectRaw('COALESCE(SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END), 0) AS delivered_orders')
                ->selectRaw('COALESCE(SUM(CASE WHEN status IN ("pending", "processing", "shipped") THEN 1 ELSE 0 END), 0) AS open_orders')
                ->selectRaw('COALESCE(SUM(CASE WHEN status != "cancelled" THEN total_amount ELSE 0 END), 0) AS total_sales')
                ->selectRaw('COALESCE(SUM(CASE WHEN status = "delivered" THEN total_amount ELSE 0 END), 0) AS delivered_sales')
                ->selectRaw('COALESCE(SUM(CASE WHEN status IN ("pending", "processing", "shipped") THEN total_amount ELSE 0 END), 0) AS pending_sales')
                ->selectRaw($clearedCommissionSql . ' AS cleared_commission')
                ->selectRaw($pendingCommissionSql . ' AS pending_commission')
                ->where('supplier_id', $item->id)
                ->first();

            $blocks[] = [
                'title' => 'Seller Activity Summary',
                'subtitle' => 'Completed sales are cleared; pending, processing, and shipped orders are being cleared.',
                'columns' => ['total_orders', 'delivered_orders', 'open_orders', 'total_sales', 'delivered_sales', 'pending_sales', 'cleared_commission', 'pending_commission'],
                'rows' => collect([$activity]),
            ];

            $orderColumns = [
                'o.order_number',
                'b.store_name',
                'b.buyer_name',
                'o.order_date',
                'o.total_amount',
                'o.status',
            ];
            if (Schema::hasColumn('orders', 'admin_commission_percentage')) {
                $orderColumns[] = 'o.admin_commission_percentage';
            }
            if ($hasCommissionAmount) {
                $orderColumns[] = 'o.admin_commission_amount';
            }
            if ($hasStatusReason) {
                $orderColumns[] = 'o.status_reason';
            }
            $orders = DB::table('orders as o')
                ->join('buyers as b', 'b.id', '=', 'o.buyer_id')
                ->select($orderColumns)
                ->selectSub(
                    DB::table('order_items as oi')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('oi.order_id', 'o.id'),
                    'item_count'
                )
                ->where('o.supplier_id', $item->id)
                ->orderByDesc('o.order_date')
                ->orderByDesc('o.id')
                ->limit(25)
                ->get();

            $orderBlockColumns = ['order_number', 'store_name', 'buyer_name', 'order_date', 'item_count', 'total_amount'];
            if (Schema::hasColumn('orders', 'admin_commission_percentage')) {
                $orderBlockColumns[] = 'admin_commission_percentage';
            }
            if ($hasCommissionAmount) {
                $orderBlockColumns[] = 'admin_commission_amount';
            }
            $orderBlockColumns[] = 'status';
            if ($hasStatusReason) {
                $orderBlockColumns[] = 'status_reason';
            }

            $blocks[] = [
                'title' => 'Seller Orders',
                'subtitle' => 'Latest buyer orders for this seller, including order amount, commission, and current status.',
                'columns' => $orderBlockColumns,
                'rows' => $orders,
            ];

            $orderedProducts = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->join('buyers as b', 'b.id', '=', 'o.buyer_id')
                ->select([
                    'o.order_number',
                    'b.store_name',
                    'oi.product_name',
                    'oi.quantity',
                    'oi.unit_price',
                    'oi.line_total',
                    'o.status',
                    'o.order_date',
                ])
                ->where('o.supplier_id', $item->id)
                ->orderByDesc('o.order_date')
                ->orderByDesc('oi.id')
                ->limit(50)
                ->get();

            $blocks[] = [
                'title' => 'Ordered Products',
                'subtitle' => 'Products buyers have ordered from this seller.',
                'columns' => ['order_number', 'store_name', 'product_name', 'quantity', 'unit_price', 'line_total', 'status', 'order_date'],
                'rows' => $orderedProducts,
            ];

            $rows = DB::table('supplier_products as sp')
                ->join('catalog_products as cp', 'cp.id', '=', 'sp.catalog_product_id')
                ->leftJoin('categories as c', 'c.id', '=', 'cp.category_id')
                ->select([
                    'cp.name as product_name',
                    'c.name as category_name',
                    'sp.price',
                    'sp.stock_quantity',
                    'sp.status',
                ])
                ->where('sp.supplier_id', $item->id)
                ->orderBy('cp.name')
                ->get();

            $blocks[] = [
                'title' => 'Supplier Products',
                'subtitle' => $rows->count() . ' linked catalog listings.',
                'columns' => ['product_name', 'category_name', 'price', 'stock_quantity', 'status'],
                'rows' => $rows,
            ];
        }

        if ($module === 'buyers') {
            $rows = DB::table('orders as o')
                ->join('suppliers as s', 's.id', '=', 'o.supplier_id')
                ->select([
                    'o.order_number',
                    's.business_name',
                    'o.order_date',
                    'o.total_amount',
                    'o.status',
                ])
                ->where('o.buyer_id', $item->id)
                ->orderByDesc('o.order_date')
                ->limit(6)
                ->get();

            $blocks[] = [
                'title' => 'Recent Orders',
                'subtitle' => 'Latest orders placed by this buyer.',
                'columns' => ['order_number', 'business_name', 'order_date', 'total_amount', 'status'],
                'rows' => $rows,
            ];
        }

        if ($module === 'orders') {
            $summary = collect([
                (object) [
                    'buyer' => $item->store_name ?? '',
                    'supplier' => $item->business_name ?? '',
                    'order_date' => $item->order_date ?? null,
                    'status' => $item->status ?? '',
                    'status_reason' => $item->status_reason ?? '',
                    'subtotal' => $item->subtotal ?? 0,
                    'delivery_fee' => $item->delivery_fee ?? 0,
                    'total_amount' => $item->total_amount ?? 0,
                    'admin_commission_percentage' => $item->admin_commission_percentage ?? 0,
                    'admin_commission_amount' => $item->admin_commission_amount ?? 0,
                    'commission_status' => match ((string) ($item->status ?? '')) {
                        'delivered' => 'Cleared',
                        'cancelled' => 'Cancelled',
                        default => 'Being cleared',
                    },
                ],
            ]);
            $blocks[] = [
                'title' => 'Order Financials',
                'subtitle' => 'Buyer, seller, status, and admin commission details.',
                'columns' => ['buyer', 'supplier', 'order_date', 'status', 'status_reason', 'subtotal', 'delivery_fee', 'total_amount', 'admin_commission_percentage', 'admin_commission_amount', 'commission_status'],
                'rows' => $summary,
            ];

            $rows = DB::table('order_items')
                ->select(['product_name', 'unit_label', 'quantity', 'unit_price', 'line_total'])
                ->where('order_id', $item->id)
                ->orderBy('id')
                ->get();

            $blocks[] = [
                'title' => 'Order Items',
                'subtitle' => 'Line items attached to this order.',
                'columns' => ['product_name', 'unit_label', 'quantity', 'unit_price', 'line_total'],
                'rows' => $rows,
            ];
        }

        if ($module === 'chats') {
            $rows = DB::table('chat_messages')
                ->select(['sender_name', 'sender_type', 'message_body', 'created_at'])
                ->where('thread_id', $item->id)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $blocks[] = [
                'title' => 'Messages',
                'subtitle' => 'Conversation history inside this thread.',
                'columns' => ['sender_name', 'sender_type', 'message_body', 'created_at'],
                'rows' => $rows,
            ];
        }

        return $blocks;
    }

    private function showSubtitle(string $module, object $item): string
    {
        return match ($module) {
            'suppliers' => (string) ($item->description ?: 'No supplier description added yet.'),
            'buyers' => 'Retail buyer profile from the buyer app onboarding and shopping flow.',
            'products' => (string) ($item->description ?: 'No description added yet.'),
            'categories' => (string) ($item->description ?: 'No description added yet.'),
            'offers' => (string) ($item->description ?: 'No description added yet.'),
            'orders' => (string) ($item->store_name . ' ordered from ' . $item->business_name . '.'),
            'chats' => (string) ($item->store_name . ' with ' . $item->business_name),
            'settings' => 'Operational default used by the buyer and supplier apps.',
            default => 'View record details from the Muhalli admin workflow.',
        };
    }

    private function orderSummaryCards(): array
    {
        return [
            'all' => DB::table('orders')->count(),
            'pending' => DB::table('orders')->where('status', 'pending')->count(),
            'processing' => DB::table('orders')->where('status', 'processing')->count(),
            'shipped' => DB::table('orders')->where('status', 'shipped')->count(),
            'delivered' => DB::table('orders')->where('status', 'delivered')->count(),
        ];
    }

    private function applySearch($query, array $columns, string $search): void
    {
        if ($search === '') {
            return;
        }

        $query->where(function ($builder) use ($columns, $search) {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', '%' . $search . '%');
            }
        });
    }
}

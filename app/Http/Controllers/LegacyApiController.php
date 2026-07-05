<?php

namespace App\Http\Controllers;

use App\Support\CatalogProductBulkImporter;
use App\Support\ProductBulkImporter;
use App\Support\PushNotifications;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class LegacyApiController extends Controller
{
    public function handle(Request $request, ?string $path = null)
    {
        $endpoint = trim((string) ($request->query('endpoint') ?: $path ?: ''), '/');

        try {
            switch ($endpoint) {
                case 'auth/buyer/login':
                    $this->requireMethod($request, 'POST');
                    $buyer = $this->findBuyerByEmail((string) $this->value($request, 'email', ''));
                    if (!$buyer || !Hash::check((string) $this->value($request, 'password', ''), (string) $buyer['password_hash'])) {
                        $this->fail('Invalid buyer credentials.', 401);
                    }

                    return $this->ok([
                        'token' => $this->issueApiToken('buyer', (int) $buyer['id']),
                        'buyer' => $this->buyerAuthPayload($buyer),
                    ], 'Buyer login successful.');

                case 'auth/buyer/register':
                    $this->requireMethod($request, 'POST');
                    $buyerId = $this->persist('buyers', [
                        'store_name' => (string) $this->value($request, 'store_name', ''),
                        'buyer_name' => (string) $this->value($request, 'buyer_name', ''),
                        'email' => $this->generatedAppEmail('buyer', (string) $this->value($request, 'phone', '')),
                        'phone' => $this->normalizePhone((string) $this->value($request, 'phone', '')),
                        'city' => (string) $this->value($request, 'city', ''),
                        'address' => (string) $this->value($request, 'address', ''),
                        'latitude' => $this->nullableNumber($this->value($request, 'latitude')),
                        'longitude' => $this->nullableNumber($this->value($request, 'longitude')),
                        'password_hash' => Hash::make((string) $this->value($request, 'password', 'password')),
                        'preferred_language' => (string) $this->value($request, 'preferred_language', 'en'),
                        'status' => 'active',
                        'member_since' => now()->toDateString(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $this->ok(['token' => $this->issueApiToken('buyer', $buyerId), 'buyer_id' => $buyerId], 'Buyer registered.', 201);

                case 'auth/supplier/login':
                    $this->requireMethod($request, 'POST');
                    $supplier = $this->findSupplierByEmail((string) $this->value($request, 'email', ''));
                    if (!$supplier || !Hash::check((string) $this->value($request, 'password', ''), (string) $supplier['password_hash'])) {
                        $this->fail('Invalid supplier credentials.', 401);
                    }

                    return $this->ok([
                        'token' => $this->issueApiToken('supplier', (int) $supplier['id']),
                        'supplier' => $this->supplierAuthPayload($supplier),
                    ], 'Supplier login successful.');

                case 'auth/supplier/register':
                    $this->requireMethod($request, 'POST');
                    $supplierId = $this->persist('suppliers', [
                        'business_name' => (string) $this->value($request, 'business_name', ''),
                        'owner_name' => (string) $this->value($request, 'owner_name', ''),
                        'email' => $this->generatedAppEmail('supplier', (string) $this->value($request, 'phone', '')),
                        'phone' => $this->normalizePhone((string) $this->value($request, 'phone', '')),
                        'city' => (string) $this->value($request, 'city', ''),
                        'address' => (string) $this->value($request, 'address', ''),
                        'latitude' => $this->nullableNumber($this->value($request, 'latitude')),
                        'longitude' => $this->nullableNumber($this->value($request, 'longitude')),
                        'business_license_number' => (string) $this->value($request, 'business_license_number', ''),
                        'password_hash' => Hash::make((string) $this->value($request, 'password', 'password')),
                        'minimum_order_quantity' => (int) $this->value($request, 'minimum_order_quantity', 1),
                        'minimum_order_amount' => (float) $this->value($request, 'minimum_order_amount', 0),
                        'delivery_time' => (string) $this->value($request, 'delivery_time', '24-48 hours'),
                        'payment_terms' => (string) $this->value($request, 'payment_terms', 'Net 15'),
                        'description' => (string) $this->value($request, 'description', ''),
                        'logo_url' => (string) $this->value($request, 'logo_url', ''),
                        'status' => 'pending',
                        'is_verified' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $this->ok(['token' => $this->issueApiToken('supplier', $supplierId), 'supplier_id' => $supplierId], 'Supplier registered.', 201);

                case 'auth/request-otp':
                    $this->requireMethod($request, 'POST');
                    return $this->requestOtp($request);

                case 'auth/verify-otp':
                    $this->requireMethod($request, 'POST');
                    return $this->verifyOtp($request);

                case 'settings/public':
                    return $this->ok($this->settingsMap('public'));

                case 'buyer/home':
                    return $this->ok($this->buyerHomePayload($request));

                case 'buyer/categories':
                    return $this->ok($this->allCategories());

                case 'buyer/suppliers':
                    $city = (string) $this->value($request, 'city', '');
                    return $this->ok($this->allSuppliers(array_merge([
                        'search' => (string) $this->value($request, 'search', ''),
                        'status' => 'active',
                        'city' => $city,
                        'sort' => (string) $this->value($request, 'sort', 'default'),
                    ], $this->paginationParams($request, 50, 100))));

                case 'buyer/products':
                    $city = (string) $this->value($request, 'city', '');
                    return $this->ok($this->allProductListings(array_merge([
                        'search' => (string) $this->value($request, 'search', ''),
                        'status' => 'active',
                        'supplier_id' => (int) $this->value($request, 'supplier_id', 0),
                        'category_id' => (int) $this->value($request, 'category_id', 0),
                        'city' => $city,
                        'sort' => (string) $this->value($request, 'sort', 'default'),
                    ], $this->paginationParams($request, 50, 100))));

                case 'buyer/offers':
                    $city = (string) $this->value($request, 'city', '');
                    if ($identity = $this->identity($request, 'buyer')) {
                        $buyer = $this->findBuyer((int) $identity['user_id']);
                        $city = (string) ($buyer['city'] ?? $city);
                    }
                    return $this->ok($this->activeOffersPayload($city));

                case 'buyer/notifications':
                    $identity = $this->requireIdentity($request, 'buyer');
                    return $this->ok($this->buyerNotificationsPayload((int) $identity['user_id'], $this->paginationParams($request, 30, 100)));

                case 'buyer/notifications/register-device':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'buyer');
                    $this->registerBuyerDeviceToken(
                        (int) $identity['user_id'],
                        (string) $this->value($request, 'firebase_token', ''),
                        (string) $this->value($request, 'platform', 'android')
                    );
                    return $this->ok(['registered' => true], 'Buyer device token saved.');

                case 'buyer/referrals':
                    $identity = $this->requireIdentity($request, 'buyer');
                    return $this->ok($this->buyerReferralSummaryPayload((int) $identity['user_id']));

                case 'buyer/referrals/apply':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'buyer');
                    return $this->ok(
                        $this->applyBuyerReferralCode((int) $identity['user_id'], (string) $this->value($request, 'referral_code', '')),
                        'Referral code applied.'
                    );

                case 'buyer/orders':
                    $identity = $this->requireIdentity($request, 'buyer');
                    return $this->ok($this->buyerOrdersPayload((int) $identity['user_id'], $this->paginationParams($request, 20, 50)));

                case 'buyer/orders/detail':
                    $identity = $this->requireIdentity($request, 'buyer');
                    $order = $this->findOrder((int) $this->value($request, 'order_id', 0));
                    if (!$order || (int) $order['buyer_id'] !== (int) $identity['user_id']) {
                        $this->fail('Order not found.', 404);
                    }
                    return $this->ok($order);

                case 'buyer/profile':
                    $identity = $this->requireIdentity($request, 'buyer');
                    $buyer = $this->findBuyer((int) $identity['user_id']);
                    if (!$buyer) {
                        $this->fail('Buyer not found.', 404);
                    }
                    return $this->ok($this->buyerPublicPayload($buyer));

                case 'buyer/profile/update':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'buyer');
                    $buyer = $this->findBuyer((int) $identity['user_id']);
                    if (!$buyer) {
                        $this->fail('Buyer not found.', 404);
                    }
                    $this->persist('buyers', [
                        'store_name' => (string) $this->value($request, 'store_name', $buyer['store_name']),
                        'buyer_name' => (string) $this->value($request, 'buyer_name', $buyer['buyer_name']),
                        'phone' => $this->normalizePhone((string) $this->value($request, 'phone', $buyer['phone'])),
                        'city' => (string) $this->value($request, 'city', $buyer['city']),
                        'address' => (string) $this->value($request, 'address', $buyer['address'] ?? ''),
                        'latitude' => $this->nullableNumber($this->value($request, 'latitude', $buyer['latitude'] ?? null)),
                        'longitude' => $this->nullableNumber($this->value($request, 'longitude', $buyer['longitude'] ?? null)),
                        'password_hash' => (string) $buyer['password_hash'],
                        'preferred_language' => (string) $this->value($request, 'preferred_language', $buyer['preferred_language']),
                        'status' => (string) $buyer['status'],
                        'member_since' => (string) $buyer['member_since'],
                        'created_at' => (string) $buyer['created_at'],
                        'updated_at' => now(),
                    ], (int) $buyer['id']);
                    return $this->ok($this->buyerPublicPayload($this->findBuyer((int) $buyer['id'])), 'Buyer profile updated.');

                case 'buyer/chats':
                    $identity = $this->requireIdentity($request, 'buyer');
                    return $this->ok($this->buyerChatsPayload((int) $identity['user_id'], $this->paginationParams($request, 30, 50)));

                case 'buyer/chats/start':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'buyer');
                    $threadId = $this->ensureBuyerSupplierThread(
                        (int) $identity['user_id'],
                        (int) $this->value($request, 'supplier_id', 0)
                    );
                    return $this->ok($this->findThread($threadId), 'Chat ready.');

                case 'buyer/chats/thread':
                    $identity = $this->requireIdentity($request, 'buyer');
                    $threadId = (int) $this->value($request, 'thread_id', 0);
                    $thread = $this->findThread($threadId);
                    if (!$thread || (int) $thread['buyer_id'] !== (int) $identity['user_id']) {
                        $this->fail('Thread not found.', 404);
                    }
                    DB::table('chat_threads')->where('id', $threadId)->update([
                        'buyer_unread_count' => 0,
                        'updated_at' => now(),
                    ]);
                    return $this->ok($this->findThread($threadId));

                case 'buyer/chats/send':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'buyer');
                    return $this->sendChatMessage($request, (int) $identity['user_id'], 'buyer');

                case 'buyer/orders/create':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'buyer');
                    $orderPayload = $this->orderPayloadFromItems(
                        (int) $identity['user_id'],
                        (int) $this->value($request, 'supplier_id', 0),
                        $this->value($request, 'items', []),
                        (string) $this->value($request, 'notes', ''),
                        (float) $this->value($request, 'delivery_fee', 0)
                    );
                    $orderId = $this->createOrderWithItems($orderPayload);
                    PushNotifications::notifySupplier(
                        (int) $orderPayload['supplier_id'],
                        'New order received',
                        'A buyer placed order ' . $orderPayload['order_number'] . '.',
                        ['navigate_to' => 'supplier_orders', 'link_type' => 'order', 'link_value' => (string) $orderId]
                    );
                    return $this->ok($this->findOrder($orderId), 'Order created.', 201);

                case 'supplier/dashboard':
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->ok($this->supplierDashboardPayload((int) $identity['user_id']));

                case 'supplier/profile':
                    $identity = $this->requireIdentity($request, 'supplier');
                    $supplier = $this->findSupplier((int) $identity['user_id']);
                    if (!$supplier) {
                        $this->fail('Supplier not found.', 404);
                    }
                    return $this->ok($this->supplierPublicPayload($supplier));

                case 'supplier/profile/update':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    $supplier = $this->findSupplier((int) $identity['user_id']);
                    if (!$supplier) {
                        $this->fail('Supplier not found.', 404);
                    }
                    $this->persist('suppliers', [
                        'business_name' => (string) $this->value($request, 'business_name', $supplier['business_name']),
                        'owner_name' => (string) $this->value($request, 'owner_name', $supplier['owner_name']),
                        'phone' => $this->normalizePhone((string) $this->value($request, 'phone', $supplier['phone'])),
                        'city' => (string) $this->value($request, 'city', $supplier['city']),
                        'address' => (string) $this->value($request, 'address', $supplier['address']),
                        'latitude' => $this->nullableNumber($this->value($request, 'latitude', $supplier['latitude'] ?? null)),
                        'longitude' => $this->nullableNumber($this->value($request, 'longitude', $supplier['longitude'] ?? null)),
                        'business_license_number' => (string) $this->value($request, 'business_license_number', $supplier['business_license_number']),
                        'password_hash' => (string) $supplier['password_hash'],
                        'minimum_order_quantity' => (int) $this->value($request, 'minimum_order_quantity', $supplier['minimum_order_quantity']),
                        'minimum_order_amount' => (float) $this->value($request, 'minimum_order_amount', $supplier['minimum_order_amount']),
                        'delivery_time' => (string) $this->value($request, 'delivery_time', $supplier['delivery_time']),
                        'payment_terms' => (string) $this->value($request, 'payment_terms', $supplier['payment_terms']),
                        'description' => (string) $this->value($request, 'description', $supplier['description']),
                        'logo_url' => (string) $this->value($request, 'logo_url', $supplier['logo_url']),
                        'status' => (string) $supplier['status'],
                        'is_verified' => (int) $supplier['is_verified'],
                        'created_at' => (string) $supplier['created_at'],
                        'updated_at' => now(),
                    ], (int) $supplier['id']);
                    return $this->ok($this->supplierPublicPayload($this->findSupplier((int) $supplier['id'])), 'Supplier profile updated.');

                case 'supplier/catalog':
                    return $this->ok($this->supplierCatalogPayload(
                        $this->paginationParams($request, 50, 100),
                        (int) $this->value($request, 'category_id', 0)
                    ));

                case 'supplier/catalog/bulk-upload':
                    $this->requireMethod($request, 'POST');
                    $this->requireIdentity($request, 'supplier');
                    return $this->bulkUploadCatalogProducts($request);

                case 'supplier/products':
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->ok($this->supplierProductsPayload((int) $identity['user_id'], $this->paginationParams($request, 50, 100)));

                case 'supplier/products/create':
                case 'supplier/products/update':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->saveSupplierProduct($request, (int) $identity['user_id']);

                case 'supplier/products/bulk-upload':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->bulkUploadSupplierProducts($request, (int) $identity['user_id']);

                case 'supplier/offers/create':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->saveSupplierOffer($request, (int) $identity['user_id']);

                case 'supplier/notifications/register-device':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    $this->registerSupplierDeviceToken(
                        (int) $identity['user_id'],
                        (string) $this->value($request, 'firebase_token', ''),
                        (string) $this->value($request, 'platform', 'android')
                    );
                    return $this->ok(['registered' => true], 'Supplier device token saved.');

                case 'supplier/orders':
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->ok($this->supplierOrdersPayload((int) $identity['user_id'], $this->paginationParams($request, 20, 50)));

                case 'supplier/orders/detail':
                    $identity = $this->requireIdentity($request, 'supplier');
                    $order = $this->findOrder((int) $this->value($request, 'order_id', 0));
                    if (!$order || (int) $order['supplier_id'] !== (int) $identity['user_id']) {
                        $this->fail('Order not found.', 404);
                    }
                    $order['delivery_address'] = $order['buyer_address'] ?? '';
                    return $this->ok($order);

                case 'supplier/orders/status':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->updateSupplierOrderStatus($request, (int) $identity['user_id']);

                case 'supplier/earnings':
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->ok($this->supplierEarningsPayload((int) $identity['user_id']));

                case 'supplier/messages':
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->ok($this->supplierMessagesPayload((int) $identity['user_id'], $this->paginationParams($request, 30, 50)));

                case 'supplier/messages/thread':
                    $identity = $this->requireIdentity($request, 'supplier');
                    $threadId = (int) $this->value($request, 'thread_id', 0);
                    $thread = $this->findThread($threadId);
                    if (!$thread || (int) $thread['supplier_id'] !== (int) $identity['user_id']) {
                        $this->fail('Thread not found.', 404);
                    }
                    DB::table('chat_threads')->where('id', $threadId)->update([
                        'supplier_unread_count' => 0,
                        'updated_at' => now(),
                    ]);
                    return $this->ok($this->findThread($threadId));

                case 'supplier/messages/send':
                    $this->requireMethod($request, 'POST');
                    $identity = $this->requireIdentity($request, 'supplier');
                    return $this->sendChatMessage($request, (int) $identity['user_id'], 'supplier');

                default:
                    return $this->failResponse('Endpoint not found: ' . ($endpoint ?: '[empty]'), 404);
            }
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->failResponse($exception->getMessage(), 500);
        }
    }

    private function requestOtp(Request $request)
    {
        $role = strtolower((string) $this->value($request, 'role', ''));
        $purpose = strtolower((string) $this->value($request, 'purpose', 'login'));
        $phone = $this->normalizePhone((string) $this->value($request, 'phone', ''));

        if (!in_array($role, ['buyer', 'supplier'], true)) {
            $this->fail('A valid role is required.');
        }
        if (!in_array($purpose, ['login', 'register'], true)) {
            $this->fail('A valid OTP purpose is required.');
        }
        if ($phone === '' || strlen(preg_replace('/\D/', '', $phone) ?? '') < 8) {
            $this->fail('A valid phone number is required.');
        }

        if ($role === 'buyer') {
            $existingBuyer = $this->findBuyerByPhone($phone);
            if ($purpose === 'login' && !$existingBuyer) {
                $this->fail('Buyer account not found for this phone number.', 404);
            }
            if ($purpose === 'register' && $existingBuyer) {
                $this->fail('A buyer account already exists for this phone number.');
            }

            $payload = $purpose === 'register'
                ? [
                    'store_name' => (string) $this->value($request, 'store_name', ''),
                    'buyer_name' => (string) $this->value($request, 'buyer_name', $this->value($request, 'store_name', '')),
                    'phone' => $phone,
                    'city' => (string) $this->value($request, 'city', ''),
                    'address' => (string) $this->value($request, 'address', ''),
                    'latitude' => $this->nullableNumber($this->value($request, 'latitude')),
                    'longitude' => $this->nullableNumber($this->value($request, 'longitude')),
                    'preferred_language' => (string) $this->value($request, 'preferred_language', 'en'),
                ]
                : [];
        } else {
            $existingSupplier = $this->findSupplierByPhone($phone);
            if ($purpose === 'login' && !$existingSupplier) {
                $this->fail('Supplier account not found for this phone number.', 404);
            }
            if ($purpose === 'register' && $existingSupplier) {
                $this->fail('A supplier account already exists for this phone number.');
            }

            $payload = $purpose === 'register'
                ? [
                    'business_name' => (string) $this->value($request, 'business_name', ''),
                    'owner_name' => (string) $this->value($request, 'owner_name', ''),
                    'phone' => $phone,
                    'city' => (string) $this->value($request, 'city', ''),
                    'address' => (string) $this->value($request, 'address', ''),
                    'latitude' => $this->nullableNumber($this->value($request, 'latitude')),
                    'longitude' => $this->nullableNumber($this->value($request, 'longitude')),
                    'business_license_number' => (string) $this->value($request, 'business_license_number', ''),
                    'minimum_order_quantity' => (int) $this->value($request, 'minimum_order_quantity', 1),
                    'minimum_order_amount' => (float) $this->value($request, 'minimum_order_amount', 0),
                    'delivery_time' => (string) $this->value($request, 'delivery_time', $this->settingValue('default_delivery_window', '24-48 hours')),
                    'payment_terms' => (string) $this->value($request, 'payment_terms', 'Net 15'),
                    'description' => (string) $this->value($request, 'description', ''),
                ]
                : [];
        }

        $otpRequest = $this->createOtpRequestRecord($role, $purpose, $phone, $payload);

        return $this->ok([
            'phone' => $otpRequest['phone'],
            'expires_at' => $otpRequest['expires_at'],
            'delivery' => $otpRequest['delivery'],
            'debug_code' => $otpRequest['debug_code'],
        ], 'OTP requested successfully.');
    }

    private function verifyOtp(Request $request)
    {
        $role = strtolower((string) $this->value($request, 'role', ''));
        $purpose = strtolower((string) $this->value($request, 'purpose', 'login'));
        $phone = $this->normalizePhone((string) $this->value($request, 'phone', ''));
        $code = trim((string) $this->value($request, 'code', ''));

        if (!in_array($role, ['buyer', 'supplier'], true)) {
            $this->fail('A valid role is required.');
        }
        if (!in_array($purpose, ['login', 'register'], true)) {
            $this->fail('A valid OTP purpose is required.');
        }
        if ($phone === '' || $code === '') {
            $this->fail('Phone number and OTP code are required.');
        }

        $verification = $this->verifyOtpRequestCode($role, $purpose, $phone, $code);
        $payload = $verification['payload'];

        if ($role === 'buyer') {
            if ($purpose === 'login') {
                $buyer = $this->findBuyerByPhone($phone);
                if (!$buyer) {
                    $this->fail('Buyer account not found.', 404);
                }
            } else {
                $buyerId = $this->persist('buyers', [
                    'store_name' => (string) ($payload['store_name'] ?? ''),
                    'buyer_name' => (string) ($payload['buyer_name'] ?? ($payload['store_name'] ?? '')),
                    'email' => $this->generatedAppEmail('buyer', $phone),
                    'phone' => $phone,
                    'city' => (string) ($payload['city'] ?? ''),
                    'address' => (string) ($payload['address'] ?? ''),
                    'latitude' => $this->nullableNumber($payload['latitude'] ?? null),
                    'longitude' => $this->nullableNumber($payload['longitude'] ?? null),
                    'password_hash' => Hash::make(Str::random(32)),
                    'preferred_language' => (string) ($payload['preferred_language'] ?? 'en'),
                    'status' => 'active',
                    'member_since' => now()->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $buyer = $this->findBuyer($buyerId);
            }

            return $this->ok([
                'token' => $this->issueApiToken('buyer', (int) $buyer['id']),
                'buyer_id' => (int) $buyer['id'],
                'buyer' => $this->buyerAuthPayload($buyer),
            ], 'Buyer authenticated successfully.');
        }

        if ($purpose === 'login') {
            $supplier = $this->findSupplierByPhone($phone);
            if (!$supplier) {
                $this->fail('Supplier account not found.', 404);
            }
        } else {
            $supplierId = $this->persist('suppliers', [
                'business_name' => (string) ($payload['business_name'] ?? ''),
                'owner_name' => (string) ($payload['owner_name'] ?? ''),
                'email' => $this->generatedAppEmail('supplier', $phone),
                'phone' => $phone,
                'city' => (string) ($payload['city'] ?? ''),
                'address' => (string) ($payload['address'] ?? ''),
                'latitude' => $this->nullableNumber($payload['latitude'] ?? null),
                'longitude' => $this->nullableNumber($payload['longitude'] ?? null),
                'business_license_number' => (string) ($payload['business_license_number'] ?? ''),
                'password_hash' => Hash::make(Str::random(32)),
                'minimum_order_quantity' => (int) ($payload['minimum_order_quantity'] ?? 1),
                'minimum_order_amount' => (float) ($payload['minimum_order_amount'] ?? 0),
                'delivery_time' => (string) ($payload['delivery_time'] ?? $this->settingValue('default_delivery_window', '24-48 hours')),
                'payment_terms' => (string) ($payload['payment_terms'] ?? 'Net 15'),
                'description' => (string) ($payload['description'] ?? ''),
                'logo_url' => '',
                'status' => 'pending',
                'is_verified' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $supplier = $this->findSupplier($supplierId);
        }

        return $this->ok([
            'token' => $this->issueApiToken('supplier', (int) $supplier['id']),
            'supplier_id' => (int) $supplier['id'],
            'supplier' => $this->supplierAuthPayload($supplier),
        ], 'Supplier authenticated successfully.');
    }

    private function saveSupplierProduct(Request $request, int $supplierId)
    {
        $listingId = (int) $this->value($request, 'listing_id', 0);
        $catalogId = (int) $this->value($request, 'catalog_product_id', 0);
        $existingListing = $listingId > 0 ? $this->findListing($listingId) : null;
        if ($listingId > 0 && $catalogId === 0) {
            if ($existingListing) {
                $catalogId = (int) $existingListing['catalog_id'];
            }
        }
        if ($catalogId === 0) {
            $this->fail('catalog_product_id is required.');
        }

        $resolvedPrice = $this->optionalFloat($request, 'price', isset($existingListing['price']) ? (float) $existingListing['price'] : 0);
        if ($resolvedPrice <= 0) {
            $this->fail('price is required.');
        }
        $resolvedStock = $this->optionalInt($request, 'stock_quantity', isset($existingListing['stock_quantity']) ? (int) $existingListing['stock_quantity'] : 0);
        $resolvedDeliveryTime = $this->optionalString($request, 'delivery_time', (string) ($existingListing['delivery_time'] ?? '24-48 hours'));
        $resolvedStatus = $this->optionalString($request, 'status', (string) ($existingListing['status'] ?? 'active'));
        $resolvedSku = $this->optionalString($request, 'sku', (string) ($existingListing['sku'] ?? ''));
        if ($resolvedSku === null || $resolvedSku === '') {
            $resolvedSku = 'SKU-' . strtoupper(substr(md5((string) microtime()), 0, 8));
        }
        $resolvedMinOrderQty = $this->optionalInt($request, 'min_order_qty', isset($existingListing['min_order_qty']) ? (int) $existingListing['min_order_qty'] : (int) $this->value($request, 'min_order_qty', 1));
        $resolvedMinOrderAmount = $this->optionalFloat($request, 'min_order_amount', isset($existingListing['min_order_amount']) ? (float) $existingListing['min_order_amount'] : (float) $this->value($request, 'min_order_amount', 0));

        $imageDataUrl = (string) $this->value($request, 'image_data_url', '');
        $imageUrl = trim((string) $this->value($request, 'image_url', ''));
        if ($imageDataUrl !== '' || $imageUrl !== '') {
            $catalogProduct = (array) DB::table('catalog_products')->where('id', $catalogId)->first();
            if (!$catalogProduct) {
                $this->fail('Catalog product not found.', 404);
            }
            $resolvedImageUrl = (string) ($catalogProduct['image_url'] ?? '');
            if ($imageDataUrl !== '') {
                $resolvedImageUrl = $this->storeDataUrlImage($imageDataUrl, 'products');
            } elseif ($imageUrl !== '') {
                $resolvedImageUrl = $imageUrl;
            }
            DB::table('catalog_products')->where('id', $catalogId)->update([
                'image_url' => $resolvedImageUrl,
                'updated_at' => now(),
            ]);
        }

        $payload = [
            'catalog_product_id' => $catalogId,
            'supplier_id' => $supplierId,
            'sku' => $resolvedSku,
            'price' => $resolvedPrice,
            'stock_quantity' => $resolvedStock,
            'min_order_qty' => $resolvedMinOrderQty,
            'min_order_amount' => $resolvedMinOrderAmount,
            'delivery_time' => $resolvedDeliveryTime,
            'status' => $resolvedStatus,
            'is_featured' => (int) $this->value($request, 'is_featured', 0),
            'updated_at' => now(),
        ];
        if ($listingId === 0) {
            $payload['created_at'] = now();
        }

        $savedId = $this->persist('supplier_products', $payload, $listingId ?: null);
        $offerPrice = (float) $this->value($request, 'offer_price', 0);
        if ($offerPrice > 0) {
            $this->saveOfferForListing(
                $supplierId,
                $savedId,
                $offerPrice,
                (int) $this->value($request, 'maximum_quantity', 0),
                (string) $this->value($request, 'title', ''),
                (string) $this->value($request, 'description', 'Supplier special offer')
            );
        }
        return $this->ok($this->findListing($savedId), 'Supplier product saved.', $listingId ? 200 : 201);
    }

    private function saveSupplierOffer(Request $request, int $supplierId)
    {
        $listingId = (int) $this->value($request, 'listing_id', 0);
        $listing = $this->findListing($listingId);
        if (!$listing || (int) $listing['supplier_id'] !== $supplierId) {
            $this->fail('Supplier product not found.', 404);
        }

        $offerPrice = (float) $this->value($request, 'offer_price', 0);
        if ($offerPrice <= 0) {
            $this->fail('offer_price is required.');
        }

        $existing = $this->row(
            'SELECT * FROM offers WHERE supplier_id = :supplier_id AND supplier_product_id = :supplier_product_id AND status IN ("active", "draft") ORDER BY id DESC LIMIT 1',
            ['supplier_id' => $supplierId, 'supplier_product_id' => $listingId]
        );

        $maximumQuantity = (int) $this->value($request, 'maximum_quantity', 0);
        $offerId = $this->persist('offers', [
            'title' => (string) $this->value($request, 'title', $listing['catalog_name']),
            'description' => (string) $this->value($request, 'description', 'Supplier special offer'),
            'badge_label' => (string) $this->value($request, 'badge_label', 'Special Offer'),
            'discount_label' => (string) $this->value($request, 'discount_label', $this->currencyAmountLabel($offerPrice)),
            'image_url' => (string) ($listing['image_url'] ?? ''),
            'supplier_id' => $supplierId,
            'supplier_product_id' => $listingId,
            'catalog_product_id' => (int) $listing['catalog_product_id'],
            'offer_price' => $offerPrice,
            'maximum_quantity' => $maximumQuantity > 0 ? $maximumQuantity : null,
            'city' => (string) ($listing['supplier_city'] ?? $this->value($request, 'city', '')),
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => (string) ($existing['created_at'] ?? now()),
            'updated_at' => now(),
        ], $existing ? (int) $existing['id'] : null);

        return $this->ok($this->findOffer($offerId), 'Offer saved.', $existing ? 200 : 201);
    }

    private function saveOfferForListing(int $supplierId, int $listingId, float $offerPrice, int $maximumQuantity = 0, string $title = '', string $description = ''): void
    {
        $listing = $this->findListing($listingId);
        if (!$listing || (int) $listing['supplier_id'] !== $supplierId || $offerPrice <= 0) {
            return;
        }

        $existing = $this->row(
            'SELECT * FROM offers WHERE supplier_id = :supplier_id AND supplier_product_id = :supplier_product_id AND status IN ("active", "draft") ORDER BY id DESC LIMIT 1',
            ['supplier_id' => $supplierId, 'supplier_product_id' => $listingId]
        );

        $this->persist('offers', [
            'title' => $title ?: (string) $listing['catalog_name'],
            'description' => $description ?: 'Supplier special offer',
            'badge_label' => 'Special Offer',
            'discount_label' => $this->currencyAmountLabel($offerPrice),
            'image_url' => (string) ($listing['image_url'] ?? ''),
            'supplier_id' => $supplierId,
            'supplier_product_id' => $listingId,
            'catalog_product_id' => (int) $listing['catalog_product_id'],
            'offer_price' => $offerPrice,
            'maximum_quantity' => $maximumQuantity > 0 ? $maximumQuantity : null,
            'city' => (string) ($listing['supplier_city'] ?? ''),
            'status' => 'active',
            'starts_at' => null,
            'ends_at' => null,
            'created_at' => (string) ($existing['created_at'] ?? now()),
            'updated_at' => now(),
        ], $existing ? (int) $existing['id'] : null);
    }

    private function bulkUploadSupplierProducts(Request $request, int $supplierId)
    {
        $fileName = basename((string) $this->value($request, 'file_name', 'products.csv'));
        $encoded = (string) $this->value($request, 'file_data_base64', '');
        if ($encoded === '') {
            $this->fail('file_data_base64 is required.');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            $this->fail('Upload a CSV or XLSX file.');
        }

        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            $this->fail('Uploaded file data is invalid.');
        }

        $basePath = tempnam(sys_get_temp_dir(), 'muhalli-bulk-');
        $path = $basePath . '.' . $extension;
        file_put_contents($path, $bytes);

        try {
            $summary = ProductBulkImporter::importFile($path, $supplierId);
        } finally {
            @unlink($path);
            @unlink($basePath);
        }

        return $this->ok($summary, $summary['error_count'] > 0 ? 'Bulk upload completed with row errors.' : 'Bulk upload completed.');
    }

    private function bulkUploadCatalogProducts(Request $request)
    {
        $fileName = basename((string) $this->value($request, 'file_name', 'catalog-products.csv'));
        $encoded = (string) $this->value($request, 'file_data_base64', '');
        if ($encoded === '') {
            $this->fail('file_data_base64 is required.');
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            $this->fail('Upload a CSV or XLSX file.');
        }

        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            $this->fail('Uploaded file data is invalid.');
        }

        $basePath = tempnam(sys_get_temp_dir(), 'muhalli-catalog-bulk-');
        $path = $basePath . '.' . $extension;
        file_put_contents($path, $bytes);

        try {
            $summary = CatalogProductBulkImporter::importFile($path);
        } finally {
            @unlink($path);
            @unlink($basePath);
        }

        return $this->ok($summary, $summary['error_count'] > 0 ? 'Catalog bulk upload completed with row errors.' : 'Catalog bulk upload completed.');
    }

    private function updateSupplierOrderStatus(Request $request, int $supplierId)
    {
        $order = $this->findOrder((int) $this->value($request, 'order_id', 0));
        if (!$order || (int) $order['supplier_id'] !== $supplierId) {
            $this->fail('Order not found.', 404);
        }

        $nextStatus = (string) $this->value($request, 'status', $order['status']);
        $statusReason = match ($nextStatus) {
            'processing' => 'Supplier confirmed the order and started processing.',
            'shipped' => 'Supplier marked the order as shipped.',
            'delivered' => 'Order completed and seller revenue is now counted.',
            'cancelled' => 'Order was cancelled before completion.',
            default => 'Waiting for supplier confirmation.',
        };

        $updates = [
            'status' => $nextStatus,
            'payment_status' => (string) $this->value($request, 'payment_status', $order['payment_status']),
            'delivery_date' => (string) $this->value($request, 'delivery_date', $order['delivery_date']) ?: null,
            'notes' => (string) $this->value($request, 'notes', $order['notes']),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('orders', 'status_reason')) {
            $updates['status_reason'] = $statusReason;
        }
        if ($nextStatus === 'processing' && Schema::hasColumn('orders', 'seller_confirmed_at') && empty($order['seller_confirmed_at'])) {
            $updates['seller_confirmed_at'] = now();
        }
        if ($nextStatus === 'delivered' && Schema::hasColumn('orders', 'completed_at')) {
            $updates['completed_at'] = now();
            $updates['payment_status'] = 'paid';
        }

        $this->persist('orders', $updates, (int) $order['id']);

        PushNotifications::notifyBuyer(
            (int) $order['buyer_id'],
            'Order status updated',
            'Order ' . $order['order_number'] . ' is now ' . $nextStatus . '.',
            ['navigate_to' => 'orders', 'link_type' => 'order', 'link_value' => (string) $order['id']]
        );

        return $this->ok($this->findOrder((int) $order['id']), 'Order status updated.');
    }

    private function sendChatMessage(Request $request, int $userId, string $senderType)
    {
        $threadId = (int) $this->value($request, 'thread_id', 0);
        $thread = $this->findThread($threadId);
        $column = $senderType === 'buyer' ? 'buyer_id' : 'supplier_id';
        if (!$thread || (int) $thread[$column] !== $userId) {
            $this->fail('Thread not found.', 404);
        }
        $messageType = strtolower((string) $this->value($request, 'message_type', 'text'));
        $voiceDuration = trim((string) $this->value($request, 'voice_duration', ''));
        $body = trim((string) $this->value($request, 'message_body', ''));
        if ($messageType === 'voice') {
            $voiceDataUrl = trim((string) $this->value($request, 'voice_data_url', ''));
            if ($voiceDataUrl === '') {
                $this->fail('voice_data_url is required for voice messages.');
            }
            $body = $this->storeDataUrlAudio($voiceDataUrl, 'chat_voice');
        } elseif ($body === '') {
            $this->fail('message_body is required.');
        }

        $senderName = $senderType === 'buyer' ? (string) $thread['store_name'] : (string) $thread['business_name'];
        $payload = [
            'thread_id' => $threadId,
            'sender_type' => $senderType,
            'sender_name' => $senderName,
            'message_body' => $body,
            'message_type' => $messageType,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('chat_messages', 'voice_duration')) {
            $payload['voice_duration'] = $messageType === 'voice' ? $voiceDuration : null;
        }
        $this->persist('chat_messages', $payload);

        $lastMessage = $messageType === 'voice' ? 'Voice message' : $body;
        $updates = [
            'last_message' => $lastMessage,
            'last_message_at' => now(),
            'updated_at' => now(),
        ];
        if ($senderType === 'buyer') {
            $updates['buyer_unread_count'] = 0;
            DB::table('chat_threads')->where('id', $threadId)->increment('supplier_unread_count');
            DB::table('chat_threads')->where('id', $threadId)->update($updates);
        } else {
            $updates['supplier_unread_count'] = 0;
            DB::table('chat_threads')->where('id', $threadId)->increment('buyer_unread_count');
            DB::table('chat_threads')->where('id', $threadId)->update($updates);
        }

        if ($senderType === 'buyer') {
            PushNotifications::notifySupplier(
                (int) $thread['supplier_id'],
                'New buyer message',
                $senderName . ': ' . Str::limit($lastMessage, 80),
                ['navigate_to' => 'supplier_messages', 'link_type' => 'chat', 'link_value' => (string) $threadId]
            );
        } else {
            PushNotifications::notifyBuyer(
                (int) $thread['buyer_id'],
                'New supplier message',
                $senderName . ': ' . Str::limit($lastMessage, 80),
                ['navigate_to' => 'chats', 'link_type' => 'chat', 'link_value' => (string) $threadId]
            );
        }

        return $this->ok($this->findThread($threadId), 'Message sent.');
    }

    private function allCategories(): array
    {
        return $this->rows(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM catalog_products cp WHERE cp.category_id = c.id) AS catalog_count,
                    (SELECT COUNT(*) FROM supplier_products sp JOIN catalog_products cp2 ON cp2.id = sp.catalog_product_id WHERE cp2.category_id = c.id) AS listing_count
             FROM categories c
             ORDER BY c.sort_order ASC, c.name ASC'
        );
    }

    private function activeOfferJoinSql(string $listingAlias = 'sp', string $offerAlias = 'ao'): string
    {
        return ' LEFT JOIN offers ' . $offerAlias . ' ON ' . $offerAlias . '.id = (
                    SELECT o2.id
                    FROM offers o2
                    WHERE o2.supplier_id = ' . $listingAlias . '.supplier_id
                      AND (o2.supplier_product_id = ' . $listingAlias . '.id
                           OR (o2.supplier_product_id IS NULL AND o2.catalog_product_id = ' . $listingAlias . '.catalog_product_id))
                      AND o2.status = "active"
                      AND (o2.starts_at IS NULL OR o2.starts_at <= NOW())
                      AND (o2.ends_at IS NULL OR o2.ends_at >= NOW())
                    ORDER BY COALESCE(o2.starts_at, o2.created_at) DESC, o2.id DESC
                    LIMIT 1
                ) ';
    }

    private function effectivePriceSql(string $listingAlias = 'sp', string $offerAlias = 'ao'): string
    {
        return 'CASE WHEN ' . $offerAlias . '.id IS NOT NULL
                       AND ' . $offerAlias . '.offer_price IS NOT NULL
                       AND ' . $offerAlias . '.offer_price > 0
                    THEN ' . $offerAlias . '.offer_price
                    ELSE ' . $listingAlias . '.price END';
    }

    private function allSuppliers(array $filters): array
    {
        $search = (string) ($filters['search'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $city = trim((string) ($filters['city'] ?? ''));
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'default')));
        $limitSql = $this->limitSql($filters);
        $orderBy = match ($sort) {
            'cheapest' => 'COALESCE(lowest_price, 99999999) ASC, s.minimum_order_amount ASC, s.business_name ASC',
            'low_min_order' => 's.minimum_order_amount ASC, COALESCE(lowest_price, 99999999) ASC, s.business_name ASC',
            default => 's.is_verified DESC, FIELD(s.status, "pending", "active", "suspended"), s.business_name ASC',
        };

        $offerJoin = $this->activeOfferJoinSql('sp', 'so');
        $effectivePrice = $this->effectivePriceSql('sp', 'so');

        return $this->rows(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM supplier_products sp WHERE sp.supplier_id = s.id AND sp.status = "active" AND sp.stock_quantity > 0) AS product_count,
                    (SELECT COUNT(*) FROM orders o WHERE o.supplier_id = s.id) AS order_count,
                    (SELECT COALESCE(SUM(CASE WHEN o.status = "delivered" THEN o.total_amount ELSE 0 END), 0) FROM orders o WHERE o.supplier_id = s.id) AS revenue_total,
                    (SELECT MIN(' . $effectivePrice . ') FROM supplier_products sp ' . $offerJoin . ' WHERE sp.supplier_id = s.id AND sp.status = "active" AND sp.stock_quantity > 0) AS lowest_price
             FROM suppliers s
             WHERE (:search = ""
                OR s.business_name LIKE :like
                OR s.owner_name LIKE :like
                OR s.city LIKE :like
                OR EXISTS (
                    SELECT 1 FROM supplier_products sp2
                    JOIN catalog_products cp2 ON cp2.id = sp2.catalog_product_id
                    LEFT JOIN categories c2 ON c2.id = cp2.category_id
                    WHERE sp2.supplier_id = s.id
                      AND sp2.status = "active"
                      AND sp2.stock_quantity > 0
                      AND (cp2.name LIKE :like OR cp2.packaging LIKE :like OR c2.name LIKE :like)
                ))
               AND (:status = "" OR s.status = :status)
               AND (:city = "" OR s.city = :city)
             ORDER BY ' . $orderBy . $limitSql,
            ['search' => $search, 'like' => '%' . $search . '%', 'status' => $status, 'city' => $city]
        );
    }

    private function allProductListings(array $filters): array
    {
        $search = (string) ($filters['search'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $city = trim((string) ($filters['city'] ?? ''));
        $sort = strtolower(trim((string) ($filters['sort'] ?? 'default')));
        $limitSql = $this->limitSql($filters);
        $offerJoin = $this->activeOfferJoinSql('sp', 'ao');
        $effectivePrice = $this->effectivePriceSql('sp', 'ao');
        $orderBy = match ($sort) {
            'cheapest' => 'effective_price ASC, s.minimum_order_amount ASC, cp.name ASC',
            'low_min_order' => 's.minimum_order_amount ASC, effective_price ASC, cp.name ASC',
            default => 'sp.is_featured DESC, sp.created_at DESC, sp.id DESC',
        };

        return $this->rows(
            'SELECT sp.*, cp.name AS catalog_name, cp.emoji, cp.packaging, cp.unit_type, cp.description, cp.image_url,
                    c.name AS category_name, s.business_name AS supplier_name, s.city AS supplier_city,
                    s.minimum_order_amount AS supplier_minimum_order_amount,
                    s.minimum_order_quantity AS supplier_minimum_order_quantity,
                    ao.id AS active_offer_id,
                    ao.offer_price,
                    ao.maximum_quantity,
                    ' . $effectivePrice . ' AS effective_price
             FROM supplier_products sp
             JOIN catalog_products cp ON cp.id = sp.catalog_product_id
             LEFT JOIN categories c ON c.id = cp.category_id
             LEFT JOIN suppliers s ON s.id = sp.supplier_id
             ' . $offerJoin . '
             WHERE (:search = "" OR cp.name LIKE :like OR cp.packaging LIKE :like OR s.business_name LIKE :like OR c.name LIKE :like OR s.city LIKE :like)
               AND (:status = "" OR sp.status = :status)
               AND (:status != "active" OR sp.stock_quantity > 0)
               AND (:status != "active" OR s.status = "active")
               AND (:supplier_id = 0 OR sp.supplier_id = :supplier_id)
               AND (:category_id = 0 OR cp.category_id = :category_id)
               AND (:city = "" OR s.city = :city)
             ORDER BY ' . $orderBy . $limitSql,
            [
                'search' => $search,
                'like' => '%' . $search . '%',
                'status' => $status,
                'supplier_id' => $supplierId,
                'category_id' => $categoryId,
                'city' => $city,
            ]
        );
    }

    private function buyerHomePayload(Request $request): array
    {
        $city = trim((string) $this->value($request, 'city', ''));
        $buyerId = (int) $this->value($request, 'buyer_id', 0);
        if ($buyerId > 0) {
            $buyer = $this->findBuyer($buyerId);
            $city = trim((string) ($buyer['city'] ?? $city));
        }

        return [
            'featured_categories' => $this->rows('SELECT id, name, icon, accent_color, description FROM categories WHERE status = "active" ORDER BY sort_order ASC, name ASC LIMIT 8'),
            'featured_suppliers' => $this->rows(
                'SELECT id, business_name, owner_name, city, minimum_order_amount, minimum_order_quantity, delivery_time, is_verified, status,
                        (SELECT MIN(' . $this->effectivePriceSql('sp', 'so') . ') FROM supplier_products sp ' . $this->activeOfferJoinSql('sp', 'so') . ' WHERE sp.supplier_id = suppliers.id AND sp.status = "active" AND sp.stock_quantity > 0) AS lowest_price
                 FROM suppliers
                 WHERE status = "active" AND (:city = "" OR city = :city)
                 ORDER BY is_verified DESC, business_name ASC
                 LIMIT 8',
                ['city' => $city]
            ),
            'featured_products' => $this->rows(
                'SELECT sp.id, cp.name, cp.emoji, cp.packaging, cp.unit_type, cp.description, cp.image_url,
                        sp.price, sp.stock_quantity, sp.delivery_time, s.business_name, c.name AS category_name,
                        s.minimum_order_amount AS supplier_minimum_order_amount, s.city AS supplier_city,
                        ao.id AS active_offer_id, ao.offer_price, ao.maximum_quantity,
                        ' . $this->effectivePriceSql('sp', 'ao') . ' AS effective_price
                 FROM supplier_products sp
                 JOIN catalog_products cp ON cp.id = sp.catalog_product_id
                 LEFT JOIN suppliers s ON s.id = sp.supplier_id
                 LEFT JOIN categories c ON c.id = cp.category_id
                 ' . $this->activeOfferJoinSql('sp', 'ao') . '
                 WHERE sp.status = "active" AND sp.stock_quantity > 0 AND s.status = "active" AND (:city = "" OR s.city = :city)
                 ORDER BY sp.is_featured DESC, sp.created_at DESC
                 LIMIT 10',
                ['city' => $city]
            ),
            'offers' => $this->activeOffersPayload($city),
            'public_settings' => $this->settingsMap('public'),
        ];
    }

    private function activeOffersPayload(string $city = ''): array
    {
        return $this->rows(
            'SELECT o.*, s.business_name AS supplier_name, cp.name AS product_name,
                    sp.price AS original_price,
                    sp.stock_quantity,
                    sp.id AS listing_id
             FROM offers o
             LEFT JOIN suppliers s ON s.id = o.supplier_id
             LEFT JOIN catalog_products cp ON cp.id = o.catalog_product_id
             LEFT JOIN supplier_products sp ON (sp.id = o.supplier_product_id OR (sp.supplier_id = o.supplier_id AND sp.catalog_product_id = o.catalog_product_id))
             WHERE o.status = "active"
               AND (o.starts_at IS NULL OR o.starts_at <= NOW())
               AND (o.ends_at IS NULL OR o.ends_at >= NOW())
               AND (sp.id IS NULL OR (sp.status = "active" AND sp.stock_quantity > 0))
               AND (s.id IS NULL OR s.status = "active")
               AND (:city = "" OR o.city IS NULL OR o.city = "" OR o.city = :city)
             ORDER BY COALESCE(o.starts_at, o.created_at) DESC, o.id DESC
             LIMIT 12',
            ['city' => $city]
        );
    }

    private function buyerNotificationsPayload(int $buyerId, array $pagination = []): array
    {
        $buyer = $this->findBuyer($buyerId);
        $city = trim((string) ($buyer['city'] ?? ''));
        $limitSql = $this->limitSql($pagination);

        return $this->rows(
            'SELECT * FROM app_notifications
             WHERE status = "active"
               AND (target_type = "all" OR (target_type = "city" AND target_value = :city) OR (target_type = "buyer" AND target_value = :buyer_id))
             ORDER BY created_at DESC, id DESC' . $limitSql,
            ['city' => $city, 'buyer_id' => (string) $buyerId]
        );
    }

    private function buyerOrdersPayload(int $buyerId, array $pagination = []): array
    {
        $limitSql = $this->limitSql($pagination);
        return $this->rows(
            'SELECT o.*, s.business_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
             FROM orders o
             JOIN suppliers s ON s.id = o.supplier_id
             WHERE o.buyer_id = :buyer_id
             ORDER BY o.order_date DESC' . $limitSql,
            ['buyer_id' => $buyerId]
        );
    }

    private function buyerChatsPayload(int $buyerId, array $pagination = []): array
    {
        $limitSql = $this->limitSql($pagination);
        return $this->rows(
            'SELECT t.*, s.business_name, s.owner_name
             FROM chat_threads t
             JOIN suppliers s ON s.id = t.supplier_id
             WHERE t.buyer_id = :buyer_id
             ORDER BY t.last_message_at DESC' . $limitSql,
            ['buyer_id' => $buyerId]
        );
    }

    private function supplierDashboardPayload(int $supplierId): array
    {
        $stats = $this->row(
            'SELECT COUNT(DISTINCT sp.id) AS total_products,
                    SUM(CASE WHEN sp.stock_quantity <= 10 THEN 1 ELSE 0 END) AS low_stock_count,
                    COUNT(DISTINCT CASE WHEN o.status = "pending" THEN o.id END) AS pending_orders,
                    COUNT(DISTINCT CASE WHEN DATE(o.order_date) = CURDATE() THEN o.id END) AS today_orders,
                    COALESCE(SUM(CASE WHEN o.status = "delivered" AND DATE_FORMAT(o.order_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m") THEN o.total_amount ELSE 0 END), 0) AS month_revenue
             FROM suppliers s
             LEFT JOIN supplier_products sp ON sp.supplier_id = s.id
             LEFT JOIN orders o ON o.supplier_id = s.id
             WHERE s.id = :supplier_id
             GROUP BY s.id',
            ['supplier_id' => $supplierId]
        );

        return [
            'stats' => $stats,
            'recent_orders' => $this->rows(
                'SELECT order_number, status, total_amount, order_date, delivery_date
                 FROM orders WHERE supplier_id = :supplier_id ORDER BY order_date DESC LIMIT 5',
                ['supplier_id' => $supplierId]
            ),
            'products' => $this->rows(
                'SELECT sp.id, cp.name, cp.packaging, cp.unit_type, cp.image_url, sp.price, sp.stock_quantity, sp.delivery_time, sp.status
                 FROM supplier_products sp
                 JOIN catalog_products cp ON cp.id = sp.catalog_product_id
                 WHERE sp.supplier_id = :supplier_id
                 ORDER BY cp.name ASC',
                ['supplier_id' => $supplierId]
            ),
        ];
    }

    private function supplierProductsPayload(int $supplierId, array $pagination = []): array
    {
        $limitSql = $this->limitSql($pagination);
        return $this->rows(
            'SELECT sp.id, sp.catalog_product_id, sp.supplier_id, sp.sku, sp.price, sp.stock_quantity,
                    sp.min_order_qty, sp.min_order_amount, sp.delivery_time, sp.status, sp.is_featured,
                    sp.created_at, sp.updated_at,
                    cp.name, cp.packaging, cp.unit_type, cp.emoji, cp.image_url, c.name AS category_name,
                    ao.id AS active_offer_id,
                    ao.offer_price,
                    ao.maximum_quantity,
                    CASE WHEN ao.id IS NULL THEN 0 ELSE 1 END AS is_on_offer
             FROM supplier_products sp
             JOIN catalog_products cp ON cp.id = sp.catalog_product_id
             LEFT JOIN categories c ON c.id = cp.category_id
             ' . $this->activeOfferJoinSql('sp', 'ao') . '
             WHERE sp.supplier_id = :supplier_id
             ORDER BY cp.name ASC' . $limitSql,
            ['supplier_id' => $supplierId]
        );
    }

    private function supplierCatalogPayload(array $pagination = [], int $categoryId = 0): array
    {
        $limitSql = $this->limitSql($pagination);
        $categorySql = $categoryId > 0 ? ' WHERE cp.category_id = :category_id' : '';
        return $this->rows(
            'SELECT cp.id, cp.category_id, cp.name, cp.slug, cp.emoji, cp.packaging,
                    cp.unit_type, cp.image_url, cp.status, c.name AS category_name
             FROM catalog_products cp
             LEFT JOIN categories c ON c.id = cp.category_id
             ' . $categorySql . '
             ORDER BY c.name ASC, cp.name ASC' . $limitSql,
            $categoryId > 0 ? ['category_id' => $categoryId] : []
        );
    }

    private function supplierOrdersPayload(int $supplierId, array $pagination = []): array
    {
        $limitSql = $this->limitSql($pagination);
        $orders = $this->rows(
            'SELECT o.*, b.store_name, b.buyer_name, b.city AS buyer_city, b.address AS buyer_address,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
             FROM orders o
             JOIN buyers b ON b.id = o.buyer_id
             WHERE o.supplier_id = :supplier_id
             ORDER BY o.order_date DESC' . $limitSql,
            ['supplier_id' => $supplierId]
        );

        foreach ($orders as &$order) {
            $order['delivery_address'] = $order['buyer_address'] ?? '';
            $order['items'] = $this->orderItemsPayload((int) $order['id']);
        }

        return $orders;
    }

    private function supplierEarningsPayload(int $supplierId): array
    {
        return [
            'summary' => $this->row(
                'SELECT COALESCE(SUM(CASE WHEN status = "delivered" THEN total_amount ELSE 0 END), 0) AS all_time,
                        COALESCE(SUM(CASE WHEN status = "delivered" AND DATE_FORMAT(order_date, "%Y-%m") = DATE_FORMAT(CURDATE(), "%Y-%m") THEN total_amount ELSE 0 END), 0) AS this_month
                 FROM orders WHERE supplier_id = :supplier_id',
                ['supplier_id' => $supplierId]
            ),
            'transactions' => $this->rows(
                'SELECT order_number, total_amount, order_date, status
                 FROM orders
                 WHERE supplier_id = :supplier_id AND status = "delivered"
                 ORDER BY order_date DESC',
                ['supplier_id' => $supplierId]
            ),
        ];
    }

    private function supplierMessagesPayload(int $supplierId, array $pagination = []): array
    {
        $limitSql = $this->limitSql($pagination);
        return $this->rows(
            'SELECT t.*, b.store_name, b.buyer_name
             FROM chat_threads t
             JOIN buyers b ON b.id = t.buyer_id
             WHERE t.supplier_id = :supplier_id
             ORDER BY t.last_message_at DESC' . $limitSql,
            ['supplier_id' => $supplierId]
        );
    }

    private function findBuyer(int $id): ?array
    {
        $buyer = $this->row('SELECT * FROM buyers WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$buyer) {
            return null;
        }
        $buyer['orders'] = $this->rows(
            'SELECT o.order_number, o.status, o.total_amount, o.order_date, s.business_name
             FROM orders o JOIN suppliers s ON s.id = o.supplier_id
             WHERE o.buyer_id = :buyer_id ORDER BY o.order_date DESC LIMIT 6',
            ['buyer_id' => $id]
        );
        $buyer['threads'] = $this->rows(
            'SELECT t.subject, t.last_message, t.last_message_at, s.business_name
             FROM chat_threads t JOIN suppliers s ON s.id = t.supplier_id
             WHERE t.buyer_id = :buyer_id ORDER BY t.last_message_at DESC LIMIT 5',
            ['buyer_id' => $id]
        );
        return $buyer;
    }

    private function findSupplier(int $id): ?array
    {
        $supplier = $this->row('SELECT * FROM suppliers WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$supplier) {
            return null;
        }
        $supplier['products'] = $this->rows(
            'SELECT sp.*, cp.name AS product_name, cp.packaging, cp.unit_type, c.name AS category_name
             FROM supplier_products sp
             JOIN catalog_products cp ON cp.id = sp.catalog_product_id
             LEFT JOIN categories c ON c.id = cp.category_id
             WHERE sp.supplier_id = :supplier_id
             ORDER BY cp.name ASC',
            ['supplier_id' => $id]
        );
        $supplier['recent_orders'] = $this->rows(
            'SELECT order_number, status, total_amount, order_date
             FROM orders
             WHERE supplier_id = :supplier_id
             ORDER BY order_date DESC LIMIT 5',
            ['supplier_id' => $id]
        );
        return $supplier;
    }

    private function findListing(int $id): ?array
    {
        return $this->row(
            'SELECT sp.*, cp.id AS catalog_id, cp.name AS catalog_name, cp.slug, cp.emoji, cp.description, cp.packaging, cp.unit_type, cp.image_url, cp.category_id,
                    s.business_name AS supplier_name, s.city AS supplier_city, c.name AS category_name
             FROM supplier_products sp
             JOIN catalog_products cp ON cp.id = sp.catalog_product_id
             LEFT JOIN suppliers s ON s.id = sp.supplier_id
             LEFT JOIN categories c ON c.id = cp.category_id
             WHERE sp.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    private function findOffer(int $id): ?array
    {
        return $this->row(
            'SELECT o.*, s.business_name AS supplier_name, cp.name AS product_name
             FROM offers o
             LEFT JOIN suppliers s ON s.id = o.supplier_id
             LEFT JOIN catalog_products cp ON cp.id = o.catalog_product_id
             WHERE o.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    private function findOrder(int $id): ?array
    {
        $order = $this->row(
            'SELECT o.*, b.store_name, b.buyer_name, b.phone AS buyer_phone, b.city AS buyer_city, b.address AS buyer_address,
                    s.business_name, s.owner_name, s.phone AS supplier_phone
             FROM orders o
             JOIN buyers b ON b.id = o.buyer_id
             JOIN suppliers s ON s.id = o.supplier_id
             WHERE o.id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$order) {
            return null;
        }
        $order['delivery_address'] = $order['buyer_address'] ?? '';
        $order['items'] = $this->orderItemsPayload($id);
        $order['chat_thread_id'] = (int) DB::table('chat_threads')
            ->where('buyer_id', (int) $order['buyer_id'])
            ->where('supplier_id', (int) $order['supplier_id'])
            ->value('id');
        return $order;
    }

    private function orderItemsPayload(int $orderId): array
    {
        return $this->rows(
            'SELECT oi.supplier_product_id,
                    COALESCE(NULLIF(oi.product_name, ""), cp.name, "Product item") AS product_name,
                    COALESCE(NULLIF(oi.unit_label, ""), cp.unit_type, "") AS unit_label,
                    cp.packaging,
                    oi.quantity,
                    oi.unit_price,
                    oi.line_total,
                    sp.sku
             FROM order_items oi
             LEFT JOIN supplier_products sp ON sp.id = oi.supplier_product_id
             LEFT JOIN catalog_products cp ON cp.id = sp.catalog_product_id
             WHERE oi.order_id = :order_id
             ORDER BY oi.id ASC',
            ['order_id' => $orderId]
        );
    }

    private function findThread(int $id): ?array
    {
        $thread = $this->row(
            'SELECT t.*, b.store_name, b.buyer_name, s.business_name, s.owner_name
             FROM chat_threads t
             JOIN buyers b ON b.id = t.buyer_id
             JOIN suppliers s ON s.id = t.supplier_id
             WHERE t.id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$thread) {
            return null;
        }
        $thread['messages'] = $this->rows(
            'SELECT * FROM chat_messages WHERE thread_id = :thread_id ORDER BY created_at ASC, id ASC',
            ['thread_id' => $id]
        );
        return $thread;
    }

    private function ensureBuyerSupplierThread(int $buyerId, int $supplierId): int
    {
        if ($buyerId <= 0 || $supplierId <= 0) {
            $this->fail('Buyer and supplier are required.');
        }
        if (!DB::table('buyers')->where('id', $buyerId)->exists() || !DB::table('suppliers')->where('id', $supplierId)->exists()) {
            $this->fail('Buyer or supplier not found.', 404);
        }

        $existing = DB::table('chat_threads')
            ->where('buyer_id', $buyerId)
            ->where('supplier_id', $supplierId)
            ->first();
        if ($existing) {
            return (int) $existing->id;
        }

        return $this->persist('chat_threads', [
            'buyer_id' => $buyerId,
            'supplier_id' => $supplierId,
            'subject' => 'Order chat',
            'last_message' => '',
            'last_message_at' => now(),
            'buyer_unread_count' => 0,
            'supplier_unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function orderPayloadFromItems(int $buyerId, int $supplierId, $items, string $notes, float $deliveryFee): array
    {
        if (!is_array($items) || empty($items)) {
            $this->fail('Order items are required.');
        }

        $prepared = [];
        $subtotal = 0.0;
        foreach ($items as $item) {
            if (is_object($item)) {
                $item = (array) $item;
            }
            $listingId = (int) ($item['supplier_product_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $listing = $this->findListing($listingId);
            if (!$listing || (int) $listing['supplier_id'] !== $supplierId) {
                $this->fail('Invalid supplier product in order items.');
            }
            if ((int) ($listing['stock_quantity'] ?? 0) < $quantity) {
                $this->fail($listing['catalog_name'] . ' does not have enough stock for this order.');
            }
            $pricing = $this->hybridListingPrice($listing, $quantity);
            $lineTotal = $pricing['line_total'];
            $subtotal += $lineTotal;
            $prepared[] = [
                'supplier_product_id' => $listingId,
                'product_name' => $listing['catalog_name'],
                'unit_label' => $listing['unit_type'],
                'quantity' => $quantity,
                'unit_price' => $pricing['display_unit_price'],
                'line_total' => $lineTotal,
            ];
        }

        $commissionPercentage = max(0.0, (float) $this->settingValue('admin_commission_percentage', '0'));
        $totalAmount = $subtotal + $deliveryFee;

        return [
            'order_number' => 'MW-' . now()->format('Ymd') . '-' . random_int(100, 999),
            'buyer_id' => $buyerId,
            'supplier_id' => $supplierId,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'total_amount' => $totalAmount,
            'admin_commission_percentage' => $commissionPercentage,
            'admin_commission_amount' => round($totalAmount * ($commissionPercentage / 100), 2),
            'notes' => $notes,
            'status' => 'pending',
            'status_reason' => 'Waiting for supplier confirmation.',
            'payment_status' => 'pending',
            'items' => $prepared,
        ];
    }

    private function hybridListingPrice(array $listing, int $quantity): array
    {
        $originalPrice = (float) $listing['price'];
        $offer = $this->activeOfferForListing($listing);
        $offerPrice = $offer ? (float) ($offer['offer_price'] ?? 0) : 0.0;
        $maxOfferQuantity = $offer ? (int) ($offer['maximum_quantity'] ?? 0) : 0;

        if ($offerPrice <= 0 || $offerPrice >= $originalPrice) {
            return [
                'line_total' => $originalPrice * $quantity,
                'display_unit_price' => $originalPrice,
                'offer_quantity' => 0,
                'regular_quantity' => $quantity,
            ];
        }

        $offerQuantity = $maxOfferQuantity > 0 ? min($quantity, $maxOfferQuantity) : $quantity;
        $regularQuantity = max(0, $quantity - $offerQuantity);
        $lineTotal = ($offerQuantity * $offerPrice) + ($regularQuantity * $originalPrice);

        return [
            'line_total' => $lineTotal,
            'display_unit_price' => $quantity > 0 ? $lineTotal / $quantity : $offerPrice,
            'offer_quantity' => $offerQuantity,
            'regular_quantity' => $regularQuantity,
        ];
    }

    private function activeOfferForListing(array $listing): ?array
    {
        return $this->row(
            'SELECT *
             FROM offers o
             WHERE o.supplier_id = :supplier_id
               AND (o.supplier_product_id = :listing_id
                    OR (o.supplier_product_id IS NULL AND o.catalog_product_id = :catalog_product_id))
               AND o.status = "active"
               AND (o.starts_at IS NULL OR o.starts_at <= NOW())
               AND (o.ends_at IS NULL OR o.ends_at >= NOW())
             ORDER BY COALESCE(o.starts_at, o.created_at) DESC, o.id DESC
             LIMIT 1',
            [
                'supplier_id' => (int) $listing['supplier_id'],
                'listing_id' => (int) $listing['id'],
                'catalog_product_id' => (int) $listing['catalog_product_id'],
            ]
        );
    }

    private function createOrderWithItems(array $payload): int
    {
        return DB::transaction(function () use ($payload) {
            $orderData = [
                'order_number' => $payload['order_number'],
                'buyer_id' => $payload['buyer_id'],
                'supplier_id' => $payload['supplier_id'],
                'status' => $payload['status'] ?? 'pending',
                'payment_status' => $payload['payment_status'] ?? 'pending',
                'subtotal' => $payload['subtotal'],
                'delivery_fee' => $payload['delivery_fee'],
                'total_amount' => $payload['total_amount'],
                'notes' => $payload['notes'] ?? '',
                'order_date' => $payload['order_date'] ?? now()->toDateString(),
                'delivery_date' => $payload['delivery_date'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach (['status_reason', 'admin_commission_percentage', 'admin_commission_amount'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $orderData[$column] = $payload[$column] ?? null;
                }
            }

            $orderId = $this->persist('orders', $orderData);

            foreach ($payload['items'] as $item) {
                $this->persist('order_items', [
                    'order_id' => $orderId,
                    'supplier_product_id' => $item['supplier_product_id'],
                    'product_name' => $item['product_name'],
                    'unit_label' => $item['unit_label'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);
            }

            $this->ensureOrderChatThread(
                (int) $payload['buyer_id'],
                (int) $payload['supplier_id'],
                (string) $payload['order_number']
            );

            return $orderId;
        });
    }

    private function ensureOrderChatThread(int $buyerId, int $supplierId, string $orderNumber): int
    {
        $buyer = $this->findBuyer($buyerId);
        $supplier = $this->findSupplier($supplierId);
        $message = 'Order ' . $orderNumber . ' was placed and is waiting for supplier confirmation.';

        $existing = DB::table('chat_threads')
            ->where('buyer_id', $buyerId)
            ->where('supplier_id', $supplierId)
            ->first();

        if ($existing) {
            DB::table('chat_threads')->where('id', $existing->id)->update([
                'subject' => $existing->subject ?: 'Order chat',
                'last_message' => $message,
                'last_message_at' => now(),
                'supplier_unread_count' => ((int) $existing->supplier_unread_count) + 1,
                'updated_at' => now(),
            ]);
            $threadId = (int) $existing->id;
        } else {
            $threadId = $this->persist('chat_threads', [
                'buyer_id' => $buyerId,
                'supplier_id' => $supplierId,
                'subject' => 'Order chat',
                'last_message' => $message,
                'last_message_at' => now(),
                'buyer_unread_count' => 0,
                'supplier_unread_count' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->persist('chat_messages', [
            'thread_id' => $threadId,
            'sender_type' => 'buyer',
            'sender_name' => (string) ($buyer['store_name'] ?? 'Buyer'),
            'message_body' => $message,
            'message_type' => 'order',
            'created_at' => now(),
        ]);

        return $threadId;
    }

    private function registerBuyerDeviceToken(int $buyerId, string $firebaseToken, string $platform = 'android'): void
    {
        $firebaseToken = trim($firebaseToken);
        if ($firebaseToken === '') {
            throw new RuntimeException('Firebase token is required.');
        }
        $existing = DB::table('buyer_devices')
            ->where('buyer_id', $buyerId)
            ->where('firebase_token', $firebaseToken)
            ->first();
        $this->persist('buyer_devices', [
            'buyer_id' => $buyerId,
            'firebase_token' => $firebaseToken,
            'platform' => $platform,
            'created_at' => $existing->created_at ?? now(),
            'updated_at' => now(),
        ], $existing ? (int) $existing->id : null);
    }

    private function registerSupplierDeviceToken(int $supplierId, string $firebaseToken, string $platform = 'android'): void
    {
        $firebaseToken = trim($firebaseToken);
        if ($firebaseToken === '') {
            throw new RuntimeException('Firebase token is required.');
        }
        $existing = DB::table('supplier_devices')
            ->where('supplier_id', $supplierId)
            ->where('firebase_token', $firebaseToken)
            ->first();
        $this->persist('supplier_devices', [
            'supplier_id' => $supplierId,
            'firebase_token' => $firebaseToken,
            'platform' => $platform,
            'created_at' => $existing->created_at ?? now(),
            'updated_at' => now(),
        ], $existing ? (int) $existing->id : null);
    }

    private function buyerReferralSummaryPayload(int $buyerId): array
    {
        $code = $this->ensureBuyerReferralCode($buyerId);
        $stats = $this->row(
            'SELECT COUNT(*) AS total_claims, COALESCE(SUM(reward_amount), 0) AS earned_amount
             FROM buyer_referral_claims WHERE referrer_buyer_id = :buyer_id',
            ['buyer_id' => $buyerId]
        ) ?: ['total_claims' => 0, 'earned_amount' => 0];

        return [
            'enabled' => $this->referralProgramEnabled(),
            'referral_code' => (string) ($code['referral_code'] ?? ''),
            'reward_amount' => $this->referralRewardAmount(),
            'referee_reward_amount' => $this->referralRefereeRewardAmount(),
            'total_claims' => (int) ($stats['total_claims'] ?? 0),
            'earned_amount' => (float) ($stats['earned_amount'] ?? 0),
            'recent_claims' => $this->rows(
                'SELECT c.*, b.store_name AS referred_store_name, b.city AS referred_city
                 FROM buyer_referral_claims c
                 JOIN buyers b ON b.id = c.referred_buyer_id
                 WHERE c.referrer_buyer_id = :buyer_id
                 ORDER BY c.created_at DESC, c.id DESC
                 LIMIT 20',
                ['buyer_id' => $buyerId]
            ),
        ];
    }

    private function applyBuyerReferralCode(int $buyerId, string $referralCode): array
    {
        if (!$this->referralProgramEnabled()) {
            throw new RuntimeException('Referral program is currently disabled.');
        }
        $referralCode = strtoupper(trim($referralCode));
        if ($referralCode === '') {
            throw new RuntimeException('Referral code is required.');
        }
        $buyer = $this->findBuyer($buyerId);
        if (!$buyer) {
            throw new RuntimeException('Buyer not found.');
        }
        $referrerCode = $this->row(
            'SELECT * FROM buyer_referral_codes WHERE referral_code = :referral_code LIMIT 1',
            ['referral_code' => $referralCode]
        );
        if (!$referrerCode) {
            throw new RuntimeException('Referral code not found.');
        }
        if ((int) $referrerCode['buyer_id'] === $buyerId) {
            throw new RuntimeException('You cannot apply your own referral code.');
        }
        if ($this->row('SELECT id FROM buyer_referral_claims WHERE referred_buyer_id = :id LIMIT 1', ['id' => $buyerId])) {
            throw new RuntimeException('A referral code was already applied to this buyer account.');
        }

        $claimId = $this->persist('buyer_referral_claims', [
            'referrer_buyer_id' => (int) $referrerCode['buyer_id'],
            'referred_buyer_id' => $buyerId,
            'referral_code' => $referralCode,
            'used_by_phone' => $this->normalizePhone((string) ($buyer['phone'] ?? '')),
            'reward_amount' => $this->referralRewardAmount(),
            'referee_reward_amount' => $this->referralRefereeRewardAmount(),
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->row(
            'SELECT c.*, rb.store_name AS referrer_store_name
             FROM buyer_referral_claims c
             JOIN buyers rb ON rb.id = c.referrer_buyer_id
             WHERE c.id = :id LIMIT 1',
            ['id' => $claimId]
        ) ?? [];
    }

    private function ensureBuyerReferralCode(int $buyerId): array
    {
        $existing = $this->row('SELECT * FROM buyer_referral_codes WHERE buyer_id = :buyer_id LIMIT 1', ['buyer_id' => $buyerId]);
        if ($existing) {
            return $existing;
        }
        $buyer = $this->findBuyer($buyerId);
        if (!$buyer) {
            throw new RuntimeException('Buyer not found.');
        }
        $candidate = $this->generateReferralCodeSeed($buyer);
        $counter = 1;
        while ($this->row('SELECT id FROM buyer_referral_codes WHERE referral_code = :referral_code LIMIT 1', ['referral_code' => $candidate])) {
            $candidate = $this->generateReferralCodeSeed($buyer) . $counter;
            $counter++;
        }
        $id = $this->persist('buyer_referral_codes', [
            'buyer_id' => $buyerId,
            'referral_code' => $candidate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return $this->row('SELECT * FROM buyer_referral_codes WHERE id = :id LIMIT 1', ['id' => $id]) ?? [];
    }

    private function createOtpRequestRecord(string $role, string $purpose, string $phone, array $payload = []): array
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes($this->otpExpiryMinutes())->format('Y-m-d H:i:s');
        $channel = strtolower($this->settingValue('otp_delivery_channel', 'sms'));
        $provider = strtolower($this->settingValue('otp_provider', 'demo'));

        DB::table('otp_requests')
            ->where('user_role', $role)
            ->where('purpose', $purpose)
            ->where('phone', $normalizedPhone)
            ->whereNull('consumed_at')
            ->whereIn('status', ['pending', 'sent'])
            ->update([
                'status' => 'superseded',
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

        $requestId = $this->persist('otp_requests', [
            'user_role' => $role,
            'purpose' => $purpose,
            'phone' => $normalizedPhone,
            'channel' => $channel,
            'provider' => $provider,
            'code_hash' => hash('sha256', $code),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'verified_at' => null,
            'consumed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delivery = $this->deliverOtpCode($normalizedPhone, $code);
        $this->persist('otp_requests', [
            'status' => !empty($delivery['delivered']) ? 'sent' : 'pending',
            'delivery_response' => json_encode($delivery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ], $requestId);

        return [
            'id' => $requestId,
            'phone' => $normalizedPhone,
            'expires_at' => $expiresAt,
            'delivery' => $delivery,
            'debug_code' => $delivery['debug_code'] ?? null,
        ];
    }

    private function verifyOtpRequestCode(string $role, string $purpose, string $phone, string $code): array
    {
        $request = DB::table('otp_requests')
            ->where('user_role', $role)
            ->where('purpose', $purpose)
            ->where('phone', $this->normalizePhone($phone))
            ->whereNull('consumed_at')
            ->whereIn('status', ['pending', 'sent'])
            ->orderByDesc('id')
            ->first();
        if (!$request) {
            throw new RuntimeException('No active OTP request found. Please request a new code.');
        }
        $request = (array) $request;
        if (strtotime((string) $request['expires_at']) < time()) {
            $this->persist('otp_requests', ['status' => 'expired', 'updated_at' => now()], (int) $request['id']);
            throw new RuntimeException('OTP expired. Please request a new code.');
        }
        if (!hash_equals((string) $request['code_hash'], hash('sha256', trim($code)))) {
            throw new RuntimeException('Invalid OTP code.');
        }
        $this->persist('otp_requests', [
            'status' => 'verified',
            'verified_at' => now(),
            'consumed_at' => now(),
            'updated_at' => now(),
        ], (int) $request['id']);

        $payload = json_decode((string) ($request['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return ['request' => $request, 'payload' => $payload];
    }

    private function deliverOtpCode(string $phone, string $code): array
    {
        $provider = strtolower($this->settingValue('otp_provider', 'demo'));
        $expiry = $this->otpExpiryMinutes();
        $template = $this->settingValue('otp_message_template', 'Your Muhalli verification code is {{CODE}}. It expires in {{MINUTES}} minutes.');
        $message = str_replace(['{{CODE}}', '{{MINUTES}}'], [$code, (string) $expiry], $template);

        if ($provider === 'brqsms') {
            return $this->sendBrqSmsOtp($phone, $message, $code);
        }

        $logPath = public_path('uploads/otp-debug.log');
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0775, true);
        }
        file_put_contents($logPath, sprintf("[%s] %s => %s\n", now()->format('Y-m-d H:i:s'), $phone, $message), FILE_APPEND);

        return [
            'provider' => $provider,
            'delivered' => false,
            'debug_code' => $code,
            'message' => $message,
        ];
    }

    private function sendBrqSmsOtp(string $phone, string $message, string $code): array
    {
        $url = $this->settingValue('otp_api_url', 'https://dash.brqsms.com/api/http/sms/send');
        $token = trim($this->settingValue('otp_api_token', ''));
        $senderId = trim($this->settingValue('otp_sender_id', ''));
        $recipient = ltrim(trim($phone), '+');
        if ($token === '') {
            return ['provider' => 'brqsms', 'delivered' => false, 'debug_code' => $code, 'message' => $message, 'warning' => 'BRQSMS token is missing.'];
        }
        if ($senderId === '') {
            return ['provider' => 'brqsms', 'delivered' => false, 'debug_code' => $code, 'message' => $message, 'warning' => 'BRQSMS sender ID is missing.'];
        }

        $response = Http::acceptJson()->timeout(20)->post($url, [
            'api_token' => $token,
            'recipient' => $recipient,
            'sender_id' => $senderId,
            'type' => 'plain',
            'message' => $message,
        ]);
        $decoded = $response->json() ?: [];
        $providerStatus = strtolower((string) ($decoded['status'] ?? ''));
        $delivered = $response->successful() && $providerStatus === 'success';

        return [
            'provider' => 'brqsms',
            'delivered' => $delivered,
            'status' => $response->status(),
            'body' => $response->body(),
            'provider_status' => $providerStatus,
            'provider_message' => (string) ($decoded['message'] ?? ''),
            'debug_code' => $delivered ? null : $code,
        ];
    }

    private function generatedAppEmail(string $role, string $phone): string
    {
        $normalized = preg_replace('/[^0-9]+/', '', $phone) ?: (string) Str::uuid();
        return $role . '+' . $normalized . '@muhalli.local';
    }

    private function findBuyerByEmail(string $email): ?array
    {
        return $this->row('SELECT * FROM buyers WHERE email = :email LIMIT 1', ['email' => $email]);
    }

    private function findBuyerByPhone(string $phone): ?array
    {
        return $this->row('SELECT * FROM buyers WHERE phone = :phone LIMIT 1', ['phone' => $this->normalizePhone($phone)]);
    }

    private function findSupplierByEmail(string $email): ?array
    {
        return $this->row('SELECT * FROM suppliers WHERE email = :email LIMIT 1', ['email' => $email]);
    }

    private function findSupplierByPhone(string $phone): ?array
    {
        return $this->row('SELECT * FROM suppliers WHERE phone = :phone LIMIT 1', ['phone' => $this->normalizePhone($phone)]);
    }

    private function issueApiToken(string $userType, int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        DB::table('api_tokens')->insert([
            'user_type' => $userType,
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);
        return $token;
    }

    private function identity(Request $request, ?string $expectedType = null): ?array
    {
        $header = (string) $request->header('Authorization', '');
        $token = preg_match('/Bearer\s+(.+)/i', $header, $matches) ? trim($matches[1]) : '';
        if ($token === '') {
            return null;
        }
        $query = DB::table('api_tokens')
            ->where('token', $token)
            ->where(function ($builder) {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->orderByDesc('id');
        if ($expectedType !== null) {
            $query->where('user_type', $expectedType);
        }
        $record = $query->first();
        return $record ? (array) $record : null;
    }

    private function requireIdentity(Request $request, string $expectedType): array
    {
        if ($identity = $this->identity($request, $expectedType)) {
            return $identity;
        }
        $fallbackId = (int) $this->value($request, $expectedType . '_id', 0);
        if ($fallbackId > 0) {
            return ['user_type' => $expectedType, 'user_id' => $fallbackId];
        }
        $this->fail('Unauthorized. Provide a bearer token or ' . $expectedType . '_id.', 401);
    }

    private function settingsMap(?string $group = null): array
    {
        $query = DB::table('settings')->orderBy('setting_group')->orderBy('id');
        if ($group !== null) {
            $query->where('setting_group', $group);
        }
        $map = [];
        foreach ($query->get() as $row) {
            $map[$row->setting_key] = $row->setting_value;
        }
        return $map;
    }

    private function settingValue(string $key, string $default = ''): string
    {
        $value = DB::table('settings')->where('setting_key', $key)->value('setting_value');
        return $value === null ? $default : (string) $value;
    }

    private function currencyAmountLabel(float $amount): string
    {
        $currency = trim($this->settingValue('default_currency', 'PKR')) ?: 'PKR';
        $formatted = rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');

        return $formatted . ' ' . $currency;
    }

    private function paginationParams(Request $request, int $defaultLimit = 50, int $maxLimit = 100): array
    {
        $page = max(1, (int) $this->value($request, 'page', 1));
        $limit = max(1, min($maxLimit, (int) $this->value($request, 'limit', $defaultLimit)));

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    private function limitSql(array $pagination): string
    {
        $limit = (int) ($pagination['limit'] ?? 0);
        if ($limit <= 0) {
            return '';
        }

        $offset = max(0, (int) ($pagination['offset'] ?? 0));
        return ' LIMIT ' . $limit . ' OFFSET ' . $offset;
    }

    private function referralProgramEnabled(): bool
    {
        return $this->settingValue('referral_enabled', '1') === '1';
    }

    private function referralRewardAmount(): float
    {
        return (float) $this->settingValue('referral_reward_amount', '20');
    }

    private function referralRefereeRewardAmount(): float
    {
        return (float) $this->settingValue('referral_referee_reward_amount', '10');
    }

    private function generateReferralCodeSeed(array $buyer): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string) ($buyer['store_name'] ?? 'BUYER'))) ?: 'BUYER', 0, 4));
        $base = str_pad($base, 4, 'X');
        return 'MUH' . str_pad((string) ((int) ($buyer['id'] ?? 0)), 4, '0', STR_PAD_LEFT) . $base;
    }

    private function otpExpiryMinutes(): int
    {
        return max(1, (int) $this->settingValue('otp_expiry_minutes', '10'));
    }

    private function buyerAuthPayload(array $buyer): array
    {
        return $this->buyerPublicPayload($buyer) + [
            'preferred_language' => $buyer['preferred_language'],
        ];
    }

    private function buyerPublicPayload(array $buyer): array
    {
        return [
            'id' => (int) $buyer['id'],
            'store_name' => $buyer['store_name'],
            'buyer_name' => $buyer['buyer_name'],
            'phone' => $buyer['phone'] ?? '',
            'city' => $buyer['city'],
        ];
    }

    private function supplierAuthPayload(array $supplier): array
    {
        return $this->supplierPublicPayload($supplier) + [
            'status' => $supplier['status'],
        ];
    }

    private function supplierPublicPayload(array $supplier): array
    {
        return [
            'id' => (int) $supplier['id'],
            'business_name' => $supplier['business_name'],
            'owner_name' => $supplier['owner_name'],
            'phone' => $supplier['phone'] ?? '',
            'city' => $supplier['city'],
        ];
    }

    private function rows(string $sql, array $bindings = []): array
    {
        [$sql, $bindings] = $this->expandNamedBindings($sql, $bindings);
        return array_map(static fn ($row) => (array) $row, DB::select($sql, $bindings));
    }

    private function row(string $sql, array $bindings = []): ?array
    {
        $rows = $this->rows($sql, $bindings);
        return $rows[0] ?? null;
    }

    private function persist(string $table, array $data, ?int $id = null): int
    {
        if ($id === null) {
            return (int) DB::table($table)->insertGetId($data);
        }
        DB::table($table)->where('id', $id)->update($data);
        return $id;
    }

    private function expandNamedBindings(string $sql, array $bindings): array
    {
        if ($bindings === []) {
            return [$sql, $bindings];
        }

        $counts = [];
        $expanded = $bindings;
        $sql = preg_replace_callback('/(?<!:):([A-Za-z_][A-Za-z0-9_]*)/', function (array $matches) use (&$counts, &$expanded, $bindings) {
            $name = $matches[1];
            if (!array_key_exists($name, $bindings)) {
                return $matches[0];
            }

            $counts[$name] = ($counts[$name] ?? 0) + 1;
            if ($counts[$name] === 1) {
                return $matches[0];
            }

            $newName = $name . '_' . $counts[$name];
            $expanded[$newName] = $bindings[$name];
            return ':' . $newName;
        }, $sql);

        return [$sql, $expanded];
    }

    private function value(Request $request, string $key, $default = null)
    {
        return $request->input($key, $request->query($key, $default));
    }

    private function requireMethod(Request $request, string $method): void
    {
        if (!$request->isMethod($method)) {
            $this->fail('Method not allowed.', 405);
        }
    }

    private function ok($data = null, string $message = 'OK', int $status = 200)
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    private function fail(string $message, int $status = 422): void
    {
        throw new HttpResponseException($this->failResponse($message, $status));
    }

    private function failResponse(string $message, int $status = 422)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';
        if ($normalized === '') {
            return $normalized;
        }

        if ($normalized[0] === '+') {
            return $normalized;
        }

        // Auto-fill the default region's country code (Sudan, 249) when the
        // user typed a bare local number, so the app never has to force a
        // country-code picker on the phone input.
        $defaultCountryCode = (string) $this->settingValue('default_phone_country_code', '249');
        $digits = $normalized;

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = $defaultCountryCode . substr($digits, 1);
        } elseif (strlen($digits) === 9 && !str_starts_with($digits, $defaultCountryCode)) {
            $digits = $defaultCountryCode . $digits;
        }

        return '+' . $digits;
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    private function nullableEmail($value): ?string
    {
        $email = trim((string) $value);
        return $email === '' ? null : $email;
    }

    private function optionalString(Request $request, string $key, ?string $default = null): ?string
    {
        $value = trim((string) $this->value($request, $key, ''));
        return $value === '' ? $default : $value;
    }

    private function optionalInt(Request $request, string $key, ?int $default = null): ?int
    {
        $value = trim((string) $this->value($request, $key, ''));
        if ($value === '') {
            return $default;
        }
        return is_numeric($value) ? (int) $value : $default;
    }

    private function optionalFloat(Request $request, string $key, ?float $default = null): ?float
    {
        $value = trim((string) $this->value($request, $key, ''));
        if ($value === '') {
            return $default;
        }
        return is_numeric($value) ? (float) $value : $default;
    }

    private function storeDataUrlImage(string $dataUrl, string $folder): string
    {
        if (!preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/i', $dataUrl, $matches)) {
            throw new RuntimeException('Invalid image data.');
        }
        $extension = strtolower($matches[1]) === 'jpeg' ? 'jpg' : strtolower($matches[1]);
        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            throw new RuntimeException('Invalid image data.');
        }
        $directory = public_path('uploads/' . trim($folder, '/'));
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $filename = Str::uuid() . '.' . $extension;
        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $binary);
        return '/uploads/' . trim($folder, '/') . '/' . $filename;
    }

    private function storeDataUrlAudio(string $dataUrl, string $folder): string
    {
        if (!preg_match('/^data:(audio\/[a-z0-9.+-]+);base64,(.+)$/i', $dataUrl, $matches)) {
            throw new RuntimeException('Invalid audio data.');
        }
        $mimeType = strtolower($matches[1]);
        $binary = base64_decode($matches[2], true);
        if ($binary === false) {
            throw new RuntimeException('Invalid audio data.');
        }
        $extension = match (true) {
            str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'm4a') || str_contains($mimeType, 'mp4') => 'm4a',
            str_contains($mimeType, 'aac') => 'aac',
            default => 'm4a',
        };
        $directory = public_path('uploads/' . trim($folder, '/'));
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $filename = Str::uuid() . '.' . $extension;
        file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $binary);
        return '/uploads/' . trim($folder, '/') . '/' . $filename;
    }
}



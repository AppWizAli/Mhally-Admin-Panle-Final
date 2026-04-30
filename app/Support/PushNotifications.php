<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PushNotifications
{
    public static function dispatchAppNotification(int $notificationId): void
    {
        $notification = DB::table('app_notifications')->where('id', $notificationId)->first();
        if (!$notification || $notification->status !== 'active') {
            return;
        }

        if (!Schema::hasTable('buyer_devices')) {
            return;
        }

        $query = DB::table('buyer_devices as d')
            ->join('buyers as b', 'b.id', '=', 'd.buyer_id')
            ->select('d.firebase_token');

        if ($notification->target_type === 'city') {
            $query->where('b.city', $notification->target_value);
        } elseif ($notification->target_type === 'buyer') {
            $query->where('b.id', (int) $notification->target_value);
        }

        self::sendToTokens(
            $query->pluck('firebase_token')->filter()->unique()->values()->all(),
            (string) $notification->title,
            (string) $notification->message,
            [
                'navigate_to' => self::navigationTarget((string) $notification->link_type),
                'link_type' => (string) $notification->link_type,
                'link_value' => (string) $notification->link_value,
            ]
        );
    }

    public static function notifyBuyer(int $buyerId, string $title, string $message, array $data = []): int
    {
        $notificationId = (int) DB::table('app_notifications')->insertGetId([
            'title' => $title,
            'message' => $message,
            'target_type' => 'buyer',
            'target_value' => (string) $buyerId,
            'link_type' => $data['link_type'] ?? null,
            'link_value' => $data['link_value'] ?? null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::sendToBuyer($buyerId, $title, $message, $data);

        return $notificationId;
    }

    public static function notifySupplier(int $supplierId, string $title, string $message, array $data = []): void
    {
        if (!Schema::hasTable('supplier_devices')) {
            return;
        }

        $tokens = DB::table('supplier_devices')
            ->where('supplier_id', $supplierId)
            ->pluck('firebase_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        self::sendToTokens($tokens, $title, $message, $data + ['navigate_to' => 'supplier_home']);
    }

    public static function sendToBuyer(int $buyerId, string $title, string $message, array $data = []): void
    {
        if (!Schema::hasTable('buyer_devices')) {
            return;
        }

        $tokens = DB::table('buyer_devices')
            ->where('buyer_id', $buyerId)
            ->pluck('firebase_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        self::sendToTokens($tokens, $title, $message, $data + ['navigate_to' => 'home']);
    }

    private static function sendToTokens(array $tokens, string $title, string $message, array $data = []): void
    {
        $tokens = array_values(array_filter(array_unique($tokens)));
        if (empty($tokens)) {
            return;
        }

        $serverKey = trim((string) (self::setting('firebase_server_key') ?: env('FCM_SERVER_KEY', '')));
        if ($serverKey === '') {
            Log::info('Firebase server key missing; push notification stored but not sent.', [
                'token_count' => count($tokens),
                'title' => $title,
            ]);
            return;
        }

        foreach (array_chunk($tokens, 900) as $chunk) {
            try {
                Http::timeout(12)
                    ->withHeaders([
                        'Authorization' => 'key=' . $serverKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://fcm.googleapis.com/fcm/send', [
                        'registration_ids' => array_values($chunk),
                        'notification' => [
                            'title' => $title,
                            'body' => $message,
                            'sound' => 'default',
                        ],
                        'data' => $data + [
                            'title' => $title,
                            'message' => $message,
                        ],
                        'priority' => 'high',
                    ]);
            } catch (\Throwable $exception) {
                Log::warning('Firebase push send failed.', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private static function setting(string $key): ?string
    {
        return DB::table('settings')->where('setting_key', $key)->value('setting_value');
    }

    private static function navigationTarget(string $linkType): string
    {
        return match ($linkType) {
            'orders', 'order' => 'orders',
            'chats', 'chat' => 'chats',
            'cart' => 'cart',
            'categories' => 'categories',
            default => 'home',
        };
    }
}

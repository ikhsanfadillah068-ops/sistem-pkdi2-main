<?php

namespace App\Support;

use App\Models\AppNotification;

class PkdiDemoData
{
    public static function statusMeta(string $code): array
    {
        $map = config('pkdi.statuses');

        return $map[$code] ?? ['label' => $code, 'tone' => 'neutral'];
    }

    public static function roleMeta(string $role): array
    {
        $roles = config('pkdi.roles');

        return $roles[$role] ?? $roles['client'];
    }

    public static function orderSlug(string $orderNumber): string
    {
        return str_replace('/', '-', $orderNumber);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function orders(): array
    {
        return [];
    }

    public static function findOrderOrNull(string $orderNumber): ?array
    {
        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function notificationsForRole(string $role): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return AppNotification::query()
            ->where('recipient_user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(static fn (AppNotification $n): array => [
                'title' => $n->title,
                'meta' => $n->created_at?->timezone('Asia/Jakarta')->format('d M Y, H.i').' WIB',
                'tone' => 'info',
                'icon' => 'bell',
                'url' => $n->action_url ?: route($user->homeRoute()),
            ])
            ->values()
            ->all();
    }
}

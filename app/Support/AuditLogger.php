<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function record(
        string $action,
        Model|string $auditable,
        int $auditableId = 0,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?User $actor = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();
        $actor ??= $request?->user();

        $type = $auditable instanceof Model ? class_basename($auditable) : $auditable;
        $id = $auditable instanceof Model ? (int) $auditable->getKey() : $auditableId;

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'user_role' => $actor?->role ?? 'guest',
            'action' => $action,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}

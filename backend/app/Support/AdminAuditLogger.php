<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AdminAuditLogger
{
    public static function log(string $action, string $targetType, ?string $targetId = null, array $meta = [], ?string $note = null, ?Request $request = null): void
    {
        $req = $request ?? request();
        $user = auth()->user();

        if ($note !== null && $note !== '') {
            $meta['note'] = $note;
        }

        DB::table('admin_audits')->insert([
            'admin_user_id' => $user?->id,
            'actor_user_id' => $user?->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip' => $req->ip(),
            'user_agent' => substr((string) $req->userAgent(), 0, 500),
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

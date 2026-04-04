<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class NotificationTemplateService
{
    public function findActiveTemplate(string $code, ?string $channel = null): ?object
    {
        if ($channel !== null) {
            $row = DB::table('notification_templates')
                ->where('code', $code)
                ->where('channel', $channel)
                ->where('is_active', true)
                ->first();

            if ($row) {
                return $row;
            }
        }

        return DB::table('notification_templates')
            ->where('code', $code)
            ->whereNull('channel')
            ->where('is_active', true)
            ->first();
    }
}

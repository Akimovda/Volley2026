<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Единая проверка доступа к записи активности.
     * Используется в ActivityDashboardController, ActivityRecordController и blade-гейте.
     * Источник истины — здесь; менять только тут.
     */
    protected function canRecordActivity(?\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        if (!$user) return false;
        return config('activity.recording_open')
            || $user->isAdmin()
            || in_array($user->id, config('activity.recording_allowlist', []), true);
    }
}

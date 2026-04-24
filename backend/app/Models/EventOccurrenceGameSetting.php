<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Override игровых настроек на уровне occurrence.
 *
 * Семантика:
 * - Запись отсутствует → occurrence наследует все настройки от event_game_settings
 * - Запись существует, поле NULL → это конкретное поле наследуется
 * - Запись существует, поле с значением → override для этой даты
 *
 * Для получения эффективных настроек используй EventGameSettingsResolver.
 */
class EventOccurrenceGameSetting extends Model
{
    protected $table = 'event_occurrence_game_settings';

    protected $fillable = [
        'occurrence_id',

        'subtype',
        'teams_count',
        'libero_mode',
        'min_players',
        'max_players',
        'positions',

        'gender_policy',
        'gender_limited_side',
        'gender_limited_max',
        'gender_limited_positions',
        'gender_limited_reg_starts_days_before',

        'allow_girls',
        'girls_max',
    ];

    protected $casts = [
        'positions' => 'array',
        'gender_limited_positions' => 'array',

        'teams_count' => 'integer',
        'min_players' => 'integer',
        'max_players' => 'integer',

        'gender_limited_max' => 'integer',
        'gender_limited_reg_starts_days_before' => 'integer',

        'allow_girls' => 'boolean',
        'girls_max' => 'integer',
    ];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }
}

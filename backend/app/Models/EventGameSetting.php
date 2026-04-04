<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventGameSetting extends Model
{
    protected $table = 'event_game_settings';

    protected $fillable = [
        'event_id',
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

        'allow_girls' => 'boolean',
        'girls_max' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * расчет max_players
     */
    public function calculateMaxPlayers(): int
    {
        $teams = $this->teams_count ?? 2;

        $direction = $this->event->direction ?? 'classic';

        $config = config("volleyball.$direction.{$this->subtype}");

        if (!$config) {
            return 0;
        }

        $playersPerTeam = $config['players_per_team'] ?? 0;

        return $playersPerTeam * $teams;
    }

    /**
     * расчет позиций
     */
    public function calculatePositions(): array
    {
        $teams = $this->teams_count ?? 2;

        $direction = $this->event->direction ?? 'classic';

        $config = config("volleyball.$direction.{$this->subtype}");

        if (!$config || empty($config['positions'])) {
            return [];
        }

        $positions = [];

        foreach ($config['positions'] as $pos => $countPerTeam) {

            $positions[$pos] = $countPerTeam * $teams;
        
        }

        return $positions;
    }

}
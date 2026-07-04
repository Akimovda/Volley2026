<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClubOrganizerTrust extends Model
{
    public const LEVEL_PREPAID_ONLY = 'prepaid_only';
    public const LEVEL_ALLOW_ON_SITE = 'allow_on_site';
    public const LEVEL_TRUSTED = 'trusted';

    protected $table = 'club_organizer_trust';

    protected $fillable = [
        'location_id',
        'organizer_id',
        'trust_level',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }
}

<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CouponTemplate extends Model
{
    protected $fillable = [
        'organizer_id', 'name', 'description', 'event_ids',
        'valid_from', 'valid_until', 'discount_pct', 'uses_per_coupon',
        'cancel_hours_before', 'transfer_enabled',
        'issue_limit', 'issued_count', 'is_active',
    ];

    protected $casts = [
        'event_ids'        => 'array',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
        'transfer_enabled' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function coupons(): HasMany     { return $this->hasMany(Coupon::class, 'template_id'); }

    public function canIssueMore(): bool
    {
        return $this->issue_limit === null || $this->issued_count < $this->issue_limit;
    }

    public function appliesToEvent(int $eventId): bool
    {
        // Мероприятие должно принадлежать организатору этого купона
        $event = \App\Models\Event::find($eventId);
        if (!$event || (int)$event->organizer_id !== (int)$this->organizer_id) {
            return false;
        }
        // Если event_ids не задан — действует на все мероприятия этого организатора
        if (empty($this->event_ids)) return true;
        return in_array($eventId, $this->event_ids);
    }

    public function scopeActive($q) { return $q->where('is_active', true); }
}

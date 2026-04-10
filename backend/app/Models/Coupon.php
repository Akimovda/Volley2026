<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Coupon extends Model
{
    protected $fillable = [
        'user_id', 'template_id', 'organizer_id', 'code',
        'starts_at', 'expires_at',
        'uses_total', 'uses_used', 'uses_remaining',
        'status', 'issued_by', 'issue_channel',
    ];

    protected $casts = [
        'starts_at'  => 'date',
        'expires_at' => 'date',
    ];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function template(): BelongsTo { return $this->belongsTo(CouponTemplate::class); }
    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }

    public function isActive(): bool { return $this->status === 'active'; }

    public function isUsableForEvent(int $eventId): bool
    {
        if (!$this->isActive()) return false;
        if ($this->uses_remaining <= 0) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        return $this->template->appliesToEvent($eventId);
    }

    public function getDiscountPct(): int
    {
        return $this->template->discount_pct ?? 0;
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());
        return $code;
    }

    public function scopeActive($q)           { return $q->where('status', 'active'); }
    public function scopeForUser($q, int $uid) { return $q->where('user_id', $uid); }
}

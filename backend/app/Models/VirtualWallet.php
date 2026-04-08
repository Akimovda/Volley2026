<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VirtualWallet extends Model
{
    protected $fillable = ['user_id', 'organizer_id', 'balance_minor', 'currency'];

    public function user() { return $this->belongsTo(User::class); }
    public function organizer() { return $this->belongsTo(User::class, 'organizer_id'); }
    public function transactions() { return $this->hasMany(WalletTransaction::class, 'wallet_id'); }

    public function getBalanceAttribute(): float
    {
        return $this->balance_minor / 100;
    }

    public static function forUserAndOrganizer(int $userId, int $organizerId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'organizer_id' => $organizerId],
            ['balance_minor' => 0, 'currency' => 'RUB']
        );
    }

    public function credit(int $amountMinor, string $reason, ?int $eventId = null, ?int $paymentId = null): void
    {
        $this->increment('balance_minor', $amountMinor);
        $this->transactions()->create([
            'type'         => 'credit',
            'amount_minor' => $amountMinor,
            'currency'     => $this->currency,
            'reason'       => $reason,
            'event_id'     => $eventId,
            'payment_id'   => $paymentId,
        ]);
    }

    public function debit(int $amountMinor, string $reason, ?int $eventId = null, ?int $paymentId = null): bool
    {
        if ($this->balance_minor < $amountMinor) return false;
        $this->decrement('balance_minor', $amountMinor);
        $this->transactions()->create([
            'type'         => 'debit',
            'amount_minor' => $amountMinor,
            'currency'     => $this->currency,
            'reason'       => $reason,
            'event_id'     => $eventId,
            'payment_id'   => $paymentId,
        ]);
        return true;
    }
}

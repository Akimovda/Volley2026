<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Клиент арендатора (гость, бронировавший через виджет). Виден только владельцу площадки. */
class RentalClient extends Model
{
    protected $fillable = ['owner_id', 'name', 'phone', 'email', 'consent_at', 'notes'];

    protected $casts = ['consent_at' => 'datetime'];

    public function bookings(): HasMany
    {
        return $this->hasMany(CourtBooking::class);
    }

    public static function normalizePhone(string $raw): string
    {
        $d = preg_replace('/\D+/', '', $raw);
        if (strlen($d) === 11 && $d[0] === '8') {
            $d = '7' . substr($d, 1);
        }
        if (strlen($d) === 10) {
            $d = '7' . $d;
        }

        return $d;
    }

    public function phoneFormatted(): string
    {
        $p = $this->phone;

        return strlen($p) === 11
            ? '+' . $p[0] . ' (' . substr($p, 1, 3) . ') ' . substr($p, 4, 3) . '-' . substr($p, 7, 2) . '-' . substr($p, 9, 2)
            : '+' . $p;
    }
}

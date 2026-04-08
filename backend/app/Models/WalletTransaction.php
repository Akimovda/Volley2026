<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id', 'type', 'amount_minor', 'currency',
        'reason', 'event_id', 'occurrence_id', 'payment_id', 'description',
    ];

    public function wallet() { return $this->belongsTo(VirtualWallet::class); }
}

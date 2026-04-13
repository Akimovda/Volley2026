<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffLog extends Model
{
    protected $fillable = [
        'staff_user_id', 'organizer_id',
        'action', 'entity_type', 'entity_id',
        'description', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }
}

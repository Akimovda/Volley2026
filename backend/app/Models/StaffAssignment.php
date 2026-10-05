<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAssignment extends Model
{
    protected $table = 'organizer_staff';

    protected $fillable = ['staff_user_id', 'organizer_id', 'can_manage_subs'];

    protected $casts = ['can_manage_subs' => 'boolean'];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }
}

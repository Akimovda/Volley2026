<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    // Обычно таблица cities без created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'name',
        'region',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'city_id');
    }
}

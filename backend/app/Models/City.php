<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

   protected $table = 'cities';


    protected $fillable = [
        'name',
        'region',
        'timezone',   // ✅ если есть в таблице
    ];
        // app/Models/City.php
        public function locations()
    {
        return $this->hasMany(\App\Models\Location::class, 'city_id');
    }
       public function users(): HasMany
    {
        return $this->hasMany(User::class, 'city_id');
    }
}

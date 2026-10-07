<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LevelScheme extends Model
{
    protected $fillable = ['owner_type', 'owner_id', 'level', 'name_ru', 'name_en', 'short_ru', 'short_en', 'color', 'text_color'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainerProfile extends Model
{
    protected $fillable = [
        'user_id', 'specialization', 'bio', 'experience_years', 'is_public',
    ];

    protected $casts = [
        'experience_years' => 'integer',
        'is_public'         => 'boolean',
    ];

    /** HTML для вывода: старые записи (простой текст с переносами) приводим к HTML. */
    public function getBioHtmlAttribute(): string
    {
        $bio = (string) ($this->bio ?? '');
        if ($bio === '') {
            return '';
        }

        return $bio === strip_tags($bio) ? nl2br(e($bio)) : $bio;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

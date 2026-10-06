<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Итог теста на уровень (без ответов): источник, ник, дисциплина, баллы, уровень. */
class LevelTestResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['source', 'nickname', 'discipline', 'lang', 'score', 'level', 'capped'];

    protected $casts = ['capped' => 'boolean', 'created_at' => 'datetime'];
}

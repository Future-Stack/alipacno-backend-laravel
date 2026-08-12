<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faq extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'question',
        'answers',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}
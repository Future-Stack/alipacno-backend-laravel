<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiRecommendation extends Model
{
    use HasFactory;

    protected $table = 'ai_recommendations';

    protected $fillable = ['title', 'recommendation', 'priority', 'status'];


}
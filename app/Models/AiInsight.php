<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiInsight extends Model
{
    use HasFactory;

    protected $table = 'ai_insights';

    protected $fillable = ['title', 'type', 'description', 'data', 'generated_at'];


}
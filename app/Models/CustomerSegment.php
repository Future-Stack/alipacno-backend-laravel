<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    use HasFactory;

    protected $table = 'customer_segments';

    protected $fillable = ['name', 'conditions'];

    protected $casts = [
        'conditions' => 'array',
    ];

    /**
     * Scope query to search segment name.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where('name', 'like', "%{$search}%");
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerTag extends Model
{
    use HasFactory;

    protected $table = 'customer_tags';

    protected $fillable = ['name', 'color'];

    /**
     * Scope query to search tag name or color hex code.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('color', 'like', "%{$search}%");
        });
    }
}
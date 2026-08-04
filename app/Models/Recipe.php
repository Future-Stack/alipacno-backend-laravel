<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    use HasFactory;

    protected $table = 'recipes';

    protected $fillable = ['menu_item_id', 'branch_id', 'name'];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * Scope query to search recipes by name or menu item name.
     */
    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhereHas('menuItem', function ($mq) use ($search) {
                  $mq->where('name', 'like', "%{$search}%");
              });
        });
    }

    /**
     * Scope query to filter recipes by branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        if (empty($branchId)) {
            return $query;
        }

        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope query to filter recipes by menu item.
     */
    public function scopeForMenuItem($query, $menuItemId)
    {
        if (empty($menuItemId)) {
            return $query;
        }

        return $query->where('menu_item_id', $menuItemId);
    }
}
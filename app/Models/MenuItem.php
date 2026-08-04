<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory, LogsActivity, Auditable;

    protected $table = 'menu_items';

    protected $fillable = [
        'restaurant_id',
        'branch_id',
        'category_id',
        'name',
        'slug',
        'description',
        'image',
        'price',
        'original_price',
        'discount_price',
        'preparation_time',
        'calories',
        'rating',
        'review_count',
        'is_popular',
        'is_featured',
        'is_happy_hour_eligible',
        'status',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        return str_starts_with($this->image, 'http') || str_starts_with($this->image, '/')
            ? $this->image
            : asset('storage/' . $this->image);
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function sizes()
    {
        return $this->hasMany(ItemSize::class);
    }

    public function cookingPreferences()
    {
        return $this->hasMany(CookingPreference::class);
    }

    public function spiceLevels()
    {
        return $this->hasMany(SpiceLevel::class);
    }

    public function toppings()
    {
        return $this->hasMany(Topping::class);
    }

    public function recipes()
    {
        return $this->hasMany(Recipe::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
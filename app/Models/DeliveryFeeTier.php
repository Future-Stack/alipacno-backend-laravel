<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryFeeTier extends Model
{
    use HasFactory;

    protected $table = 'delivery_fee_tiers';

    protected $fillable = [
        'name',
        'min_distance_miles',
        'max_distance_miles',
        'fee',
        'is_active',
    ];

    protected $casts = [
        'min_distance_miles' => 'float',
        'max_distance_miles' => 'float',
        'fee' => 'float',
        'is_active' => 'boolean',
    ];

    /**
     * Determine the driver delivery fee based on distance in miles.
     */
    public static function calculateFeeForDistance(float $distanceMiles): float
    {
        $tier = static::where('is_active', true)
            ->where('min_distance_miles', '<=', $distanceMiles)
            ->where(function ($query) use ($distanceMiles) {
                $query->whereNull('max_distance_miles')
                    ->orWhere('max_distance_miles', '>=', $distanceMiles);
            })
            ->orderBy('min_distance_miles', 'desc')
            ->first();

        if ($tier) {
            return (float) $tier->fee;
        }

        // Fallback default calculation if tier is not explicitly found
        if ($distanceMiles <= 3) {
            return 1.00;
        } elseif ($distanceMiles <= 5) {
            return 2.00;
        }
        return 3.00;
    }
}

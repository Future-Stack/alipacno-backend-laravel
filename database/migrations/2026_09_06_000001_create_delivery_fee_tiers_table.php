<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('min_distance_miles', 8, 2)->default(0.00);
            $table->decimal('max_distance_miles', 8, 2)->nullable(); // Null means no upper limit (e.g., 6+ miles)
            $table->decimal('fee', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default initial tiers (0-3 mi: £1.00, 3-5 mi: £2.00, 5+ mi: £3.00)
        DB::table('delivery_fee_tiers')->insert([
            [
                'name' => '0-3 Miles Tier',
                'min_distance_miles' => 0.00,
                'max_distance_miles' => 3.00,
                'fee' => 1.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '3-5 Miles Tier',
                'min_distance_miles' => 3.00,
                'max_distance_miles' => 5.00,
                'fee' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '5+ Miles Tier',
                'min_distance_miles' => 5.00,
                'max_distance_miles' => null,
                'fee' => 3.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_fee_tiers');
    }
};

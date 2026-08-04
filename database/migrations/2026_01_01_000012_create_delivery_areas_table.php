<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->string('postcode');
            $table->decimal('delivery_fee', 8, 2)->default(0);
            $table->decimal('minimum_order', 8, 2)->default(0);
            $table->integer('estimated_delivery_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');
    }
};
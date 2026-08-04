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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('driver_id')->nullable();
            $table->enum('delivery_status', ['assigned', 'picked_up', 'on_the_way', 'delivered', 'failed'])->default('assigned');
            $table->timestamp('pickup_time')->nullable();
            $table->timestamp('delivered_time')->nullable();
            $table->timestamp('estimated_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
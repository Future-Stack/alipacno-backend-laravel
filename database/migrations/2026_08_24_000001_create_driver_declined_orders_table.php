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
        Schema::create('driver_declined_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_declined_orders');
    }
};

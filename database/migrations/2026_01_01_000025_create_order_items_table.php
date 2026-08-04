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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id');
            $table->foreignId('menu_item_id');
            $table->string('item_name');
            $table->foreignId('size_id')->nullable();
            $table->string('size_name')->nullable();
            $table->foreignId('cooking_preference_id')->nullable();
            $table->string('cooking_preference')->nullable();
            $table->foreignId('spice_level_id')->nullable();
            $table->string('spice_level')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('subtotal', 8, 2);
            $table->text('special_instructions')->nullable();
            $table->text('options_summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
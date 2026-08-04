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
        Schema::create('stock_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->foreignId('inventory_item_id');
            $table->foreignId('converted_item_id');
            $table->decimal('quantity', 10, 2);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_conversions');
    }
};
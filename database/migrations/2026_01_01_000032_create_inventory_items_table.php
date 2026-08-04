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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->foreignId('category_id');
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('minimum_stock', 10, 2)->default(0);
            $table->string('unit');
            $table->decimal('purchase_price', 8, 2);
            $table->decimal('selling_price', 8, 2)->nullable();
            $table->enum('status', ['in_stock', 'low_stock', 'out_of_stock'])->default('in_stock');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
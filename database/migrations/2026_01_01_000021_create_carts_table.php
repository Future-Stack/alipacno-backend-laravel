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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('session_id')->nullable()->index();
            $table->foreignId('restaurant_id')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->enum('order_type', ['delivery', 'collection', 'dine_in', 'table', 'table_order'])->default('delivery');
            $table->string('delivery_postcode')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tip', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
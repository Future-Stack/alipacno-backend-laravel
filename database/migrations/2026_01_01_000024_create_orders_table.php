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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('restaurant_id')->nullable();
            $table->foreignId('branch_id')->nullable();
            $table->foreignId('address_id')->nullable();
            $table->foreignId('table_id')->nullable();
            $table->foreignId('reservation_id')->nullable();
            $table->enum('order_type', ['delivery', 'collection', 'dine_in', 'table', 'table_order'])->default('delivery');
            $table->enum('order_status', ['pending', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'refunded'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('payment_method', ['stripe', 'cash', 'card', 'digital', 'apple_pay', 'google_pay'])->default('cash');
            $table->string('order_source')->default('online');
            $table->foreignId('assigned_staff_id')->nullable();
            $table->foreignId('assigned_driver_id')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('vat', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tip', 10, 2)->default(0);
            $table->decimal('rider_tip', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->integer('loyalty_points_earned')->default(0);
            $table->integer('loyalty_points_used')->default(0);
            $table->timestamp('estimated_delivery_time')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
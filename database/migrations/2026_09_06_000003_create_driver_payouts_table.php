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
        Schema::create('driver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->integer('year')->nullable();
            $table->integer('week_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('hours_worked', 8, 2)->nullable()->default(0.00);
            $table->decimal('hourly_rate', 8, 2)->nullable()->default(0.00);
            $table->decimal('hourly_earnings', 10, 2)->nullable()->default(0.00);
            $table->decimal('delivery_fees', 10, 2)->nullable()->default(0.00);
            $table->decimal('tips', 10, 2)->nullable()->default(0.00);
            $table->decimal('gross_earnings', 10, 2)->nullable()->default(0.00);
            $table->decimal('cash_collected', 10, 2)->nullable()->default(0.00);
            $table->decimal('net_payout', 10, 2)->nullable()->default(0.00);
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
            $table->string('stripe_payout_id')->nullable();
            $table->string('stripe_transfer_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_payouts');
    }
};

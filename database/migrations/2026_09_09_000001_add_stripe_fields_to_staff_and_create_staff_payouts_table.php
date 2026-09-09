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
        Schema::table('staff', function (Blueprint $table) {
            $table->string('stripe_account_id')->nullable()->after('commission');
            $table->boolean('stripe_onboarding_completed')->nullable()->default(false)->after('stripe_account_id');
        });

        Schema::create('staff_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('hours_worked', 8, 2)->nullable()->default(0.00);
            $table->decimal('hourly_rate', 8, 2)->nullable()->default(0.00);
            $table->decimal('gross_earnings', 10, 2)->nullable()->default(0.00);
            $table->decimal('net_payout', 10, 2)->nullable()->default(0.00);
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending');
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
        Schema::dropIfExists('staff_payouts');

        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['stripe_account_id', 'stripe_onboarding_completed']);
        });
    }
};

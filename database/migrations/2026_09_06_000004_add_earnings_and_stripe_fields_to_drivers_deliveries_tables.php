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
        Schema::table('drivers', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable()->default(0.00)->after('status');
            $table->string('stripe_account_id')->nullable()->after('hourly_rate');
            $table->boolean('stripe_onboarding_completed')->nullable()->default(false)->after('stripe_account_id');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('driver_shift_id')->nullable()->after('driver_id')->constrained('driver_shifts')->nullOnDelete();
            $table->decimal('distance_miles', 8, 2)->nullable()->default(0.00)->after('estimated_time');
            $table->decimal('driver_fee', 10, 2)->nullable()->default(0.00)->after('distance_miles');
            $table->boolean('is_cod')->nullable()->default(false)->after('driver_fee');
            $table->decimal('cash_collected', 10, 2)->nullable()->default(0.00)->after('is_cod');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'stripe_account_id', 'stripe_onboarding_completed']);
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['driver_shift_id']);
            $table->dropColumn(['driver_shift_id', 'distance_miles', 'driver_fee', 'is_cod', 'cash_collected']);
        });
    }
};

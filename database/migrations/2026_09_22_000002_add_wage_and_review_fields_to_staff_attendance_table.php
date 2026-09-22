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
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable();
            $table->decimal('shift_earnings', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();

            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('payout_id')->references('id')->on('staff_payouts')->restrictOnDelete();
        });

        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->index('payout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['payout_id']);
            $table->dropIndex(['payout_id']);
            $table->dropColumn(['hourly_rate', 'shift_earnings', 'notes', 'reviewed_at', 'reviewed_by', 'payout_id']);
        });
    }
};

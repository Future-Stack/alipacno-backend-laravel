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
        Schema::table('staff_payouts', function (Blueprint $table) {
            $table->integer('year')->nullable()->after('staff_id');
            $table->integer('week_number')->nullable()->after('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_payouts', function (Blueprint $table) {
            $table->dropColumn(['year', 'week_number']);
        });
    }
};

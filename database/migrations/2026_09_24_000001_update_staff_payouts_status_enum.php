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
            $table->string('status', 20)->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_payouts', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'paid', 'failed'])->default('pending')->change();
        });
    }
};
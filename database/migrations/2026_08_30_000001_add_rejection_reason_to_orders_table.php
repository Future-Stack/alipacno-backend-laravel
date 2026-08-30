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
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'rejection_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'rejection_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};

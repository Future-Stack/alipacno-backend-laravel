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
        if (Schema::hasTable('branch_admins') && !Schema::hasColumn('branch_admins', 'user_id')) {
            Schema::table('branch_admins', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('branch_admins') && Schema::hasColumn('branch_admins', 'user_id')) {
            Schema::table('branch_admins', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};

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
        Schema::create('digital_screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->string('screen_name');
            $table->foreignId('screen_group_id')->nullable();
            $table->string('device_uuid')->unique();
            $table->string('resolution')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['online', 'offline', 'maintenance'])->default('online');
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digital_screens');
    }
};
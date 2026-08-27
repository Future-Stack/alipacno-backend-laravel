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
        Schema::create('screen_impressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id');
            $table->foreignId('content_id');
            $table->integer('total_views')->default(0);
            $table->integer('play_count')->default(0);
            $table->integer('play_duration')->default(0);
            $table->timestamp('recorded_at');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_impressions');
    }
};
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
        Schema::create('screen_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screen_id')->nullable();
            $table->foreignId('playlist_id')->nullable();
            $table->foreignId('signage_content_id')->nullable();
            $table->string('schedule_name')->nullable();
            $table->string('title')->nullable();
            $table->string('display_type')->default('schedule_later'); // publish_now, schedule_later
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('recurrence')->default('daily'); // daily, weekdays, weekends, custom
            $table->string('repeat_type')->nullable();
            $table->json('branch_ids')->nullable();
            $table->json('screen_group_ids')->nullable();
            $table->json('screen_ids')->nullable();
            $table->integer('priority')->default(1);
            $table->enum('status', ['active', 'inactive', 'draft', 'scheduled'])->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_schedules');
    }
};
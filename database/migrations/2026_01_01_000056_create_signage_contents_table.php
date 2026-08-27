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
        Schema::create('signage_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('content_name')->nullable();
            $table->string('content_type')->default('image'); // image, video, playlist
            $table->string('campaign_tag')->nullable();
            $table->text('description')->nullable();
            $table->string('file');
            $table->string('thumbnail')->nullable();
            $table->string('resolution')->nullable();
            $table->string('file_size')->nullable();
            $table->string('dimensions')->nullable();
            $table->string('aspect_ratio')->nullable();
            $table->integer('duration')->default(15);
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
        Schema::dropIfExists('signage_contents');
    }
};
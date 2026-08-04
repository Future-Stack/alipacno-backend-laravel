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
            $table->string('content_type');
            $table->string('file');
            $table->string('thumbnail')->nullable();
            $table->integer('duration')->default(10);
            $table->enum('status', ['active', 'inactive'])->default('active');
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
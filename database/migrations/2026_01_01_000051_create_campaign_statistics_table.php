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
        Schema::create('campaign_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id');
            $table->integer('sent')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('opened')->default(0);
            $table->integer('clicked')->default(0);
            $table->integer('converted')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_statistics');
    }
};
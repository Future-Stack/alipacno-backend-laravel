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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('branch_id');
            $table->string('name');
            $table->string('phone');
            $table->string('vehicle_type')->default('Motorcycle');
            $table->string('license_number')->nullable();
            $table->string('license_image')->nullable();
            $table->enum('kyc_status', ['pending', 'submitted', 'approved', 'rejected'])->default('pending');
            $table->text('reject_reason')->nullable();
            $table->boolean('is_online')->default(false);
            $table->enum('status', ['available', 'on_delivery', 'offline'])->default('available');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // Employee Information
            $table->string('employee_id')->unique();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('email')->nullable();
            $table->string('phone');

            // Organization
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->nullable()
                ->constrained('roles')
                ->nullOnDelete();

            // Shift
            // Example: Morning 8-5, Evening 4-12, Night 12-8
            $table->string('shift')->nullable();

            // Attendance
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();

            // Staff Status
            // Example: active, inactive, on_leave, on_break, off_duty, absent
            $table->string('status')->default('active');

            // Salary & Commission
            $table->decimal('salary', 10, 2)->nullable();
            $table->decimal('commission', 5, 2)->nullable();

            // Employment
            $table->date('hire_date')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
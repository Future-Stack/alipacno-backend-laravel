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
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('staff_id')->nullable();
            $table->foreignId('order_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone');
            $table->string('postcode')->nullable();
            $table->string('call_sid')->nullable();
            $table->enum('call_type', ['incoming', 'outgoing'])->default('incoming');
            $table->enum('call_status', ['answered', 'missed', 'busy', 'cancelled'])->default('answered');
            $table->integer('call_duration')->default(0);
            $table->enum('call_outcome', ['converted', 'no_order', 'callback', 'complaint', 'inquiry', 'reservation'])->nullable();
            $table->text('notes')->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
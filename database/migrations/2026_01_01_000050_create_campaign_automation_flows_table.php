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
        Schema::create('campaign_automation_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable();
            $table->string('campaign_title')->nullable();
            $table->string('gender')->default('All Demographics');
            $table->string('postcode')->default('All Area Sector');
            $table->string('marketing_type')->default('SMS Campaign');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('period')->nullable();
            $table->text('campaign_description_details')->nullable();
            $table->string('attachment')->nullable();
            $table->string('trigger')->nullable();
            $table->string('condition')->nullable();
            $table->string('action')->nullable();
            $table->enum('status', ['active', 'inactive', 'completed', 'draft', 'running', 'cancelled'])->default('active');
            $table->integer('sent_count')->default(0);
            $table->integer('delivered_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('replies_count')->default(0);
            $table->foreignId('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_automation_flows');
    }
};
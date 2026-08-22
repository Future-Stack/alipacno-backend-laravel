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
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->enum('type', ['raw_material', 'prepared'])->default('raw_material')->after('category_id');
            $table->string('image')->nullable()->after('unit');
            $table->text('description')->nullable()->after('image');

            $table->foreignId('made_from_item_id')->nullable()->after('description')->constrained('inventory_items')->nullOnDelete();
            $table->decimal('pack_size', 10, 2)->nullable()->after('made_from_item_id');
            $table->string('pack_unit')->nullable()->after('pack_size');
            $table->decimal('yield_qty', 10, 2)->nullable()->after('pack_unit');
            $table->string('yield_unit')->nullable()->after('yield_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('made_from_item_id');
            $table->dropColumn(['type', 'image', 'description', 'pack_size', 'pack_unit', 'yield_qty', 'yield_unit']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable();
            // Money in piastres (1,250.00 EGP = 125000), tax included.
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedBigInteger('cost_price')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            // Inventory (single warehouse). Signed on purpose; the CHECK below forbids < 0.
            $table->boolean('track_inventory')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_active']);
        });

        // Last line of defence against overselling, even if application code is wrong.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE product_variants ADD CONSTRAINT product_variants_stock_non_negative CHECK (stock_quantity >= 0)');
        }

        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->restrictOnDelete();

            $table->primary(['product_variant_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_product_variant');
        Schema::dropIfExists('product_variants');
    }
};

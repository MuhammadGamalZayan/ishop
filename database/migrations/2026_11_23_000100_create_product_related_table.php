<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hand-picked suggestions for a product ("You may also like" on the product
 * page, "Complete with" in the cart). When a product has none, the
 * storefront falls back to products from the same category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_related', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);

            $table->primary(['product_id', 'related_product_id']);
            $table->index('related_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_related');
    }
};

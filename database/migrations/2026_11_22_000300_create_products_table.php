<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('short_description')->nullable();
            $table->json('description')->nullable();
            // Both languages + SKUs, rebuilt on every save; feeds the FULLTEXT search.
            $table->text('search_text')->nullable();
            $table->string('status', 32)->default('draft'); // App\Enums\ProductStatus
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index('is_featured');

            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText('search_text');
            }
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->primary(['category_id', 'product_id']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->unsignedBigInteger('shipping_price'); // piastres
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['governorate_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_areas');
    }
};

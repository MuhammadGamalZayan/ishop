<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // stored uppercase
            $table->json('description')->nullable();
            $table->string('type', 32); // App\Enums\CouponType
            $table->unsignedBigInteger('value'); // basis points or piastres, see CouponType
            $table->unsignedBigInteger('min_subtotal')->nullable();
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

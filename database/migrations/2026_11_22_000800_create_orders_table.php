<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Generated when the checkout page renders; a resubmit returns the same order.
            $table->uuid('idempotency_key')->unique();
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->string('status', 32);          // App\Enums\OrderStatus
            $table->string('payment_method', 32);  // App\Enums\PaymentMethod
            $table->string('payment_status', 32);  // App\Enums\PaymentStatus
            $table->char('currency', 3)->default('EGP');
            // Money in piastres.
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('shipping_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0); // already inside grand_total
            $table->unsignedBigInteger('grand_total');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code', 50)->nullable();
            $table->timestamp('payment_expires_at')->nullable();
            $table->text('customer_note')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'placed_at']);
            $table->index(['status', 'payment_expires_at']);
            $table->index('payment_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->json('product_name');
            $table->string('sku', 64);
            $table->json('options')->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('phone', 20);
            $table->foreignId('governorate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipping_area_id')->nullable()->constrained()->nullOnDelete();
            $table->json('governorate_name');
            $table->json('area_name');
            $table->string('street');
            $table->string('building', 50);
            $table->string('floor', 20)->nullable();
            $table->string('apartment', 20)->nullable();
            $table->string('landmark')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = system
            $table->string('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_addresses');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

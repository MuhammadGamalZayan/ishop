<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per payment attempt; only one per order ends `paid`.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 32);          // App\Enums\PaymentMethod
            $table->string('method', 32)->nullable(); // card, wallet, cash
            $table->string('status', 32);           // App\Enums\PaymentAttemptStatus
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_transaction_id']);
            $table->index(['gateway', 'gateway_order_id']);
            $table->index(['order_id', 'status']);
        });

        // Every webhook call is stored once; a duplicate delivery hits the unique key and is skipped.
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 32);
            $table->string('event_key', 191);
            $table->boolean('signature_valid');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['gateway', 'event_key']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['coupon_id', 'order_id']);
            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payments');
    }
};

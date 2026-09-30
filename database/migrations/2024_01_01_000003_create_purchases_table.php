<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();

            // Stripe references. Both are unique when present, so a replayed
            // or out-of-order webhook cannot create a second purchase. MySQL
            // and Postgres allow any number of NULLs in a unique index, which
            // is what lets a checkout session be recorded before Stripe has
            // told us anything about it.
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable()->unique();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_event_id')->nullable();

            // Customer details captured by Stripe at checkout.
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();

            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('gbp');
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per purchase, and the record of what Stripe told us about it.
 *
 * The tick box on the review page used to arrive as a separate
 * `add_terms_accepted_to_purchases` migration; its columns are declared inline
 * here instead, because folding a change into the table it belongs to is only
 * safe while there is no data anywhere that matters. From the first deploy
 * onwards, new columns go in a new migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();

            /*
             * Stripe references.
             *
             * Both are unique when present, so a replayed or out-of-order
             * webhook cannot create a second purchase. MySQL and Postgres allow
             * any number of NULLs in a unique index, which is what lets a
             * checkout session be recorded before Stripe has told us anything
             * about it.
             */
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

            /*
             * The tick box on the review page, captured against the purchase it
             * authorises. `terms_version` records which wording was agreed to,
             * so a later rewrite cannot retroactively change what a buyer
             * accepted.
             */
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_version')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

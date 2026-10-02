<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every Stripe event id we have handled.
 *
 * Stripe delivers events at least once, and retries anything it considers
 * unacknowledged. Storing every event id gives us a cheap, race-free
 * idempotency guard: the unique index below means a duplicate delivery loses
 * the insert and is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('type');
            $table->boolean('livemode')->default(false);
            $table->longText('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};

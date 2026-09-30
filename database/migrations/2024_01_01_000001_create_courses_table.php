<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('badge')->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();

            // Amount in the currency's smallest unit (pence for GBP).
            $table->unsignedInteger('price');
            $table->string('currency', 3)->default('gbp');

            // Marketing bullet points shown on the homepage and course page.
            $table->json('features')->nullable();

            // Maps to a key in config/stripe.php => ['prices'].
            $table->string('stripe_price_key')->nullable();
            // Optional per-course override of the Stripe Price ID.
            $table->string('stripe_price_id')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};

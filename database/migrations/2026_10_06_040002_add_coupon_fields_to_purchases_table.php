<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('course_id')->constrained('coupons')->nullOnDelete();
            $table->unsignedInteger('discount_amount')->default(0)->after('amount'); // in pence
            $table->string('payment_method')->nullable()->after('currency'); // 'stripe', 'bank_transfer', 'scholarship', etc.
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'discount_amount', 'payment_method']);
        });
    }
};

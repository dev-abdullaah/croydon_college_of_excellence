<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('contact_phone', 50)->nullable()->after('customer_email');
            $table->text('learner_notes')->nullable()->after('failure_reason');
            $table->text('admin_notes')->nullable()->after('learner_notes');
            $table->timestamp('requested_at')->nullable()->after('admin_notes');
            $table->timestamp('admitted_at')->nullable()->after('requested_at');
            $table->timestamp('revoked_at')->nullable()->after('admitted_at');
            $table->foreignId('admitted_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['admitted_by']);
            $table->dropColumn([
                'contact_phone',
                'learner_notes',
                'admin_notes',
                'requested_at',
                'admitted_at',
                'revoked_at',
                'admitted_by',
            ]);
        });
    }
};

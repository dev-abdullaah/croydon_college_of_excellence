<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('new_email')->nullable()->after('email');
            $table->string('email_change_token')->nullable()->after('new_email');
            $table->timestamp('email_change_token_expires_at')->nullable()->after('email_change_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['new_email', 'email_change_token', 'email_change_token_expires_at']);
        });
    }
};

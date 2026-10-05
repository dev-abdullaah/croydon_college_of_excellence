<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The login history table.
 *
 * One row per authentication attempt, successful or not. `session_id` used to
 * arrive in a separate `add_session_id_to_login_histories_table` migration; it
 * is declared inline here instead, so the table is described in one place.
 *
 * The table is singular: `login_history`. App\Models\LoginHistory therefore
 * sets `protected $table`, because the default would be the plural
 * `login_histories`.
 *
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('email');
            $table->string('ip_address', 45)->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('operating_system')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('status')->default('success'); // success, failed
            $table->timestamp('login_at')->nullable();
            $table->timestamp('logout_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};

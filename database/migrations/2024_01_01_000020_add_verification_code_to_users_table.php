<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a mailed confirmation code lives.
 *
 * The link this replaces was a signed URL: unforgeable, and long enough that
 * guessing it was hopeless. A six digit code is a different proposition - a
 * million values, and only a handful of tries allowed. So the code is stored
 * hashed rather than in the clear, and the columns that make guessing
 * expensive sit next to it:
 *
 *   - `verification_code_attempts` counts wrong answers, and the controller
 *     refuses to keep checking past a small number. This is what turns a
 *     million guesses into a dozen.
 *   - `verification_code_sent_at` is the expiry clock. Short, because unlike a
 *     link there is no reason for it to live for an hour.
 *   - `verification_code_locked_until` is the penalty after the attempts run
 *     out, so the lockout does not merely reset on the next request and get
 *     retried immediately.
 *
 * Null means "no code outstanding", which is the state for a verified account
 * and for one that has never asked for a code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('verification_code_hash')->nullable()->after('email_verified_at');
            $table->timestamp('verification_code_sent_at')->nullable()->after('verification_code_hash');
            $table->unsignedTinyInteger('verification_code_attempts')->default(0)->after('verification_code_sent_at');
            $table->timestamp('verification_code_locked_until')->nullable()->after('verification_code_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'verification_code_hash',
                'verification_code_sent_at',
                'verification_code_attempts',
                'verification_code_locked_until',
            ]);
        });
    }
};

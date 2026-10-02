<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The users table.
 *
 * This baseline was built once and is not edited afterwards. The mailed
 * verification code used to arrive as a separate `add_verification_code_to_users`
 * migration; its columns are declared inline here instead, because folding a
 * change into the table it belongs to is only safe while there is no data
 * anywhere that matters. From the first deploy onwards, new columns go in a
 * new migration.
 *
 * Note on `email`: it is unique, and has been since the table was written.
 * Registration does not lean on that index to produce "that email is taken" -
 * App\Http\Controllers\Auth\RegisterController checks the state of the
 * existing account instead, so somebody who abandoned a half-made account can
 * come back and finish it rather than being told the address is spoken for.
 * The index is the backstop that makes a second row for one address impossible
 * whatever the application layer decides to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();

            /*
             * The mailed confirmation code, stored hashed.
             *
             * The link this replaced was unforgeable and long enough that
             * guessing it was hopeless. A six digit code is a different
             * proposition - a million values, and only a handful of tries
             * allowed. So the code is hashed rather than stored in the clear,
             * and the columns that make guessing expensive sit next to it:
             *
             *   - attempts counts wrong answers, and the controller refuses to
             *     keep checking past a small number. This is what turns a
             *     million guesses into a dozen.
             *   - sent_at is the expiry clock. Short, because unlike a link
             *     there is no reason for it to live for an hour.
             *   - locked_until is the penalty once the attempts run out, so
             *     the lockout does not merely reset on the next request and
             *     get retried immediately.
             *
             * Null means "no code outstanding", which is the state for a
             * verified account and for one that never asked for a code.
             */
            $table->string('verification_code_hash')->nullable();
            $table->timestamp('verification_code_sent_at')->nullable();
            $table->unsignedTinyInteger('verification_code_attempts')->default(0);
            $table->timestamp('verification_code_locked_until')->nullable();

            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

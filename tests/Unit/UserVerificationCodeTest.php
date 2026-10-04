<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_code_is_stored_as_hash_not_plain(): void
    {
        $user = User::factory()->create();
        $code = $user->issueVerificationCode();

        $this->assertNotNull($user->verification_code_hash);
        $this->assertNotEquals($code, $user->verification_code_hash);
        $this->assertEquals(64, strlen($user->verification_code_hash)); // SHA-256 hex
    }

    public function test_correct_code_verifies_successfully(): void
    {
        $user = User::factory()->create();
        $code = $user->issueVerificationCode();

        $this->assertTrue($user->verificationCodeMatches($code));
    }

    public function test_incorrect_code_fails_verification(): void
    {
        $user = User::factory()->create();
        $user->issueVerificationCode();

        $this->assertFalse($user->verificationCodeMatches('000000'));
    }

    public function test_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $code = $user->issueVerificationCode();

        $this->assertTrue($user->verificationCodeMatches($code));

        // After first use, code is cleared
        $user->clearVerificationCode();

        $this->assertFalse($user->verificationCodeMatches($code));
        $this->assertNull($user->verification_code_hash);
    }

    public function test_failed_attempts_are_counted_and_lock_out(): void
    {
        $user = User::factory()->create();
        $user->issueVerificationCode();

        for ($i = 0; $i < 5; $i++) {
            $user->recordFailedVerificationAttempt();
        }

        $this->assertEquals(5, $user->verification_code_attempts);
        $this->assertTrue($user->verificationCodeLocked());
        $this->assertNotNull($user->verification_code_locked_until);
    }

    public function test_new_code_resets_attempts_and_lockout(): void
    {
        $user = User::factory()->create();
        $user->issueVerificationCode();

        // Fail 5 times to lock
        for ($i = 0; $i < 5; $i++) {
            $user->recordFailedVerificationAttempt();
        }

        $this->assertTrue($user->verificationCodeLocked());

        // Issue a new code directly - should reset attempts and lockout
        $user->issueVerificationCode();

        $this->assertEquals(0, $user->verification_code_attempts);
        $this->assertNull($user->verification_code_locked_until);
        $this->assertFalse($user->verificationCodeLocked());
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $user->issueVerificationCode();

        // Manually expire the code
        $user->verification_code_sent_at = now()->subMinutes(20);
        $user->save();

        $this->assertTrue($user->verificationCodeExpired());
    }
}
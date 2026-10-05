<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentVerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_code_is_stored_as_hash_not_plain(): void
    {
        $student = Student::factory()->create();
        $code = $student->issueVerificationCode();

        $this->assertNotNull($student->verification_code_hash);
        $this->assertNotEquals($code, $student->verification_code_hash);
        $this->assertEquals(64, strlen($student->verification_code_hash)); // SHA-256 hex
    }

    public function test_correct_code_verifies_successfully(): void
    {
        $student = Student::factory()->create();
        $code = $student->issueVerificationCode();

        $this->assertTrue($student->verificationCodeMatches($code));
    }

    public function test_incorrect_code_fails_verification(): void
    {
        $student = Student::factory()->create();
        $student->issueVerificationCode();

        $this->assertFalse($student->verificationCodeMatches('000000'));
    }

    public function test_code_can_only_be_used_once(): void
    {
        $student = Student::factory()->create();
        $code = $student->issueVerificationCode();

        $this->assertTrue($student->verificationCodeMatches($code));

        // After first use, code is cleared
        $student->clearVerificationCode();

        $this->assertFalse($student->verificationCodeMatches($code));
        $this->assertNull($student->verification_code_hash);
    }

    public function test_failed_attempts_are_counted_and_lock_out(): void
    {
        $student = Student::factory()->create();
        $student->issueVerificationCode();

        for ($i = 0; $i < 5; $i++) {
            $student->recordFailedVerificationAttempt();
        }

        $this->assertEquals(5, $student->verification_code_attempts);
        $this->assertTrue($student->verificationCodeLocked());
        $this->assertNotNull($student->verification_code_locked_until);
    }

    public function test_new_code_resets_attempts_and_lockout(): void
    {
        $student = Student::factory()->create();
        $student->issueVerificationCode();

        // Fail 5 times to lock
        for ($i = 0; $i < 5; $i++) {
            $student->recordFailedVerificationAttempt();
        }

        $this->assertTrue($student->verificationCodeLocked());

        // Issue a new code directly - should reset attempts and lockout
        $student->issueVerificationCode();

        $this->assertEquals(0, $student->verification_code_attempts);
        $this->assertNull($student->verification_code_locked_until);
        $this->assertFalse($student->verificationCodeLocked());
    }

    public function test_expired_code_is_rejected(): void
    {
        $student = Student::factory()->create();
        $student->issueVerificationCode();

        // Manually expire the code
        $student->verification_code_sent_at = now()->subMinutes(20);
        $student->save();

        $this->assertTrue($student->verificationCodeExpired());
    }
}
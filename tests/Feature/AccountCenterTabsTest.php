<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Models\Student;
use App\Models\StudentEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountCenterTabsTest extends TestCase
{
    use RefreshDatabase;
    private function verifiedStudent(): Student
    {
        return Student::factory()->create(['email_verified_at' => now()]);
    }

    public function test_the_active_tab_survives_a_reload(): void
    {
        $student = $this->verifiedStudent();

        // Each tab has to render itself as the open one when asked for by name,
        // because a reload is just another GET of the same URL.
        foreach (['security', 'emails', 'sessions', 'danger'] as $tab) {
            $html = $this->actingAs($student)
                ->get(route('account.center', ['tab' => $tab]))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString(
                "class=\"tab-pane fade show active\" id=\"{$tab}\"",
                $html,
                "the {$tab} pane should be the visible one"
            );

            // And exactly one pane is visible.
            $this->assertSame(
                1,
                substr_count($html, 'tab-pane fade show active'),
                "only the {$tab} pane should be visible"
            );
        }
    }

    public function test_the_address_bar_carries_the_tab(): void
    {
        $student = $this->verifiedStudent();

        $html = $this->actingAs($student)
            ->get(route('account.center', ['tab' => 'sessions']))
            ->assertOk()
            ->getContent();

        // Real links, so a shared URL and the back button work.
        $this->assertStringContainsString(route('account.center', ['tab' => 'sessions']), $html);
        $this->assertStringContainsString(route('account.center', ['tab' => 'emails']), $html);
        $this->assertStringNotContainsString('data-bs-toggle="pill"', $html);
        $this->assertStringNotContainsString('href="#sessions"', $html);
    }

    public function test_an_unknown_tab_falls_back_to_security(): void
    {
        $student = $this->verifiedStudent();

        $this->actingAs($student)
            ->get(route('account.center', ['tab' => 'not-a-tab']))
            ->assertOk()
            ->assertSee('class="tab-pane fade show active" id="security"', false);
    }

    public function test_no_tab_given_still_shows_security(): void
    {
        $student = $this->verifiedStudent();

        // The dashboard links here with no tab at all.
        $this->actingAs($student)
            ->get(route('account.center'))
            ->assertOk()
            ->assertSee('class="tab-pane fade show active" id="security"', false);
    }

    public function test_deleting_an_email_returns_to_the_emails_tab(): void
    {
        $student = $this->verifiedStudent();
        $email = StudentEmail::create([
            'student_id' => $student->id,
            'email' => 'second@example.com',
            'is_primary' => false,
            'is_verified' => true,
        ]);

        $response = $this->actingAs($student)->delete(route('account.emails.remove', $email), [
            'current_password' => 'password',
            'tab' => 'emails',
        ]);

        $response->assertRedirect(route('account.center', ['tab' => 'emails']));
        $this->assertDatabaseMissing('student_emails', ['id' => $email->id]);
    }

    public function test_a_validation_failure_also_returns_to_the_tab_it_came_from(): void
    {
        $student = $this->verifiedStudent();
        $email = StudentEmail::create([
            'student_id' => $student->id,
            'email' => 'second@example.com',
            'is_primary' => false,
            'is_verified' => true,
        ]);

        // Wrong password: the reader must not be thrown to Security to find the
        // message, because the message lives on the tab they were working in.
        $this->actingAs($student)
            ->from(route('account.center', ['tab' => 'emails']))
            ->delete(route('account.emails.remove', $email), [
                'current_password' => 'wrong',
                'tab' => 'emails',
            ])
            ->assertRedirect(route('account.center', ['tab' => 'emails']));
    }

    public function test_revoking_a_session_returns_to_the_sessions_tab(): void
    {
        $student = $this->verifiedStudent();

        $login = LoginHistory::create([
            'student_id' => $student->id,
            'email' => $student->email,
            'session_id' => 'some-other-session',
            'status' => 'success',
            'login_at' => now(),
        ]);

        $this->actingAs($student)
            ->delete(route('account.sessions.revoke', $login), [
                'current_password' => 'password',
                'tab' => 'sessions',
            ])
            ->assertRedirect(route('account.center', ['tab' => 'sessions']));
    }

    public function test_adding_an_email_returns_to_the_emails_tab(): void
    {
        $student = $this->verifiedStudent();

        $this->actingAs($student)
            ->post(route('account.emails.add', ['tab' => 'emails']), [
                'current_password' => 'password',
                'email' => 'new@example.com',
                'tab' => 'emails',
            ])
            ->assertRedirect(route('account.center', ['tab' => 'emails']));
    }

    public function test_the_verification_link_lands_on_the_emails_tab(): void
    {
        $student = $this->verifiedStudent();
        $email = StudentEmail::create([
            'student_id' => $student->id,
            'email' => 'second@example.com',
            'is_primary' => false,
            'is_verified' => false,
        ]);
        $token = $email->generateVerificationToken();

        $this->actingAs($student)->get(route('account.emails.verify', $token))
            ->assertRedirect(route('account.center', ['tab' => 'emails']));

        $this->actingAs($student)->get(route('account.emails.verify', 'not-a-real-token'))
            ->assertRedirect(route('account.center', ['tab' => 'emails']));
    }

    public function test_a_tampered_tab_field_cannot_reach_an_unlisted_tab(): void
    {
        $student = $this->verifiedStudent();

        // The tab decides what is rendered, so it is not taken on trust from
        // the form body.
        $this->actingAs($student)
            ->post(route('account.emails.add'), [
                'current_password' => 'password',
                'email' => 'new@example.com',
                'tab' => '../../evil',
            ])
            ->assertRedirect(route('account.center', ['tab' => 'security']));
    }
}
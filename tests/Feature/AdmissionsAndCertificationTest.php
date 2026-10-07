<?php

namespace Tests\Feature;

use App\Mail\AdmissionApprovedMail;
use App\Mail\AdmissionRequestedMail;
use App\Models\Admission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdmissionsAndCertificationTest extends TestCase
{
    use RefreshDatabase;

    protected function course(string $slug = 'life-in-the-uk-course'): Course
    {
        return Course::firstOrCreate(
            ['slug' => $slug],
            [
                'name'        => $slug === 'life-in-the-uk-course' ? 'Life in the UK Course' : '24 Mock Tests Package',
                'description' => 'Complete preparation course with classroom mocks and study cards.',
                'price'       => $slug === 'life-in-the-uk-course' ? 9900 : 4900,
                'currency'    => 'gbp',
                'is_active'   => true,
            ]
        );
    }

    protected function superAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    // =========================================================================
    // 1. Admission Application & Course Access Lifecycle
    // =========================================================================

    public function test_learner_can_submit_admission_application(): void
    {
        Mail::fake();

        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        $response = $this->actingAs($student)->post(route('checkout.store', $course), [
            'consent'        => '1',
            'phone'          => '07700900123',
            'payment_method' => 'bank_transfer',
            'learner_notes'  => 'I would like to start immediately.',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('purchases', [
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'status'         => Admission::STATUS_PENDING,
            'contact_phone'  => '07700900123',
            'payment_method' => 'bank_transfer',
            'learner_notes'  => 'I would like to start immediately.',
        ]);

        // Student's profile phone is updated
        $this->assertSame('07700900123', $student->fresh()->phone);

        // Student does NOT have learning access yet
        $this->assertFalse($student->fresh()->hasPurchased($course));

        // Automated notification email sent to applicant
        Mail::assertSent(AdmissionRequestedMail::class, function (AdmissionRequestedMail $mail) use ($student) {
            return $mail->hasTo($student->email);
        });
    }

    public function test_admin_can_view_admissions_index_and_filter(): void
    {
        $admin = $this->superAdmin();
        $student = Student::factory()->create(['name' => 'Alice Applicant']);
        $course = $this->course('life-in-the-uk-course');

        Admission::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'customer_name'  => $student->name,
            'customer_email' => $student->email,
            'contact_phone'  => '07700900555',
            'amount'         => $course->price,
            'currency'       => 'gbp',
            'payment_method' => 'cash',
            'status'         => Admission::STATUS_PENDING,
            'requested_at'   => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.admissions.index'));
        $response->assertOk();
        $response->assertSee('Student Admissions');
        $response->assertSee('Alice Applicant');
        $response->assertSee('07700900555');

        // Filter by pending status
        $response = $this->actingAs($admin, 'admin')->get(route('admin.admissions.index', ['status' => 'pending']));
        $response->assertOk();
        $response->assertSee('Alice Applicant');
    }

    public function test_admin_approving_admission_grants_course_access(): void
    {
        Mail::fake();

        $admin = $this->superAdmin();
        $student = Student::factory()->create(['name' => 'Bob Learner']);
        $course = $this->course('life-in-the-uk-course');

        $admission = Admission::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'customer_name'  => $student->name,
            'customer_email' => $student->email,
            'contact_phone'  => '07700900666',
            'amount'         => $course->price,
            'currency'       => 'gbp',
            'payment_method' => 'bank_transfer',
            'status'         => Admission::STATUS_PENDING,
            'requested_at'   => now(),
        ]);

        // Prior to approval: no access
        $this->actingAs($student)->get(route('learn.index', $course))->assertForbidden();

        // Admin approves admission
        $response = $this->actingAs($admin, 'admin')->post(route('admin.admissions.approve', $admission), [
            'payment_method' => 'bank_transfer',
            'admin_notes'    => 'Bank ref #1002 verified on HSBC account.',
        ]);

        $response->assertRedirect();
        $admission->refresh();

        $this->assertSame(Admission::STATUS_ADMITTED, $admission->status);
        $this->assertNotNull($admission->admitted_at);
        $this->assertSame($admin->id, $admission->admitted_by);

        // Learning area is now accessible to the student
        $this->assertTrue($student->fresh()->hasPurchased($course));
        $this->actingAs($student)->get(route('learn.index', $course))->assertOk();

        // Access approval email sent to student
        Mail::assertSent(AdmissionApprovedMail::class, function (AdmissionApprovedMail $mail) use ($student) {
            return $mail->hasTo($student->email);
        });
    }

    public function test_admin_revoking_admission_immediately_removes_course_access(): void
    {
        $admin = $this->superAdmin();
        $student = Student::factory()->create(['name' => 'Charlie Student']);
        $course = $this->course('life-in-the-uk-course');

        $admission = Admission::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'customer_name'  => $student->name,
            'customer_email' => $student->email,
            'amount'         => $course->price,
            'currency'       => 'gbp',
            'payment_method' => 'card',
            'status'         => Admission::STATUS_ADMITTED,
            'admitted_at'    => now(),
            'paid_at'        => now(),
            'admitted_by'    => $admin->id,
        ]);

        // Student currently has active access
        $this->actingAs($student)->get(route('learn.index', $course))->assertOk();

        // Admin revokes access
        $response = $this->actingAs($admin, 'admin')->post(route('admin.admissions.revoke', $admission), [
            'admin_notes' => 'Fee chargeback / revoked by college office.',
        ]);

        $response->assertRedirect();
        $admission->refresh();

        $this->assertSame(Admission::STATUS_REVOKED, $admission->status);
        $this->assertNotNull($admission->revoked_at);

        // Course is now locked and forbidden
        $this->assertFalse($student->fresh()->hasPurchased($course));
        $this->actingAs($student)->get(route('learn.index', $course))->assertForbidden();
    }

    public function test_admin_can_directly_admit_student(): void
    {
        Mail::fake();

        $admin = $this->superAdmin();
        $student = Student::factory()->create(['name' => 'Walk In Student']);
        $course = $this->course('24-mock-tests');

        $response = $this->actingAs($admin, 'admin')->post(route('admin.admissions.manual-admit'), [
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'payment_method' => 'cash',
            'amount'         => 49.00,
            'admin_notes'    => 'Paid in cash at campus front desk receipt #982.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('purchases', [
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'status'         => Admission::STATUS_ADMITTED,
            'payment_method' => 'cash',
            'amount'         => 4900,
        ]);

        // Student immediately has access
        $this->assertTrue($student->fresh()->hasPurchased($course));

        // Access approval email sent to student
        Mail::assertSent(AdmissionApprovedMail::class, function (AdmissionApprovedMail $mail) use ($student) {
            return $mail->hasTo($student->email);
        });
    }

    // =========================================================================
    // 2. Certificate Issuance & Public Verification
    // =========================================================================

    public function test_admin_can_issue_certificate_and_public_can_verify(): void
    {
        $admin = $this->superAdmin();
        $student = Student::factory()->create(['name' => 'Dr. Elizabeth Sterling']);
        $course = $this->course('life-in-the-uk-course');

        $response = $this->actingAs($admin, 'admin')->post(route('admin.certificates.store'), [
            'student_id' => $student->id,
            'course_id'  => $course->id,
            'grade'      => 'Distinction (96%)',
            'issued_at'  => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cert = Certificate::first();
        $this->assertNotNull($cert);
        $this->assertSame($student->id, $cert->student_id);
        $this->assertSame('Distinction (96%)', $cert->grade);
        $this->assertSame(Certificate::STATUS_ACTIVE, $cert->status);

        // Public visitor verifies certificate WITHOUT signing in
        $verifyUrl = route('certificates.verify', $cert->certificate_number);
        $publicResponse = $this->get($verifyUrl);

        $publicResponse->assertOk();
        $publicResponse->assertSee('Authenticated &amp; Verified Credential', false);
        $publicResponse->assertSee('Dr. Elizabeth Sterling');
        $publicResponse->assertSee('Life in the UK Course');
        $publicResponse->assertSee('Distinction (96%)');
        $publicResponse->assertSee($cert->certificate_number);
    }

    public function test_admin_can_revoke_and_restore_certificate(): void
    {
        $admin = $this->superAdmin();
        $student = Student::factory()->create(['name' => 'Revocation Candidate']);
        $course = $this->course('life-in-the-uk-course');

        $cert = Certificate::create([
            'certificate_number' => Certificate::generateNumber(),
            'student_id'         => $student->id,
            'course_id'          => $course->id,
            'issued_at'          => now(),
            'grade'              => 'Passed',
            'verification_hash'  => Certificate::generateHash('TEST-NUM', $student->id),
            'status'             => Certificate::STATUS_ACTIVE,
            'issued_by'          => $admin->id,
        ]);

        // Revoke certificate
        $response = $this->actingAs($admin, 'admin')->post(route('admin.certificates.revoke', $cert), [
            'revocation_reason' => 'Identity check failed during audit.',
        ]);

        $response->assertRedirect();
        $cert->refresh();
        $this->assertSame(Certificate::STATUS_REVOKED, $cert->status);

        // Public page reflects revocation
        $publicResponse = $this->get(route('certificates.verify', $cert->certificate_number));
        $publicResponse->assertOk();
        $publicResponse->assertSee('REVOKED CREDENTIAL');
        $publicResponse->assertSee('Identity check failed during audit.');

        // Restore certificate
        $response = $this->actingAs($admin, 'admin')->post(route('admin.certificates.restore', $cert));
        $response->assertRedirect();
        $cert->refresh();
        $this->assertSame(Certificate::STATUS_ACTIVE, $cert->status);

        // Public page is valid again
        $publicResponse = $this->get(route('certificates.verify', $cert->certificate_number));
        $publicResponse->assertOk();
        $publicResponse->assertSee('Authenticated &amp; Verified Credential', false);
    }

    public function test_unrecognized_certificate_number_shows_not_found(): void
    {
        $response = $this->get(route('certificates.verify', 'CCE-9999-NONEXISTENT'));
        $response->assertOk();
        $response->assertSee('Record Not Found');
        $response->assertSee('CCE-9999-NONEXISTENT');
    }

    // =========================================================================
    // 3. Static Curriculum & Question Bank Inspector
    // =========================================================================

    public function test_curriculum_inspector_renders_static_json_overview(): void
    {
        $admin = $this->superAdmin();
        $this->course('life-in-the-uk-course');
        $this->course('24-mock-tests');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.curriculum.index'));
        $response->assertOk();
        $response->assertSee('Static Curriculum &amp; Question Bank', false);
        $response->assertSee('lesson-content.json');
        $response->assertSee('quiz-content.json');
        $response->assertSee('1000 Study Cards');
        $response->assertSee('40');
    }

    public function test_curriculum_inspector_renders_lessons(): void
    {
        $admin = $this->superAdmin();
        $this->course('life-in-the-uk-course');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.curriculum.lessons', 'life-in-the-uk-course'));
        $response->assertOk();
        $response->assertSee('Lessons &amp; Study Cards Inspector', false);
        $response->assertSee('Lesson 1');
        $response->assertSee('Democracy, the rule of law');
    }

    public function test_curriculum_inspector_renders_quizzes_and_question_bank(): void
    {
        $admin = $this->superAdmin();
        $this->course('life-in-the-uk-course');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.curriculum.quizzes', 'life-in-the-uk-course'));
        $response->assertOk();
        $response->assertSee('Knowledge Check 1');
        $response->assertSee('What four fundamental values are commonly associated with life in the UK?');
        $response->assertSee('Democracy, the rule of law');
    }

    public function test_views_and_system_contain_no_mention_of_staff(): void
    {
        $admin = $this->superAdmin();
        $student = Student::factory()->create();
        $course = $this->course('life-in-the-uk-course');

        Admission::create([
            'student_id'     => $student->id,
            'course_id'      => $course->id,
            'customer_name'  => $student->name,
            'customer_email' => $student->email,
            'contact_phone'  => '07700900999',
            'amount'         => $course->price,
            'currency'       => 'gbp',
            'payment_method' => 'bank_transfer',
            'status'         => Admission::STATUS_PENDING,
            'requested_at'   => now(),
        ]);

        // 1. Admin Admissions Index & Show
        $res = $this->actingAs($admin, 'admin')->get(route('admin.admissions.index'))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        $admission = Admission::first();
        $res = $this->actingAs($admin, 'admin')->get(route('admin.admissions.show', $admission))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        // 2. Admin Users Index & Create & Edit
        $res = $this->actingAs($admin, 'admin')->get(route('admin.users.index'))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        $res = $this->actingAs($admin, 'admin')->get(route('admin.users.create'))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        $res = $this->actingAs($admin, 'admin')->get(route('admin.users.edit', $admin))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        // 3. Admin Login page (as guest)
        Auth::guard('admin')->logout();
        $res = $this->get(route('admin.login'))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        // 4. Student Dashboard
        $res = $this->actingAs($student)->get(route('dashboard'))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');

        // 5. Checkout Review
        $res = $this->actingAs($student)->get(route('checkout.review', $course))->assertOk();
        $res->assertDontSee('staff');
        $res->assertDontSee('Staff');
    }

    public function test_anyone_can_visit_id_card_page(): void
    {
        $response = $this->get('/id-card');
        $response->assertOk();
        $response->assertSee('ID Card Maker');
        $response->assertSee('Sharon Ann Joseph');
        $response->assertSee('Outreach Primary Tutor');
        $response->assertSee('front');
        $response->assertSee('back');
    }
}

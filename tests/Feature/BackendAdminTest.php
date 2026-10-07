<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackendAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'              => 'Master Admin',
            'username'          => 'masteradmin',
            'email'             => 'admin@croydon.ac.uk',
            'password'          => Hash::make('password123'),
            'role'              => 'super_admin',
            'is_active'         => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    protected function createStaffAdmin(array $attributes = []): User
    {
        return User::create(array_merge([
            'name'              => 'Staff Member',
            'username'          => 'staffmember',
            'email'             => 'staff@croydon.ac.uk',
            'password'          => Hash::make('password123'),
            'role'              => 'admin',
            'is_active'         => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    // -------------------------------------------------------------
    // 1. Guard Separation & Guest Access
    // -------------------------------------------------------------

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect(route('admin.login'));

        $response = $this->get('/admin/dashboard');
        $response->assertRedirect(route('admin.login'));

        $response = $this->get('/admin/students');
        $response->assertRedirect(route('admin.login'));
    }

    public function test_logged_in_student_cannot_access_admin_panel(): void
    {
        $student = Student::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($student, 'web')->get('/admin');
        $response->assertRedirect(route('admin.login'));
    }

    // -------------------------------------------------------------
    // 2. Admin Seeding, Factory & Authentication (No Public Registration)
    // -------------------------------------------------------------

    public function test_admin_cannot_be_registered_publicly(): void
    {
        $this->get('/admin/register')->assertNotFound();

        $this->post('/admin/register', [
            'name'                  => 'Hacker User',
            'username'              => 'hacker',
            'email'                 => 'hacker@croydon.ac.uk',
            'password'              => 'SecretAdmin123!',
            'password_confirmation' => 'SecretAdmin123!',
        ])->assertNotFound();
    }

    public function test_admin_login_screen_renders_with_updated_text(): void
    {
        $response = $this->get(route('admin.login'));
        $response->assertOk();
        $response->assertSee('Admin Login');
        $response->assertSee('Enter your email or username');
        $response->assertDontSee('Staff access only. New accounts are provisioned by an Administrator.');
        $response->assertDontSee('Staff Portal Login');
    }

    public function test_admin_user_seeder_creates_initial_super_admin(): void
    {
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $this->assertDatabaseHas('users', [
            'email'     => 'admin@croydoncollegeofexcellence.co.uk',
            'role'      => 'super_admin',
            'is_active' => true,
        ]);

        $admin = User::where('email', 'admin@croydoncollegeofexcellence.co.uk')->first();
        $this->assertTrue($admin->isSuperAdmin());

        // Initial seeded admin can log into the admin panel
        $response = $this->post(route('admin.login.store'), [
            'login'    => 'admin@croydoncollegeofexcellence.co.uk',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_user_factory_generates_valid_users_and_roles(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertSame('super_admin', $superAdmin->role);

        $admin = User::factory()->create();
        $this->assertTrue($admin->isAdmin());
        $this->assertSame('admin', $admin->role);

        $staff = User::factory()->staff()->create();
        $this->assertFalse($staff->isAdmin());
        $this->assertSame('staff', $staff->role);
    }

    public function test_admin_can_login_with_email_or_username(): void
    {
        $admin = $this->createStaffAdmin([
            'username' => 'staffone',
            'email'    => 'staff1@croydon.ac.uk',
            'password' => Hash::make('password123'),
        ]);

        // Login with email
        $response = $this->post(route('admin.login.store'), [
            'login'    => 'staff1@croydon.ac.uk',
            'password' => 'password123',
        ]);
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');

        Auth::guard('admin')->logout();

        // Login with username
        $response = $this->post(route('admin.login.store'), [
            'login'    => 'staffone',
            'password' => 'password123',
        ]);
        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_deactivated_admin_cannot_login(): void
    {
        $this->createStaffAdmin([
            'email'     => 'inactive@croydon.ac.uk',
            'password'  => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->post(route('admin.login.store'), [
            'login'    => 'inactive@croydon.ac.uk',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('login');
        $this->assertGuest('admin');
    }

    public function test_admin_logout_clears_session(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));
        $response->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    // -------------------------------------------------------------
    // 3. Dashboard
    // -------------------------------------------------------------

    public function test_dashboard_renders_with_metrics(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create();

        $course = Course::firstOrCreate(
            ['slug' => 'dashboard-test-course'],
            [
                'name'        => 'Test Course',
                'description' => 'A test course description',
                'price'       => 5000,
                'is_active'   => true,
            ]
        );

        Purchase::create([
            'student_id'                => $student->id,
            'course_id'                 => $course->id,
            'amount'                    => 5000,
            'status'                    => Purchase::STATUS_PAID,
            'stripe_checkout_session_id' => 'cs_test_dash_123',
            'paid_at'                   => now(),
        ]);

        ContactSubmission::create([
            'type'    => 'contact',
            'name'    => 'Prospective Learner',
            'email'   => 'learner@example.com',
            'subject' => 'Course inquiry',
            'message' => 'Interested in joining.',
            'status'  => 'unread',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Croydon College Administration');
        $response->assertSee('Total Revenue');
        $response->assertSee('50.00');
        $response->assertSee('Prospective Learner');
    }

    // -------------------------------------------------------------
    // 4. Students Management
    // -------------------------------------------------------------

    public function test_admin_can_view_students_list_and_details(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create([
            'name'  => 'Arthur Dent',
            'email' => 'arthur@galaxy.org',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.students.index'));
        $response->assertOk();
        $response->assertSee('Arthur Dent');
        $response->assertSee('arthur@galaxy.org');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.students.show', $student));
        $response->assertOk();
        $response->assertSee('Arthur Dent');
        $response->assertSee('arthur@galaxy.org');
    }

    public function test_admin_can_toggle_student_status_and_block_login(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create([
            'email'             => 'blocked@learner.com',
            'password'          => 'password123',
            'is_active'         => true,
            'email_verified_at' => now(),
        ]);

        // Toggle to inactive
        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.students.toggle-status', $student));
        $response->assertRedirect();
        $student->refresh();
        $this->assertFalse($student->is_active);

        // Clear admin session to simulate guest visitor on public login
        Auth::guard('admin')->logout();

        // Deactivated student fails frontend login
        $loginResponse = $this->post(route('login'), [
            'email'    => 'blocked@learner.com',
            'password' => 'password123',
        ]);
        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest('web');

        // Toggle back to active
        $this->actingAs($admin, 'admin')
            ->post(route('admin.students.toggle-status', $student));
        $student->refresh();
        $this->assertTrue($student->is_active);
    }

    public function test_admin_can_reset_student_password(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.students.reset-password', $student), [
                'password'              => 'NewSecureP@ss2026',
                'password_confirmation' => 'NewSecureP@ss2026',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $student->refresh();
        $this->assertTrue(Hash::check('NewSecureP@ss2026', $student->password));
    }

    // -------------------------------------------------------------
    // 5. Course Catalog Management
    // -------------------------------------------------------------

    public function test_admin_can_edit_course_with_gbp_to_pence_conversion(): void
    {
        $admin = $this->createSuperAdmin();
        $course = Course::firstOrCreate(
            ['slug' => 'course-edit-test'],
            [
                'name'        => 'Old Title',
                'description' => 'Old description',
                'price'       => 1000,
                'is_active'   => true,
            ]
        );

        $response = $this->actingAs($admin, 'admin')->get(route('admin.courses.edit', $course));
        $response->assertOk();
        $response->assertSee('Old Title');

        // Update with £49.99
        $response = $this->actingAs($admin, 'admin')->put(route('admin.courses.update', $course), [
            'name'            => 'Updated Masterclass',
            'price'           => '49.99',
            'stripe_price_id' => 'price_updated_123',
            'description'     => 'A revamped masterclass description.',
            'is_active'       => '1',
        ]);

        $response->assertRedirect(route('admin.courses.index'));
        $course->refresh();
        $this->assertSame('Updated Masterclass', $course->name);
        $this->assertSame(4999, $course->price); // Pence conversion verified
        $this->assertSame('price_updated_123', $course->stripe_price_id);
    }

    public function test_admin_can_toggle_course_status(): void
    {
        $admin = $this->createSuperAdmin();
        $course = Course::firstOrCreate(
            ['slug' => 'toggle-test-course'],
            [
                'name'        => 'Toggle Course',
                'description' => 'Test course',
                'price'       => 2000,
                'is_active'   => true,
            ]
        );

        $this->actingAs($admin, 'admin')->post(route('admin.courses.toggle', $course));
        $course->refresh();
        $this->assertFalse((bool)$course->is_active);

        $this->actingAs($admin, 'admin')->post(route('admin.courses.toggle', $course));
        $course->refresh();
        $this->assertTrue((bool)$course->is_active);
    }

    // -------------------------------------------------------------
    // 6. Purchases & Financials
    // -------------------------------------------------------------

    public function test_admin_can_view_purchases_and_update_status(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create(['name' => 'Paying Learner']);
        $course = Course::firstOrCreate(
            ['slug' => 'order-test-course'],
            [
                'name'        => 'Order Course',
                'description' => 'Course description',
                'price'       => 7500,
                'is_active'   => true,
            ]
        );

        $purchase = Purchase::create([
            'student_id'                 => $student->id,
            'course_id'                  => $course->id,
            'amount'                     => 7500,
            'status'                     => Purchase::STATUS_PENDING,
            'stripe_checkout_session_id' => 'cs_test_order_123',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.purchases.index'));
        $response->assertOk();
        $response->assertSee('Paying Learner');
        $response->assertSee('75.00');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.purchases.show', $purchase));
        $response->assertOk();
        $response->assertSee('cs_test_order_123');

        // Update status to paid
        $response = $this->actingAs($admin, 'admin')->post(route('admin.purchases.status', $purchase), [
            'status' => Purchase::STATUS_PAID,
        ]);
        $response->assertRedirect();
        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertNotNull($purchase->paid_at);
    }

    // -------------------------------------------------------------
    // 7. Lead Forms Pipeline & Submissions Management
    // -------------------------------------------------------------

    public function test_public_contact_form_saves_to_database(): void
    {
        $data = [
            'name'    => 'Jane Prospective',
            'email'   => 'jane@example.com',
            'phone'   => '+447000112233',
            'subject' => 'Life in the UK Enquiry',
            'message' => 'I would like to know if classes are on weekends.',
        ];

        $response = $this->post(route('contact.send'), $data);
        $response->assertRedirect();

        $submission = ContactSubmission::where('email', 'jane@example.com')->first();
        $this->assertNotNull($submission);
        $this->assertSame('contact', $submission->type);
        $this->assertSame('Jane Prospective', $submission->name);
        $this->assertSame('Life in the UK Enquiry', $submission->subject);
    }

    public function test_admin_can_manage_submissions(): void
    {
        $admin = $this->createSuperAdmin();

        $submission = ContactSubmission::create([
            'type'    => 'enroll',
            'name'    => 'Student Hopeful',
            'email'   => 'hopeful@example.com',
            'phone'   => '07123456789',
            'subject' => 'Enrolment Application',
            'message' => 'Please enrol me.',
            'status'  => 'new',
        ]);

        // View index & show
        $response = $this->actingAs($admin, 'admin')->get(route('admin.submissions.index'));
        $response->assertOk();
        $response->assertSee('Student Hopeful');

        $response = $this->actingAs($admin, 'admin')->get(route('admin.submissions.show', $submission));
        $response->assertOk();
        $submission->refresh();
        $this->assertTrue($submission->isRead()); // Opening show auto-marks as read
        $this->assertNotNull($submission->read_at);

        // Add admin note
        $this->actingAs($admin, 'admin')->post(route('admin.submissions.notes', $submission), [
            'admin_notes' => 'Called applicant, scheduled orientation.',
            'status'      => 'contacted',
        ]);
        $submission->refresh();
        $this->assertSame('Called applicant, scheduled orientation.', $submission->admin_notes);
        $this->assertSame('contacted', $submission->status);

        // Delete submission
        $deleteResponse = $this->actingAs($admin, 'admin')->delete(route('admin.submissions.destroy', $submission));
        $deleteResponse->assertRedirect(route('admin.submissions.index'));
        $this->assertDatabaseMissing('contact_submissions', ['id' => $submission->id]);
    }

    // -------------------------------------------------------------
    // 8. Staff Users Management & Super Admin Authorization
    // -------------------------------------------------------------

    public function test_super_admin_can_create_staff_user(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'admin')->post(route('admin.users.store'), [
            'name'                  => 'Operations Staff',
            'username'              => 'ops_user',
            'email'                 => 'ops@croydon.ac.uk',
            'role'                  => 'admin',
            'password'              => 'Operations123!',
            'password_confirmation' => 'Operations123!',
            'is_active'             => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email'    => 'ops@croydon.ac.uk',
            'username' => 'ops_user',
            'role'     => 'admin',
        ]);
    }

    public function test_staff_role_user_is_forbidden_from_managing_users(): void
    {
        $staffUser = User::factory()->staff()->create();

        $response = $this->actingAs($staffUser, 'admin')->get(route('admin.users.index'));
        $response->assertForbidden();

        $response = $this->actingAs($staffUser, 'admin')->get(route('admin.users.create'));
        $response->assertForbidden();
    }

    public function test_seeded_admin_can_add_other_users_and_new_user_can_access_panel(): void
    {
        $this->seed(\Database\Seeders\AdminUserSeeder::class);
        $seededAdmin = User::where('email', 'admin@croydoncollegeofexcellence.co.uk')->first();

        // Seeded admin logs in and adds a new admin user
        $response = $this->actingAs($seededAdmin, 'admin')->post(route('admin.users.store'), [
            'name'                  => 'Operations Manager',
            'username'              => 'opsmanager',
            'email'                 => 'ops@croydon.ac.uk',
            'role'                  => 'admin',
            'password'              => 'OperationsManager123!',
            'password_confirmation' => 'OperationsManager123!',
            'is_active'             => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        // Clear session
        Auth::guard('admin')->logout();

        // Newly added admin logs in and accesses dashboard
        $loginResponse = $this->post(route('admin.login.store'), [
            'login'    => 'ops@croydon.ac.uk',
            'password' => 'OperationsManager123!',
        ]);

        $loginResponse->assertRedirect(route('admin.dashboard'));
        $newAdmin = User::where('email', 'ops@croydon.ac.uk')->first();
        $this->assertAuthenticatedAs($newAdmin, 'admin');

        // New admin can manage the admin panel
        $this->actingAs($newAdmin, 'admin')->get(route('admin.students.index'))->assertOk();
        $this->actingAs($newAdmin, 'admin')->get(route('admin.courses.index'))->assertOk();
        $this->actingAs($newAdmin, 'admin')->get(route('admin.purchases.index'))->assertOk();
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'admin')->delete(route('admin.users.destroy', $superAdmin));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    // -------------------------------------------------------------
    // 9. Analytics Dashboard
    // -------------------------------------------------------------

    public function test_analytics_page_renders_successfully(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.analytics.index'));
        $response->assertOk();
        $response->assertSee('Platform Analytics');
        $response->assertSee('Gross Paid Revenue');
    }

    public function test_student_search_and_filters_work(): void
    {
        $admin = $this->createSuperAdmin();

        $activeStudent = Student::factory()->create([
            'name'      => 'Alice Wonderland',
            'email'     => 'alice@croydon.ac.uk',
            'is_active' => true,
        ]);

        $suspendedStudent = Student::factory()->create([
            'name'      => 'Bob Suspended',
            'email'     => 'bob@croydon.ac.uk',
            'is_active' => false,
        ]);

        // Search by name
        $response = $this->actingAs($admin, 'admin')->get(route('admin.students.index', ['search' => 'Alice']));
        $response->assertOk();
        $response->assertSee('Alice Wonderland');
        $response->assertDontSee('Bob Suspended');

        // Filter by suspended status
        $response = $this->actingAs($admin, 'admin')->get(route('admin.students.index', ['status' => 'suspended']));
        $response->assertOk();
        $response->assertSee('Bob Suspended');
        $response->assertDontSee('Alice Wonderland');
    }

    public function test_purchase_filters_and_search_work(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create(['name' => 'Charlie Buyer']);
        $course1 = Course::firstOrCreate(
            ['slug' => 'filter-c1'],
            ['name' => 'Course Alpha', 'description' => 'Desc A', 'price' => 2000, 'is_active' => true]
        );
        $course2 = Course::firstOrCreate(
            ['slug' => 'filter-c2'],
            ['name' => 'Course Beta', 'description' => 'Desc B', 'price' => 3000, 'is_active' => true]
        );

        $purchase1 = Purchase::create([
            'student_id'                => $student->id,
            'course_id'                 => $course1->id,
            'amount'                    => 2000,
            'status'                    => Purchase::STATUS_PAID,
            'stripe_checkout_session_id' => 'cs_alpha_111',
            'paid_at'                   => now(),
        ]);

        $purchase2 = Purchase::create([
            'student_id'                => $student->id,
            'course_id'                 => $course2->id,
            'amount'                    => 3000,
            'status'                    => Purchase::STATUS_PENDING,
            'stripe_checkout_session_id' => 'cs_beta_222',
        ]);

        // Filter by course
        $response = $this->actingAs($admin, 'admin')->get(route('admin.purchases.index', ['course_id' => $course1->id]));
        $response->assertOk();
        $response->assertSee('cs_alpha_111');
        $response->assertDontSee('cs_beta_222');

        // Filter by status
        $response = $this->actingAs($admin, 'admin')->get(route('admin.purchases.index', ['status' => 'paid']));
        $response->assertOk();
        $response->assertSee('cs_alpha_111');
        $response->assertDontSee('cs_beta_222');
    }

    public function test_course_features_parsed_from_multiline_text(): void
    {
        $admin = $this->createSuperAdmin();
        $course = Course::firstOrCreate(
            ['slug' => 'feature-parse-course'],
            ['name' => 'Parsing Course', 'description' => 'Description', 'price' => 2500, 'is_active' => true]
        );

        $featuresInput = "Interactive Quizzes\nVideo Lessons\nOfficial Certificate";

        $response = $this->actingAs($admin, 'admin')->put(route('admin.courses.update', $course), [
            'name'        => 'Parsing Course Updated',
            'price'       => '25.00',
            'features'    => $featuresInput,
            'description' => 'Updated Desc',
        ]);

        $response->assertRedirect(route('admin.courses.index'));
        $course->refresh();
        $this->assertIsArray($course->features);
        $this->assertSame(['Interactive Quizzes', 'Video Lessons', 'Official Certificate'], $course->features);
    }

    public function test_staff_admin_can_update_their_own_profile(): void
    {
        $staff = $this->createStaffAdmin(['name' => 'Original Name']);

        $response = $this->actingAs($staff, 'admin')->put(route('admin.users.update', $staff), [
            'name'     => 'Updated Name',
            'email'    => 'staff@croydon.ac.uk',
            'username' => 'staffmember',
            'role'     => 'admin',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $staff->refresh();
        $this->assertSame('Updated Name', $staff->name);
    }

    public function test_submission_toggle_read_status(): void
    {
        $admin = $this->createSuperAdmin();

        $submission = ContactSubmission::create([
            'type'    => 'contact',
            'name'    => 'Toggle Reader',
            'email'   => 'toggle@croydon.ac.uk',
            'phone'   => '0123456789',
            'subject' => 'Read Toggle Test',
            'message' => 'Hello',
            'status'  => 'new',
        ]);

        $this->assertFalse($submission->isRead());

        // Toggle to read
        $this->actingAs($admin, 'admin')->post(route('admin.submissions.read', $submission));
        $submission->refresh();
        $this->assertTrue($submission->isRead());

        // Toggle back to unread
        $this->actingAs($admin, 'admin')->post(route('admin.submissions.read', $submission));
        $submission->refresh();
        $this->assertFalse($submission->isRead());
    }

    public function test_admin_can_export_students_csv(): void
    {
        $admin = $this->createSuperAdmin();
        Student::factory()->create([
            'name'  => 'Exportable Student',
            'email' => 'exportable@croydon.ac.uk',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.students.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Exportable Student', $response->streamedContent());
        $this->assertStringContainsString('exportable@croydon.ac.uk', $response->streamedContent());
    }

    public function test_admin_can_export_purchases_csv(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create(['name' => 'Buyer Export', 'email' => 'buyer@croydon.ac.uk']);
        $course = Course::factory()->create(['name' => 'Export Course']);

        Purchase::create([
            'student_id'                 => $student->id,
            'course_id'                  => $course->id,
            'amount'                     => 4900,
            'currency'                   => 'GBP',
            'status'                     => Purchase::STATUS_PAID,
            'stripe_checkout_session_id' => 'cs_export_test_999',
            'stripe_payment_intent_id'   => 'pi_export_test_999',
            'paid_at'                    => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.purchases.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Buyer Export', $content);
        $this->assertStringContainsString('Export Course', $content);
        $this->assertStringContainsString('cs_export_test_999', $content);
    }

    public function test_admin_can_export_submissions_csv(): void
    {
        $admin = $this->createSuperAdmin();
        ContactSubmission::create([
            'type'    => 'contact',
            'name'    => 'CSV Inquirer',
            'email'   => 'csvinq@croydon.ac.uk',
            'phone'   => '07700900123',
            'subject' => 'CSV Export Subject',
            'message' => 'Testing CSV export stream',
            'status'  => 'new',
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.submissions.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('CSV Inquirer', $content);
        $this->assertStringContainsString('CSV Export Subject', $content);
    }

    public function test_admin_can_manually_enroll_student_in_course(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create();
        $course = Course::factory()->create(['price' => 3500]);

        $this->assertFalse($student->hasPurchased($course->id));

        $response = $this->actingAs($admin, 'admin')->post(route('admin.students.enroll', $student), [
            'course_id'      => $course->id,
            'payment_method' => 'bank_transfer',
            'admin_notes'    => 'Enrolled via BACS invoice 1044',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertTrue($student->fresh()->hasPurchased($course->id));

        $purchase = Purchase::where('student_id', $student->id)->where('course_id', $course->id)->first();
        $this->assertNotNull($purchase);
        $this->assertSame(Purchase::STATUS_PAID, $purchase->status);
        $this->assertSame(3500, $purchase->amount);
    }

    public function test_admin_can_impersonate_student_and_stop_impersonating(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create();

        $response = $this->actingAs($admin, 'admin')->get(route('admin.students.impersonate', $student));
        $response->assertRedirect(route('dashboard'));
        $this->assertTrue(Auth::guard('web')->check());
        $this->assertSame($student->id, Auth::guard('web')->id());

        // Stop impersonating
        $stopResponse = $this->get(route('stop-impersonating'));
        $stopResponse->assertRedirect(route('admin.students.index'));
        $this->assertFalse(Auth::guard('web')->check());
    }

    public function test_refunding_purchase_revokes_course_access_and_logs_audit(): void
    {
        $admin = $this->createSuperAdmin();
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $purchase = Purchase::create([
            'student_id'                 => $student->id,
            'course_id'                  => $course->id,
            'amount'                     => 2900,
            'currency'                   => 'GBP',
            'status'                     => Purchase::STATUS_PAID,
            'stripe_checkout_session_id' => 'cs_refund_revocation_test',
            'paid_at'                    => now(),
        ]);

        $this->assertTrue($student->hasPurchased($course->id));

        // Admin updates status to refunded
        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.purchases.show', $purchase))
            ->patch(route('admin.purchases.status', $purchase), [
                'status' => 'refunded',
                'notes'  => 'Customer requested cancellation within 14 days',
            ]);

        $response->assertRedirect(route('admin.purchases.show', $purchase));
        $purchase->refresh();
        $this->assertSame(Purchase::STATUS_REFUNDED, $purchase->status);

        // Course access immediately revoked
        $this->assertFalse($student->fresh()->hasPurchased($course->id));
    }

    public function test_audit_logs_screen_and_activity_tracking(): void
    {
        $admin = $this->createSuperAdmin();
        $course = Course::factory()->create(['name' => 'Audit Course', 'price' => 1000]);

        // Toggle course status triggers audit log
        $this->actingAs($admin, 'admin')->post(route('admin.courses.toggle', $course));

        $response = $this->actingAs($admin, 'admin')->get(route('admin.audit-logs.index'));
        $response->assertOk();
        $response->assertSee('Audit Trail');
        $response->assertSee('course_status_toggled');
    }
}


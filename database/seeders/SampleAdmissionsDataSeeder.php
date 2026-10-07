<?php

namespace Database\Seeders;

use App\Models\AdminAuditLog;
use App\Models\Admission;
use App\Models\Certificate;
use App\Models\ContactSubmission;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleAdmissionsDataSeeder extends Seeder
{
    /**
     * Run the database seeds for sample admissions, students, and certificates.
     */
    public function run(): void
    {
        // 1. Ensure Courses & Admin User are present
        $this->call([
            CourseSeeder::class,
            AdminUserSeeder::class,
        ]);

        $admin = User::where('role', 'super_admin')->first();
        $lifeCourse = Course::where('slug', 'life-in-the-uk-course')->firstOrFail();
        $mocksCourse = Course::where('slug', '24-mock-tests')->firstOrFail();

        $defaultPassword = Hash::make('password');

        // =====================================================================
        // 2. Pending Admissions (Awaiting Administrator Approval)
        // =====================================================================
        $pendingData = [
            [
                'name'           => 'Tariq Mahmood',
                'email'          => 'tariq.mahmood@example.co.uk',
                'phone'          => '07700 900142',
                'course'         => $lifeCourse,
                'payment_method' => 'bank_transfer',
                'notes'          => 'Please call me after 3:30 PM. I would like to pay via online bank transfer.',
                'hours_ago'      => 3,
            ],
            [
                'name'           => 'Elena Rostova',
                'email'          => 'elena.rostova@example.com',
                'phone'          => '07700 900385',
                'course'         => $mocksCourse,
                'payment_method' => 'phone_card',
                'notes'          => 'I am ready to settle the fee over the phone with my debit card.',
                'hours_ago'      => 8,
            ],
            [
                'name'           => 'Kwame Mensah',
                'email'          => 'kwame.mensah@example.org',
                'phone'          => '07700 900891',
                'course'         => $lifeCourse,
                'payment_method' => 'cash',
                'notes'          => 'Will visit the campus reception tomorrow to pay in cash.',
                'hours_ago'      => 20,
            ],
        ];

        foreach ($pendingData as $item) {
            $student = Student::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name'              => $item['name'],
                    'phone'             => $item['phone'],
                    'password'          => $defaultPassword,
                    'email_verified_at' => now()->subHours($item['hours_ago'] + 1),
                    'is_active'         => true,
                ]
            );

            Admission::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'course_id'  => $item['course']->id,
                ],
                [
                    'amount'            => $item['course']->price,
                    'currency'          => 'gbp',
                    'status'            => Admission::STATUS_PENDING,
                    'contact_phone'     => $item['phone'],
                    'payment_method'    => $item['payment_method'],
                    'learner_notes'     => $item['notes'],
                    'customer_name'     => $student->name,
                    'customer_email'    => $student->email,
                    'requested_at'      => now()->subHours($item['hours_ago']),
                    'terms_accepted_at' => now()->subHours($item['hours_ago']),
                    'terms_version'     => 'v1',
                ]
            );
        }

        // =====================================================================
        // 3. Admitted Learners (Active Lifetime Access) & Certifications
        // =====================================================================
        $admittedData = [
            [
                'name'           => 'Amina Al-Mansoor',
                'email'          => 'amina.almansoor@example.com',
                'phone'          => '07700 900512',
                'course'         => $lifeCourse,
                'payment_method' => 'bank_transfer',
                'admin_notes'    => 'Bank transfer verified HSBC Ref #AM904.',
                'days_ago'       => 14,
                'certificate'    => [
                    'grade'     => 'Distinction (96%)',
                    'issued_at' => now()->subDays(2),
                ],
            ],
            [
                'name'           => 'David O\'Connor',
                'email'          => 'david.oconnor@example.ie',
                'phone'          => '07700 900673',
                'course'         => $mocksCourse,
                'payment_method' => 'phone_card',
                'admin_notes'    => 'Card payment collected by phone terminal.',
                'days_ago'       => 18,
                'certificate'    => [
                    'grade'     => 'Pass (88%)',
                    'issued_at' => now()->subDays(4),
                ],
            ],
            [
                'name'           => 'Priyanka Sharma',
                'email'          => 'priyanka.sharma@example.co.uk',
                'phone'          => '07700 900234',
                'course'         => $lifeCourse,
                'payment_method' => 'cash',
                'admin_notes'    => 'Paid £99 in office. Receipt #1084 issued.',
                'days_ago'       => 6,
                'certificate'    => null,
            ],
            [
                'name'           => 'Mateusz Kowalski',
                'email'          => 'mateusz.kowalski@example.com',
                'phone'          => '07700 900746',
                'course'         => $mocksCourse,
                'payment_method' => 'bank_transfer',
                'admin_notes'    => 'Faster Payments confirmed ref: MKOW992.',
                'days_ago'       => 5,
                'certificate'    => null,
            ],
            [
                'name'           => 'Fatima Zahra',
                'email'          => 'fatima.zahra@example.net',
                'phone'          => '07700 900958',
                'course'         => $lifeCourse,
                'payment_method' => 'bank_transfer',
                'admin_notes'    => 'Lloyds bank transfer verified.',
                'days_ago'       => 9,
                'certificate'    => null,
            ],
        ];

        foreach ($admittedData as $item) {
            $student = Student::updateOrCreate(
                ['email' => $item['email']],
                [
                    'name'              => $item['name'],
                    'phone'             => $item['phone'],
                    'password'          => $defaultPassword,
                    'email_verified_at' => now()->subDays($item['days_ago'] + 1),
                    'is_active'         => true,
                ]
            );

            $admission = Admission::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'course_id'  => $item['course']->id,
                ],
                [
                    'amount'            => $item['course']->price,
                    'currency'          => 'gbp',
                    'status'            => Admission::STATUS_ADMITTED,
                    'contact_phone'     => $item['phone'],
                    'payment_method'    => $item['payment_method'],
                    'admin_notes'       => $item['admin_notes'],
                    'customer_name'     => $student->name,
                    'customer_email'    => $student->email,
                    'requested_at'      => now()->subDays($item['days_ago']),
                    'admitted_at'       => now()->subDays($item['days_ago'] - 1),
                    'paid_at'           => now()->subDays($item['days_ago'] - 1),
                    'admitted_by'       => $admin?->id,
                    'terms_accepted_at' => now()->subDays($item['days_ago']),
                    'terms_version'     => 'v1',
                ]
            );

            // Seed Certificate if specified
            if (! empty($item['certificate'])) {
                $certNumber = Certificate::generateNumber();
                $hash = Certificate::generateHash($certNumber, $student->id);

                Certificate::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_id'  => $item['course']->id,
                    ],
                    [
                        'certificate_number' => $certNumber,
                        'admission_id'       => $admission->id,
                        'issued_at'          => $item['certificate']['issued_at'],
                        'grade'              => $item['certificate']['grade'],
                        'verification_hash'  => $hash,
                        'status'             => Certificate::STATUS_ACTIVE,
                        'issued_by'          => $admin?->id,
                    ]
                );
            }
        }

        // =====================================================================
        // 4. Revoked Admission Record
        // =====================================================================
        $revokedStudent = Student::updateOrCreate(
            ['email' => 'james.campbell@example.com'],
            [
                'name'              => 'James Campbell',
                'phone'             => '07700 900119',
                'password'          => $defaultPassword,
                'email_verified_at' => now()->subDays(26),
                'is_active'         => true,
            ]
        );

        Admission::updateOrCreate(
            [
                'student_id' => $revokedStudent->id,
                'course_id'  => $lifeCourse->id,
            ],
            [
                'amount'            => $lifeCourse->price,
                'currency'          => 'gbp',
                'status'            => Admission::STATUS_REVOKED,
                'contact_phone'     => '07700 900119',
                'payment_method'    => 'bank_transfer',
                'admin_notes'       => 'Learner requested cancellation before starting module 1. Tuition refunded.',
                'customer_name'     => $revokedStudent->name,
                'customer_email'    => $revokedStudent->email,
                'requested_at'      => now()->subDays(25),
                'admitted_at'       => now()->subDays(24),
                'revoked_at'        => now()->subDays(10),
                'paid_at'           => now()->subDays(24),
                'admitted_by'       => $admin?->id,
                'terms_accepted_at' => now()->subDays(25),
                'terms_version'     => 'v1',
            ]
        );

        // =====================================================================
        // 5. Sample Lead Inquiries
        // =====================================================================
        $submissions = [
            [
                'type'        => 'contact',
                'name'        => 'Rashid Al-Khatib',
                'email'       => 'rashid.khatib@example.com',
                'phone'       => '07400 123456',
                'subject'     => 'Life in the UK Classroom Schedule',
                'message'     => 'Hi, are the preparation classes held during weekends or weekday evenings?',
                'status'      => 'new',
                'created_at'  => now()->subHours(5),
            ],
            [
                'type'        => 'enroll',
                'name'        => 'Sarah Jenkins',
                'email'       => 'sarah.j@example.co.uk',
                'phone'       => '07511 234567',
                'subject'     => 'Corporate Group Enrollment Inquiry',
                'message'     => 'We have 4 candidates requiring Life in the UK certification for settlement.',
                'status'      => 'contacted',
                'admin_notes' => 'Called Sarah. Sent college quotation for 4 enrollments.',
                'created_at'  => now()->subDays(2),
            ],
            [
                'type'        => 'assessment',
                'name'        => 'Vikram Patel',
                'email'       => 'vikram.patel@example.com',
                'phone'       => '07622 345678',
                'subject'     => 'Free Assessment Booking',
                'message'     => 'I took an online test and scored 17/24. I need tutoring to pass reliably.',
                'status'      => 'resolved',
                'admin_notes' => 'Completed initial assessment call. Admitted into 24 Mock Tests.',
                'created_at'  => now()->subDays(7),
            ],
        ];

        foreach ($submissions as $sub) {
            ContactSubmission::updateOrCreate(
                ['email' => $sub['email'], 'type' => $sub['type']],
                $sub
            );
        }

        // =====================================================================
        // 6. Realistic Audit Trail Records
        // =====================================================================
        if ($admin) {
            AdminAuditLog::create([
                'user_id'   => $admin->id,
                'user_name' => $admin->name,
                'action'    => 'admission_approved',
                'notes'     => "Approved admission for Amina Al-Mansoor into 'Life in the UK Course' via Bank transfer.",
                'created_at'=> now()->subDays(13),
            ]);

            AdminAuditLog::create([
                'user_id'   => $admin->id,
                'user_name' => $admin->name,
                'action'    => 'certificate_issued',
                'notes'     => "Issued Certificate for Amina Al-Mansoor with Distinction (96%).",
                'created_at'=> now()->subDays(2),
            ]);

            AdminAuditLog::create([
                'user_id'   => $admin->id,
                'user_name' => $admin->name,
                'action'    => 'admission_approved',
                'notes'     => "Approved admission for David O'Connor into '24 Mock Tests Package' via Phone Card terminal.",
                'created_at'=> now()->subDays(19),
            ]);
        }
    }
}

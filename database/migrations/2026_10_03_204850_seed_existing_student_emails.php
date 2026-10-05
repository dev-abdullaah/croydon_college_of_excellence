<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing students' emails to the student_emails table
        DB::table('students')
            ->whereNotNull('email')
            ->chunkById(100, function ($students) {
                $rows = [];
                foreach ($students as $student) {
                    $rows[] = [
                        'student_id' => $student->id,
                        'email' => $student->email,
                        'is_primary' => true,
                        'is_verified' => (bool) $student->email_verified_at,
                        'verified_at' => $student->email_verified_at,
                        'created_at' => $student->created_at,
                        'updated_at' => $student->updated_at,
                    ];
                }
                if (! empty($rows)) {
                    DB::table('student_emails')->insert($rows);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot easily reverse, but we can truncate
        DB::table('student_emails')->truncate();
    }
};

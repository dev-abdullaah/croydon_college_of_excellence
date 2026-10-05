<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // lesson_progress: student_id + course_slug for dashboard queries
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->index(['student_id', 'course_slug'], 'lesson_progress_student_course_idx');
        });

        // quiz_attempts: student_id + course_slug for dashboard queries
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(['student_id', 'course_slug'], 'quiz_attempts_student_course_idx');
        });

        // login_history: student_id + login_at for recent activity queries
        Schema::table('login_history', function (Blueprint $table) {
            $table->index(['student_id', 'login_at'], 'login_history_student_login_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex('lesson_progress_student_course_idx');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('quiz_attempts_student_course_idx');
        });

        Schema::table('login_history', function (Blueprint $table) {
            $table->dropIndex('login_history_student_login_idx');
        });
    }
};
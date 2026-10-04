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
        // lesson_progress: user_id + course_slug for dashboard queries
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->index(['user_id', 'course_slug'], 'lesson_progress_user_course_idx');
        });

        // quiz_attempts: user_id + course_slug for dashboard queries
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(['user_id', 'course_slug'], 'quiz_attempts_user_course_idx');
        });

        // login_history: user_id + login_at for recent activity queries
        Schema::table('login_history', function (Blueprint $table) {
            $table->index(['user_id', 'login_at'], 'login_history_user_login_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex('lesson_progress_user_course_idx');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('quiz_attempts_user_course_idx');
        });

        Schema::table('login_history', function (Blueprint $table) {
            $table->dropIndex('login_history_user_login_idx');
        });
    }
};
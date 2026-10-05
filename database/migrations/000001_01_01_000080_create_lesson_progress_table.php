<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I have finished this lesson".
 *
 * A lesson is not a row: it lives in the JSON content file and is named here by
 * its slug. That keeps this table to what is genuinely per learner, and it
 * means a lesson can be reworded or re-extracted without a learner losing the
 * fact that they read it.
 *
 * The unique triple is what stops a double-click, or a replayed request,
 * adding the same row twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_id')->constrained()->cascadeOnDelete();

            // Both slugs, because a slug is only unique within its course and
            // the two courses are sold separately.
            $table->string('course_slug', 255);
            $table->string('lesson_slug', 255);

            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['student_id', 'course_slug', 'lesson_slug'], 'lesson_progress_unique_read');
            $table->index(['student_id', 'course_slug'], 'lesson_progress_by_course');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};

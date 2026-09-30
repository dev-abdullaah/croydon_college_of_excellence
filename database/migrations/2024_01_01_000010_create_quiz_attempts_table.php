<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per sitting of a paper.
        //
        // A paper is not a row: it lives in the JSON content file and is named
        // here by its slug. This table holds only the learner's own work.
        //
        // Answers are written as the learner moves through the paper so a
        // refresh, a dropped connection or a closed tab does not lose their
        // work. `answers` is a JSON object of question number => "a"|"b"|"c"|"d",
        // the numbers being the question's place on the paper. Nothing in it is
        // trusted: the score is always recomputed from the content file at submit
        // time, so an edited paper cannot quietly rewrite a stored result and a
        // posted answer cannot invent a pass.
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('course_slug', 255);
            $table->string('quiz_slug', 255);

            $table->string('status', 16)->default('in_progress');
            $table->unsignedSmallInteger('current_position')->default(1);
            $table->json('answers')->nullable();

            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('total')->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->boolean('passed')->nullable();

            $table->unsignedInteger('time_taken_seconds')->nullable();

            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'course_slug', 'quiz_slug'], 'quiz_attempts_by_paper');
            $table->index(['user_id', 'status'], 'quiz_attempts_by_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};

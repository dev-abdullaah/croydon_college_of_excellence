<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');

            // Filename inside the `course-files` directory. Files are never
            // moved or renamed - only added to the catalogue.
            $table->string('filename');
            $table->text('description')->nullable();
            $table->string('file_type')->default('docx');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_documents');
    }
};

<?php

namespace App\Console\Commands;

use App\Content\CourseContent;
use App\Services\CourseContentExtractor;
use App\Services\CourseContentParser;
use Illuminate\Console\Command;
use Throwable;

/**
 * Reads the .docx material in `course-files/` and writes the JSON files the
 * site serves.
 *
 *   php artisan courses:extract --dry-run   parse and report, write nothing
 *   php artisan courses:extract             parse and write both files
 *   php artisan courses:extract --path=/tmp/docx
 *
 * The site does not need this. The JSON files in `database/data/` are the
 * course, and they are read straight from the repository. This exists so that
 * the source documents can be used to *change* the course without hand-editing
 * ten lessons and forty papers of JSON — and so the two can be compared.
 */
class CoursesExtract extends Command
{
    protected $signature = 'courses:extract
                            {--dry-run : Parse and report, but do not write the files}
                            {--path= : Override the course-files directory}';

    protected $description = 'Extract the lessons and papers from the course-files .docx documents into JSON';

    public function handle(CourseContentExtractor $extractor, CourseContentParser $parser, CourseContent $content): int
    {
        $directory = $this->option('path') ?: base_path('course-files');

        $this->line("Reading course material from <info>{$directory}</info>");

        if ($this->option('dry-run')) {
            return $this->report($parser->parse($directory));
        }

        try {
            $stats = $extractor->extract($directory);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            if (method_exists($e, 'problems')) {
                $this->printProblems($e->problems());
            }

            return self::FAILURE;
        }

        // The files are read back through the same code that serves them, so
        // nothing is reported as extracted that the site could not actually use.
        $content->flush();
        $loaded = $content->summary();

        $this->info('Course content written.');
        $this->newLine();

        foreach ($stats['written'] as $path) {
            $this->line('  '.$path);
        }

        $this->newLine();
        $this->line(sprintf('  Lessons        %d', $loaded['lessons']));
        $this->line(sprintf('  Study cards    %d', $loaded['cards']));
        $this->line(sprintf('  Papers         %d', $loaded['quizzes']));
        $this->line(sprintf('  Questions      %d', $loaded['questions']));
        $this->newLine();

        if ($loaded['lessons'] !== $stats['lessons']
            || $loaded['quizzes'] !== $stats['papers']
            || $loaded['questions'] !== $stats['questions']
            || $loaded['cards'] !== $stats['cards']) {
            $this->error('The files were written but do not read back the same. Nothing should be committed.');
            $this->line('  Run `php artisan courses:extract --dry-run` and check the documents.');

            return self::FAILURE;
        }

        $this->line('Read back the same as written.');
        $this->newLine();
        $this->line('Review the diff, then:');
        $this->line('  php artisan test --filter=CourseContent');

        return self::SUCCESS;
    }

    /**
     * @param  array{lessons: array, quizzes: array, problems: array}  $parsed
     */
    protected function report(array $parsed): int
    {
        $cards = array_sum(array_column($parsed['lessons'], 'item_count'));

        $this->newLine();
        $this->line(sprintf('  Lessons        %d (%d study cards)', count($parsed['lessons']), $cards));

        foreach ($parsed['quizzes'] as $kind => $papers) {
            $questions = array_sum(array_map(fn ($paper) => count($paper['questions']), $papers));

            $this->line(sprintf('  %-14s %d papers, %d questions', $kind, count($papers), $questions));
        }

        $this->newLine();

        if ($parsed['problems'] === []) {
            $this->info('No problems found. The documents parse cleanly.');

            return self::SUCCESS;
        }

        $this->printProblems($parsed['problems']);

        return self::FAILURE;
    }

    /**
     * @param  array<int, array{severity: string, message: string, context: array}>  $problems
     */
    protected function printProblems(array $problems): void
    {
        $errors = array_filter($problems, fn (array $p) => $p['severity'] === 'error');

        foreach ($problems as $problem) {
            $label = $problem['severity'] === 'error' ? 'ERROR' : 'WARN ';

            $this->line("  <{$label}> {$problem['message']}");

            foreach ($problem['context'] as $key => $value) {
                $this->line("         {$key}: ".mb_substr((string) $value, 0, 200));
            }
        }

        $this->newLine();

        if ($errors !== []) {
            $this->error(count($errors).' error(s). Nothing was written.');
            $this->line('  Fix the .docx and run this command again.');
        } else {
            $this->warn(count($problems).' warning(s), but nothing blocking.');
        }
    }
}

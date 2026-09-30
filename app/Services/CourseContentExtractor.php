<?php

namespace App\Services;

use App\Content\Quiz;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Turns the .docx course material into the JSON files the site reads.
 *
 * The .docx files in `course-files/` were the source text while the course was
 * being written. They are not a permanent home for it — they have been handed
 * to a designer and may be reformatted, and a course whose content lives in
 * Word documents cannot be reviewed in a pull request. So the text is extracted
 * once into `database/data/lesson-content.json` and
 * `database/data/quiz-content.json`, and those files are what the site serves.
 *
 * This class is the way back, if the originals ever come to mean something
 * different: drop the revised .docx files into `course-files/`, run
 * `php artisan courses:extract`, and read the diff. Every check the parser makes
 * still applies — in particular, an answer key that does not match the option it
 * claims to answer stops the extraction, so a bad file is never written.
 *
 * The output is laid out with one lesson, or one paper, per line. That makes a
 * content change a one-line diff in review instead of a reformatted wall of
 * JSON, which is the only reason a JSON file can be reviewed at all.
 */
class CourseContentExtractor
{
    /**
     * The format version, so a future change is recognised rather than
     * silently misread. Kept in step with App\Content\CourseContent.
     */
    public const VERSION = 1;

    /**
     * Extract both files from a directory of .docx documents.
     *
     * @return array{lessons: int, cards: int, papers: int, questions: int, problems: array<int, array>, written: array<int, string>}
     *
     * @throws CourseContentExtractionFailedException if the documents do not line up; nothing is written
     */
    public function extract(string $directory): array
    {
        $parsed = (new CourseContentParser)->parse($directory);

        $errors = array_values(array_filter(
            $parsed['problems'],
            fn (array $problem) => $problem['severity'] === 'error'
        ));

        if ($errors !== []) {
            // Never write a half-parsed course. A question whose answer key
            // points at the wrong option is worse than a course that refuses to
            // be extracted, because the learner finds out at the results page.
            throw new CourseContentExtractionFailedException($errors);
        }

        $lessons = $this->lessonFile($parsed['lessons']);
        $papers = $this->paperFile($this->flattenPapers($parsed['quizzes']));

        $this->write(config('course-content.lessons'), $lessons);
        $this->write(config('course-content.quizzes'), $papers);

        return [
            'lessons' => count($parsed['lessons']),
            'cards' => array_sum(array_column($parsed['lessons'], 'item_count')),
            'papers' => count($this->flattenPapers($parsed['quizzes'])),
            'questions' => $this->countQuestions($this->flattenPapers($parsed['quizzes'])),
            'problems' => $parsed['problems'],
            'written' => [config('course-content.lessons'), config('course-content.quizzes')],
        ];
    }

    /* -----------------------------------------------------------------
     | Building the two files
     | ----------------------------------------------------------------- */

    /**
     * @param  array<int, array<string, mixed>>  $lessons
     */
    private function lessonFile(array $lessons): string
    {
        $rows = [];

        foreach ($lessons as $lesson) {
            $rows[$lesson['course']]['name'] ??= $this->courseName($lesson['course']);
            $rows[$lesson['course']]['lessons'][] = [
                'number' => $lesson['number'],
                'slug' => $lesson['slug'],
                'title' => $lesson['title'],
                'summary' => $lesson['summary'] ?? null,
                // A card has no number of its own: its place in the lesson is
                // its number, and storing that separately would only give the
                // two a way to disagree.
                'items' => array_map(fn (array $item) => [
                    'question' => $item['question'],
                    'answer' => $item['answer'],
                ], $lesson['items']),
            ];
        }

        return $this->encode(
            $this->header('The lesson cards, in the order a learner reads them.'),
            $rows,
            'lessons'
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $papers
     */
    private function paperFile(array $papers): string
    {
        $rows = [];

        foreach ($papers as $paper) {
            $rows[$paper['course']]['name'] ??= $this->courseName($paper['course']);
            $rows[$paper['course']]['papers'][] = [
                'kind' => $paper['kind'],
                'number' => $paper['number'],
                'slug' => $paper['slug'],
                'title' => $paper['title'],
                'description' => $paper['description'] ?? null,
                'time_limit_minutes' => $paper['time_limit_minutes'],
                'pass_mark_percent' => $paper['pass_mark_percent'],
                'questions' => array_map(fn (array $q) => [
                    'prompt' => $q['prompt'],
                    // Keyed by letter rather than held as a list, so the answer
                    // key below reads as "the answer is b", and a question
                    // cannot quietly lose an option from the middle.
                    'options' => [
                        'a' => $q['option_a'],
                        'b' => $q['option_b'],
                        'c' => $q['option_c'],
                        'd' => $q['option_d'],
                    ],
                    'correct' => $q['correct_option'],
                    'explanation' => $q['explanation'],
                ], $paper['questions']),
            ];
        }

        return $this->encode(
            $this->header('Every paper a learner can sit, each with its answer key.'),
            $rows,
            'papers'
        );
    }

    /**
     * The parser groups papers by kind and then by number; the file lists them
     * the way a learner works through them, which is the same order.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $byKind
     * @return array<int, array<string, mixed>>
     */
    private function flattenPapers(array $byKind): array
    {
        $kinds = array_flip(Quiz::kindOrder());
        $flat = [];

        foreach ($byKind as $papers) {
            foreach ($papers as $paper) {
                $flat[] = $paper;
            }
        }

        usort(
            $flat,
            fn (array $a, array $b) => [($kinds[$a['kind']] ?? 99), $a['number']]
                <=> [($kinds[$b['kind']] ?? 99), $b['number']]
        );

        return $flat;
    }

    /**
     * @param  array<string, mixed>  $head
     * @return array<string, mixed>
     */
    private function header(string $note): array
    {
        return [
            'version' => self::VERSION,
            'note' => $note.' Extracted from the source .docx documents by `php artisan courses:extract`. '
                .'This file is the course: edit it here, or re-extract from the documents, but not both for the same lesson.',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $papers
     */
    private function countQuestions(array $papers): int
    {
        return array_sum(array_map(fn (array $paper) => count($paper['questions']), $papers));
    }

    /**
     * A name for a course slug, for anyone opening the file in an editor.
     *
     * The catalogue is a list of courses rather than a map, so it is searched
     * for the slug rather than indexed by it. A course in the content files that
     * the catalogue does not know about still gets a readable name.
     */
    private function courseName(string $slug): string
    {
        foreach ((array) config('catalog.courses', []) as $course) {
            if (($course['slug'] ?? null) === $slug) {
                return (string) ($course['name'] ?? Str::headline($slug));
            }
        }

        return Str::headline($slug);
    }

    /* -----------------------------------------------------------------
     | Writing it out
     | ----------------------------------------------------------------- */

    private function write(?string $path, string $contents): void
    {
        if ($path === null || $path === '') {
            throw new RuntimeException(
                'No content file is configured in config/course-content.php, so there is nowhere to write.'
            );
        }

        $absolute = str_starts_with($path, '/') ? $path : base_path($path);

        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $contents);
    }

    /**
     * Lay the file out with one lesson, or one paper, per line.
     *
     * A pretty-printed file this size diffs to noise: reordering a lesson shows
     * every line as changed. Keeping each row on one line means a content change
     * is a change to one line, which is what makes it reviewable.
     *
     * The layout is written by hand rather than left to JSON_PRETTY_PRINT,
     * because the whole point of it is the rows staying on their own line.
     *
     * @param  array<string, mixed>  $head
     * @param  array<string, array{name: string, lessons?: array, papers?: array}>  $courses
     */
    private function encode(array $head, array $courses, string $rowKey): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        $lines = ['{'];

        foreach ($head as $key => $value) {
            $lines[] = '    '.json_encode((string) $key, $flags).': '
                .json_encode($value, $flags | JSON_PRETTY_PRINT).',';
        }

        $lines[] = '    "courses": {';

        $lastCourse = array_key_last($courses);

        foreach ($courses as $slug => $course) {
            $lines[] = '        '.json_encode((string) $slug, $flags).': {';
            $lines[] = '            "name": '.json_encode($course['name'], $flags).',';
            $lines[] = '            "'.$rowKey.'": [';

            $rows = $course[$rowKey] ?? [];
            $lastRow = array_key_last($rows);

            foreach ($rows as $index => $row) {
                $lines[] = '                '.json_encode($row, $flags).($index === $lastRow ? '' : ',');
            }

            $lines[] = '            ]';
            $lines[] = '        }'.($slug === $lastCourse ? '' : ',');
        }

        $lines[] = '    }';
        $lines[] = '}';

        return implode("\n", $lines)."\n";
    }
}

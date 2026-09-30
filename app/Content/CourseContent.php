<?php

namespace App\Content;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * The course material, read from the JSON files in the repository.
 *
 * The lessons and papers are the product, and they are held as files rather than
 * in database tables. That has a few consequences worth stating, because they are
 * the reason for the shape of everything here:
 *
 *   - A course is deployed by copying the code. There is no content to import
 *     and no seeder to remember, so a half-deployed site is not a state that can
 *     be reached.
 *   - The content cannot drift away from the code that serves it, because there
 *     is only ever one copy.
 *   - Only a learner's own progress needs a database, and that is small: which
 *     lessons they have read, and how they did on each paper.
 *
 * Files are read once per request and held in memory. They are a few hundred
 * kilobytes of text, which is cheaper than a query per lesson, and it means an
 * edit to a file is live the next time the page is loaded with no cache to clear.
 *
 * Everything read is validated on the way in. A paper missing a question, or an
 * answer key pointing at an option that does not exist, throws here with the
 * file and the place in it named - rather than reaching a learner as a paper that
 * cannot be finished or a result that cannot be believed.
 */
class CourseContent
{
    /**
     * The format of the JSON content files, so a future change is recognised
     * rather than silently misread. Kept in step with the extractor that writes
     * them.
     */
    public const VERSION = 1;

    /** @var array<string, Collection<int, Lesson>>|null */
    private ?array $lessons = null;

    /** @var array<string, Collection<int, Quiz>>|null */
    private ?array $quizzes = null;

    /* -----------------------------------------------------------------
     | Reading it
     | ----------------------------------------------------------------- */

    /**
     * The lessons for a course, in order.
     *
     * @return Collection<int, Lesson>
     */
    public function lessons(string $course): Collection
    {
        return $this->lessonsByCourse()[$course] ?? collect();
    }

    /**
     * One lesson by slug, or null if this course has no such lesson.
     *
     * Scoping by course is what stops one course's lesson being read through
     * another course's URL.
     */
    public function lesson(string $course, string $slug): ?Lesson
    {
        return $this->lessons($course)->first(fn (Lesson $lesson) => $lesson->slug === $slug);
    }

    /**
     * One lesson by its number in the course, which is how a knowledge check
     * finds the lesson it belongs to.
     */
    public function lessonByNumber(string $course, int $number): ?Lesson
    {
        return $this->lessons($course)->first(fn (Lesson $lesson) => $lesson->number === $number);
    }

    /**
     * The papers for a course, knowledge checks first, then the classroom
     * papers, then the full mocks - the order a learner works through them.
     *
     * @return Collection<int, Quiz>
     */
    public function quizzes(string $course): Collection
    {
        return $this->quizzesByCourse()[$course] ?? collect();
    }

    /**
     * The papers for a course, grouped by kind and in the order above.
     *
     * @return array<string, Collection<int, Quiz>>
     */
    public function quizzesByKind(string $course): array
    {
        $order = array_flip(Quiz::kindOrder());

        $grouped = $this->quizzes($course)
            ->groupBy(fn (Quiz $quiz) => $quiz->kind)
            ->all();

        uksort($grouped, fn ($a, $b) => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return $grouped;
    }

    /**
     * One paper by slug, or null if this course has no such paper.
     */
    public function quiz(string $course, string $slug): ?Quiz
    {
        return $this->quizzes($course)->first(fn (Quiz $quiz) => $quiz->slug === $slug);
    }

    /**
     * The knowledge check that revises a given lesson, if the course has one.
     */
    public function knowledgeCheck(string $course, int $lessonNumber): ?Quiz
    {
        return $this->quizzes($course)->first(
            fn (Quiz $quiz) => $quiz->isKnowledgeCheck() && $quiz->number === $lessonNumber
        );
    }

    /**
     * Does this course have anything to read or sit?
     *
     * The dashboard uses this to decide whether to offer the learning area at
     * all, rather than sending a buyer to an empty page.
     */
    public function hasContent(string $course): bool
    {
        return $this->lessons($course)->isNotEmpty() || $this->quizzes($course)->isNotEmpty();
    }

    /**
     * Forget what has been read.
     *
     * Only needed when the files change underneath a long-running process, which
     * in practice means a test pointing at a fixture.
     */
    public function flush(): void
    {
        $this->lessons = null;
        $this->quizzes = null;
    }

    /**
     * A one-line summary of what is loaded, for the doctor command.
     *
     * @return array{lessons: int, cards: int, quizzes: int, questions: int}
     */
    public function summary(): array
    {
        $lessons = collect($this->lessonsByCourse())->flatten();
        $quizzes = collect($this->quizzesByCourse())->flatten();

        return [
            'lessons' => $lessons->count(),
            'cards' => $lessons->sum(fn (Lesson $lesson) => $lesson->itemCount()),
            'quizzes' => $quizzes->count(),
            'questions' => $quizzes->sum(fn (Quiz $quiz) => $quiz->questionCount()),
        ];
    }

    /* -----------------------------------------------------------------
     | Loading and validating
     | ----------------------------------------------------------------- */

    /**
     * @return array<string, Collection<int, Lesson>>
     */
    private function lessonsByCourse(): array
    {
        return $this->lessons ??= $this->read(
            config('course-content.lessons'),
            'lessons',
            fn (string $course, array $row, string $where) => $this->makeLesson($course, $row, $where)
        );
    }

    /**
     * @return array<string, Collection<int, Quiz>>
     */
    private function quizzesByCourse(): array
    {
        return $this->quizzes ??= $this->read(
            config('course-content.quizzes'),
            'papers',
            fn (string $course, array $row, string $where) => $this->makeQuiz($course, $row, $where)
        );
    }

    /**
     * Read a content file, validate every row, and index the result by course.
     *
     * Rows are filed under the course they belong to, so a row does not name its
     * own course: the key it is filed under *is* its course. There is then no way
     * for a row to disagree with the section it sits in.
     *
     * @param  string  $key  the array each course holds its rows under: the
     *                       lessons file holds "lessons", the papers file "papers"
     * @param  callable(string, array<string, mixed>, string): object  $make
     * @return array<string, Collection<int, object>>
     */
    private function read(?string $path, string $key, callable $make): array
    {
        $path = $this->absolute($path);

        if (! is_file($path)) {
            throw CourseContentException::unreadable(
                (string) $path,
                'the file does not exist. The course material is held in the repository; '
                .'restore it from version control, or rebuild it with: php artisan courses:extract'
            );
        }

        $raw = File::get($path);
        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw CourseContentException::unreadable((string) $path, 'it is not valid JSON ('.json_last_error_msg().')');
        }

        if (($decoded['version'] ?? null) !== 1) {
            throw CourseContentException::invalid(
                (string) $path,
                'it is version '.var_export($decoded['version'] ?? null, true).', but this site reads version 1'
            );
        }

        if (! is_array($decoded['courses'] ?? null)) {
            throw CourseContentException::invalid((string) $path, 'it has no "courses" section');
        }

        $byCourse = [];

        foreach ($decoded['courses'] as $slug => $content) {
            if (! is_array($content)) {
                throw CourseContentException::invalid("{$path} / course {$slug}", 'it is not an object');
            }

            if (! is_array($content[$key] ?? null)) {
                throw CourseContentException::invalid(
                    "{$path} / course {$slug}",
                    "it has no \"{$key}\" array, so the course is empty"
                );
            }

            $byCourse[$slug] = collect();

            foreach ($content[$key] as $index => $row) {
                $where = "{$path} / course {$slug} / {$key} ".($index + 1);

                if (! is_array($row)) {
                    throw CourseContentException::invalid($where, 'it is not an object');
                }

                $byCourse[$slug]->push($make((string) $slug, $row, $where));
            }
        }

        return $this->sorted($byCourse);
    }

    /**
     * Put the rows of each course into the order a learner meets them.
     *
     * The files are written in this order already, but a lesson reached out of
     * sequence, or a knowledge check filed after the mock tests, would quietly
     * show the wrong thing - so the order is settled here rather than assumed.
     *
     * @param  array<string, Collection<int, object>>  $byCourse
     * @return array<string, Collection<int, object>>
     */
    private function sorted(array $byCourse): array
    {
        $kinds = array_flip(Quiz::kindOrder());

        foreach ($byCourse as $slug => $rows) {
            $byCourse[$slug] = $rows->sortBy(
                fn (object $row) => $row instanceof Quiz
                    ? [$kinds[$row->kind] ?? 99, $row->number]
                    : [$row->number],
            )->values();
        }

        return $byCourse;
    }

    private function absolute(?string $path): string
    {
        if ($path === null || $path === '') {
            throw CourseContentException::invalid('(no path configured)', 'no content file is configured in config/course-content.php');
        }

        // An absolute path is taken as given, so a test can point at a fixture in
        // a temporary directory.
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function makeLesson(string $course, array $row, string $where): Lesson
    {
        $this->requireFields($where, $row, ['number', 'slug', 'title', 'items']);

        $cards = collect($row['items'])->values()->map(
            fn ($item, $index) => $this->makeCard($item, $index + 1, "{$where} / card ".($index + 1))
        );

        $number = $this->toInt($where, 'number', $row['number']);

        if ($number < 1) {
            throw CourseContentException::invalid($where, "the lesson number is {$number}, which is not a position in a course");
        }

        return new Lesson(
            course: $this->toSlug($where, 'course', $course),
            number: $number,
            slug: $this->toSlug($where, 'slug', $row['slug']),
            title: $this->toText($where, 'title', $row['title']),
            summary: isset($row['summary']) && is_string($row['summary']) && trim($row['summary']) !== ''
                ? $row['summary']
                : null,
            items: $cards,
        );
    }

    /**
     * @param  mixed  $item
     */
    private function makeCard($item, int $position, string $where): LessonCard
    {
        if (! is_array($item)) {
            throw CourseContentException::invalid($where, 'it is not an object');
        }

        $this->requireFields($where, $item, ['question', 'answer']);

        return new LessonCard(
            // A card's position is its place in the lesson, so it is taken from
            // the order rather than stored: the two cannot disagree.
            position: $position,
            question: $this->toText($where, 'question', $item['question']),
            answer: $this->toText($where, 'answer', $item['answer']),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function makeQuiz(string $course, array $row, string $where): Quiz
    {
        $this->requireFields(
            $where,
            $row,
            ['kind', 'number', 'slug', 'title', 'time_limit_minutes', 'pass_mark_percent', 'questions']
        );

        // A kind is a machine key rather than a url segment, so it is checked
        // against the kinds that exist instead of being held to slug rules.
        $kind = $row['kind'];

        if (! is_string($kind) || ! in_array($kind, Quiz::kindOrder(), true)) {
            throw CourseContentException::invalid(
                $where,
                '"'.(is_string($kind) ? $kind : gettype($kind)).'" is not a kind of paper. Expected one of: '
                .implode(', ', Quiz::kindOrder())
            );
        }

        $questions = collect($row['questions'])->values()
            ->map(fn ($q, $index) => $this->makeQuestion($q, $index + 1, "{$where} / question ".($index + 1)));

        if ($questions->isEmpty()) {
            throw CourseContentException::invalid($where, 'the paper has no questions, so it could not be sat');
        }

        $passMark = $this->toInt($where, 'pass_mark_percent', $row['pass_mark_percent']);

        if ($passMark < 1 || $passMark > 100) {
            throw CourseContentException::invalid($where, "the pass mark is {$passMark}%, which is not a percentage");
        }

        $minutes = $this->toInt($where, 'time_limit_minutes', $row['time_limit_minutes']);

        if ($minutes < 1) {
            throw CourseContentException::invalid($where, "the time limit is {$minutes} minutes");
        }

        return new Quiz(
            course: $this->toSlug($where, 'course', $course),
            kind: $kind,
            number: $this->toInt($where, 'number', $row['number']),
            slug: $this->toSlug($where, 'slug', $row['slug']),
            title: $this->toText($where, 'title', $row['title']),
            description: isset($row['description']) && is_string($row['description']) && trim($row['description']) !== ''
                ? $row['description']
                : null,
            time_limit_minutes: $minutes,
            pass_mark_percent: $passMark,
            questions: $questions,
        );
    }

    /**
     * @param  mixed  $row
     */
    private function makeQuestion($row, int $position, string $where): Question
    {
        if (! is_array($row)) {
            throw CourseContentException::invalid($where, 'it is not an object');
        }

        $this->requireFields($where, $row, ['prompt', 'options', 'correct', 'explanation']);

        $options = $row['options'];

        if (! is_array($options)) {
            throw CourseContentException::invalid($where, 'the options are not an object keyed a-d');
        }

        $options = array_change_key_case($options, CASE_LOWER);

        foreach (Question::LETTERS as $letter) {
            if (! isset($options[$letter]) || ! is_string($options[$letter]) || trim($options[$letter]) === '') {
                throw CourseContentException::invalid($where, "option {$letter} is missing or empty");
            }
        }

        $extra = array_diff(array_keys($options), Question::LETTERS);

        if ($extra !== []) {
            throw CourseContentException::invalid(
                $where,
                'there are options beyond a-d: '.implode(', ', $extra).'. A paper has four options.'
            );
        }

        $correct = $row['correct'];

        // A question whose key points at an option that is not there is the one
        // failure that would be invisible to a learner and wrong to guess at.
        if (! is_string($correct) || ! in_array(strtolower($correct), Question::LETTERS, true)) {
            throw CourseContentException::invalid(
                $where,
                'the correct answer is '.var_export($correct, true).', which is not one of a, b, c or d'
            );
        }

        return new Question(
            // A question's position is its number on the paper, taken from the
            // order rather than stored: the two cannot disagree, and the number
            // is what a learner's saved answer is keyed by.
            position: $position,
            prompt: $this->toText($where, 'prompt', $row['prompt']),
            options: array_map('strval', $options),
            correct: strtolower($correct),
            explanation: $this->toText($where, 'explanation', $row['explanation']),
        );
    }

    /* -----------------------------------------------------------------
     | Small assertions
     | ----------------------------------------------------------------- */

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $fields
     */
    private function requireFields(string $where, array $row, array $fields): void
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field, $row)) {
                throw CourseContentException::invalid($where, "it has no \"{$field}\"");
            }
        }
    }

    private function toText(string $where, string $field, mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw CourseContentException::invalid($where, "\"{$field}\" is empty");
        }

        return $value;
    }

    private function toInt(string $where, string $field, mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        throw CourseContentException::invalid($where, "\"{$field}\" is not a whole number: ".var_export($value, true));
    }

    private function toSlug(string $where, string $field, mixed $value): string
    {
        $text = $this->toText($where, $field, $value);

        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $text)) {
            throw CourseContentException::invalid(
                $where,
                "\"{$field}\" is \"{$text}\", which is not a url-safe slug"
            );
        }

        return $text;
    }
}

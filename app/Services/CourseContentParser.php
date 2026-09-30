<?php

namespace App\Services;

use App\Support\DocxReader;
use Throwable;

/**
 * Reads the course material out of the .docx files in `course-files/` and
 * turns it into lessons and quiz papers.
 *
 * The .docx files stay the source of truth. Nothing is hand typed into the
 * database, and re-running the importer after an edit to a document picks the
 * change up.
 *
 * The importer validates as it parses. Every answer key entry is checked back
 * against the options of the question it is supposed to answer, and anything
 * that does not line up is reported as a problem rather than quietly written
 * to the database. A course that would teach the wrong answer is worse than a
 * course that fails to import.
 */
class CourseContentParser
{
    /**
     * Where each piece of course material lives, and which course it belongs to.
     *
     * The two courses are separate products. The lesson pack, its knowledge
     * checks and the classroom papers all belong to the £99 course; the 24
     * paper practice pack is the £49 course on its own.
     *
     * @var array<string, array{file: string, course: string}>
     */
    public const SOURCES = [
        'lessons' => [
            'file' => 'Life in the UK Lesson 1-10.docx',
            'course' => 'life-in-the-uk-course',
        ],

        'knowledge_check' => [
            'file' => 'Life in the UK Lesson 1-10 Final Knowledge Checks.docx',
            'course' => 'life-in-the-uk-course',
        ],

        'classroom_mock' => [
            'file' => '6 Classroom Mock Test.docx',
            'course' => 'life-in-the-uk-course',
        ],

        'mock_test' => [
            'file' => 'Total 24 Mock Tests Life in the UK.docx',
            'course' => '24-mock-tests',
        ],
    ];

    /**
     * The three kinds of paper a learner can sit.
     *
     * `paper` and `answers` locate the heading that starts the paper and the
     * heading that starts its answer key. Both capture the paper's number.
     */
    private const PAPERS = [
        'knowledge_check' => [
            'title' => 'Knowledge Check :number',
            'description' => 'Ten questions covering Lesson :number. Pass mark 75%.',
            'paper' => '/^Knowledge Check (\d+)$/',
            'answers' => '/^Knowledge Check (\d+) Answers$/',
            'sort_group' => 0,
            'slug' => 'knowledge-check-:number',
            'time_limit' => 10,
        ],

        'classroom_mock' => [
            'title' => 'Class Mock Test :number',
            'description' => 'Class Mock Test :number - 24 questions drawn from across all ten lessons. Allow 45 minutes; 18 out of 24 is a pass.',
            'paper' => '/^Class Mock Test (\d+)$/',
            'answers' => '/^Class Mock Test (\d+)\s*[\x{2014}\x{2013}-]\s*Answers$/u',
            'sort_group' => 100,
            'slug' => 'class-mock-test-:number',
            'time_limit' => 45,
        ],

        'mock_test' => [
            'title' => 'Mock Test :number',
            'description' => 'Mock Test :number - 24 questions drawn from across all ten lessons. Allow 45 minutes; 18 out of 24 is a pass.',
            'paper' => '/^Mock Test (\d+)$/',
            'answers' => '/^Mock Test (\d+) Answers$/',
            'sort_group' => 200,
            'slug' => 'mock-test-:number',
            'time_limit' => 45,
        ],
    ];

    /**
     * Typos in the source documents that would otherwise end up on screen.
     *
     * Kept as an explicit, reviewed list rather than a blanket search and
     * replace, so it is obvious what the importer is rewriting and why.
     *
     * @var array<string, string>
     */
    private const TITLE_FIXES = [
        'The UK, ITs Countries, Geography & Symbols' => 'The UK, its Countries, Geography and Symbols',
        'The UK, ITs Countries, Geography &amp; Symbols' => 'The UK, its Countries, Geography and Symbols',
    ];

    /** @var array<int, string> */
    private array $problems = [];

    /**
     * Parse everything described by SOURCES.
     *
     * @param  string  $directory  the `course-files` directory
     * @return array{lessons: array<string, array>, quizzes: array<string, array>, problems: array<int, array>}
     */
    public function parse(string $directory): array
    {
        $this->problems = [];

        $lessons = $this->parseLessons($this->read($directory, 'lessons'));
        $quizzes = [];

        foreach (array_keys(self::PAPERS) as $kind) {
            $quizzes[$kind] = $this->parsePapers($directory, $kind);
        }

        return [
            'lessons' => $lessons,
            'quizzes' => $quizzes,
            'problems' => $this->problems,
        ];
    }

    /**
     * Parse the ten lessons.
     *
     * The document is a numbered question followed by an "Answer:" line, one
     * pair per study card.
     *
     * @param  array<int, string>  $paragraphs
     * @return array<string, array>
     */
    private function parseLessons(array $paragraphs): array
    {
        $lessons = [];
        $total = count($paragraphs);

        for ($i = 0; $i < $total; $i++) {
            if (! preg_match('/^Lesson\s*-?\s*(\d+)\s*:\s*(.+)$/i', $paragraphs[$i], $match)) {
                continue;
            }

            // The document opens with a "Course Contents" list that repeats
            // every lesson heading. A real lesson body always starts with
            // question 1, so that is what tells the two apart.
            if (! isset($paragraphs[$i + 1]) || ! preg_match('/^1\.\s+\S/', $paragraphs[$i + 1])) {
                continue;
            }

            $number = (int) $match[1];
            $items = [];

            for ($j = $i + 1; $j < $total; $j++) {
                // The next lesson heading ends this one.
                if (preg_match('/^Lesson\s*-?\s*\d+\s*[:\-]/i', $paragraphs[$j])) {
                    break;
                }

                if (! preg_match('/^(\d+)\.\s+(.+)$/', $paragraphs[$j], $question)) {
                    continue;
                }

                if (! isset($paragraphs[$j + 1]) || ! preg_match('/^Answer:\s*(\S.*)$/i', $paragraphs[$j + 1], $answer)) {
                    $this->problem(
                        "Lesson {$number} question {$question[1]} has no answer line.",
                        ['paragraph' => $paragraphs[$j]]
                    );

                    continue;
                }

                $items[] = [
                    'position' => count($items) + 1,
                    'question' => $question[2],
                    'answer' => $answer[1],
                ];

                $j++;
            }

            if ($items === []) {
                $this->problem("Lesson {$number} parsed with no study cards.");

                continue;
            }

            $lessons[(string) $number] = [
                'course' => self::SOURCES['lessons']['course'],
                'number' => $number,
                'slug' => 'lesson-'.$number,
                'title' => $this->fixTitle($match[2]),
                'item_count' => count($items),
                'items' => $items,
            ];
        }

        $this->checkLessonNumbers(array_keys($lessons), 10);

        return $lessons;
    }

    /**
     * Parse one kind of paper - a document of numbered papers each with an
     * answer key at the back.
     *
     * @return array<string, array>
     */
    private function parsePapers(string $directory, string $kind): array
    {
        $spec = self::PAPERS[$kind];
        $paragraphs = $this->read($directory, $kind);

        $papers = $this->locate($paragraphs, $spec['paper'], $spec['answers']);
        $keys = [];

        foreach ($this->locate($paragraphs, $spec['answers'], null) as $number => $block) {
            $keys[$number] = $block;
        }

        if ($papers === []) {
            // Either the file is missing, or its headings have been changed
            // out of recognition. Both mean this kind of paper would silently
            // vanish from the site, so neither is allowed through.
            $this->problem(
                "{$kind}: no papers were found in [".self::SOURCES[$kind]['file'].'].',
                ['expected_heading' => $spec['paper']],
                severity: 'error'
            );

            return [];
        }

        $quizzes = [];

        foreach ($papers as $number => $block) {
            $questions = $this->parseQuestions($block, "{$kind} {$number}");

            $key = $keys[$number] ?? null;

            if ($key === null) {
                $this->problem("{$kind} {$number} has no answer key, so it cannot be sat on this site.", severity: 'error');

                continue;
            }

            $this->applyAnswerKey($questions, $key, "{$kind} {$number}");

            $complete = array_filter($questions, fn (array $q) => $q['correct_option'] !== null);

            if (count($complete) !== count($questions)) {
                $this->problem(
                    "{$kind} {$number} has ".count($questions) - count($complete).' question(s) the answer key does not cover.',
                    severity: 'error'
                );
            }

            if ($questions === []) {
                $this->problem("{$kind} {$number} parsed with no questions.");

                continue;
            }

            $quizzes[(string) $number] = [
                'course' => self::SOURCES[$kind]['course'],
                'kind' => $kind,
                'number' => $number,
                'slug' => str_replace(':number', (string) $number, $spec['slug']),
                'title' => str_replace(':number', (string) $number, $spec['title']),
                'description' => str_replace(':number', (string) $number, $spec['description']),
                'time_limit_minutes' => $spec['time_limit'],
                'pass_mark_percent' => 75,
                'sort_order' => $spec['sort_group'] + $number,
                'questions' => array_values($complete),
            ];
        }

        $this->checkPaperNumbers(array_keys($quizzes), $kind, $papers, $keys);

        return $quizzes;
    }

    /**
     * Split a document into blocks, each starting at a line matching the
     * heading pattern and running until the next heading.
     *
     * Every heading - paper and answer key alike - is a boundary, so a paper
     * never runs into an answer key and an answer key never runs into the
     * next paper.
     *
     * @param  array<int, string>  $paragraphs
     * @return array<string, array<int, string>>
     */
    private function locate(array $paragraphs, string $pattern, ?string $alsoCutOn): array
    {
        $boundaries = [];

        foreach ($paragraphs as $index => $line) {
            if (preg_match($pattern, $line) || $alsoCutOn && preg_match($alsoCutOn, $line)) {
                $boundaries[] = $index;
            } elseif (preg_match('/^Answer Keys?$/i', $line)) {
                $boundaries[] = $index;
            }
        }

        $blocks = [];
        $last = count($paragraphs);

        foreach ($boundaries as $position => $start) {
            if (! preg_match($pattern, $paragraphs[$start], $match)) {
                continue;
            }

            $end = $boundaries[$position + 1] ?? $last;

            $blocks[(string) (int) $match[1]] = array_slice($paragraphs, $start + 1, $end - $start - 1);
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $block
     * @return array<int, array>
     */
    private function parseQuestions(array $block, string $label): array
    {
        $questions = [];
        $current = null;

        foreach ($block as $line) {
            if (preg_match('/^(\d+)\.\s+(\S.*)$/', $line, $match)) {
                if ($current !== null) {
                    $questions[] = $this->finishQuestion($current, $label);
                }

                $current = [
                    'source_number' => (int) $match[1],
                    'prompt' => $match[2],
                    'options' => [],
                ];

                continue;
            }

            if ($current !== null && preg_match('/^([A-D])\.\s+(\S.*)$/', $line, $match)) {
                $current['options'][strtolower($match[1])] = $match[2];
            }
        }

        if ($current !== null) {
            $questions[] = $this->finishQuestion($current, $label);
        }

        return $questions;
    }

    /**
     * @param  array{prompt: string, options: array<string, string>, source_number: int|null}  $question
     */
    private function finishQuestion(array $question, string $label): array
    {
        $number = $question['source_number'];

        if (count($question['options']) !== 4) {
            $this->problem(
                "{$label} question {$number} has ".count($question['options']).' options, not 4.',
                ['prompt' => $question['prompt']],
                severity: 'error'
            );
        }

        return [
            'position' => 0,
            'source_number' => $number,
            'prompt' => $question['prompt'],
            'option_a' => $question['options']['a'] ?? '',
            'option_b' => $question['options']['b'] ?? '',
            'option_c' => $question['options']['c'] ?? '',
            'option_d' => $question['options']['d'] ?? '',
            'correct_option' => null,
            'explanation' => null,
        ];
    }

    /**
     * Read the answer key and attach the correct answer to each question.
     *
     * @param  array<int, array>  $questions
     * @param  array<int, string>  $block
     */
    private function applyAnswerKey(array &$questions, array $block, string $label): void
    {
        $letters = [];
        $explanations = [];

        foreach ($block as $line) {
            // "1. A - the answer, and why" - the explanation lines.
            if (preg_match('/^(\d+)\.\s*([A-D])\s*[\x{2014}\x{2013}-]\s*(\S.*)$/u', $line, $match)) {
                $text = preg_replace('/\s*\(Source question[^)]*\)\s*$/u', '', $match[3]);

                $explanations[(int) $match[1]] = [
                    'option' => strtolower($match[2]),
                    'text' => trim((string) $text),
                ];

                continue;
            }

            // "1. A    2. B    3. C" - the at-a-glance letter grid.
            if (preg_match_all('/(\d+)\.\s*([A-D])(?=\s|$)/u', $line, $grid, PREG_SET_ORDER)) {
                foreach ($grid as $pair) {
                    $letters[(int) $pair[1]] = strtolower($pair[2]);
                }
            }
        }

        foreach ($questions as $index => $question) {
            $number = $question['source_number'] ?? ($index + 1);

            $explanation = $explanations[$number] ?? null;
            $letter = $explanation['option'] ?? $letters[$number] ?? null;

            if ($letter === null) {
                $this->problem("{$label} question {$number} is missing from the answer key.");

                continue;
            }

            if (isset($letters[$number]) && $letters[$number] !== $letter) {
                $this->problem(
                    "{$label} question {$number}: the answer grid says {$letters[$number]} but the answer text says {$letter}.",
                    severity: 'error'
                );

                continue;
            }

            $option = 'option_'.$letter;

            // The answer text restates the correct option. If it does not match
            // then the key has drifted from the paper and importing it would
            // teach the wrong answer, so stop rather than guess.
            if ($explanation !== null && trim($question[$option]) !== $explanation['text']) {
                $this->problem(
                    "{$label} question {$number}: the answer key text does not match option {$letter}.",
                    [
                        'prompt' => $question['prompt'],
                        'option' => $question[$option],
                        'answer_key' => $explanation['text'],
                    ],
                    severity: 'error'
                );

                continue;
            }

            $questions[$index]['position'] = $index + 1;
            $questions[$index]['correct_option'] = $letter;
            $questions[$index]['explanation'] = $explanation['text'] ?? null;
        }
    }

    /**
     * Read one source document.
     *
     * A missing or unreadable file is recorded as a problem and treated as an
     * empty document rather than thrown, so one bad file produces one report
     * listing every problem instead of stopping at the first. Either way the
     * caller refuses to write, so nothing partial reaches the database.
     *
     * @return array<int, string>
     */
    private function read(string $directory, string $key): array
    {
        $file = self::SOURCES[$key]['file'];
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;

        if (! is_file($path)) {
            $this->problem(
                "Course material is missing: [{$path}].",
                ['document' => $file, 'expected_in' => $directory],
                severity: 'error'
            );

            return [];
        }

        try {
            return DocxReader::paragraphs($path);
        } catch (Throwable $e) {
            $this->problem(
                "Could not read [{$path}]: {$e->getMessage()}",
                ['document' => $file],
                severity: 'error'
            );

            return [];
        }
    }

    private function fixTitle(string $title): string
    {
        $title = trim($title);

        return self::TITLE_FIXES[$title] ?? $title;
    }

    /**
     * @param  array<int, string>  $found
     */
    private function checkLessonNumbers(array $found, int $expected): void
    {
        if (count($found) === $expected && array_keys($found) === range(0, $expected - 1)) {
            return;
        }

        $this->problem(
            "Expected lessons 1 to {$expected}, found: ".($found === [] ? 'none' : implode(', ', $found)).'.',
            severity: 'error'
        );
    }

    /**
     * @param  array<int, string>  $found
     * @param  array<string, array>  $papers
     * @param  array<string, array>  $keys
     */
    private function checkPaperNumbers(array $found, string $kind, array $papers, array $keys): void
    {
        if ($found === [] || $papers === []) {
            return;
        }

        $expected = count($papers);
        $missingKeys = array_diff(array_keys($papers), array_keys($keys));

        if ($missingKeys !== []) {
            $this->problem(
                "{$kind}: no answer key for paper(s) ".implode(', ', $missingKeys).'.',
                severity: 'error'
            );
        }

        if (count($found) === $expected) {
            return;
        }

        $this->problem(
            "{$kind}: expected {$expected} papers, imported ".count($found).' (found: '.implode(', ', $found).').',
            severity: 'error'
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function problem(string $message, array $context = [], string $severity = 'error'): void
    {
        $this->problems[] = [
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
        ];
    }
}

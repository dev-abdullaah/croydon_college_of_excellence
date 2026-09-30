<?php

namespace App\Support;

use RuntimeException;

/**
 * Minimal .docx text reader.
 *
 * A .docx is a zip containing WordprocessingML. The course material in
 * `course-files/` is plain prose and lettered lists, so we only need the
 * paragraph text in document order - no styling, tables or images.
 *
 * Written here rather than pulled in as a dependency because the project
 * deliberately keeps its composer footprint small, and because the exact
 * paragraph boundaries the importer relies on are easiest to see in ~40 lines
 * of code than to configure in a general purpose library.
 */
class DocxReader
{
    /** Stand-in for a paragraph break while the XML tags are stripped. */
    private const PARAGRAPH = "\x02";

    /**
     * Every non-empty paragraph of the document, in order, trimmed.
     *
     * Tabs and soft line breaks inside a paragraph are kept, so a caller can
     * tell "one long line" from "two stacked lines".
     *
     * @return array<int, string>
     */
    public static function paragraphs(string $path): array
    {
        $xml = (new ZipReader($path))->read('word/document.xml');

        if ($xml === null) {
            throw new RuntimeException("[{$path}] does not contain word/document.xml.");
        }

        // Order matters: record the structural breaks before removing tags, or
        // the tags would swallow them.
        $xml = preg_replace('#<w:tab\s*/?>#', "\t", $xml);
        $xml = preg_replace('#<w:(br|cr)\b[^>]*/?>#', "\n", $xml);

        // Paragraph ends, and table cell / row ends, all separate content.
        $xml = str_replace(['</w:p>', '</w:tc>', '</w:tr>'], self::PARAGRAPH, $xml);

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');

        $paragraphs = [];

        foreach (explode(self::PARAGRAPH, $text) as $paragraph) {
            // A soft line break inside one paragraph is really a separate
            // line as far as the importer is concerned.
            foreach (preg_split('/\r\n|\r|\n/', $paragraph) as $line) {
                $line = trim($line);

                if ($line !== '') {
                    $paragraphs[] = $line;
                }
            }
        }

        return $paragraphs;
    }
}

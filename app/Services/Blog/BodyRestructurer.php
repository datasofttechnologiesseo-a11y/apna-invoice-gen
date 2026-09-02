<?php

namespace App\Services\Blog;

/**
 * Rebuild Markdown structure in a post body that was pasted as plain text.
 *
 * When rich text is pasted into a textarea, only the text/plain half arrives.
 * That strips the Markdown SYNTAX but not the shape: Google Docs and Word keep
 * line breaks, blank lines and the actual bullet characters. So a flattened
 * article still carries enough signal to rebuild - headings are the short
 * standalone lines, bullets are the lines starting with a bullet glyph.
 *
 * Deliberately conservative. Every rule here can be wrong about a given line,
 * and a wrong heading in the middle of a sentence is worse than a missing one,
 * so anything ambiguous is left as a paragraph.
 *
 * Idempotent: running it over already-structured Markdown changes nothing.
 */
class BodyRestructurer
{
    /** Bullet glyphs Docs, Word and web pages leave behind in plain text. */
    private const BULLETS = ['•', '●', '▪', '◦', '‣', '·', '∙', '–', '—', '*'];

    /** A heading rarely runs longer than this. */
    private const HEADING_MAX_CHARS = 80;

    private const HEADING_MIN_WORDS = 2;

    private const HEADING_MAX_WORDS = 12;

    /**
     * @param  string  $body   the stored post body
     * @param  string|null  $title  the post title, so a repeated H1 can be dropped
     * @return array{body:string, changes:array<string,int>}
     */
    public function restructure(string $body, ?string $title = null): array
    {
        $changes = ['headings' => 0, 'bullets' => 0, 'numbered' => 0, 'title_removed' => 0];

        $text = str_replace(["\r\n", "\r"], "\n", $body);
        // Docs pastes are full of non-breaking spaces, which defeat trim().
        $text = str_replace(["\u{00A0}", "\u{200B}"], [' ', ''], $text);

        $lines = explode("\n", $text);
        $out = [];
        $inFence = false;

        foreach ($lines as $i => $raw) {
            $line = rtrim($raw);
            $trimmed = trim($line);

            // Never touch fenced code.
            if (str_starts_with($trimmed, '```')) {
                $inFence = ! $inFence;
                $out[] = $line;
                continue;
            }
            if ($inFence || $trimmed === '') {
                $out[] = $line;
                continue;
            }

            // Leave anything already carrying Markdown structure alone. This is
            // what makes the pass idempotent and safe to re-run.
            if ($this->isAlreadyStructured($trimmed)) {
                $out[] = $line;
                continue;
            }

            // A body that opens by repeating the post title gives the page two
            // H1s worth of the same words.
            if ($title !== null && $i < 3 && $this->sameText($trimmed, $title)) {
                $changes['title_removed']++;
                continue;
            }

            if (($converted = $this->asBullet($trimmed)) !== null) {
                $out[] = $converted;
                $changes['bullets']++;
                continue;
            }

            if (($converted = $this->asNumbered($trimmed)) !== null) {
                $out[] = $converted;
                $changes['numbered']++;
                continue;
            }

            if ($this->looksLikeHeading($trimmed, $lines, $i)) {
                $out[] = '## '.$trimmed;
                $changes['headings']++;
                continue;
            }

            $out[] = $line;
        }

        return [
            'body' => $this->normaliseBlankLines(implode("\n", $out)),
            'changes' => $changes,
        ];
    }

    /** True when the line already uses Markdown syntax we would otherwise add. */
    private function isAlreadyStructured(string $line): bool
    {
        // Only the DOT form is Markdown. "1) " is the Word/Docs shape and is
        // exactly what asNumbered() converts, so matching it here would skip
        // the conversion entirely.
        return (bool) preg_match('/^(#{1,6}\s|[-*+]\s|\d+\.\s|>\s|\||!\[|\[.*\]\(|---+$|===+$)/u', $line);
    }

    /** A bullet glyph at the start becomes a Markdown list item. */
    private function asBullet(string $line): ?string
    {
        foreach (self::BULLETS as $glyph) {
            if (str_starts_with($line, $glyph.' ') || str_starts_with($line, $glyph."\t")) {
                $rest = trim(mb_substr($line, mb_strlen($glyph)));

                return $rest === '' ? null : '- '.$rest;
            }
        }

        return null;
    }

    /**
     * "1) Do this" becomes "1. Do this".
     *
     * A bare "1. " is already Markdown and is caught by isAlreadyStructured,
     * so only the paren form needs converting.
     */
    private function asNumbered(string $line): ?string
    {
        if (preg_match('/^(\d{1,2})\)\s+(.+)$/u', $line, $m)) {
            return $m[1].'. '.$m[2];
        }

        return null;
    }

    /**
     * Conservative heading test.
     *
     * A heading is a short standalone line that introduces the text under it.
     * The strongest signals are structural rather than typographic: a blank
     * line above, real content below, no sentence-ending punctuation.
     */
    private function looksLikeHeading(string $line, array $lines, int $index): bool
    {
        if (mb_strlen($line) > self::HEADING_MAX_CHARS) {
            return false;
        }

        $words = preg_split('/\s+/u', $line) ?: [];
        $wordCount = count(array_filter($words));
        if ($wordCount < self::HEADING_MIN_WORDS || $wordCount > self::HEADING_MAX_WORDS) {
            return false;
        }

        // Sentence punctuation means prose. A question mark does not - "What is
        // a GST invoice?" is one of the most common heading shapes there is.
        if (preg_match('/[.,;:]$/u', $line)) {
            return false;
        }

        // A line that reads as a sentence fragment mid-paragraph is not a
        // heading, so require a blank line (or the very start) above it.
        $above = $index > 0 ? trim($lines[$index - 1] ?? '') : '';
        if ($index > 0 && $above !== '') {
            return false;
        }

        // And require something underneath for it to introduce.
        $below = trim($lines[$index + 1] ?? '');
        if ($below === '') {
            // Allow one blank line between a heading and its paragraph.
            $below = trim($lines[$index + 2] ?? '');
        }
        if ($below === '') {
            return false;
        }
        // If what follows is itself heading-shaped, this is probably a list of
        // short lines rather than a heading with a body.
        if (mb_strlen($below) <= self::HEADING_MAX_CHARS && ! preg_match('/[.?!]$/u', $below)) {
            return false;
        }

        // Finally: a heading is rarely all lower case.
        return (bool) preg_match('/^[\p{Lu}\p{N}]/u', $line);
    }

    private function sameText(string $a, string $b): bool
    {
        $norm = fn (string $s) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s));

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    /** One blank line between blocks; none doubled up, none at the ends. */
    private function normaliseBlankLines(string $text): string
    {
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // A heading needs air above and below it or the renderer runs it into
        // the paragraph before.
        $text = preg_replace("/(?<!\n)\n(#{2,6}\s)/", "\n\n$1", $text);
        $text = preg_replace("/(#{2,6}[^\n]+)\n(?!\n)/", "$1\n\n", $text);

        return trim($text)."\n";
    }
}

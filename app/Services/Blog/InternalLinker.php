<?php

namespace App\Services\Blog;

use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Adds contextual internal links to rendered post HTML.
 *
 * Blog traffic that never reaches a tool page is traffic that does nothing.
 * The editor's checklist asks the author for an internal link, but asking is
 * not the same as getting: across the posts we had, almost none carried one.
 *
 * Rules, which are the SEO-hygiene part rather than the mechanical part:
 *
 *  - First occurrence only. The same link repeated eight times down a page
 *    reads as keyword stuffing and the repeats carry no additional weight.
 *  - Never inside an existing <a>, a heading, or code. Linking a heading
 *    steals the click from the heading; nesting an <a> is invalid HTML.
 *  - Longest phrase first, so "GST invoice format" wins over "invoice" on the
 *    same words rather than fragmenting it.
 *  - Capped per post, so a long article does not turn blue.
 *  - Word boundaries, so "GST" never matches inside "GSTIN".
 */
class InternalLinker
{
    /** Elements whose text must never be linked. */
    private const FORBIDDEN_ANCESTORS = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'blockquote'];

    public function __construct(
        private readonly array $targets,
        private readonly int $maxLinks,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (array) config('internal_links.targets', []),
            (int) config('internal_links.max_per_post', 6),
        );
    }

    /**
     * @param  string  $html      rendered post body
     * @param  string|null  $selfUrl  the post's own URL, never linked to itself
     */
    public function apply(string $html, ?string $selfUrl = null): string
    {
        if ($html === '' || $this->targets === [] || $this->maxLinks < 1) {
            return $html;
        }

        $phrases = $this->phrasesLongestFirst();
        if ($phrases === []) {
            return $html;
        }

        $dom = new \DOMDocument();
        // The body is a fragment, so wrap it and declare UTF-8 - without the
        // meta, DOMDocument mangles rupee signs and Devanagari.
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $html;   // malformed markup: leave it exactly as it was
        }

        $root = $dom->getElementById('__root');
        if (! $root) {
            return $html;
        }

        $used = [];      // url => true, so each destination is linked once
        $count = 0;

        // A work queue rather than a single pass: linking splits a text node,
        // and the remainder after the match is a NEW node. Iterating a list
        // captured up front would never visit it - which silently dropped
        // every phrase that appeared after the first match in a paragraph, and
        // pushed links onto later occurrences instead of the first one.
        $queue = $this->textNodes($dom, $root);

        while ($queue !== [] && $count < $this->maxLinks) {
            $node = array_shift($queue);

            if (! $node->parentNode || trim($node->nodeValue) === '') {
                continue;
            }
            if ($this->hasForbiddenAncestor($node)) {
                continue;
            }

            // Take the EARLIEST match in this node, not the first phrase in
            // the list that happens to match. Sorting longest-first exists to
            // settle overlapping matches at the same position - applying it
            // across the whole node let a long phrase later in the sentence
            // jump ahead of a shorter one that came first, so links landed out
            // of reading order and intervening phrases were skipped entirely.
            $best = null;   // [offset, length, url]

            foreach ($phrases as [$phrase, $url]) {
                if (isset($used[$url]) || ($selfUrl !== null && $url === $selfUrl)) {
                    continue;
                }

                $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/iu';
                if (! preg_match($pattern, $node->nodeValue, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                $offset = (int) $m[0][1];
                $length = strlen($m[0][0]);

                // Earlier wins; at the same offset the longer phrase wins, so
                // "GST invoice format" beats "invoice format".
                if ($best === null || $offset < $best[0] || ($offset === $best[0] && $length > $best[1])) {
                    $best = [$offset, $length, $url];
                }
            }

            if ($best !== null) {
                [$offset, $length, $url] = $best;
                $tail = $this->linkAt($dom, $node, $offset, $length, $url);
                $used[$url] = true;
                $count++;

                // Re-scan what came after the match, at the FRONT of the queue,
                // so this paragraph is finished before moving to the next one.
                if ($tail !== null && trim($tail->nodeValue) !== '') {
                    array_unshift($queue, $tail);
                }
            }
        }

        $inner = '';
        foreach ($root->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner;
    }

    /** Phrase list flattened and sorted so the most specific match wins. */
    private function phrasesLongestFirst(): array
    {
        $out = [];
        foreach ($this->targets as $routeName => $phrases) {
            if (! RouteFacade::has($routeName)) {
                continue;   // a renamed route should not fatal the blog
            }
            $url = route($routeName, [], false);
            foreach ((array) $phrases as $phrase) {
                $phrase = trim((string) $phrase);
                if ($phrase !== '') {
                    $out[] = [$phrase, $url];
                }
            }
        }

        usort($out, fn ($a, $b) => mb_strlen($b[0]) <=> mb_strlen($a[0]));

        return $out;
    }

    /** @return \DOMText[] */
    private function textNodes(\DOMDocument $dom, \DOMNode $root): array
    {
        $xpath = new \DOMXPath($dom);
        $nodes = [];
        foreach ($xpath->query('.//text()', $root) ?: [] as $node) {
            if (trim($node->nodeValue) !== '') {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function hasForbiddenAncestor(\DOMNode $node): bool
    {
        for ($p = $node->parentNode; $p !== null; $p = $p->parentNode) {
            if ($p->nodeType === XML_ELEMENT_NODE
                && in_array(strtolower($p->nodeName), self::FORBIDDEN_ANCESTORS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split a text node around the match and drop an <a> in the middle.
     *
     * @return \DOMText|null the text following the link, for re-scanning
     */
    private function linkAt(\DOMDocument $dom, \DOMText $node, int $byteOffset, int $byteLength, string $url): ?\DOMText
    {
        $text = $node->nodeValue;
        $before = substr($text, 0, $byteOffset);
        $matched = substr($text, $byteOffset, $byteLength);
        $after = substr($text, $byteOffset + $byteLength);

        $link = $dom->createElement('a');
        $link->setAttribute('href', $url);
        // Marks these as generated, so a later pass can find or strip them.
        $link->setAttribute('data-internal-link', 'auto');
        $link->appendChild($dom->createTextNode($matched));

        $parent = $node->parentNode;
        $parent->insertBefore($dom->createTextNode($before), $node);
        $parent->insertBefore($link, $node);
        $tail = $dom->createTextNode($after);
        $parent->insertBefore($tail, $node);
        $parent->removeChild($node);

        return $tail;
    }
}

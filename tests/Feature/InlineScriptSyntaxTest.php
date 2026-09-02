<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Every inline <script> in a Blade view must be parseable JavaScript.
 *
 * This exists because a single malformed regex literal in the blog editor -
 * one that spanned a line break, which JavaScript does not allow - threw a
 * SyntaxError that killed the whole script block. Alpine then had no
 * postEditor() to call, so every binding on the page failed at once: the
 * title, slug and body fields filled with "[object HTMLInputElement]", the
 * word count went blank, and every toolbar button (bold, italic, H2, H3,
 * links, lists), the preview and the SEO checklist stopped working.
 *
 * Nothing caught it. The page still returned 200, so the route smoke tests
 * passed; the PHP was valid, so nothing else complained. The failure lived
 * entirely in the browser, and it shipped to production.
 *
 * The first version of this test skipped any block containing Blade - which
 * skipped exactly the block that broke, since the editor's script is full of
 * @json and {{ }}. So the templating is normalised to JS placeholders rather
 * than used as a reason to look away.
 */
class InlineScriptSyntaxTest extends TestCase
{
    public function test_every_inline_script_block_parses(): void
    {
        $node = $this->nodeBinary();
        if ($node === null) {
            $this->markTestSkipped('node is not available on this machine');
        }

        $failures = [];
        $checked = 0;

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $source, $m)) {
                continue;
            }

            foreach ($m[1] as $i => $js) {
                if (trim($js) === '') {
                    continue;
                }

                $checked++;
                $error = $this->syntaxError($node, $this->stripBlade($js));

                if ($error !== null) {
                    $relative = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $failures[] = "{$relative} (block ".($i + 1)."): {$error}";
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'expected to find inline scripts to check');
        $this->assertSame([], $failures,
            "inline JavaScript that will not parse in a browser:\n".implode("\n", $failures));
    }

    /**
     * Replace Blade templating with JavaScript placeholders.
     *
     * The point is not to reproduce what the server renders - it is to leave
     * the JavaScript structure intact so a real syntax error still surfaces.
     */
    private function stripBlade(string $js): string
    {
        $directivesWithArgs = 'if|elseif|unless|foreach|forelse|for|while|isset|empty|auth|guest|can|cannot|'
            .'production|env|hasSection|push|prepend|section|class|checked|selected|disabled|readonly';
        $bareDirectives = 'endif|endunless|endforeach|endforelse|endfor|endwhile|endisset|endempty|endauth|'
            .'endguest|endcan|endcannot|endproduction|endenv|else|endpush|endprepend|endsection|csrf|method|verbatim|endverbatim';

        // Control structures wrap JavaScript rather than being it.
        $js = preg_replace('/@('.$directivesWithArgs.')\s*\((?:[^()]|\([^()]*\))*\)/', '', $js);
        $js = preg_replace('/@('.$bareDirectives.')\b/', '', $js);

        // Value expressions become a literal so the surrounding syntax parses.
        $js = preg_replace('/@(js|json)\s*\((?:[^()]|\([^()]*\))*\)/s', 'null', $js);
        $js = preg_replace('/\{!!.*?!!\}/s', 'null', $js);
        $js = preg_replace('/\{\{.*?\}\}/s', 'null', $js);

        // Blade comments.
        return preg_replace('/\{\{--.*?--\}\}/s', '', $js);
    }

    private function nodeBinary(): ?string
    {
        $out = [];
        $code = 1;
        exec('node --version 2>&1', $out, $code);

        return $code === 0 ? 'node' : null;
    }

    /** @return string|null the parse error, or null when the source is valid */
    private function syntaxError(string $node, string $js): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'inline').'.js';
        file_put_contents($tmp, $js);

        $out = [];
        $code = 0;
        // --check parses without executing, so a script that talks to the DOM
        // is still validated safely.
        exec(escapeshellarg($node).' --check '.escapeshellarg($tmp).' 2>&1', $out, $code);
        @unlink($tmp);

        if ($code === 0) {
            return null;
        }

        foreach ($out as $line) {
            if (str_contains($line, 'Error:')) {
                return trim($line);
            }
        }

        return trim(implode(' ', array_slice($out, 0, 3)));
    }
}

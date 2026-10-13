<?php

namespace Tests\Feature\Fintech\Security\Concerns;

use Illuminate\Http\Response;

/**
 * Stand-in for barryvdh's PDF wrapper ('dompdf.wrapper') that keeps the HTML
 * each PDF would have been drawn from, so a test can inspect what a PDF
 * contains (escaping, other businesses' data) without parsing a binary.
 */
class CapturingPdf
{
    /** @var list<string> */
    public static array $html = [];

    public function loadView($view, $data = [], $mergeData = [], $encoding = null): static
    {
        self::$html[] = view($view, $data, $mergeData)->render();

        return $this;
    }

    public function loadHTML($string, $encoding = null): static
    {
        self::$html[] = (string) $string;

        return $this;
    }

    public function download($filename = 'document.pdf'): Response
    {
        return new Response('%PDF-1.4 captured', 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"']);
    }

    public function stream($filename = 'document.pdf'): Response
    {
        return new Response('%PDF-1.4 captured', 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"']);
    }

    public function output($options = []): string
    {
        return '%PDF-1.4 captured';
    }

    public function __call($method, $args)
    {
        return $this;
    }
}

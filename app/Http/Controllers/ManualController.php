<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloadable user manuals, rendered from config/handbook.php.
 *
 * Public and unauthenticated on purpose: a prospect deciding whether the tool
 * fits their shop should be able to read the manual before signing up, and
 * search engines should be able to reach it.
 */
class ManualController extends Controller
{
    /** Values every manual template needs. */
    private function shared(): array
    {
        return [
            'appName' => config('seo.name', config('app.name')),
            'siteUrl' => preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')),
            'supportEmail' => config('contacts.support'),
            'version' => config('handbook.version'),
            'updated' => \Illuminate\Support\Carbon::parse(config('handbook.updated'))->format('d M Y'),
        ];
    }

    /** The full handbook — every feature, chapter by chapter. */
    public function handbook(): Response
    {
        $pdf = Pdf::loadView('manuals.handbook', $this->shared() + [
            'chapters' => config('handbook.chapters'),
        ])->setPaper('a4');

        return $pdf->download('apna-invoice-handbook.pdf');
    }

    /** One page: first invoice in five steps. Made to be printed and pinned up. */
    public function quickStart(): Response
    {
        $pdf = Pdf::loadView('manuals.quick-start', $this->shared() + [
            'quick' => config('handbook.quick_start'),
        ])->setPaper('a4');

        return $pdf->download('apna-invoice-quick-start.pdf');
    }
}

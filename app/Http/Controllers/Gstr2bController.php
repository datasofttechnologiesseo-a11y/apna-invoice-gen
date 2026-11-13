<?php

namespace App\Http\Controllers;

use App\Services\Gstr2bBook;
use App\Services\Gstr2bMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GSTR-2B match: upload the 2B the portal gives you, see which purchase
 * invoices it confirms, which differ, and what is on one side only.
 */
class Gstr2bController extends Controller
{
    public function __construct(private readonly Gstr2bBook $book) {}

    public function show(Request $request): View
    {
        $company = $request->user()->ensureCompany();
        $period = Gstr2bBook::period($request->input('month'));

        return view('finance.gstr2b', [
            'company' => $company,
            'period' => $period,
            'periodLabel' => Carbon::createFromFormat('!Y-m', $period)->format('F Y'),
            'result' => app(Gstr2bMatcher::class)->match($company, $period),
            'uploadedPeriods' => $this->book->uploadedPeriods($company),
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $company = $request->user()->ensureCompany();
        $request->validate(Gstr2bBook::UPLOAD_RULES);

        $done = $this->book->import($company, $request->file('file'), $request->input('month'));
        $period = $done['period'];

        return redirect()->route('finance.gstr2b', ['month' => $period])
            ->with('status', 'GSTR-2B for ' . Carbon::createFromFormat('!Y-m', $period)->format('F Y') . ' read: ' . $done['documents'] . ' documents. The match is below.');
    }

    public function export(Request $request): StreamedResponse
    {
        $company = $request->user()->ensureCompany();

        return $this->book->export($company, Gstr2bBook::period($request->input('month')));
    }
}

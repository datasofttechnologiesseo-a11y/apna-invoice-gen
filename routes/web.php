<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CashMemoController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\QuotationShareController;
use App\Http\Controllers\ReferralController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sitemap.xml', function () {
    $today = now()->toDateString();
    $base = rtrim(config('app.url'), '/');

    $urls = [
        ['loc' => $base . '/',                   'priority' => '1.0', 'changefreq' => 'weekly'],
        ['loc' => $base . '/' . ltrim(route('register', [], false), '/'), 'priority' => '0.9', 'changefreq' => 'monthly'],
        ['loc' => $base . '/' . ltrim(route('login', [], false), '/'),    'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/features',           'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/how-to-use',         'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/pricing',            'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/faq',                'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => $base . '/gst-calculator',     'priority' => '0.9', 'changefreq' => 'monthly'],
        ['loc' => $base . '/free-gst-invoice-format', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/free-billing-software', 'priority' => '0.9', 'changefreq' => 'monthly'],
        ['loc' => $base . '/cash-memo-format',   'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/gst-credit-note-format', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => $base . '/about',              'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => $base . '/press',              'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => $base . '/partners',           'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => $base . '/contact',            'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => $base . '/terms',              'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => $base . '/privacy',            'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => $base . '/refund',             'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => $base . '/cookies',            'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => $base . '/security',           'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => $base . '/blog',               'priority' => '0.8', 'changefreq' => 'weekly'],
        // Downloadable manuals. Free, public, and the kind of reference asset
        // other sites link to, so they belong in the index like any other page.
        ['loc' => $base . '/manuals/apna-invoice-handbook.pdf',    'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => $base . '/manuals/apna-invoice-quick-start.pdf', 'priority' => '0.5', 'changefreq' => 'monthly'],
    ];

    // Static marketing/legal pages change rarely. Stamp a stable content date
    // so the sitemap doesn't claim the whole site was "modified today" on every
    // crawl — Google distrusts and ignores an always-fresh lastmod. Bump this
    // when the landing-page copy is meaningfully revised.
    $siteLastmod = '2026-07-18';
    foreach ($urls as &$u) {
        $u['lastmod'] = $siteLastmod;
    }
    unset($u);

    // Append every published blog post to the sitemap so search engines can
    // discover and index them as soon as they're live.
    foreach (\App\Models\Post::published()->orderByDesc('published_at')->get() as $post) {
        $urls[] = [
            'loc' => $base . '/blog/' . $post->slug,
            'priority' => '0.7',
            'changefreq' => 'monthly',
            'lastmod' => $post->updated_at?->toDateString() ?: $today,
        ];
    }

    return response()->view('sitemap', compact('urls', 'today'))
        ->header('Content-Type', 'application/xml; charset=utf-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    $base = rtrim(config('app.url'), '/');
    $lines = [
        'User-agent: *',
        'Allow: /',
        '',
        // Blog cover + inline images live under /storage/posts — they must stay
        // crawlable (Google Images, Discover, Article rich results need the
        // image URL fetchable). Allow them BEFORE the blanket /storage/ block;
        // Google's parser applies the most-specific matching rule.
        'Allow: /storage/posts/',
        '',
        // Private, auth-gated app areas — no indexing value, and crawling them
        // just burns crawl budget that should go to blog posts + landing pages.
        'Disallow: /dashboard',
        'Disallow: /invoices',
        'Disallow: /quotations',
        'Disallow: /customers',
        'Disallow: /company',
        'Disallow: /products',
        'Disallow: /profile',
        'Disallow: /settings',
        'Disallow: /setup',
        'Disallow: /finance',
        'Disallow: /payments',
        'Disallow: /credit-notes',
        'Disallow: /refer',
        'Disallow: /backup',
        'Disallow: /admin',
        'Disallow: /auth/',
        'Disallow: /storage/',
        'Disallow: /build/',
        // Note: /forgot-password, /reset-password etc. are intentionally NOT
        // disallowed — their views carry <meta robots noindex>, and blocking
        // crawl would stop Google ever reading that tag (letting the URL surface
        // as a bare, description-less result linked from /login).
        '',
        'Sitemap: ' . $base . '/sitemap.xml',
    ];
    return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain']);
});

// Cookie banner sync — open to guests and users; rate-limited so it can't be
// used to fill the consent log. Anonymous choices stay in localStorage; this
// endpoint is only useful once a user has an ID to attach the record to.
Route::post('/cookie-consent', [CookieConsentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('cookie-consent.store');

// Public signed invoice link — recipient opens an HTML landing page (with a
// Download-PDF button + product CTA) without logging in. The `signed`
// middleware verifies the URL signature and expiry; `throttle` limits abuse if
// a link gets shared in the wild. The PDF itself streams from the /pdf child.
Route::get('i/{invoice}', [InvoiceShareController::class, 'publicView'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('invoices.public');
Route::get('i/{invoice}/pdf', [InvoiceShareController::class, 'publicPdf'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('invoices.public.pdf');

// Public signed quotation link — same threat model as the invoice version.
Route::get('q/{quotation}', [QuotationShareController::class, 'publicView'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('quotations.public');
Route::get('q/{quotation}/pdf', [QuotationShareController::class, 'publicPdf'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('quotations.public.pdf');

// Public blog — drives SEO traffic. Open to everyone, no auth.
// Throttle each show route to 60/min so a scraper or bot can't hammer the
// per-post view counter.
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:60,1')
    ->name('blog.show');

Route::prefix('/')->name('pages.')->group(function () {
    // Free indexable tools / guides — high-intent organic landing pages.
    Route::view('/gst-calculator', 'pages.gst-calculator')->name('gst-calculator');
    // Chromeless, iframe-embeddable calculator for third-party sites — a
    // backlink/referral surface. noindex (set in the view) keeps it out of the
    // index so it never competes with the full page above.
    Route::view('/gst-calculator/embed', 'pages.gst-calculator-embed')->name('gst-calculator.embed');
    Route::view('/free-gst-invoice-format', 'pages.gst-invoice-format')->name('gst-invoice-format');
    Route::view('/free-billing-software', 'pages.billing-software')->name('billing-software');
    Route::view('/cash-memo-format', 'pages.cash-memo-format')->name('cash-memo-format');
    Route::view('/gst-credit-note-format', 'pages.credit-note-format')->name('credit-note-format');
    // Standalone pages for the main home-page sections (previously #anchors) —
    // each an indexable, separately-rankable page in its own right.
    Route::view('/features', 'pages.features')->name('features');
    Route::view('/how-to-use', 'pages.how-to-use')->name('how-to-use');
    // Downloadable manuals. Public and unauthenticated so a prospect can read
    // the whole thing before signing up, and so crawlers can reach them.
    Route::get('/manuals/apna-invoice-handbook.pdf', [ManualController::class, 'handbook'])
        ->name('manuals.handbook');
    Route::get('/manuals/apna-invoice-quick-start.pdf', [ManualController::class, 'quickStart'])
        ->name('manuals.quick-start');
    Route::view('/pricing', 'pages.pricing')->name('pricing');
    Route::view('/faq', 'pages.faq')->name('faq');
    Route::view('/about', 'pages.about')->name('about');
    Route::view('/press', 'pages.press')->name('press');
    Route::view('/partners', 'pages.partners')->name('partners');
    Route::view('/contact', 'pages.contact')->name('contact');
    Route::post('/contact', [ContactController::class, 'send'])
        ->middleware('throttle:5,10')
        ->name('contact.send');
    Route::view('/terms', 'pages.terms')->name('terms');
    Route::view('/privacy', 'pages.privacy')->name('privacy');
    Route::view('/refund', 'pages.refund')->name('refund');
    Route::view('/cookies', 'pages.cookies')->name('cookies');
    Route::view('/security', 'pages.security')->name('security');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('setup')->name('onboarding.')->group(function () {
        Route::get('/', [OnboardingController::class, 'index'])->name('index');
        Route::get('/business', [OnboardingController::class, 'business'])->name('business');
        Route::post('/business', [OnboardingController::class, 'saveBusiness'])->name('business.save');
        Route::get('/customer', [OnboardingController::class, 'customer'])->name('customer');
        Route::post('/customer', [OnboardingController::class, 'saveCustomer'])->name('customer.save');
        Route::get('/customer/skip', [OnboardingController::class, 'skipCustomer'])->name('customer.skip');
        Route::get('/done', [OnboardingController::class, 'done'])->name('done');
    });

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::view('/help', 'help.index')->name('help');

    Route::get('/refer', [ReferralController::class, 'index'])->name('referrals.index');

    // Backups (download + email + toggle). Building a backup ZIP is expensive
    // and sending email is rate-limited by every provider, so we cap both.
    Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/backup/download', [BackupController::class, 'download'])
        ->middleware('throttle:6,1')
        ->name('backup.download');
    Route::post('/backup/email', [BackupController::class, 'emailNow'])
        ->middleware('throttle:3,1')
        ->name('backup.email');
    Route::post('/backup/toggle', [BackupController::class, 'toggle'])->name('backup.toggle');

    // Multi-company routes. /company (singular) keeps the "active company edit"
    // shortcut used by the dashboard and other inbound links. We forward any
    // query string (e.g. ?from=settings) so the destination form can return
    // the user to where they came from after save.
    Route::get('/company', function (Request $request) {
        $company = $request->user()->ensureCompany();
        $query = $request->query();
        return redirect()->route('companies.edit', array_merge(['company' => $company->id], $query));
    })->name('company.edit');

    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
    Route::patch('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
    Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');
    Route::post('/companies/{company}/switch', [CompanyController::class, 'switch'])->name('companies.switch');

    // Legacy alias: code that still posts to /company updates the active company
    Route::patch('/company', function (Request $request) {
        $company = $request->user()->ensureCompany();
        return app(CompanyController::class)->update($request, $company);
    })->name('company.update');

    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger'])->name('customers.ledger');
    // JSON endpoint for the inline "+ New customer" modal on invoice/quotation
    // forms — keeps the user from losing their typed work to a page navigation.
    Route::post('customers/quick-create', [CustomerController::class, 'quickStore'])->name('customers.quick-create');

    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    // Lightweight inline create from the invoice form — same pattern as customers.quick-create.
    Route::post('products/quick-create', [ProductController::class, 'quickStore'])->name('products.quick-create');
    Route::resource('products', ProductController::class)->except(['show']);

    Route::get('invoices/export/gstr1', [InvoiceController::class, 'gstr1Csv'])->name('invoices.gstr1');
    Route::get('invoices/templates', [InvoiceController::class, 'templates'])->name('invoices.templates');
    Route::get('invoices/templates/{template}/preview', [InvoiceController::class, 'templatePreview'])->name('invoices.templates.preview');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments');
    Route::get('payments/{payment}/receipt', [InvoiceController::class, 'receipt'])->name('payments.receipt');
    Route::delete('payments/{payment}', [InvoiceController::class, 'deletePayment'])->name('payments.destroy');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'printView'])->name('invoices.print');
    // Duplicate any invoice into a fresh editable draft (repeat-billing convenience).
    Route::post('invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');

    // Share + cancel. Outbound email endpoints are rate-limited to stop a
    // compromised account from blasting customers / burning SES quota.
    Route::post('invoices/{invoice}/share/email', [InvoiceShareController::class, 'email'])
        ->middleware('throttle:10,1')
        ->name('invoices.share.email');
    Route::get('invoices/{invoice}/share/link', [InvoiceShareController::class, 'publicLink'])->name('invoices.share.link');
    Route::post('invoices/{invoice}/cancel', [InvoiceShareController::class, 'cancel'])->name('invoices.cancel');

    // Payment reminders — hard cap to prevent spam against a single customer.
    Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'sendReminder'])
        ->middleware('throttle:5,1')
        ->name('invoices.remind');

    // Quotations (Proforma) — pre-sale price proposals. Entirely separate from
    // invoices and excluded from GST returns / books-locked enforcement. Once
    // accepted, a quote can be converted ONCE to a draft tax invoice.
    Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept'])->name('quotations.accept');
    Route::post('quotations/{quotation}/decline', [QuotationController::class, 'decline'])->name('quotations.decline');
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convertToInvoice'])->name('quotations.convert');
    Route::post('quotations/{quotation}/share/email', [QuotationShareController::class, 'email'])
        ->middleware('throttle:10,1')
        ->name('quotations.share.email');
    Route::get('quotations/{quotation}/share/link', [QuotationShareController::class, 'publicLink'])->name('quotations.share.link');
    Route::resource('quotations', QuotationController::class);

    // Credit notes (Section 34 CGST). Always linked to a parent invoice.
    Route::get('invoices/{invoice}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    Route::delete('credit-notes/{creditNote}', [CreditNoteController::class, 'destroy'])->name('credit-notes.destroy');
    Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'pdf'])->name('credit-notes.pdf');

    // Finance — P&L analytics + expense tracking
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/aging', [FinanceController::class, 'aging'])->name('finance.aging');
    Route::get('/finance/aging/export/pdf', [FinanceController::class, 'agingPdf'])->name('finance.aging.export.pdf');
    Route::get('/finance/aging/export/csv', [FinanceController::class, 'agingCsv'])->name('finance.aging.export.csv');
    Route::get('/finance/gstr3b', [FinanceController::class, 'gstr3b'])->name('finance.gstr3b');
    Route::get('/finance/gstr3b/export/pdf', [FinanceController::class, 'gstr3bPdf'])->name('finance.gstr3b.export.pdf');
    Route::get('/finance/gstr3b/export/csv', [FinanceController::class, 'gstr3bCsv'])->name('finance.gstr3b.export.csv');
    Route::get('/finance/expenses', [FinanceController::class, 'expenses'])->name('finance.expenses');
    Route::get('/finance/expenses/export/pdf', [FinanceController::class, 'expensesPdf'])->name('finance.expenses.export.pdf');
    Route::get('/finance/expenses/export/csv', [FinanceController::class, 'expensesCsv'])->name('finance.expenses.export.csv');
    Route::get('/finance/expenses/create', [FinanceController::class, 'createExpense'])->name('finance.expenses.create');
    Route::post('/finance/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
    Route::get('/finance/expenses/{expense}/pdf', [FinanceController::class, 'expensePdf'])->name('finance.expenses.pdf');
    Route::get('/finance/expenses/{expense}/edit', [FinanceController::class, 'editExpense'])->name('finance.expenses.edit');
    Route::patch('/finance/expenses/{expense}', [FinanceController::class, 'updateExpense'])->name('finance.expenses.update');
    Route::delete('/finance/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('finance.expenses.destroy');

    // Cash Memos — self-prepared purchase vouchers for cash purchases
    Route::get('/finance/cash-memos', [CashMemoController::class, 'index'])->name('finance.cash-memos.index');
    // Bulk export routes — placed BEFORE the {cashMemo} routes so "export"
    // isn't captured as a memo id by Laravel's route binding.
    Route::get('/finance/cash-memos/export/pdf', [CashMemoController::class, 'exportPdf'])->name('finance.cash-memos.export.pdf');
    Route::get('/finance/cash-memos/export/csv', [CashMemoController::class, 'exportCsv'])->name('finance.cash-memos.export.csv');
    Route::get('/finance/cash-memos/create', [CashMemoController::class, 'create'])->name('finance.cash-memos.create');
    Route::post('/finance/cash-memos', [CashMemoController::class, 'store'])->name('finance.cash-memos.store');
    Route::get('/finance/cash-memos/{cashMemo}', [CashMemoController::class, 'show'])->name('finance.cash-memos.show');
    Route::get('/finance/cash-memos/{cashMemo}/edit', [CashMemoController::class, 'edit'])->name('finance.cash-memos.edit');
    Route::put('/finance/cash-memos/{cashMemo}', [CashMemoController::class, 'update'])->name('finance.cash-memos.update');
    Route::get('/finance/cash-memos/{cashMemo}/pdf', [CashMemoController::class, 'pdf'])->name('finance.cash-memos.pdf');
    Route::delete('/finance/cash-memos/{cashMemo}', [CashMemoController::class, 'destroy'])->name('finance.cash-memos.destroy');

    // Stop impersonation — must be reachable by the CURRENTLY logged-in user
    // (who isn't a super admin during impersonation), so it sits outside the
    // super-admin middleware.
    Route::post('/admin/impersonation/stop', [\App\Http\Controllers\Admin\DashboardController::class, 'stopImpersonate'])
        ->name('admin.impersonation.stop');

    // Super-admin analytics panel
    Route::middleware('super-admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/users', [\App\Http\Controllers\Admin\DashboardController::class, 'users'])->name('users');
        Route::get('/users/{user}', [\App\Http\Controllers\Admin\DashboardController::class, 'userDetail'])->name('users.show');
        Route::post('/users/{user}/impersonate', [\App\Http\Controllers\Admin\DashboardController::class, 'impersonate'])->name('users.impersonate');
        Route::post('/users/{user}/reset-password', [\App\Http\Controllers\Admin\DashboardController::class, 'resetPassword'])->name('users.reset-password');
        Route::get('/invoices', [\App\Http\Controllers\Admin\DashboardController::class, 'invoices'])->name('invoices');
        Route::get('/companies', [\App\Http\Controllers\Admin\DashboardController::class, 'companies'])->name('companies');
        Route::get('/customers', [\App\Http\Controllers\Admin\DashboardController::class, 'customers'])->name('customers');

        // Personal-data breach register (DPDP §8(6)).
        Route::get('/breaches', [\App\Http\Controllers\Admin\BreachController::class, 'index'])->name('breaches.index');
        Route::get('/breaches/create', [\App\Http\Controllers\Admin\BreachController::class, 'create'])->name('breaches.create');
        Route::post('/breaches', [\App\Http\Controllers\Admin\BreachController::class, 'store'])->name('breaches.store');
        Route::get('/breaches/{breach}', [\App\Http\Controllers\Admin\BreachController::class, 'show'])->name('breaches.show');
        Route::patch('/breaches/{breach}', [\App\Http\Controllers\Admin\BreachController::class, 'update'])->name('breaches.update');
        Route::post('/breaches/{breach}/notify', [\App\Http\Controllers\Admin\BreachController::class, 'notify'])->name('breaches.notify');

        // Blog authoring — full CRUD + quick publish toggle.
        Route::post('/blog/{post}/toggle', [\App\Http\Controllers\Admin\BlogAdminController::class, 'togglePublish'])->name('blog.toggle');
        // AJAX preview — renders raw markdown to safe HTML using the same
        // renderer the public blog uses, so the editor sees prod-true output.
        Route::post('/blog/preview', [\App\Http\Controllers\Admin\BlogAdminController::class, 'preview'])->name('blog.preview');
        // AJAX image upload — used by the toolbar Image button and the paste-from-
        // clipboard handler. Returns JSON { url } that the editor inserts as markdown.
        Route::post('/blog/upload-image', [\App\Http\Controllers\Admin\BlogAdminController::class, 'uploadImage'])->name('blog.upload-image');
        Route::resource('/blog', \App\Http\Controllers\Admin\BlogAdminController::class)->parameters(['blog' => 'post'])->except(['show']);
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile/activity', [ProfileController::class, 'activity'])->name('profile.activity');

    // Data & Privacy Center — DPDP data principal rights (consent, access, erasure).
    Route::get('/profile/privacy', [ProfileController::class, 'privacy'])->name('profile.privacy');
    Route::post('/profile/privacy/marketing', [ProfileController::class, 'updateMarketingConsent'])
        ->name('profile.privacy.marketing');

    // Settings hub — single landing page that organises every existing
    // settings route (company, profile, activity, etc.) under one roof.
    // It does NOT replace those routes, only links to them, so existing
    // bookmarks, forms and tests keep working.
    Route::view('/settings', 'settings.index')->name('settings.index');
});

require __DIR__.'/auth.php';

// TEMP local-only harness - removed before commit.
if (app()->environment('local')) {
    Route::get('/__devlogin', fn () => tap(redirect('/dashboard'), fn () => auth()->loginUsingId(1)));
}

<?php

use App\Http\Controllers\BackupController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceShareController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
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
        ['loc' => $base . '/about',              'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => $base . '/careers',            'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => $base . '/press',              'priority' => '0.5', 'changefreq' => 'monthly'],
        ['loc' => $base . '/partners',           'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => $base . '/contact',            'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => $base . '/terms',              'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => $base . '/privacy',            'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => $base . '/refund',             'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => $base . '/cookies',            'priority' => '0.3', 'changefreq' => 'yearly'],
        ['loc' => $base . '/security',           'priority' => '0.5', 'changefreq' => 'monthly'],
    ];

    return response()->view('sitemap', compact('urls', 'today'))
        ->header('Content-Type', 'application/xml; charset=utf-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    $base = rtrim(config('app.url'), '/');
    $lines = [
        'User-agent: *',
        'Allow: /',
        '',
        'Disallow: /dashboard',
        'Disallow: /invoices',
        'Disallow: /customers',
        'Disallow: /company',
        'Disallow: /profile',
        'Disallow: /setup',
        'Disallow: /forgot-password',
        'Disallow: /reset-password',
        'Disallow: /confirm-password',
        'Disallow: /verify-email',
        'Disallow: /storage/',
        'Disallow: /build/',
        '',
        'Sitemap: ' . $base . '/sitemap.xml',
    ];
    return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain']);
});

// Public signed invoice link — recipient opens PDF without logging in.
// The `signed` middleware verifies the URL signature and expiry; the `throttle`
// limits abuse if a link gets shared in the wild.
Route::get('i/{invoice}', [InvoiceShareController::class, 'publicView'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('invoices.public');

Route::prefix('/')->name('pages.')->group(function () {
    Route::view('/about', 'pages.about')->name('about');
    Route::view('/careers', 'pages.careers')->name('careers');
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
    // shortcut used by the dashboard and other inbound links.
    Route::get('/company', function (Request $request) {
        $company = $request->user()->ensureCompany();
        return redirect()->route('companies.edit', $company);
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

    Route::get('products/search', [ProductController::class, 'search'])->name('products.search');
    Route::resource('products', ProductController::class)->except(['show']);

    Route::get('invoices/templates', [InvoiceController::class, 'templates'])->name('invoices.templates');
    Route::get('invoices/templates/{template}/preview', [InvoiceController::class, 'templatePreview'])->name('invoices.templates.preview');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments');
    Route::get('payments/{payment}/receipt', [InvoiceController::class, 'receipt'])->name('payments.receipt');
    Route::delete('payments/{payment}', [InvoiceController::class, 'deletePayment'])->name('payments.destroy');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'printView'])->name('invoices.print');

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

    // Credit notes (Section 34 CGST). Always linked to a parent invoice.
    Route::get('invoices/{invoice}/credit-notes/create', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::post('invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
    Route::delete('credit-notes/{creditNote}', [CreditNoteController::class, 'destroy'])->name('credit-notes.destroy');
    Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'pdf'])->name('credit-notes.pdf');

    // Finance — P&L analytics + expense tracking
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance.index');
    Route::get('/finance/expenses', [FinanceController::class, 'expenses'])->name('finance.expenses');
    Route::get('/finance/expenses/create', [FinanceController::class, 'createExpense'])->name('finance.expenses.create');
    Route::post('/finance/expenses', [FinanceController::class, 'storeExpense'])->name('finance.expenses.store');
    Route::get('/finance/expenses/{expense}/edit', [FinanceController::class, 'editExpense'])->name('finance.expenses.edit');
    Route::patch('/finance/expenses/{expense}', [FinanceController::class, 'updateExpense'])->name('finance.expenses.update');
    Route::delete('/finance/expenses/{expense}', [FinanceController::class, 'destroyExpense'])->name('finance.expenses.destroy');

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
        Route::get('/invoices', [\App\Http\Controllers\Admin\DashboardController::class, 'invoices'])->name('invoices');
        Route::get('/companies', [\App\Http\Controllers\Admin\DashboardController::class, 'companies'])->name('companies');
        Route::get('/customers', [\App\Http\Controllers\Admin\DashboardController::class, 'customers'])->name('customers');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

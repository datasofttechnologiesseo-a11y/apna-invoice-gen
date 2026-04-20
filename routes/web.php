<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
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

Route::prefix('/')->name('pages.')->group(function () {
    Route::view('/about', 'pages.about')->name('about');
    Route::view('/careers', 'pages.careers')->name('careers');
    Route::view('/press', 'pages.press')->name('press');
    Route::view('/partners', 'pages.partners')->name('partners');
    Route::view('/contact', 'pages.contact')->name('contact');
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

    Route::get('invoices/templates', [InvoiceController::class, 'templates'])->name('invoices.templates');
    Route::get('invoices/templates/{template}/preview', [InvoiceController::class, 'templatePreview'])->name('invoices.templates.preview');
    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.payments');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/print', [InvoiceController::class, 'printView'])->name('invoices.print');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

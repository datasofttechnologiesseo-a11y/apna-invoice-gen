<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\CashMemoController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\StateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes (mobile app)
|--------------------------------------------------------------------------
| Token-based (Sanctum). All routes return JSON. The web app keeps its own
| session/Breeze routes in web.php — this is a parallel, stateless surface.
*/

// --- Public auth (rate-limited; no captcha — mobile can't render Turnstile) ---
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// Static lookup — states list is needed on the login/onboarding screens too,
// so keep it outside the auth wall.
Route::get('states', [StateController::class, 'index']);

// Public blog (read-only; no auth) — same content as the website /blog.
Route::get('blog', [BlogController::class, 'index']);
Route::get('blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9-]+');

// --- Authenticated ---
Route::middleware('auth:sanctum')->group(function () {
    // Session
    Route::get('me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Referrals (refer & earn)
    Route::get('referrals', [ReferralController::class, 'index']);

    // Profile / account
    Route::match(['put', 'patch'], 'profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('profile/activity', [ProfileController::class, 'activity']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    // Backups (download ZIP, email, toggle weekly auto-backup)
    Route::get('backup', [BackupController::class, 'index']);
    Route::get('backup/download', [BackupController::class, 'download'])->middleware('throttle:6,1');
    Route::post('backup/email', [BackupController::class, 'emailNow'])->middleware('throttle:3,1');
    Route::post('backup/toggle', [BackupController::class, 'toggle']);

    // Companies (multi-company)
    Route::get('companies', [CompanyController::class, 'index']);
    Route::post('companies', [CompanyController::class, 'store']);
    Route::get('companies/active', [CompanyController::class, 'active']);
    Route::get('companies/{company}', [CompanyController::class, 'show']);
    Route::match(['put', 'patch'], 'companies/{company}', [CompanyController::class, 'update']);
    Route::post('companies/{company}/switch', [CompanyController::class, 'switch']);

    // Customers
    Route::get('customers/{customer}/ledger', [CustomerController::class, 'ledger']);
    Route::apiResource('customers', CustomerController::class);

    // Products
    Route::get('products/search', [ProductController::class, 'search']);
    Route::apiResource('products', ProductController::class);

    // Invoices
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::match(['put', 'patch'], 'invoices/{invoice}', [InvoiceController::class, 'update']);
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);
    Route::post('invoices/{invoice}/finalize', [InvoiceController::class, 'finalize']);
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::get('invoices/{invoice}/share-link', [InvoiceController::class, 'shareLink']);
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('invoices/{invoice}/remind', [InvoiceController::class, 'sendReminder'])->middleware('throttle:5,1');

    // Payments (nested under invoice for create; flat for delete)
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment']);
    Route::delete('payments/{payment}', [InvoiceController::class, 'deletePayment']);

    // Credit notes (CGST Section 34) — nested under their parent invoice.
    Route::get('invoices/{invoice}/credit-notes', [CreditNoteController::class, 'index']);
    Route::post('invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store']);
    Route::delete('credit-notes/{creditNote}', [CreditNoteController::class, 'destroy']);
    Route::get('credit-notes/{creditNote}/pdf', [CreditNoteController::class, 'pdf']);

    // Finance reports (on-screen JSON + CSV/PDF file exports for the CA)
    Route::prefix('finance')->group(function () {
        Route::get('pnl', [FinanceController::class, 'pnl']);
        Route::get('aging', [FinanceController::class, 'aging']);
        Route::get('aging/export/csv', [FinanceController::class, 'agingCsv']);
        Route::get('aging/export/pdf', [FinanceController::class, 'agingPdf']);
        Route::get('gstr3b', [FinanceController::class, 'gstr3b']);
        Route::get('gstr3b/export/csv', [FinanceController::class, 'gstr3bCsv']);
        Route::get('gstr3b/export/pdf', [FinanceController::class, 'gstr3bPdf']);
        Route::get('gstr1', [FinanceController::class, 'gstr1']);
        Route::get('gstr1/export/csv', [FinanceController::class, 'gstr1Csv']);
        Route::get('expenses/export/csv', [FinanceController::class, 'expensesCsv']);
        Route::get('expenses/export/pdf', [FinanceController::class, 'expensesPdf']);
    });

    // Expenses (P&L cost side; cash-memo-linked ones are read-only here)
    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::post('expenses', [ExpenseController::class, 'store']);
    Route::match(['put', 'patch'], 'expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);

    // Cash memos (self-prepared purchase vouchers; each writes a linked expense)
    Route::get('cash-memos', [CashMemoController::class, 'index']);
    Route::post('cash-memos', [CashMemoController::class, 'store']);
    Route::get('cash-memos/{cashMemo}', [CashMemoController::class, 'show']);
    Route::get('cash-memos/{cashMemo}/pdf', [CashMemoController::class, 'pdf']);
    Route::delete('cash-memos/{cashMemo}', [CashMemoController::class, 'destroy']);

    // Quotations
    Route::get('quotations', [QuotationController::class, 'index']);
    Route::post('quotations', [QuotationController::class, 'store']);
    Route::get('quotations/{quotation}', [QuotationController::class, 'show']);
    Route::match(['put', 'patch'], 'quotations/{quotation}', [QuotationController::class, 'update']);
    Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy']);
    Route::post('quotations/{quotation}/send', [QuotationController::class, 'send']);
    Route::post('quotations/{quotation}/accept', [QuotationController::class, 'accept']);
    Route::post('quotations/{quotation}/decline', [QuotationController::class, 'decline']);
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert']);
    Route::get('quotations/{quotation}/share-link', [QuotationController::class, 'shareLink']);
});

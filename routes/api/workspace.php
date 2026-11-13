<?php

// Team, notifications, search, reports, settings, documents — loaded inside the auth:sanctum + team group in routes/api.php.
//
// Every route here is named "api." + the web route it mirrors, so
// App\Http\Middleware\ActAsTeamMember applies the same team-role rule
// (CompanyMember::RULES) to the app as to the website.

use App\Http\Controllers\Api\CashMemoController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TeamController;
use Illuminate\Support\Facades\Route;

// Team (owner only; a member may leave)
Route::get('team', [TeamController::class, 'index'])->name('api.team.index');
Route::post('team', [TeamController::class, 'invite'])->middleware('throttle:20,1,api-team')->name('api.team.invite');
Route::post('team/leave/{member}', [TeamController::class, 'leave'])->name('api.team.leave');
Route::post('team/{member}/resend', [TeamController::class, 'resend'])->middleware('throttle:20,1,api-team-resend')->name('api.team.resend');
Route::match(['put', 'patch'], 'team/{member}', [TeamController::class, 'update'])->name('api.team.update');
Route::delete('team/{member}', [TeamController::class, 'destroy'])->name('api.team.destroy');

// Notification bell
Route::get('notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
Route::post('notifications/seen', [NotificationController::class, 'seen'])
    ->middleware('throttle:30,1,api-notifications-seen')->name('api.notifications.seen');

// App-wide search suggestions
Route::get('search/suggest', [SearchController::class, 'suggest'])->name('api.search.suggest');

// Reports / registers (config/reports.php): hub, on-screen rows, files
Route::get('reports', [ReportController::class, 'index'])->name('api.reports.index');
Route::get('reports/{report}', [ReportController::class, 'show'])->name('api.reports.show');
Route::get('reports/{report}/export/{format}', [ReportController::class, 'export'])
    ->where('format', 'xlsx|csv|pdf')->name('api.reports.export');

// GSTR-1 files for the portal / offline tool
Route::get('finance/gstr1/export/json', [FinanceController::class, 'gstr1Json'])->name('api.finance.gstr1.export.json');
Route::get('finance/gstr1/export/xlsx', [FinanceController::class, 'gstr1Xlsx'])->name('api.finance.gstr1.export.xlsx');

// Documents
Route::post('invoices/{invoice}/duplicate', [DocumentController::class, 'duplicateInvoice'])->name('api.invoices.duplicate');
Route::get('invoices/{invoice}/print', [DocumentController::class, 'invoiceThermal'])->name('api.invoices.print');
// Not under invoices/: GET invoices/{invoice} is registered first and would take "templates".
Route::get('invoice-templates', [DocumentController::class, 'invoiceTemplates'])->name('api.invoices.templates');
Route::get('invoice-templates/{template}/preview', [DocumentController::class, 'invoiceTemplatePreview'])->name('api.invoices.templates.preview');
Route::get('payments/{payment}/receipt', [DocumentController::class, 'paymentReceipt'])->name('api.payments.receipt');
Route::get('quotations/{quotation}/pdf', [DocumentController::class, 'quotationPdf'])->name('api.quotations.pdf');
Route::get('expenses/{expense}/pdf', [DocumentController::class, 'expensePdf'])->name('api.finance.expenses.pdf');
Route::match(['put', 'patch'], 'cash-memos/{cashMemo}', [CashMemoController::class, 'update'])->name('api.finance.cash-memos.update');

// Company settings: logo and signature (multipart). The rest of the settings
// form, incl. lut_number and next_invoice_number, is PUT/PATCH companies/{company}.
Route::post('companies/{company}/branding', [CompanyController::class, 'branding'])->name('api.companies.branding');

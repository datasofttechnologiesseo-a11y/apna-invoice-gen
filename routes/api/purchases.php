<?php

// Vendors, purchase bills, GSTR-2B matching, stock — loaded inside the auth:sanctum + team group in routes/api.php.
//
// Every route is named "api.<web route name>": App\Http\Middleware\ActAsTeamMember
// strips the "api." and checks a team member's role against the web route of the
// same name, so the app and the website follow one set of role rules.

use App\Http\Controllers\Api\Gstr2bController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseBillController;
use App\Http\Controllers\Api\VendorController;
use Illuminate\Support\Facades\Route;

// Vendors (suppliers)
Route::get('vendors', [VendorController::class, 'index'])->name('api.vendors.index');
Route::post('vendors', [VendorController::class, 'store'])->name('api.vendors.store');
Route::get('vendors/{vendor}', [VendorController::class, 'show'])->name('api.vendors.show');
Route::match(['put', 'patch'], 'vendors/{vendor}', [VendorController::class, 'update'])->name('api.vendors.update');
Route::delete('vendors/{vendor}', [VendorController::class, 'destroy'])->name('api.vendors.destroy');

// Purchase invoices (supplier bills) and payments made against them
Route::get('purchases', [PurchaseBillController::class, 'index'])->name('api.purchases.index');
Route::post('purchases', [PurchaseBillController::class, 'store'])->name('api.purchases.store');
Route::get('purchases/{purchaseBill}', [PurchaseBillController::class, 'show'])->name('api.purchases.show');
Route::match(['put', 'patch'], 'purchases/{purchaseBill}', [PurchaseBillController::class, 'update'])->name('api.purchases.update');
Route::delete('purchases/{purchaseBill}', [PurchaseBillController::class, 'destroy'])->name('api.purchases.destroy');
Route::post('purchases/{purchaseBill}/payments', [PurchaseBillController::class, 'storePayment'])->name('api.purchases.payments.store');
Route::delete('purchases/{purchaseBill}/payments/{purchasePayment}', [PurchaseBillController::class, 'destroyPayment'])->name('api.purchases.payments.destroy');

// GSTR-2B match
Route::get('finance/gstr2b', [Gstr2bController::class, 'show'])->name('api.finance.gstr2b');
Route::post('finance/gstr2b', [Gstr2bController::class, 'upload'])->middleware('throttle:20,1,api-finance-gstr2b')->name('api.finance.gstr2b.upload');
Route::get('finance/gstr2b/export', [Gstr2bController::class, 'export'])->name('api.finance.gstr2b.export');

// Item stock ledger, manual adjustment, and un-archiving
Route::get('products/{product}/stock', [ProductController::class, 'stock'])->name('api.products.stock');
Route::post('products/{product}/stock', [ProductController::class, 'adjustStock'])->name('api.products.stock.adjust');
Route::post('products/{product}/restore', [ProductController::class, 'restore'])->name('api.products.restore');

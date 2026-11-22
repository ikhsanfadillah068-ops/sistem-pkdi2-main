<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredClientController;
use App\Http\Controllers\Preview\ClientPreviewController;
use App\Http\Controllers\Preview\FinancePreviewController;
use App\Http\Controllers\Preview\OwnerPreviewController;
use App\Http\Controllers\Preview\WarehousePreviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatusHeartbeatController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredClientController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredClientController::class, 'store'])->name('register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active.user'])->group(function (): void {
    Route::get('/status-heartbeat', StatusHeartbeatController::class)->name('status.heartbeat');

    // Unduhan dokumen PDF Invoice dengan otorisasi Policy (hanya pemilik Client, Keuangan, atau Owner)
    Route::get('/invoices/{code}/download-pdf', [ClientPreviewController::class, 'downloadInvoicePdf'])
        ->name('invoices.download-pdf');

    // Unduhan aman bukti transfer pembayaran yang dilindungi Policy Invoice
    Route::get('/payments/{payment}/proof', [ClientPreviewController::class, 'downloadPaymentProof'])
        ->name('payments.proof.show');

    // Akses berkas lampiran pesanan & dokumentasi foto Gudang yang dilindungi Policy
    Route::get('/orders/{code}/attachments/{type}', [ClientPreviewController::class, 'downloadOrderAttachment'])
        ->whereIn('type', ['po', 'client', 'receipt', 'sorting', 'inspection', 'process', 'result'])
        ->name('orders.attachments.show');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');

    Route::prefix('client')->name('client.')->middleware('role:client')->group(function (): void {
        Route::get('/dashboard', [ClientPreviewController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [ClientPreviewController::class, 'ordersIndex'])->name('orders.index');
        Route::get('/orders/create', [ClientPreviewController::class, 'ordersCreate'])->name('orders.create');
        Route::post('/orders', [ClientPreviewController::class, 'ordersStore'])->name('orders.store');
        Route::get('/orders/{code}', [ClientPreviewController::class, 'ordersShow'])->name('orders.show');
        Route::get('/invoices', [ClientPreviewController::class, 'invoicesIndex'])->name('invoices.index');
        Route::get('/invoices/{code}', [ClientPreviewController::class, 'invoicesShow'])->name('invoices.show');
        Route::post('/invoices/{code}/payments', [ClientPreviewController::class, 'submitPaymentProof'])->name('invoices.payments.store');
        Route::get('/company', [ClientPreviewController::class, 'company'])->name('company');
        Route::put('/company', [ClientPreviewController::class, 'updateCompany'])->name('company.update');
    });

    Route::prefix('warehouse')->name('warehouse.')->middleware('role:kepala_gudang')->group(function (): void {
        Route::get('/dashboard', [WarehousePreviewController::class, 'dashboard'])->name('dashboard');
        Route::get('/inspections', [WarehousePreviewController::class, 'inspectionsIndex'])->name('inspections.index');
        Route::get('/processes', [WarehousePreviewController::class, 'processesIndex'])->name('processes.index');
        Route::get('/reports', [WarehousePreviewController::class, 'reportsIndex'])->name('reports.index');
        Route::get('/reports/export', [WarehousePreviewController::class, 'reportsExport'])->name('reports.export');
        Route::get('/orders/{code}', [WarehousePreviewController::class, 'orderShow'])->name('orders.show');
        Route::post('/orders/{code}/review', [WarehousePreviewController::class, 'reviewStore'])->name('orders.review');
        Route::post('/orders/{code}/transport', [WarehousePreviewController::class, 'transportStore'])->name('orders.transport.store');
        Route::post('/orders/{code}/receipt', [WarehousePreviewController::class, 'receiptStore'])->name('orders.receipt.store');
        Route::post('/orders/{code}/sorting', [WarehousePreviewController::class, 'sortingStore'])->name('orders.sorting.store');
        Route::post('/orders/{code}/inspect', [WarehousePreviewController::class, 'inspectStore'])->name('orders.inspect');
        Route::post('/orders/{code}/processes', [WarehousePreviewController::class, 'processStore'])->name('orders.processes.store');
        Route::post('/orders/{code}/result', [WarehousePreviewController::class, 'resultStore'])->name('orders.result.store');
        Route::post('/orders/{code}/delivery', [WarehousePreviewController::class, 'deliveryStore'])->name('orders.delivery.store');
        Route::get('/delivery-orders', [WarehousePreviewController::class, 'deliveryOrdersIndex'])->name('delivery-orders.index');
        Route::get('/delivery-orders/{code}', [WarehousePreviewController::class, 'deliveryOrderShow'])->name('delivery-orders.show');
        Route::post('/delivery-orders/{code}', [WarehousePreviewController::class, 'deliveryOrderStore'])->name('delivery-orders.store');
    });

    Route::prefix('finance')->name('finance.')->middleware('role:keuangan')->group(function (): void {
        Route::get('/dashboard', [FinancePreviewController::class, 'dashboard'])->name('dashboard');
        Route::get('/billable-orders', [FinancePreviewController::class, 'billableIndex'])->name('billable.index');
        Route::post('/billable-orders/combined-invoice', [FinancePreviewController::class, 'combinedInvoiceStore'])->name('billable.combined.store');
        Route::get('/billable-orders/{code}', [FinancePreviewController::class, 'billableShow'])->name('billable.show');
        Route::post('/billable-orders/{code}/invoices', [FinancePreviewController::class, 'billableStore'])->name('billable.store');
        Route::get('/invoices', [FinancePreviewController::class, 'invoicesIndex'])->name('invoices.index');
        Route::get('/invoices/{code}', [FinancePreviewController::class, 'invoicesShow'])->name('invoices.show');
        Route::post('/invoices/{code}/publish', [FinancePreviewController::class, 'invoicesPublish'])->name('invoices.publish');
        Route::put('/invoices/{code}/revise', [FinancePreviewController::class, 'invoicesRevise'])->name('invoices.revise');
        Route::post('/invoices/{code}/void', [FinancePreviewController::class, 'invoicesVoid'])->name('invoices.void');
        Route::post('/invoices/{code}/payments', [FinancePreviewController::class, 'paymentsStore'])->name('invoices.payments.store');
        Route::get('/tariffs', [FinancePreviewController::class, 'tariffsIndex'])->name('tariffs.index');
        Route::post('/tariffs', [FinancePreviewController::class, 'tariffsStore'])->name('tariffs.store');
        Route::put('/tariffs/{tariff}', [FinancePreviewController::class, 'tariffsUpdate'])->name('tariffs.update');
        Route::delete('/tariffs/{tariff}', [FinancePreviewController::class, 'tariffsDestroy'])->name('tariffs.destroy');
        Route::get('/expenses', [FinancePreviewController::class, 'expensesIndex'])->name('expenses.index');
        Route::post('/expenses', [FinancePreviewController::class, 'expensesStore'])->name('expenses.store');
        Route::get('/cashbook', [FinancePreviewController::class, 'cashbookIndex'])->name('cashbook.index');
        Route::get('/reports', [FinancePreviewController::class, 'reportsIndex'])->name('reports.index');
        Route::get('/reports/export', [FinancePreviewController::class, 'reportsExport'])->name('reports.export');
    });

    Route::prefix('owner')->name('owner.')->middleware('role:owner')->group(function (): void {
        Route::get('/dashboard', [OwnerPreviewController::class, 'dashboard'])->name('dashboard');
        Route::get('/financial-summary', [OwnerPreviewController::class, 'financial'])->name('financial');
        Route::get('/reports/export', [OwnerPreviewController::class, 'reportsExport'])->name('reports.export');
        Route::get('/transactions', [OwnerPreviewController::class, 'transactionsIndex'])->name('transactions.index');
        Route::get('/transactions/{code}', [OwnerPreviewController::class, 'transactionsShow'])->name('transactions.show');
        Route::get('/audit-logs', [OwnerPreviewController::class, 'auditLogs'])->name('audit-logs');
    });
});

<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PalmOilSourceController;
use App\Http\Controllers\UnloadingPointController;
use App\Http\Controllers\JettyPointController;
use App\Http\Controllers\ProjectCalculatorController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FundingCreditorController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskProjectController;
use Illuminate\Support\Facades\Route;

// ── Auth ─────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',    [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ── Publik: verifikasi keabsahan dokumen via QR (tanpa login) ──
Route::get('/verifikasi/{token}', [DocumentController::class, 'verify'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:30,1')
    ->name('documents.verify');

// ── Protected ─────────────────────────────────────────────
Route::middleware(['auth', 'active'])->group(function () {

    Route::get('/', [DashboardController::class, '__invoke'])->name('dashboard');
    Route::inertia('/menu', 'Menu/Index')->name('menu');

    // Sumber Cangkang Sawit
    Route::middleware('permission:inventory.view')->prefix('palm-oil-sources')->group(function () {
        Route::get('/',                    [PalmOilSourceController::class, 'index'])->name('palm-oil-sources.index');
        Route::get('/create',              [PalmOilSourceController::class, 'create'])->middleware('permission:inventory.create')->name('palm-oil-sources.create');
        Route::post('/',                   [PalmOilSourceController::class, 'store'])->middleware('permission:inventory.create')->name('palm-oil-sources.store');
        Route::get('/{palmOilSource}',     [PalmOilSourceController::class, 'show'])->name('palm-oil-sources.show');
        Route::get('/{palmOilSource}/edit',[PalmOilSourceController::class, 'edit'])->middleware('permission:inventory.edit')->name('palm-oil-sources.edit');
        Route::put('/{palmOilSource}',     [PalmOilSourceController::class, 'update'])->middleware('permission:inventory.edit')->name('palm-oil-sources.update');
        Route::delete('/{palmOilSource}',  [PalmOilSourceController::class, 'destroy'])->middleware('permission:inventory.delete')->name('palm-oil-sources.destroy');
        Route::get('/api/data',            [PalmOilSourceController::class, 'api'])->name('palm-oil-sources.api');
    });

    // Titik Bongkar (Customer)
    Route::middleware('permission:inventory.view')->prefix('unloading-points')->group(function () {
        Route::get('/',                     [UnloadingPointController::class, 'index'])->name('unloading-points.index');
        Route::get('/create',               [UnloadingPointController::class, 'create'])->middleware('permission:inventory.create')->name('unloading-points.create');
        Route::post('/',                    [UnloadingPointController::class, 'store'])->middleware('permission:inventory.create')->name('unloading-points.store');
        Route::get('/{unloadingPoint}',     [UnloadingPointController::class, 'show'])->name('unloading-points.show');
        Route::get('/{unloadingPoint}/edit',[UnloadingPointController::class, 'edit'])->middleware('permission:inventory.edit')->name('unloading-points.edit');
        Route::put('/{unloadingPoint}',     [UnloadingPointController::class, 'update'])->middleware('permission:inventory.edit')->name('unloading-points.update');
        Route::delete('/{unloadingPoint}',  [UnloadingPointController::class, 'destroy'])->middleware('permission:inventory.delete')->name('unloading-points.destroy');
    });

    // Titik Dermaga (Jetty)
    Route::middleware('permission:inventory.view')->prefix('jetty-points')->group(function () {
        Route::get('/',                  [JettyPointController::class, 'index'])->name('jetty-points.index');
        Route::get('/create',            [JettyPointController::class, 'create'])->middleware('permission:inventory.create')->name('jetty-points.create');
        Route::post('/',                 [JettyPointController::class, 'store'])->middleware('permission:inventory.create')->name('jetty-points.store');
        Route::get('/{jettyPoint}',      [JettyPointController::class, 'show'])->name('jetty-points.show');
        Route::get('/{jettyPoint}/edit', [JettyPointController::class, 'edit'])->middleware('permission:inventory.edit')->name('jetty-points.edit');
        Route::put('/{jettyPoint}',      [JettyPointController::class, 'update'])->middleware('permission:inventory.edit')->name('jetty-points.update');
        Route::delete('/{jettyPoint}',   [JettyPointController::class, 'destroy'])->middleware('permission:inventory.delete')->name('jetty-points.destroy');
    });

    // Kalkulasi Proyek Pengadaan
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/project-calculator',  [ProjectCalculatorController::class, 'index'])->name('project-calculator.index');
        Route::post('/project-calculator', [ProjectCalculatorController::class, 'store'])->middleware('permission:inventory.create')->name('project-calculator.store');
        Route::delete('/project-calculator/{scenario}', [ProjectCalculatorController::class, 'destroy'])->middleware('permission:inventory.delete')->name('project-calculator.destroy');
    });

    // Akuntansi / Pembukuan (terintegrasi jurnal yang telah dirilis)
    // Catatan: aksi add/edit/delete dibatasi khusus Super Admin (role:super_admin).
    Route::middleware('permission:books.view')->prefix('books')->group(function () {
        // Daftar Akun (COA) & Control Account
        Route::get('/accounts', [AccountController::class, 'index'])->middleware('permission:books.accounts.view')->name('books.accounts.index');
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/accounts',            [AccountController::class, 'store'])->name('books.accounts.store');
            Route::put('/accounts/{account}',   [AccountController::class, 'update'])->name('books.accounts.update');
            Route::delete('/accounts/{account}',[AccountController::class, 'destroy'])->name('books.accounts.destroy');
        });

        // Jurnal Umum (Form Jurnal + Laporan Jurnal) — alur approval bertingkat
        Route::get('/journal',  [JournalController::class, 'index'])->middleware('permission:books.journal.view')->name('books.journal.index');
        // Buat/ubah/ajukan/hapus: pembuat (drafter/Accounting Staff) & Super Admin
        Route::middleware('permission:books.create')->group(function () {
            Route::post('/journal',                  [JournalController::class, 'store'])->name('books.journal.store');
            Route::put('/journal/{journal}',         [JournalController::class, 'update'])->name('books.journal.update');
            Route::post('/journal/{journal}/submit', [JournalController::class, 'submit'])->name('books.journal.submit');
            Route::delete('/journal/{journal}',      [JournalController::class, 'destroy'])->name('books.journal.destroy');
            Route::delete('/journal-attachment/{attachment}', [JournalController::class, 'destroyAttachment'])->name('books.journal.attachment.destroy');
        });
        // Setujui/tolak: Approver L1 (reviewer/approval) & Direktur (super_admin)
        Route::middleware('role:reviewer|approval|super_admin')->group(function () {
            Route::post('/journal/{journal}/approve', [JournalController::class, 'approve'])->name('books.journal.approve');
            Route::post('/journal/{journal}/reject',  [JournalController::class, 'reject'])->name('books.journal.reject');
        });

        // Master Vendor (kode bantu) + subledger utang
        Route::get('/vendors', [VendorController::class, 'index'])->middleware('permission:books.vendors.view')->name('books.vendors.index');
        Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->middleware('permission:books.vendors.view')->name('books.vendors.show');
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/vendors',           [VendorController::class, 'store'])->name('books.vendors.store');
            Route::put('/vendors/{vendor}',   [VendorController::class, 'update'])->name('books.vendors.update');
            Route::delete('/vendors/{vendor}',[VendorController::class, 'destroy'])->name('books.vendors.destroy');
        });

        // Master Customer (kode bantu) + subledger piutang
        Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:books.customers.view')->name('books.customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:books.customers.view')->name('books.customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('role:super_admin')->name('books.customers.update');

        // Master Kreditur Pendanaan (Investor/Bank, kode bantu) + subledger kewajiban pendanaan
        Route::get('/creditors', [FundingCreditorController::class, 'index'])->middleware('permission:books.creditors.view')->name('books.creditors.index');
        Route::get('/creditors/{creditor}', [FundingCreditorController::class, 'show'])->middleware('permission:books.creditors.view')->name('books.creditors.show');
        // Dokumen kreditur (kontrak, bukti transfer, foto jurnal, kwitansi) — upload/hapus dicek di controller
        Route::middleware('permission:books.creditors.view')->group(function () {
            Route::post('/creditors/{creditor}/documents',              [FundingCreditorController::class, 'uploadDocuments'])->name('books.creditors.documents.upload');
            Route::get('/creditors/documents/{document}/view',          [FundingCreditorController::class, 'viewDocument'])->name('books.creditors.documents.view');
            Route::get('/creditors/documents/{document}/download',      [FundingCreditorController::class, 'downloadDocument'])->name('books.creditors.documents.download');
            Route::delete('/creditors/documents/{document}',            [FundingCreditorController::class, 'destroyDocument'])->name('books.creditors.documents.destroy');
        });
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/creditors',            [FundingCreditorController::class, 'store'])->name('books.creditors.store');
            Route::put('/creditors/{creditor}',  [FundingCreditorController::class, 'update'])->name('books.creditors.update');
            Route::delete('/creditors/{creditor}',[FundingCreditorController::class, 'destroy'])->name('books.creditors.destroy');
        });

        // Kontrol Saldo PPN Masukan/Keluaran & WAPU
        Route::get('/tax-control', [TaxController::class, 'index'])->middleware('permission:books.tax.view')->name('books.tax-control');

        // Buku Besar (read-only; edit dilakukan di Jurnal Umum)
        Route::get('/ledger', [LedgerController::class, 'index'])->middleware('permission:books.ledger.view')->name('books.ledger.index');

        // Daftar Aset Tetap & Penyusutan
        Route::get('/fixed-assets',  [FixedAssetController::class, 'index'])->middleware('permission:books.assets.view')->name('books.fixed-assets.index');
        Route::middleware('role:super_admin')->group(function () {
            Route::post('/fixed-assets',                [FixedAssetController::class, 'store'])->name('books.fixed-assets.store');
            Route::put('/fixed-assets/{fixedAsset}',    [FixedAssetController::class, 'update'])->name('books.fixed-assets.update');
            Route::delete('/fixed-assets/{fixedAsset}', [FixedAssetController::class, 'destroy'])->name('books.fixed-assets.destroy');
        });

        // Laporan keuangan
        Route::middleware('permission:books.reports.view')->group(function () {
            Route::get('/trial-balance',  [ReportController::class, 'trialBalance'])->name('books.trial-balance');
            Route::get('/worksheet',      [ReportController::class, 'worksheet'])->name('books.worksheet');
            Route::get('/balance-sheet',  [ReportController::class, 'balanceSheet'])->name('books.balance-sheet');
            Route::get('/profit-loss',    [ReportController::class, 'profitLoss'])->name('books.profit-loss');
            Route::get('/gross-turnover', [ReportController::class, 'grossTurnover'])->name('books.gross-turnover');
        });
    });

    // Dokumen Template (Design & Generate) — terintegrasi modul terkait
    Route::middleware('permission:letters.view')->prefix('documents')->group(function () {
        Route::get('/',              [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/log',           [DocumentController::class, 'log'])->name('documents.log');
        Route::get('/create',        [DocumentController::class, 'create'])->middleware('permission:letters.create')->name('documents.create');
        Route::post('/',             [DocumentController::class, 'store'])->middleware('permission:letters.create')->name('documents.store');
        Route::get('/{document}',    [DocumentController::class, 'show'])->name('documents.show');
        // Sunting draft & komentar — Reviewer ke atas (reviewer/approval/super_admin).
        Route::middleware('role:reviewer|approval|super_admin')->group(function () {
            Route::get('/{document}/edit',     [DocumentController::class, 'edit'])->name('documents.edit');
            Route::put('/{document}',          [DocumentController::class, 'update'])->name('documents.update');
            Route::post('/{document}/comments',[DocumentCommentController::class, 'store'])->name('documents.comments.store');
            Route::post('/{document}/endorse', [DocumentController::class, 'endorse'])->name('documents.endorse');
        });
        // Ubah status (Signed/Released/Cancelled) — khusus Super Admin (Direktur).
        Route::patch('/{document}/status', [DocumentController::class, 'setStatus'])->middleware('role:super_admin')->name('documents.status');
        Route::delete('/{document}', [DocumentController::class, 'destroy'])->middleware('permission:letters.delete')->name('documents.destroy');
    });

    // Manajemen User (buat, edit, hapus akses & persetujuan akun signup) — khusus super_admin
    Route::middleware('role:super_admin')->prefix('admin/users')->group(function () {
        Route::get('/',                  [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/',                 [AdminUserController::class, 'store'])->name('admin.users.store');
        Route::put('/{user}',            [AdminUserController::class, 'update'])->name('admin.users.update');
        Route::delete('/{user}',         [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
        Route::patch('/{user}/activate', [AdminUserController::class, 'activate'])->name('admin.users.activate');
        Route::patch('/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('admin.users.deactivate');
        Route::patch('/{user}/permissions', [AdminUserController::class, 'updatePermissions'])->name('admin.users.permissions.update');
    });

    // Manajemen Tugas (Task Management ala Notion)
    Route::middleware('permission:tasks.view')->group(function () {
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');

        Route::post('/tasks',                [TaskController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}',          [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.updateStatus');
        Route::post('/tasks/{task}/close',   [TaskController::class, 'close'])->name('tasks.close');
        Route::delete('/tasks/{task}',       [TaskController::class, 'destroy'])->name('tasks.destroy');

        Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');

        Route::post('/task-projects',                 [TaskProjectController::class, 'store'])->name('task-projects.store');
        Route::put('/task-projects/{taskProject}',    [TaskProjectController::class, 'update'])->name('task-projects.update');
        Route::delete('/task-projects/{taskProject}', [TaskProjectController::class, 'destroy'])->name('task-projects.destroy');
    });
});

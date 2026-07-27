<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebDashboardController;
use App\Http\Controllers\WebRosterController;
use App\Http\Controllers\WebMonitoringController;
use App\Http\Controllers\WebLaporanController;
use App\Http\Controllers\WebPegawaiController;
use App\Http\Controllers\WebShiftController;
use App\Http\Controllers\WebHakAksesController;
use App\Http\Controllers\WebPengaturanController;

/*
|--------------------------------------------------------------------------
| Web Routes (Publik / Tidak Memerlukan Login)
|--------------------------------------------------------------------------
*/

// Tampilan Form Login
Route::get('/', [WebAuthController::class, 'showLogin'])->name('login');
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login.form');

// Proses Login dengan RATE LIMITING (Maksimal 5 percobaan per 1 menit)
Route::post('/login', [WebAuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.post');

/*
|--------------------------------------------------------------------------
| Web Routes (Terproteksi / Memerlukan Autentikasi)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    // 1. Logout
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

    // 2. Dashboard
    Route::get('/dashboard', [WebDashboardController::class, 'index'])->name('dashboard');

    // 3. Operasional (Roster & Monitoring)
    Route::get('/roster', [WebRosterController::class, 'index'])->name('roster.index');
    Route::post('/roster/bulk-store', [WebRosterController::class, 'bulkStore'])->name('roster.bulk-store');
    Route::get('/monitoring', [WebMonitoringController::class, 'index'])->name('monitoring.index');

    // 4. Master Data
    Route::resource('master-pegawai', WebPegawaiController::class, ['names' => 'master-pegawai']);
    Route::resource('master-shift', WebShiftController::class, ['names' => 'master-shift']);

    // 5. Hak Akses (Role Management)
    Route::get('/hak-akses', [WebHakAksesController::class, 'index'])->name('hak-akses.index');
    Route::get('/hak-akses/{user}/edit', [WebHakAksesController::class, 'edit'])->name('hak-akses.edit');
    Route::put('/hak-akses/{user}', [WebHakAksesController::class, 'update'])->name('hak-akses.update');

    // 6. Laporan Payroll 
    Route::prefix('laporan-payroll')->name('laporan-payroll.')->group(function () {
        Route::get('/', [WebLaporanController::class, 'index'])->name('index');
        Route::get('/excel', [WebLaporanController::class, 'exportExcel'])->name('excel');
        Route::get('/pdf', [WebLaporanController::class, 'exportPdf'])->name('pdf');
        Route::get('/lembur', [WebLaporanController::class, 'rekapLembur'])->name('lembur');
    });

    // 7. Pengaturan Aplikasi
    Route::get('/pengaturan', [WebPengaturanController::class, 'index'])->name('pengaturan.index');
    Route::put('/pengaturan/update', [WebPengaturanController::class, 'update'])->name('pengaturan.update');

    // 8. Manajemen Profil & Password User
    Route::get('/profil/ubah-password', [WebAuthController::class, 'editPassword'])->name('profil.ubah-password');
    Route::post('/profil/ubah-password', [WebAuthController::class, 'updatePassword'])->name('profil.update-password');
});

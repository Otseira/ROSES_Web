<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebDashboardController;
use App\Http\Controllers\WebRosterController;
use App\Http\Controllers\WebMonitoringController;
use App\Http\Controllers\WebLaporanController;
use App\Http\Controllers\WebPegawaiController;
use App\Http\Controllers\WebShiftController;
use App\Http\Controllers\WebUnitKerjaController;
use App\Http\Controllers\WebHakAksesController;
use App\Http\Controllers\WebPengaturanController;
use App\Http\Controllers\WebMobileController;

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
    Route::get('/dashboard/ringkasan-karyawan', [WebDashboardController::class, 'ringkasanKaryawan'])->name('dashboard.ringkasanKaryawan');
    Route::get('/dashboard/karyawan-hari-ini',  [WebDashboardController::class, 'karyawanHariIni'])->name('dashboard.karyawanHariIni');

    // 3. Operasional (Roster & Monitoring)
    Route::get('/roster', [WebRosterController::class, 'index'])->name('roster.index');
    Route::post('/roster/bulk-store', [WebRosterController::class, 'bulkStore'])->name('roster.bulk-store');
    Route::post('/roster/copy-previous', [WebRosterController::class, 'copyPrevious'])->name('roster.copy-previous'); // <-- BARIS BARU
    Route::get('/monitoring', [WebMonitoringController::class, 'index'])->name('monitoring.index');

    // 4. Master Data
    Route::resource('master-pegawai', WebPegawaiController::class, ['names' => 'master-pegawai']);
    Route::resource('master-shift', WebShiftController::class, ['names' => 'master-shift']);
    Route::resource('master-unit', WebUnitKerjaController::class, ['names' => 'master-unit']);

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
        Route::get('/lembur/excel', [WebLaporanController::class, 'exportLemburExcel'])->name('lembur.excel');
        Route::post('/lembur/validasi/{id}', [WebLaporanController::class, 'prosesValidasiLemburWeb'])->name('lembur.validasi');
        Route::post('/lembur/validasi-pengambilan/{id}', [WebLaporanController::class, 'prosesValidasiPengambilanWeb'])->name('lembur.validasi-pengambilan');
        Route::post('/lembur/saldo-manual/{userId}', [WebLaporanController::class, 'updateSaldoManual'])->name('lembur.saldo-manual');
    });

    // 7. Pengaturan Aplikasi
    Route::get('/pengaturan', [WebPengaturanController::class, 'index'])->name('pengaturan.index');
    Route::put('/pengaturan/update', [WebPengaturanController::class, 'update'])->name('pengaturan.update');

    // 8. Manajemen Profil & Password User
    Route::get('/profil/ubah-password', [WebAuthController::class, 'editPassword'])->name('profil.ubah-password');
    Route::post('/profil/ubah-password', [WebAuthController::class, 'updatePassword'])->name('profil.update-password');
});

/*
|--------------------------------------------------------------------------
| ROSES Mobile Web (Aplikasi Mobile Versi Web untuk iOS / iPhone / Android)
|--------------------------------------------------------------------------
*/
// Shortcut redirect /m ke /app
Route::redirect('/m', '/app');

Route::prefix('app')->name('app.')->group(function () {
    // Web App Manifest untuk PWA
    Route::get('/manifest.json', [WebMobileController::class, 'manifest'])->name('manifest');

    // Autentikasi Khusus Web Mobile
    Route::get('/login', [WebMobileController::class, 'loginView'])->name('login');
    Route::post('/login', [WebMobileController::class, 'loginAction'])->name('login.post');
    Route::post('/logout', [WebMobileController::class, 'logout'])->name('logout');

    // Area Terproteksi Web Mobile
    Route::middleware('auth')->group(function () {
        Route::get('/', [WebMobileController::class, 'index'])->name('index');

        // Internal Ajax/Fetch endpoints untuk Single Page Mobile Web
        Route::get('/api/csrf-token', [WebMobileController::class, 'csrfToken'])->name('api.csrf-token');
        Route::get('/api/today-status', [WebMobileController::class, 'todayStatus'])->name('api.today-status');
        Route::post('/api/check-location', [WebMobileController::class, 'checkLocation'])->name('api.check-location');
        Route::post('/api/absensi/masuk', [WebMobileController::class, 'clockIn'])->name('api.absensi.masuk');
        Route::post('/api/absensi/pulang', [WebMobileController::class, 'clockOut'])->name('api.absensi.pulang');
        Route::get('/api/jadwal-dinas', [WebMobileController::class, 'jadwalDinas'])->name('api.jadwal-dinas');
        Route::get('/api/lembur-data', [WebMobileController::class, 'lemburData'])->name('api.lembur-data');
        Route::post('/api/lembur/ekstensi', [WebMobileController::class, 'storeEkstensi'])->name('api.lembur.ekstensi');
        Route::post('/api/lembur/oncall-masuk', [WebMobileController::class, 'clockInOnCall'])->name('api.lembur.oncall-masuk');
        Route::post('/api/lembur/oncall-keluar', [WebMobileController::class, 'clockOutOnCall'])->name('api.lembur.oncall-keluar');
        Route::post('/api/lembur/ambil-jam', [WebMobileController::class, 'storePengambilanJam'])->name('api.lembur.ambil-jam');
        Route::get('/api/validasi-list', [WebMobileController::class, 'validasiList'])->name('api.validasi-list');
        Route::post('/api/validasi/proses/{id}', [WebMobileController::class, 'validasiProses'])->name('api.validasi.proses');
        Route::post('/api/validasi/ambil-jam/{id}', [WebMobileController::class, 'validasiPengambilanProses'])->name('api.validasi.ambil-jam');
        Route::post('/api/profil/foto', [WebMobileController::class, 'updateFotoProfil'])->name('api.profil.foto');
        Route::post('/api/profil/password', [WebMobileController::class, 'updatePassword'])->name('api.profil.password');
    });
});

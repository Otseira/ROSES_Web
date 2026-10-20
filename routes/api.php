<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\LemburController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\RosterController;
use App\Http\Controllers\Api\ProfilController;
use App\Http\Controllers\Api\PengaturanController;

// ===================================================================
// RUTE PUBLIK (Bisa diakses dari Flutter tanpa harus login)
// ===================================================================
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/branding',  [PengaturanController::class, 'branding']);

// ===================================================================
// RUTE TERPROTEKSI (Flutter wajib mengirimkan Bearer Token Sanctum)
// ===================================================================
Route::middleware('auth:sanctum')->group(function () {

    // ===== Manajemen Akun =====
    Route::get('/me',             [AuthController::class, 'me']);
    Route::post('/logout',        [AuthController::class, 'logout']);

    // ===== Profil =====
    Route::put('/profil',          [ProfilController::class, 'update']);
    Route::put('/profil/password', [ProfilController::class, 'updatePassword']);
    Route::post('/profil/foto',    [ProfilController::class, 'updateFoto']);

    // ===================================================================
    // ABSENSI
    // ===================================================================
    Route::post('/absensi/masuk',      [AbsensiController::class, 'clockIn']);
    Route::post('/absensi/pulang',     [AbsensiController::class, 'clockOut']);
    Route::get('/absensi/info-shift',  [LemburController::class, 'infoShiftHariIni']);

    // ===================================================================
    // LEMBUR / ON-CALL (alur 1x tekan → form → foto → selesai otomatis)
    // ===================================================================
    Route::get('/lembur/riwayat',          [LemburController::class, 'riwayatSaya']);
    Route::post('/lembur/ekstensi',        [LemburController::class, 'storeEkstensi']);
    Route::post('/lembur/oncall-masuk',    [LemburController::class, 'clockInOnCall']);
    Route::post('/lembur/oncall-keluar',   [LemburController::class, 'clockOutOnCall']);
    Route::get('/lembur/validasi',         [LemburController::class, 'listValidasi']);
    Route::post('/lembur/validasi/{id}',   [LemburController::class, 'prosesValidasi']);
    Route::get('/lembur/oncall-aktif',     [LemburController::class, 'onCallAktif']);

    // Bank Jam Lembur & Kompensasi Waktu (Time-Off in Lieu)
    Route::get('/lembur/bank-jam',                   [LemburController::class, 'getBankJam']);
    Route::post('/lembur/ambil-jam',                 [LemburController::class, 'storePengambilanJam']);
    Route::get('/lembur/riwayat-ambil-jam',          [LemburController::class, 'riwayatPengambilanJam']);
    Route::get('/lembur/validasi-pengambilan',       [LemburController::class, 'listValidasiPengambilanJam']);
    Route::post('/lembur/validasi-pengambilan/{id}', [LemburController::class, 'prosesValidasiPengambilanJam']);

    Route::post('/lembur/oncall',          [LemburController::class, 'clockInOnCall']);
    Route::post('/lembur/oncall/masuk',    [LemburController::class, 'clockInOnCall']);
    Route::post('/lembur/oncall/keluar',   [LemburController::class, 'clockOutOnCall']);
    // ===================================================================
    // OPERASIONAL & LAPORAN (Opsional untuk Mobile)
    // ===================================================================
    Route::get('/roster/unit',            [RosterController::class, 'index']);
    Route::post('/roster/bulk-store',     [RosterController::class, 'bulkStore']);
    Route::get('/jadwal-dinas',           [RosterController::class, 'jadwalDinas']);
    Route::get('/laporan/rekap-bulanan',  [LaporanController::class, 'rekapBulanan']);
});

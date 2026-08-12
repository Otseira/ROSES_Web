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
Route::post('/login',    [AuthController::class, 'login']);
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
    Route::post('/lembur/ekstensi',        [LemburController::class, 'storeEkstensi']);
    Route::post('/lembur/oncall-masuk',    [LemburController::class, 'clockInOnCall']);
    Route::post('/lembur/oncall-keluar',   [LemburController::class, 'clockOutOnCall']);
    Route::get('/lembur/validasi',         [LemburController::class, 'listValidasi']);
    Route::post('/lembur/validasi/{id}',   [LemburController::class, 'prosesValidasi']);

    // ===================================================================
    // OPERASIONAL & LAPORAN (Opsional untuk Mobile)
    // ===================================================================
    Route::get('/roster/unit',            [RosterController::class, 'index']);
    Route::post('/roster/bulk-store',     [RosterController::class, 'bulkStore']);
    Route::get('/jadwal-dinas',           [RosterController::class, 'jadwalDinas']);
    Route::get('/laporan/rekap-bulanan',  [LaporanController::class, 'rekapBulanan']);
});

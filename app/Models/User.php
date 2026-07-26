<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

use App\Models\MasterUnitKerja;
use App\Models\MasterModul;
use App\Models\JadwalRoster;
use App\Models\LogLembur;
use App\Models\LogAbsensi;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'nik',
        'email',         
        'nomor_whatsapp',
        'unit_kerja_id', 
        'role',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean', // <-- TAMBAHKAN INI agar otomatis jadi true/false
    ];

    // ===== RELASI ASLI (WAJIB ADA — ini yang bikin login error kalau hilang) =====

    // Pegawai bernaung di bawah satu unit kerja
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(MasterUnitKerja::class, 'unit_kerja_id');
    }

    // ACL: satu pegawai bisa mengakses banyak modul via tabel jembatan akses_users
    public function moduls(): BelongsToMany
    {
        return $this->belongsToMany(MasterModul::class, 'akses_users', 'user_id', 'modul_id')
            ->withTimestamps();
    }

    // Riwayat jadwal roster
    public function rosters(): HasMany
    {
        return $this->hasMany(JadwalRoster::class, 'user_id');
    }

    // Riwayat log lembur
    public function lemburs(): HasMany
    {
        return $this->hasMany(LogLembur::class, 'user_id');
    }

    // ===== RELASI TAMBAHAN (untuk fitur Laporan / Rekap) =====

    // Semua log absensi milik user (melalui roster)
    public function logAbsensis(): HasManyThrough
    {
        return $this->hasManyThrough(
            LogAbsensi::class,
            JadwalRoster::class,
            'user_id',   // FK di jadwal_rosters
            'roster_id', // FK di log_absensis
            'id',        // local key users
            'id'         // local key jadwal_rosters
        );
    }

    // Alias log lembur (kompatibilitas dengan LaporanController)
    public function logLemburs(): HasMany
    {
        return $this->hasMany(LogLembur::class, 'user_id');
    }
}

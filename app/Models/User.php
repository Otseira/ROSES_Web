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
        'foto_profil', // <-- TAMBAHKAN INI
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // ===== RELASI DASAR =====

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(MasterUnitKerja::class, 'unit_kerja_id');
    }

    public function moduls(): BelongsToMany
    {
        return $this->belongsToMany(MasterModul::class, 'akses_users', 'user_id', 'modul_id')
            ->withTimestamps();
    }

    public function rosters(): HasMany
    {
        return $this->hasMany(JadwalRoster::class, 'user_id');
    }

    public function lemburs(): HasMany
    {
        return $this->hasMany(LogLembur::class, 'user_id');
    }

    public function logAbsensis(): HasManyThrough
    {
        return $this->hasManyThrough(
            LogAbsensi::class,
            JadwalRoster::class,
            'user_id',
            'roster_id',
            'id',
            'id'
        );
    }

    public function logLemburs(): HasMany
    {
        return $this->hasMany(LogLembur::class, 'user_id');
    }

    // ===== RELASI BARU: Unit yang Dikelola (Multi-Unit) =====

    public function managesUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            MasterUnitKerja::class,
            'unit_kerja_users',
            'user_id',
            'master_unit_kerja_id'
        )->withTimestamps();
    }

    // ===== HELPER METHODS =====

    /**
     * Cek apakah user ini adalah role manajemen (bisa kelola multi-unit)
     */
    public function isManajemen(): bool
    {
        return in_array($this->role, ['kepala_unit', 'penanggung_jawab', 'manajer']);
    }

    /**
     * Cek apakah user ini adalah Direktur (akses global)
     */
    public function isDirektur(): bool
    {
        return $this->role === 'direktur';
    }

    /**
     * Cek apakah user ini adalah HRD
     */
    public function isHrd(): bool
    {
        return $this->role === 'hrd';
    }

    /**
     * Cek apakah user ini adalah Superadmin
     */
    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    /**
     * Cek apakah user memiliki akses global (Direktur, HRD, atau Superadmin)
     */
    public function hasGlobalAccess(): bool
    {
        return $this->isDirektur() || $this->isHrd() || $this->isSuperadmin();
    }
}

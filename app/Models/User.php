<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'role',
        'password',
        'is_active', // <-- TAMBAHKAN INI
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

    public function logAbsensis(): HasManyThrough
    {
        return $this->hasManyThrough(
            LogAbsensi::class,
            JadwalRoster::class,
            'user_id',        // Foreign key di jadwal_rosters
            'roster_id',      // Foreign key di log_absensis
            'id',             // Local key di users
            'id'              // Local key di jadwal_rosters
        );
    }

    // Relasi: Karyawan memiliki banyak log lembur (alias untuk kompatibilitas)
    public function logLemburs(): HasMany
    {
        return $this->hasMany(LogLembur::class, 'user_id');
    }
}

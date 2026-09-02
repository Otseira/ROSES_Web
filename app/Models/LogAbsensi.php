<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAbsensi extends Model
{
    protected $fillable = [
        'user_id',
        'roster_id',
        'waktu_masuk',
        'waktu_pulang',
        'latitude_masuk',
        'longitude_masuk',
        'latitude_pulang',
        'longitude_pulang',
        'foto_masuk',
        'foto_pulang',
        'jenis_absen',
        'menit_terlambat',
        'status_kehadiran',
        'durasi_kerja',
    ];

    protected $casts = [
        'waktu_masuk'  => 'datetime',
        'waktu_pulang' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function roster(): BelongsTo
    {
        return $this->belongsTo(JadwalRoster::class, 'roster_id');
    }
}

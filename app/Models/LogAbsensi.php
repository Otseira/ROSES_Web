<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogAbsensi extends Model
{
    protected $table = 'log_absensis';

    protected $fillable = [
        'roster_id',
        'waktu_masuk',
        'waktu_pulang',
        'menit_terlambat',
        'foto_masuk',
        'foto_pulang',
        'latitude_masuk',
        'longitude_masuk',
        'latitude_pulang',
        'longitude_pulang',
        'ip_address_masuk',
        'ip_address_pulang',
    ];

    protected $casts = [
        'waktu_masuk' => 'datetime',
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

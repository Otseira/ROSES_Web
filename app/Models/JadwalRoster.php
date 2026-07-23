<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class JadwalRoster extends Model
{
    protected $table = 'jadwal_rosters';

    protected $fillable = [
        'user_id',
        'shift_id',
        'tanggal_dinas',
    ];

    // Relasi: Jadwal ini milik pegawai tertentu
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi: Jadwal ini mengacu pada shift kerja tertentu
    public function shift(): BelongsTo
    {
        return $this->belongsTo(MasterShift::class, 'shift_id');
    }

    // Relasi: Satu slot jadwal dinas harian menghasilkan maksimal satu rekaman log absensi
    public function logAbsensi(): HasOne
    {
        return $this->hasOne(LogAbsensi::class, 'roster_id');
    }
}
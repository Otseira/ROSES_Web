<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterShift extends Model
{
    protected $fillable = [
        'nama_shift',
        'jam_masuk',
        'jam_pulang',
        'toleransi_terlambat_menit',
        'unit_kerja_id',
        'keterangan',
    ];

    /** Shift milik unit kerja tertentu */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(MasterUnitKerja::class, 'unit_kerja_id');
    }

    /** Shift yang dipakai di jadwal roster */
    public function rosters(): HasMany
    {
        return $this->hasMany(JadwalRoster::class, 'shift_id');
    }
}

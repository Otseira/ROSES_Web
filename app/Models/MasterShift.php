<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterShift extends Model
{
    protected $table = 'master_shifts';

    protected $fillable = [
        'nama_shift',
        'jam_masuk',
        'jam_pulang',
        'toleransi_terlambat_menit',
        'unit_kerja_id',   // ✅ pastikan ada
        'keterangan',
    ];

    // Relasi: Satu definisi shift dipakai di banyak jadwal roster karyawan
    public function rosters(): HasMany
    {
        return $this->hasMany(JadwalRoster::class, 'shift_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(MasterUnitKerja::class, 'unit_kerja_id');
    }
}

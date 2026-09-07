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
        'tanggal_dinas',
        'shift_id',
        'custom_jam_masuk',
        'custom_jam_pulang',
        'custom_nama_shift',
    ];

    protected $casts = [
        'tanggal_dinas' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(MasterShift::class);
    }

    /**
     * ✅ RELASI YANG HILANG: log absensi yang terhubung ke roster ini.
     * Dipakai layar "Jadwal Dinas Bulanan" di mobile untuk menampilkan
     * status Tepat Waktu / Terlambat per tanggal.
     */
    public function logAbsensi(): HasOne
    {
        return $this->hasOne(LogAbsensi::class, 'roster_id');
    }

    /**
     * Jam masuk: prioritas custom, fallback ke shift.
     */
    public function getJamMasukAttribute(): ?string
    {
        return $this->custom_jam_masuk ?? ($this->shift ? (string) $this->shift->jam_masuk : null);
    }

    /**
     * Jam pulang: prioritas custom, fallback ke shift.
     */
    public function getJamPulangAttribute(): ?string
    {
        return $this->custom_jam_pulang ?? ($this->shift ? (string) $this->shift->jam_pulang : null);
    }

    /**
     * Nama shift: prioritas custom, fallback ke shift.
     */
    public function getNamaShiftAttribute(): ?string
    {
        return $this->custom_nama_shift ?? ($this->shift ? $this->shift->nama_shift : null);
    }

    /**
     * Apakah ini shift custom?
     */
    public function isCustom(): bool
    {
        return $this->custom_jam_masuk !== null;
    }
}

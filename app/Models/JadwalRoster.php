<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Ambil jam masuk:优先 custom, fallback ke shift.
     */
    public function getJamMasukAttribute(): ?string
    {
        return $this->custom_jam_masuk ?? ($this->shift ? (string) $this->shift->jam_masuk : null);
    }

    /**
     * Ambil jam pulang:优先 custom, fallback ke shift.
     */
    public function getJamPulangAttribute(): ?string
    {
        return $this->custom_jam_pulang ?? ($this->shift ? (string) $this->shift->jam_pulang : null);
    }

    /**
     * Ambil nama shift:优先 custom, fallback ke shift.
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
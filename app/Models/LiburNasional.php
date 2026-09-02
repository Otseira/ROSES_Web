<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiburNasional extends Model
{
    protected $fillable = ['tanggal', 'nama', 'jenis'];

    protected $casts = ['tanggal' => 'date'];

    /** Scope: hanya libur nasional (bukan cuti bersama) */
    public function scopeNasional($query)
    {
        return $query->where('jenis', 'nasional');
    }

    /** Scope: hanya cuti bersama */
    public function scopeCutiBersama($query)
    {
        return $query->where('jenis', 'cuti_bersama');
    }
}
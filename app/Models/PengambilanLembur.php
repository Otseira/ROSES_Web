<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengambilanLembur extends Model
{
    protected $table = 'pengambilan_lemburs';

    protected $fillable = [
        'user_id',
        'tanggal_dinas',
        'tipe_pengambilan',
        'durasi_menit',
        'keterangan',
        'status_validasi',
        'divalidasi_oleh',
        'divalidasi_pada',
        'catatan_validasi',
    ];

    protected $casts = [
        'tanggal_dinas'   => 'date',
        'divalidasi_pada' => 'datetime',
        'durasi_menit'    => 'integer',
    ];

    // Relasi: Pengajuan dilakukan oleh seorang pegawai
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi: Divalidasi oleh atasan/pejabat
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }
}


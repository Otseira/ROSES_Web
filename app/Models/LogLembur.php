<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogLembur extends Model
{
    protected $table = 'log_lemburs';

    protected $fillable = [
        'user_id',
        'jenis_lembur',
        'waktu_mulai_lembur',
        'waktu_selesai_lembur',
        'total_jam_lembur',
        'status_validasi',
        'keterangan',
    ];

    protected $casts = [
        'waktu_mulai_lembur' => 'datetime',
        'waktu_selesai_lembur' => 'datetime',
    ];

    // Relasi: Transaksi lembur dilakukan oleh seorang pegawai
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
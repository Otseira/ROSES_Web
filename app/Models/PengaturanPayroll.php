<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanPayroll extends Model
{
    protected $table = 'pengaturan_payrolls';

    protected $fillable = [
        'potongan_terlambat_per_menit',
        'uang_lembur_per_jam',
        'tanggal_cut_off_mulai',
        'tanggal_cut_off_selesai',
    ];
}
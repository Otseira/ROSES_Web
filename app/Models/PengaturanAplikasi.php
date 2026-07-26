<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengaturanAplikasi extends Model
{
    protected $fillable = [
        'latitude',
        'longitude',
        'radius_meter',
        'logo',
        'nama_instansi',
        'tagline',
    ];
}

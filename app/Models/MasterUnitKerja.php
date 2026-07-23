<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MasterUnitKerja extends Model
{
    protected $table = 'master_unit_kerjas';

    protected $fillable = [
        'nama_unit',
        'deskripsi',
    ];

    // Relasi: Satu unit kerja memiliki banyak pegawai
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'unit_kerja_id', 'id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    // Relasi BALIK: Unit ini dikelola oleh siapa saja (Manajer, PJ, Kepala Unit)
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'unit_kerja_users',
            'master_unit_kerja_id',
            'user_id'
        )->withTimestamps();
    }
}

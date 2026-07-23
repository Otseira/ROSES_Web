<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MasterModul extends Model
{
    protected $table = 'master_moduls';

    protected $fillable = [
        'kode_modul',
        'nama_modul',
    ];

    // Relasi ACL: Satu modul dapat diakses oleh banyak user melalui tabel jembatan akses_users
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'akses_users', 'modul_id', 'user_id')
                    ->withTimestamps();
    }
}
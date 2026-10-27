<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeAkademik extends Model
{
    use HasFactory;

    protected $table = 'periode_akademik';

    public const STATUS_AKTIF    = 'Aktif';
    public const STATUS_NONAKTIF = 'Nonaktif';
    public const STATUS_ARSIP    = 'Arsip';

    protected $fillable = [
        'nama_periode',
        'status',
    ];

    public function dokumenPelaporan(): HasMany
    {
        return $this->hasMany(DokumenPelaporan::class, 'id_periode');
    }
}

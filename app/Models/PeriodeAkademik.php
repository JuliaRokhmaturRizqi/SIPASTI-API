<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodeAkademik extends Model
{
    use HasFactory;

    protected $table = 'periode_akademik';

    public const STATUS_BUKA  = 'Buka';
    public const STATUS_TUTUP = 'Tutup';

    protected $fillable = [
        'nama_periode',
        'status_pelaporan',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function dokumenPelaporan(): HasMany
    {
        return $this->hasMany(DokumenPelaporan::class, 'id_periode');
    }
}

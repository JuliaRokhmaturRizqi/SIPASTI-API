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

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_periode',
        'status_aktif',
        'status_pelaporan',
        'is_default',
    ];

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_aktif'     => true,
        'status_pelaporan' => self::STATUS_BUKA,
        'is_default'       => false,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status_aktif' => 'boolean',
        'is_default'   => 'boolean',
    ];

    /**
     * Get the attributes that should be cast (Laravel 11+).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_aktif' => 'boolean',
            'is_default'   => 'boolean',
        ];
    }

    /**
     * Mutator untuk memastikan status_aktif dan status_pelaporan selalu sinkron.
     */
    public function setStatusAktifAttribute($value): void
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            $bool = (bool) $value;
        }

        $this->attributes['status_aktif']     = $bool;
        $this->attributes['status_pelaporan'] = $bool ? self::STATUS_BUKA : self::STATUS_TUTUP;
    }

    /**
     * Mutator untuk mendukung input string status_pelaporan.
     */
    public function setStatusPelaporanAttribute($value): void
    {
        $this->attributes['status_pelaporan'] = $value;
        $this->attributes['status_aktif']     = ($value === self::STATUS_BUKA || $value === 'Aktif');
    }

    public function dokumenPelaporan(): HasMany
    {
        return $this->hasMany(DokumenPelaporan::class, 'id_periode');
    }
}

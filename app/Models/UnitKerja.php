<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    use HasFactory;

    protected $table = 'unit_kerja';

    public const KATEGORI_BIRO  = 'biro';
    public const KATEGORI_PUSAT = 'pusat';
    public const KATEGORI_LAB   = 'lab';

    public const KATEGORI_LIST = [
        self::KATEGORI_BIRO,
        self::KATEGORI_PUSAT,
        self::KATEGORI_LAB,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_unit',
        'kategori_unit',
    ];

    /**
     * Relasi ke akun pengguna yang terikat dengan unit kerja ini.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_unit_kerja');
    }

    /**
     * Relasi ke daftar laporan kinerja milik unit kerja ini.
     */
    public function laporanKinerja(): HasMany
    {
        return $this->hasMany(LaporanKinerja::class, 'id_unit_kerja');
    }
}

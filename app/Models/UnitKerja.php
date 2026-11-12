<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitKerja extends Model
{
    use HasFactory;

    protected $table = 'unit_kerja';

    public const KATEGORI_BIRO     = 'biro';
    public const KATEGORI_PUSAT    = 'pusat';
    public const KATEGORI_LAB      = 'lab';
    public const KATEGORI_FAKULTAS = 'fakultas';

    public const KATEGORI_LIST = [
        self::KATEGORI_BIRO,
        self::KATEGORI_PUSAT,
        self::KATEGORI_LAB,
        self::KATEGORI_FAKULTAS,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_unit',
        'id_kategori',
        'kategori_unit',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'kategori_unit',
    ];

    /**
     * Mutator sanitasi XSS pada nama_unit.
     */
    public function setNamaUnitAttribute(?string $value): void
    {
        $this->attributes['nama_unit'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    /**
     * Relasi ke Master Data Kategori Unit Kerja (Dinamis).
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriUnit::class, 'id_kategori');
    }

    /**
     * Alias relasi ke Master Data Kategori Unit Kerja.
     */
    public function kategoriUnit(): BelongsTo
    {
        return $this->belongsTo(KategoriUnit::class, 'id_kategori');
    }

    /**
     * Accessor backward-compatibility: $unit->kategori_unit (mengembalikan string lowercase seperti 'biro', 'pusat', 'lab', 'fakultas').
     */
    public function getKategoriUnitAttribute(): ?string
    {
        $relation = $this->relationLoaded('kategori')
            ? ($this->relations['kategori'] ?? null)
            : ($this->relationLoaded('kategoriUnit') ? ($this->relations['kategoriUnit'] ?? null) : null);

        if ($relation instanceof KategoriUnit) {
            $nama = $relation->nama_kategori;
            if (strcasecmp($nama, 'Laboratorium') === 0 || strcasecmp($nama, 'lab') === 0) {
                return 'lab';
            }
            return strtolower($nama);
        }

        if (! empty($this->attributes['id_kategori'])) {
            $kategori = KategoriUnit::find($this->attributes['id_kategori']);
            if ($kategori) {
                $nama = $kategori->nama_kategori;
                if (strcasecmp($nama, 'Laboratorium') === 0 || strcasecmp($nama, 'lab') === 0) {
                    return 'lab';
                }
                return strtolower($nama);
            }
        }

        return $this->attributes['kategori_unit'] ?? null;
    }

    /**
     * Mutator backward-compatibility: $unit->kategori_unit = 'biro'.
     */
    public function setKategoriUnitAttribute(?string $value): void
    {
        if (! $value) {
            return;
        }

        $map = [
            'biro'         => 'Biro',
            'pusat'        => 'Pusat',
            'lab'          => 'Laboratorium',
            'laboratorium' => 'Laboratorium',
            'fakultas'     => 'Fakultas',
        ];

        $normalized   = strtolower(trim($value));
        $namaKategori = $map[$normalized] ?? ucfirst($value);

        $kategori = KategoriUnit::firstOrCreate(['nama_kategori' => $namaKategori]);
        $this->attributes['id_kategori'] = $kategori->id;
    }

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

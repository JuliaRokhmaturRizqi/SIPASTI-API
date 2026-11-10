<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanKinerja extends Model
{
    use HasFactory;

    protected $table = 'laporan_kinerja';

    public const STATUS_REVIEW  = 'Review';
    public const STATUS_REVISI  = 'Revisi';
    public const STATUS_SELESAI = 'Selesai';

    public const STATUS_LIST = [
        self::STATUS_REVIEW,
        self::STATUS_REVISI,
        self::STATUS_SELESAI,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_unit_kerja',
        'id_periode',
        'nama_kegiatan',
        'uraian',
        'link_bukti',
        'kendala',
        'dukungan',
        'tindak_lanjut',
        'nama_penandatangan',
        'status',
        'catatan_revisi',
    ];

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_REVIEW,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id_unit_kerja' => 'integer',
        'id_periode'    => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /**
     * Get the attributes that should be cast (Laravel 11+).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_unit_kerja' => 'integer',
            'id_periode'    => 'integer',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
        ];
    }

    /**
     * Mutator sanitasi XSS (Defense-in-Depth) menggunakan strip_tags.
     */
    public function setNamaKegiatanAttribute(?string $value): void
    {
        $this->attributes['nama_kegiatan'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setUraianAttribute(?string $value): void
    {
        $this->attributes['uraian'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setKendalaAttribute(?string $value): void
    {
        $this->attributes['kendala'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setDukunganAttribute(?string $value): void
    {
        $this->attributes['dukungan'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setTindakLanjutAttribute(?string $value): void
    {
        $this->attributes['tindak_lanjut'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setNamaPenandatanganAttribute(?string $value): void
    {
        $this->attributes['nama_penandatangan'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setCatatanRevisiAttribute(?string $value): void
    {
        $this->attributes['catatan_revisi'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    /**
     * Relasi ke Unit Kerja pengirim laporan.
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit_kerja');
    }

    /**
     * Relasi ke Periode Akademik pelaporan.
     */
    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'id_periode');
    }

    /**
     * Alias relasi ke Periode Akademik.
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'id_periode');
    }
}

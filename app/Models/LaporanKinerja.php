<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanKinerja extends Model
{
    use HasFactory;

    protected $table = 'laporan_kinerja';

    // Status Workflow Persetujuan Bertingkat
    public const STATUS_MENUNGGU_PIMPINAN = 'menunggu_pimpinan';
    public const STATUS_REVISI_PIMPINAN   = 'revisi_pimpinan';
    public const STATUS_MENUNGGU_ADMIN    = 'menunggu_admin';
    public const STATUS_REVISI_ADMIN      = 'revisi_admin';
    public const STATUS_SELESAI           = 'selesai';

    public const WORKFLOW_STATUS_LIST = [
        self::STATUS_MENUNGGU_PIMPINAN,
        self::STATUS_REVISI_PIMPINAN,
        self::STATUS_MENUNGGU_ADMIN,
        self::STATUS_REVISI_ADMIN,
        self::STATUS_SELESAI,
    ];

    // Status Legacy untuk backward compatibility
    public const STATUS_REVIEW  = 'Review';
    public const STATUS_REVISI  = 'Revisi';
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
        'bulan_laporan',
        'status_laporan',
        'identitas_laporan',
        'pembahasan_json',
        'kendala_json',
        'dukungan_json',
        'tindak_lanjut_json',
        'kesimpulan',
        'rekomendasi',
        'ttd_penyusun_path',
        'ttd_pimpinan_path',
        // Kolom legacy/kompatibilitas
        'nama_kegiatan',
        'uraian',
        'link_bukti',
        'kendala',
        'dukungan',
        'tindak_lanjut',
        'nama_penandatangan',
        'ttd_image_path',
        'status',
        'catatan_revisi',
    ];

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_laporan' => self::STATUS_MENUNGGU_PIMPINAN,
        'status'         => self::STATUS_REVIEW,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id_unit_kerja'      => 'integer',
        'id_periode'         => 'integer',
        'identitas_laporan'  => 'array',
        'pembahasan_json'    => 'array',
        'kendala_json'       => 'array',
        'dukungan_json'      => 'array',
        'tindak_lanjut_json' => 'array',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /**
     * Get the attributes that should be cast (Laravel 11+).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_unit_kerja'      => 'integer',
            'id_periode'         => 'integer',
            'identitas_laporan'  => 'array',
            'pembahasan_json'    => 'array',
            'kendala_json'       => 'array',
            'dukungan_json'      => 'array',
            'tindak_lanjut_json' => 'array',
            'created_at'         => 'datetime',
            'updated_at'         => 'datetime',
        ];
    }

    /**
     * Accessor backward-compatibility: $laporan->ttd_image_path
     */
    public function getTtdImagePathAttribute(): ?string
    {
        return $this->attributes['ttd_penyusun_path'] ?? $this->attributes['ttd_image_path'] ?? null;
    }

    /**
     * Mutator backward-compatibility: $laporan->ttd_image_path = ...
     */
    public function setTtdImagePathAttribute(?string $value): void
    {
        $this->attributes['ttd_penyusun_path'] = $value;
    }

    /**
     * Accessor backward-compatibility: $laporan->status
     */
    public function getStatusAttribute(): ?string
    {
        if (! empty($this->attributes['status'])) {
            return $this->attributes['status'];
        }

        return match ($this->attributes['status_laporan'] ?? null) {
            self::STATUS_SELESAI                              => self::STATUS_SELESAI,
            self::STATUS_REVISI_PIMPINAN, self::STATUS_REVISI_ADMIN => self::STATUS_REVISI,
            default                                           => self::STATUS_REVIEW,
        };
    }

    /**
     * Mutator sanitasi XSS (Defense-in-Depth) menggunakan strip_tags.
     */
    public function setBulanLaporanAttribute(?string $value): void
    {
        $this->attributes['bulan_laporan'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setKesimpulanAttribute(?string $value): void
    {
        $this->attributes['kesimpulan'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    public function setRekomendasiAttribute(?string $value): void
    {
        $this->attributes['rekomendasi'] = $value !== null ? trim(strip_tags($value)) : null;
    }

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

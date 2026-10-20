<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenPelaporan extends Model
{
    use HasFactory;

    protected $table = 'dokumen_pelaporan';

    public const TAHAP_KURIKULUM = 1;
    public const TAHAP_JADWAL    = 2;
    public const TAHAP_NILAI     = 3;
    public const TAHAP_YUDISIUM  = 4;

    protected $fillable = [
        'id_prodi',
        'id_periode',
        'tahap_dokumen',
        'file_path',
        'status',
        'catatan_revisi',
        'tanggal_upload',
    ];

    protected function casts(): array
    {
        return [
            'tahap_dokumen'  => 'integer',
            'tanggal_upload' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'id_prodi');
    }

    public function periodeAkademik(): BelongsTo
    {
        return $this->belongsTo(PeriodeAkademik::class, 'id_periode');
    }
}

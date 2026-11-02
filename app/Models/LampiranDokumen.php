<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LampiranDokumen extends Model
{
    use HasFactory;

    protected $table = 'lampiran_dokumen';

    protected $fillable = [
        'dokumen_pelaporan_id',
        'jenis_lampiran',
        'nama_file_asli',
        'file_path',
    ];

    /**
     * Sembunyikan path direktori fisik agar tidak terekspos ke response JSON.
     */
    protected $hidden = [
        'file_path',
    ];

    public function dokumenPelaporan(): BelongsTo
    {
        return $this->belongsTo(DokumenPelaporan::class, 'dokumen_pelaporan_id');
    }
}

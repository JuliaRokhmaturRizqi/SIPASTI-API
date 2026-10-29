<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prodi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'prodi';

    protected $fillable = [
        'id_fakultas',
        'nama_prodi',
        'periode_mulai_aktif',
    ];

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'id_fakultas');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_prodi');
    }

    public function dokumenPelaporan(): HasMany
    {
        return $this->hasMany(DokumenPelaporan::class, 'id_prodi');
    }
}

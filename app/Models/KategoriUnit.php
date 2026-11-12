<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriUnit extends Model
{
    use HasFactory;

    protected $table = 'kategori_unit';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_kategori',
    ];

    /**
     * Mutator sanitasi XSS (Defense-in-Depth).
     */
    public function setNamaKategoriAttribute(?string $value): void
    {
        $this->attributes['nama_kategori'] = $value !== null ? trim(strip_tags($value)) : null;
    }

    /**
     * Relasi ke seluruh Unit Kerja yang berada di bawah kategori ini.
     */
    public function unitKerja(): HasMany
    {
        return $this->hasMany(UnitKerja::class, 'id_kategori');
    }
}

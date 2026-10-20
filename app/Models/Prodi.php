<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prodi extends Model
{
    use HasFactory;

    protected $table = 'prodi';

    protected $fillable = [
        'nama_prodi',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'id_prodi');
    }

    public function dokumenPelaporan(): HasMany
    {
        return $this->hasMany(DokumenPelaporan::class, 'id_prodi');
    }
}

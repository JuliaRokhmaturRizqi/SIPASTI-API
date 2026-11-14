<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    public const DEFAULT_OPERATOR_PASSWORD = 'Unuha2026!';
    public const DEFAULT_PRODI_PASSWORD    = 'Unuha2026!';

    public const JABATAN_STAF     = 'staf';
    public const JABATAN_PIMPINAN = 'pimpinan';
    public const JABATAN_LIST     = [
        self::JABATAN_STAF,
        self::JABATAN_PIMPINAN,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'role',
        'jabatan',
        'id_fakultas',
        'id_unit_kerja',
        'id_prodi',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Appends accessor attributes for serialization compatibility.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'id_prodi',
    ];

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'jabatan'   => self::JABATAN_STAF,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password'  => 'hashed',
        'is_active' => 'boolean',
    ];

    /**
     * Get the attributes that should be cast (Laravel 11+).
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password'  => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relasi ke Fakultas (1 Akun Operator bertugas untuk 1 Fakultas).
     */
    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class, 'id_fakultas');
    }

    /**
     * Relasi ke Unit Kerja (untuk akun Biro, Pusat, atau Laboratorium).
     */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'id_unit_kerja');
    }

    /**
     * Mutator kompatibilitas transisi: jika kode sebelumnya mengirimkan 'id_prodi',
     * otomatis petakan ke id_fakultas milik prodi tersebut.
     */
    public function setIdProdiAttribute($value): void
    {
        if ($value) {
            $prodi = Prodi::find($value);
            if ($prodi && $prodi->id_fakultas) {
                $this->attributes['id_fakultas'] = $prodi->id_fakultas;
            }
        }
    }

    /**
     * Accessor dinamis untuk menjaga backwards compatibility selama transisi refactoring.
     */
    public function getIdProdiAttribute(): ?int
    {
        return $this->fakultas?->prodi()->value('id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPimpinan(): bool
    {
        return $this->jabatan === self::JABATAN_PIMPINAN;
    }

    public function isStaf(): bool
    {
        return $this->jabatan === self::JABATAN_STAF;
    }
}

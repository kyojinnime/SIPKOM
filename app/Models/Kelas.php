<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model kelas = satu angkatan dalam satu prodi, dengan satu dosen wali tetap. */
class Kelas extends Model
{
    protected $table = 'kelas';
    protected $fillable = ['prodi_id', 'dosen_wali_id', 'angkatan', 'nama_kelas'];

    /** Cache tahun mulai periode aktif agar tidak query berulang per kelas. */
    protected static ?int $tahunMulaiAktif = null;

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(DosenWali::class, 'dosen_wali_id');
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'kelas_id');
    }

    /**
     * Tingkat saat ini, dihitung dari angkatan dan periode aktif.
     * Contoh: angkatan 2025, periode 2026/2027 -> tingkat 2.
     */
    public function getTingkatAttribute(): int
    {
        if (static::$tahunMulaiAktif === null) {
            static::$tahunMulaiAktif = Periode::yangAktif()?->tahunMulai() ?? (int) date('Y');
        }
        return max(1, static::$tahunMulaiAktif - $this->angkatan + 1);
    }

    /** Label untuk dropdown/tabel, mis. "2A - D3 Teknik Informatika". */
    public function getLabelAttribute(): string
    {
        return $this->tingkat . $this->nama_kelas . ' - ' . ($this->prodi->nama_prodi ?? '-');
    }
}
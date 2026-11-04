<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model kelas = satu angkatan dalam satu prodi, dengan satu dosen wali tetap. */
class Kelas extends Model
{
    protected $table = 'kelas';
    protected $fillable = ['prodi_id', 'angkatan', 'nama_kelas'];

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function penugasan(): HasMany
    {
        return $this->hasMany(PenugasanDosenWali::class, 'kelas_id');
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class, 'kelas_id');
    }

    /**
     * Semester saat ini, dihitung dari angkatan dan periode aktif sesuai dokumen.
     * Rumus: (tahun mulai tahun ajaran - angkatan) * 2 + (1 untuk ganjil, 2 untuk genap)
     */
    public function getSemesterAttribute(): int
    {
        $periode = Periode::yangAktif();
        
        // Fallback jika belum ada periode
        if (!$periode) return 1;

        $tahunMulai = $periode->tahunMulai();
        $semesterIndex = $periode->getSemesterIndex(); // 1 ganjil, 2 genap

        // Rumus sesuai dokumen: (tahunMulai - angkatan) * 2 + semesterIndex
        $semester = ($tahunMulai - $this->angkatan) * 2 + $semesterIndex;

        return max(1, min(14, $semester)); // Batasi agar tidak negatif atau terlalu besar
    }

    /** Label untuk dropdown/tabel, mis. "Semester 3A - D3 Teknik Informatika". */
    public function getLabelAttribute(): string
    {
        return $this->semester . $this->nama_kelas . ' - ' . ($this->prodi->nama_prodi ?? '-');
    }
}
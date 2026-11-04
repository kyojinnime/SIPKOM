<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Model periode (tahun ajaran + semester). */
class Periode extends Model
{
    protected $table = 'periode';
    protected $fillable = ['tahun_ajaran', 'semester', 'aktif'];
    protected $casts = ['aktif' => 'boolean'];

    /** Mengambil periode yang sedang aktif, atau null bila belum ada. */
    public static function yangAktif(): ?self
    {
        return static::where('aktif', true)->first();
    }

    /** Tahun mulai dari "2026/2027" -> 2026. */
    public function tahunMulai(): int
    {
        return (int) substr($this->tahun_ajaran, 0, 4);
    }

    /** Indeks semester: 1 untuk Ganjil, 2 untuk Genap. */
    public function getSemesterIndex(): int
    {
        return $this->semester === 'ganjil' ? 1 : 2;
    }
}
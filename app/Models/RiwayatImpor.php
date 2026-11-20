<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat import PDF: satu baris per kelas per import. */
class RiwayatImpor extends Model
{
    protected $table = 'riwayat_impor';

    protected $fillable = [
        'user_id', 'kelas_id', 'periode_id', 'nama_berkas',
        'hash_berkas', 'dicetak_pada', 'ringkasan',
    ];

    /** dicetak_pada menjadi tanggal, ringkasan (JSON) menjadi array. */
    protected function casts(): array
    {
        return ['dicetak_pada' => 'datetime', 'ringkasan' => 'array'];
    }

    /** Pengimpor (dosen wali atau admin). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    /**
     * Import terakhir untuk (kelas, periode), diurutkan menurut tanggal cetak PDF.
     * Dipakai membandingkan file baru dengan file lama (aturan 2.7a butir 5).
     * Mengembalikan null bila kelas itu belum pernah diimpor pada periode tersebut.
     */
    public static function terakhir(int $kelasId, int $periodeId): ?self
    {
        return static::where('kelas_id', $kelasId)
            ->where('periode_id', $periodeId)
            ->orderByDesc('dicetak_pada')
            ->orderByDesc('id')   // bila tanggal cetak sama, ambil yang paling akhir dibuat
            ->first();
    }
}
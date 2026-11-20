<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/** Jejak audit: siapa mengubah apa, kapan, nilai lama dan baru. */
class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';

    protected $fillable = ['user_id', 'aksi', 'subjek_tipe', 'subjek_id', 'nilai_lama', 'nilai_baru'];

    /** Kolom JSON otomatis menjadi array saat dibaca dan JSON saat disimpan. */
    protected function casts(): array
    {
        return ['nilai_lama' => 'array', 'nilai_baru' => 'array'];
    }

    /** Pelaku aksi (bisa kosong bila akunnya sudah dihapus). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pintasan untuk mencatat satu aksi.
     * Contoh: LogAktivitas::catat('ubah_jam', $jumlahKompen, ['jam' => 16], ['jam' => 20]);
     * Pelaku diambil dari akun yang sedang login (kosong bila dijalankan dari tinker atau scheduler).
     */
    public static function catat(string $aksi, ?Model $subjek = null, ?array $lama = null, ?array $baru = null): self
    {
        return static::create([
            'user_id'     => Auth::id(),
            'aksi'        => $aksi,
            'subjek_tipe' => $subjek ? $subjek::class : null,
            'subjek_id'   => $subjek?->getKey(),
            'nilai_lama'  => $lama,
            'nilai_baru'  => $baru,
        ]);
    }
}
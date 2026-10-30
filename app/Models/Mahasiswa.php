<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model mahasiswa. */
class Mahasiswa extends Model
{
    protected $table = 'mahasiswa';
    protected $fillable = ['user_id', 'nim', 'nama_mahasiswa', 'kelas_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /** Jam kompensasi wajib per periode. */
    public function jumlahKompen(): HasMany
    {
        return $this->hasMany(JumlahKompen::class, 'mahasiswa_id');
    }

    /** Sesi pengajuan/pelaksanaan kompensasi. */
    public function kompen(): HasMany
    {
        return $this->hasMany(Kompen::class, 'mahasiswa_id');
    }

    /**
     * NIM disamarkan untuk tampilan publik: 4 digit awal + bintang + 2 digit akhir.
     * Contoh: 6304250095 -> 6304****95. NIM pendek disamarkan kecuali karakter pertama.
     */
    public function getNimSamarAttribute(): string
    {
        $nim = (string) $this->nim;
        $panjang = strlen($nim);

        if ($panjang <= 6) {
            return substr($nim, 0, 1) . str_repeat('*', max(0, $panjang - 1));
        }
        return substr($nim, 0, 4) . str_repeat('*', $panjang - 6) . substr($nim, -2);
    }
}
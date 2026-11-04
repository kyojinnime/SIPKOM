<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Model ruang/lokasi kompensasi. */
class Ruang extends Model
{
    protected $table = 'ruang';
    protected $fillable = ['pengawas_id', 'pengawas_kedua_id', 'nama_ruang', 'foto', 'jenis_pekerjaan', 'status_tersedia', 'kuota', 'deskripsi', 'aktif'];
    protected $casts = [
        'jenis_pekerjaan' => 'array',      // JSON <-> array PHP
        'status_tersedia' => 'boolean',
        'aktif' => 'boolean',
        'kuota' => 'integer',
    ];

    public function pengawas(): BelongsTo
    {
        return $this->belongsTo(Pengawas::class, 'pengawas_id');
    }

    /** [BARU] Pengawas kedua: pengganti sementara saat pengawas utama berhalangan (boleh kosong). */
    public function pengawasKedua(): BelongsTo
    {
        return $this->belongsTo(Pengawas::class, 'pengawas_kedua_id');
    }

    public function kompen(): HasMany
    {
        return $this->hasMany(Kompen::class, 'ruang_id');
    }

    /**
     * URL foto ruang. Path berawalan "images/" ada di folder public (data contoh);
     * selain itu dianggap hasil upload di storage/app/public.
     * Mengembalikan null bila belum ada foto, agar tampilan memakai latar cadangan.
     */
    public function getUrlFotoAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }
        return Str::startsWith($this->foto, 'images/') ? asset($this->foto) : asset('storage/' . $this->foto);
    }
}

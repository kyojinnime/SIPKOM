<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Model jam kompensasi wajib per mahasiswa per periode. */
class JumlahKompen extends Model
{
    protected $table = 'jumlah_kompen';
    protected $fillable = ['mahasiswa_id', 'periode_id', 'jumlah_jam', 'keterangan'];

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class, 'periode_id');
    }

    public function kompen(): HasMany
    {
        return $this->hasMany(Kompen::class, 'jumlah_kompen_id');
    }

    /**
     * Menambahkan kolom virtual jam_terlaksana = total jam sesi yang selesai
     * DAN sudah divalidasi pengawas. Sesi yang di-soft-delete tidak ikut dihitung.
     */
    public function scopeDenganJamTerlaksana(Builder $query): Builder
    {
        return $query->withSum(
            ['kompen as jam_terlaksana' => fn ($k) => $k->where('status', 'selesai')->whereNotNull('divalidasi_pada')],
            'jumlah_jam_pelaksanaan'
        );
    }

    /** Sisa jam = jam wajib - jam terlaksana, tidak pernah negatif. */
    public function getSisaJamAttribute(): int
    {
        return max(0, (int) $this->jumlah_jam - (int) ($this->jam_terlaksana ?? 0));
    }

    /** True bila seluruh jam wajib sudah terpenuhi. */
    public function getSelesaiAttribute(): bool
    {
        return $this->sisa_jam === 0;
    }
}
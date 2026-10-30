<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Model pengajuan sekaligus sesi pelaksanaan kompensasi. */
class Kompen extends Model
{
    use SoftDeletes;

    protected $table = 'kompen';
    protected $fillable = [
        'jumlah_kompen_id', 'mahasiswa_id', 'ruang_id', 'status',
        'tgl_kompen', 'jam_mulai', 'jam_selesai', 'jumlah_jam_pelaksanaan',
        'dokumentasi', 'divalidasi_oleh', 'divalidasi_pada',
    ];
    protected $casts = [
        'tgl_kompen'      => 'date',
        'divalidasi_pada' => 'datetime',
    ];

    public function jumlahKompen(): BelongsTo
    {
        return $this->belongsTo(JumlahKompen::class, 'jumlah_kompen_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_id');
    }

    public function pemvalidasi(): BelongsTo
    {
        return $this->belongsTo(Pengawas::class, 'divalidasi_oleh');
    }

    /** Hanya sesi yang selesai dan sudah divalidasi pengawas (boleh tampil publik). */
    public function scopeTervalidasi(Builder $query): Builder
    {
        return $query->where('status', 'selesai')->whereNotNull('divalidasi_pada');
    }

    /** URL foto dokumentasi; aturan path sama dengan foto ruang. */
    public function getUrlDokumentasiAttribute(): ?string
    {
        if (!$this->dokumentasi) {
            return null;
        }
        return Str::startsWith($this->dokumentasi, 'images/')
            ? asset($this->dokumentasi)
            : asset('storage/' . $this->dokumentasi);
    }
}
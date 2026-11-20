<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notifikasi dalam aplikasi: satu baris = satu pesan untuk satu akun. */
class Notifikasi extends Model
{
    // Nama tabel ditulis eksplisit agar Laravel tidak menebak bentuk jamak
    protected $table = 'notifikasi';

    protected $fillable = ['user_id', 'jenis', 'judul', 'isi', 'tautan', 'dibaca_pada'];

    /** Konversi tipe: dibaca_pada menjadi objek tanggal (Carbon). */
    protected function casts(): array
    {
        return ['dibaca_pada' => 'datetime'];
    }

    /** Penerima notifikasi. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Filter: hanya notifikasi milik satu akun. Dipakai agar akun tidak melihat notifikasi orang lain. */
    public function scopeMilik(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Filter: hanya yang belum dibaca (untuk badge lonceng). */
    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->whereNull('dibaca_pada');
    }

    /** Tandai satu notifikasi sudah dibaca. Tidak menimpa waktu bila sudah pernah dibaca. */
    public function tandaiDibaca(): void
    {
        if ($this->dibaca_pada === null) {
            $this->update(['dibaca_pada' => now()]);
        }
    }

    /** True bila sudah dibaca (untuk tampilan daftar). */
    public function getSudahDibacaAttribute(): bool
    {
        return $this->dibaca_pada !== null;
    }
}
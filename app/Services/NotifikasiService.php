<?php

namespace App\Services;

use App\Models\Notifikasi;
use Illuminate\Support\Str;

/**
 * Pembuat notifikasi dalam aplikasi. Dipanggil DI DALAM transaksi pemanggil
 * agar tidak ada perubahan data tanpa notifikasi (atau sebaliknya).
 */
class NotifikasiService
{
    /** Membuat satu notifikasi. Panjang teks dipotong sesuai batas kolom. */
    public function kirim(int $userId, string $jenis, string $judul, ?string $isi = null, ?string $tautan = null): Notifikasi
    {
        return Notifikasi::create([
            'user_id' => $userId,
            'jenis'   => Str::limit($jenis, 50, ''),
            'judul'   => Str::limit($judul, 150, ''),
            'isi'     => $isi,
            'tautan'  => $tautan ? Str::limit($tautan, 255, '') : null,
        ]);
    }

    /**
     * Membuat banyak notifikasi dengan satu query.
     * $daftar = [ ['user_id'=>, 'jenis'=>, 'judul'=>, 'isi'=>, 'tautan'=>], ... ]
     * Hasil: jumlah notifikasi yang dibuat.
     */
    public function kirimBanyak(array $daftar): int
    {
        if (empty($daftar)) {
            return 0;
        }

        $sekarang = now();
        $baris = array_map(fn ($n) => [
            'user_id'     => (int) $n['user_id'],
            'jenis'       => Str::limit($n['jenis'], 50, ''),
            'judul'       => Str::limit($n['judul'], 150, ''),
            'isi'         => $n['isi'] ?? null,
            'tautan'      => isset($n['tautan']) ? Str::limit($n['tautan'], 255, '') : null,
            'dibaca_pada' => null,
            'created_at'  => $sekarang,
            'updated_at'  => $sekarang,
        ], $daftar);

        Notifikasi::insert($baris);   // insert massal tidak mengisi timestamp otomatis, jadi diisi di atas

        return count($baris);
    }

    /** Jumlah notifikasi yang belum dibaca (untuk badge lonceng). */
    public function jumlahBelumDibaca(int $userId): int
    {
        return Notifikasi::milik($userId)->belumDibaca()->count();
    }
}
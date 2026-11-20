<?php

namespace App\Services;

use App\Models\JumlahKompen;
use App\Models\Kompen;
use App\Models\Mahasiswa;

/**
 * Satu-satunya tempat menghitung jam wajib, jam terlaksana, sisa, dan status kompensasi.
 *
 * Aturan:
 * - Jam wajib = jumlah jam semua periode yang TIDAK dibatalkan (B08: sisa terbawa antar periode).
 * - Jam terlaksana = sesi berstatus selesai dan sudah divalidasi, tidak terhapus (soft delete).
 * - Sisa = wajib - terlaksana, tidak pernah negatif.
 */
class KompenHitung
{
    // Kode status yang dipakai di seluruh aplikasi
    public const BELUM_MULAI = 'belum_mulai';
    public const BERJALAN    = 'berjalan';
    public const SELESAI     = 'selesai';
    public const DIBATALKAN  = 'dibatalkan';
    public const TIDAK_ADA   = 'tidak_ada';

    // Teks untuk lencana di tampilan
    private const LABEL = [
        self::BELUM_MULAI => 'Belum Mulai',
        self::BERJALAN    => 'Berjalan',
        self::SELESAI     => 'Selesai',
        self::DIBATALKAN  => 'Dibatalkan',
        self::TIDAK_ADA   => 'Tidak ada kewajiban',
    ];

    /** Teks tampilan untuk satu kode status. */
    public static function label(string $status): string
    {
        return self::LABEL[$status] ?? $status;
    }

    /**
     * Menentukan status dari angka. Fungsi murni (tanpa database) agar mudah diuji.
     *
     * @param int $wajib             total jam wajib dari baris yang tidak dibatalkan
     * @param int $terlaksana        total jam terlaksana
     * @param int $barisAktif        jumlah baris jumlah_kompen yang tidak dibatalkan
     * @param int $barisDibatalkan   jumlah baris jumlah_kompen yang dibatalkan
     */
    public static function tentukanStatus(int $wajib, int $terlaksana, int $barisAktif, int $barisDibatalkan): string
    {
        // Tidak ada baris aktif: semuanya dibatalkan, atau memang belum pernah ada kewajiban
        if ($barisAktif === 0) {
            return $barisDibatalkan > 0 ? self::DIBATALKAN : self::TIDAK_ADA;
        }

        // Ada baris, tetapi jam wajibnya 0 (mis. setelah dikonfirmasi jadi 0) dan belum ada realisasi
        if ($wajib === 0 && $terlaksana === 0) {
            return self::TIDAK_ADA;
        }

        // Sisa tidak pernah negatif
        $sisa = max(0, $wajib - $terlaksana);

        if ($sisa === 0) {
            return self::SELESAI;
        }

        return $terlaksana === 0 ? self::BELUM_MULAI : self::BERJALAN;
    }

    /**
     * Batas bawah jam wajib untuk satu baris jumlah_kompen.
     * Syarat: total jam wajib (semua periode) tidak boleh lebih kecil dari jam terlaksana.
     * Contoh: wajib 16 + 16 = 32, terlaksana 20, baris ini 16 -> minimum 20 - (32 - 16) = 4.
     * Fungsi murni.
     */
    public static function hitungJamMinimum(int $terlaksana, int $wajibTotalAktif, int $jamBarisIni): int
    {
        return max(0, $terlaksana - ($wajibTotalAktif - $jamBarisIni));
    }

    /**
     * Ringkasan banyak mahasiswa sekaligus (dua query saja, tanpa N+1).
     * Hasil: array berkunci id mahasiswa, tiap isinya:
     *   wajib, wajib_periode, terlaksana, sisa, status, label
     *
     * @param int[]    $mahasiswaIds
     * @param int|null $periodeId  bila diisi, wajib_periode = jam wajib pada periode itu saja
     */
    public static function ringkasanBanyak(array $mahasiswaIds, ?int $periodeId = null): array
    {
        $mahasiswaIds = array_values(array_unique(array_map('intval', $mahasiswaIds)));
        if (empty($mahasiswaIds)) {
            return [];
        }

        // Query 1: jam wajib dan jumlah baris per mahasiswa (baris dibatalkan dipisah)
        $wajib = JumlahKompen::whereIn('mahasiswa_id', $mahasiswaIds)
            ->selectRaw(
                'mahasiswa_id,
                 SUM(CASE WHEN dibatalkan_pada IS NULL THEN jumlah_jam ELSE 0 END) AS wajib,
                 SUM(CASE WHEN dibatalkan_pada IS NULL AND periode_id = ? THEN jumlah_jam ELSE 0 END) AS wajib_periode,
                 SUM(CASE WHEN dibatalkan_pada IS NULL THEN 1 ELSE 0 END) AS baris_aktif,
                 SUM(CASE WHEN dibatalkan_pada IS NOT NULL THEN 1 ELSE 0 END) AS baris_batal',
                [$periodeId ?? 0]
            )
            ->groupBy('mahasiswa_id')
            ->get()
            ->keyBy('mahasiswa_id');

        // Query 2: jam terlaksana per mahasiswa (selesai + tervalidasi, soft delete otomatis dikecualikan)
        $terlaksana = Kompen::tervalidasi()
            ->whereIn('mahasiswa_id', $mahasiswaIds)
            ->selectRaw('mahasiswa_id, SUM(jumlah_jam_pelaksanaan) AS jam')
            ->groupBy('mahasiswa_id')
            ->pluck('jam', 'mahasiswa_id');

        // Susun hasil; mahasiswa tanpa data mendapat nilai 0 dan status "tidak ada"
        $hasil = [];
        foreach ($mahasiswaIds as $id) {
            $w = $wajib->get($id);

            $jamWajib   = (int) ($w->wajib ?? 0);
            $jamPeriode = (int) ($w->wajib_periode ?? 0);
            $jamLaksana = (int) ($terlaksana[$id] ?? 0);
            $status     = self::tentukanStatus(
                $jamWajib, $jamLaksana, (int) ($w->baris_aktif ?? 0), (int) ($w->baris_batal ?? 0)
            );

            $hasil[$id] = [
                'wajib'         => $jamWajib,
                'wajib_periode' => $jamPeriode,
                'terlaksana'    => $jamLaksana,
                'sisa'          => max(0, $jamWajib - $jamLaksana),
                'status'        => $status,
                'label'         => self::label($status),
            ];
        }

        return $hasil;
    }

    /** Ringkasan satu mahasiswa (model atau id). */
    public static function ringkasan(Mahasiswa|int $mahasiswa, ?int $periodeId = null): array
    {
        $id = $mahasiswa instanceof Mahasiswa ? $mahasiswa->id : $mahasiswa;

        return self::ringkasanBanyak([$id], $periodeId)[$id];
    }

    /**
     * Batas bawah jam wajib untuk satu baris jumlah_kompen milik mahasiswa.
     * Dipakai import (B06), ubah jam, dan konfirmasi "jadi 0".
     * Baris yang sudah dibatalkan tidak ikut dalam total wajib, jadi jamnya dihitung 0.
     */
    public static function jamMinimum(int $mahasiswaId, JumlahKompen $baris): int
    {
        $r = self::ringkasan($mahasiswaId);

        // Jam baris ini ikut dalam total wajib hanya bila barisnya belum dibatalkan
        $jamBarisIni = $baris->dibatalkan_pada ? 0 : (int) $baris->jumlah_jam;

        return self::hitungJamMinimum($r['terlaksana'], $r['wajib'], $jamBarisIni);
    }
}
<?php

namespace App\Services\Rekap;

/**
 * Singkatan prodi untuk rekap, dibuat dari nama prodi tanpa jenjang.
 *   "D4 - Rekayasa Perangkat Lunak" -> "RPL"
 *   "D3 Teknik Informatika"         -> "TI"
 *   "D4 Keamanan Sistem Informasi"  -> "KSI"
 */
class SingkatanProdi
{
    // Kata sambung yang tidak ikut dalam singkatan
    private const KATA_SAMBUNG = ['dan', 'dari', 'di', 'untuk', 'of', 'and', '&'];

    // Jenjang di awal nama: "D4", "D-3", "S1", "Diploma IV", "Sarjana Terapan"
    private const POLA_JENJANG = '/^\s*(?:D\s*-?\s*[1-4]|S\s*-?\s*[1-3]|Diploma\s+(?:IV|I{1,3}|[1-4])|Sarjana(?:\s+Terapan)?)\b\s*[-–:]*\s*/iu';

    public static function dari(?string $namaProdi): string
    {
        $teks = trim((string) $namaProdi);
        if ($teks === '') {
            return '-';
        }

        // Buang keterangan dalam kurung, mis. "(Reguler)", lalu jenjang di depan
        $bersih = preg_replace('/\([^)]*\)/u', ' ', $teks);
        $bersih = preg_replace(self::POLA_JENJANG, '', $bersih, 1);

        // Pecah menjadi kata, buang kata sambung
        $kata = preg_split('/[^\p{L}\p{N}]+/u', $bersih, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kata = array_values(array_filter($kata, fn ($k) => !in_array(mb_strtolower($k), self::KATA_SAMBUNG, true)));

        // Tidak ada kata tersisa: pakai nama aslinya
        if (empty($kata)) {
            return mb_strtoupper($teks);
        }
        // Satu kata ("TI", "Informatika"): dipakai apa adanya dalam huruf besar
        if (count($kata) === 1) {
            return mb_strtoupper($kata[0]);
        }

        // Huruf pertama tiap kata
        return mb_strtoupper(implode('', array_map(fn ($k) => mb_substr($k, 0, 1), $kata)));
    }
}
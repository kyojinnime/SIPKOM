<?php

namespace App\Services;

use App\Models\Periode;

/**
 * Hitungan semester dan angkatan. Dibuat terpisah dari model Kelas karena
 * Kelas::semester hanya memakai periode aktif, sedangkan daftar berfilter periode
 * dan import data lama butuh hitungan untuk periode mana pun.
 */
class SemesterHelper
{
    /**
     * Angkatan dari NIM: NIM 10 digit, digit ke-5 dan ke-6 = dua digit tahun.
     * Contoh: 6304240058 -> 2024. NIM yang bukan 10 digit angka -> null.
     */
    public static function angkatanDariNim(string $nim): ?int
    {
        $nim = trim($nim);

        // Hanya terima tepat 10 digit angka
        if (!preg_match('/^\d{10}$/', $nim)) {
            return null;
        }

        // substr mulai indeks 4 (digit ke-5), panjang 2
        return 2000 + (int) substr($nim, 4, 2);
    }

    /**
     * Semester perkuliahan pada suatu periode (apa adanya, tanpa dibatasi).
     * Rumus: (tahun mulai tahun ajaran - angkatan) * 2 + (1 ganjil | 2 genap)
     * Contoh: angkatan 2024, genap 2025/2026 -> (2025 - 2024) * 2 + 2 = 4
     */
    public static function hitung(int $angkatan, Periode $periode): int
    {
        return ($periode->tahunMulai() - $angkatan) * 2 + $periode->getSemesterIndex();
    }

    /** Versi untuk tampilan: dibatasi 1 sampai 14, sama dengan aturan di model Kelas. */
    public static function hitungTampil(int $angkatan, Periode $periode): int
    {
        return max(1, min(14, self::hitung($angkatan, $periode)));
    }

    /**
     * Kebalikan rumus di atas: dari semester di PDF, cari angkatannya.
     * Dipakai untuk pemeriksaan silang (aturan 2.7a butir 1).
     * Mengembalikan null bila semester tidak mungkin pada periode itu,
     * mis. semester 3 pada periode genap (semester genap harus 2, 4, 6, ...).
     */
    public static function angkatanDariSemester(int $semester, Periode $periode): ?int
    {
        // Selisih semester terhadap indeks periode (1 ganjil, 2 genap)
        $selisih = $semester - $periode->getSemesterIndex();

        // Harus >= 0 dan genap, karena tiap tahun menambah 2 semester
        if ($semester < 1 || $selisih < 0 || $selisih % 2 !== 0) {
            return null;
        }

        return $periode->tahunMulai() - intdiv($selisih, 2);
    }

    /** True bila angkatan (dari NIM) cocok dengan semester yang tertulis di PDF. */
    public static function semesterCocok(int $angkatan, int $semester, Periode $periode): bool
    {
        return self::angkatanDariSemester($semester, $periode) === $angkatan;
    }
}
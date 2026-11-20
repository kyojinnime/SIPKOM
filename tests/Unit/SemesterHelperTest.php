<?php

namespace Tests\Unit;

use App\Models\Periode;
use App\Services\SemesterHelper;
use Tests\TestCase;

class SemesterHelperTest extends TestCase
{
    /** Periode contoh tanpa menyentuh database (objek belum disimpan). */
    private function periode(string $tahun, string $semester): Periode
    {
        return new Periode(['tahun_ajaran' => $tahun, 'semester' => $semester]);
    }

    public function test_angkatan_dari_nim(): void
    {
        $this->assertSame(2024, SemesterHelper::angkatanDariNim('6304240058'));
        $this->assertSame(2023, SemesterHelper::angkatanDariNim('6304230047'));
        $this->assertNull(SemesterHelper::angkatanDariNim('630424'));      // kurang dari 10 digit
        $this->assertNull(SemesterHelper::angkatanDariNim('63042400AB'));  // bukan angka
    }

    public function test_semester_sesuai_contoh_pdf(): void
    {
        $genap = $this->periode('2025/2026', 'genap');

        $this->assertSame(4, SemesterHelper::hitung(2024, $genap));  // Kelas C
        $this->assertSame(6, SemesterHelper::hitung(2023, $genap));  // Kelas B
        $this->assertSame(3, SemesterHelper::hitung(2025, $this->periode('2026/2027', 'ganjil')));
    }

    public function test_angkatan_dari_semester_dan_pemeriksaan_silang(): void
    {
        $genap = $this->periode('2025/2026', 'genap');

        $this->assertSame(2024, SemesterHelper::angkatanDariSemester(4, $genap));
        $this->assertSame(2023, SemesterHelper::angkatanDariSemester(6, $genap));
        $this->assertNull(SemesterHelper::angkatanDariSemester(3, $genap)); // semester ganjil di periode genap
        $this->assertNull(SemesterHelper::angkatanDariSemester(0, $genap)); // semester tidak valid

        $this->assertTrue(SemesterHelper::semesterCocok(2024, 4, $genap));
        $this->assertFalse(SemesterHelper::semesterCocok(2024, 6, $genap));
    }
}
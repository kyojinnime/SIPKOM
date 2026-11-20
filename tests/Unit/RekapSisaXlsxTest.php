<?php

namespace Tests\Unit;

use App\Services\Rekap\RekapSisaXlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Tests\TestCase;

class RekapSisaXlsxTest extends TestCase
{
    /** Dua baris contoh; baris kedua memuat nama berawalan "=" untuk menguji perlindungan rumus. */
    private function buat(?string $keterangan = null)
    {
        return (new RekapSisaXlsx())->buat([
            ['nim' => '6304240068', 'nama' => 'Arsyah Khairizal', 'prodi' => 'RPL', 'kelas' => '4C', 'dosen' => 'Eva Yumami, S.Kom., M.T.', 'sisa' => 22],
            ['nim' => '6304240072', 'nama' => '=1+1', 'prodi' => 'RPL', 'kelas' => '4C', 'dosen' => '-', 'sisa' => 21],
        ], 'SEMESTER GENAP 2025', $keterangan, '12 November 2026, 10:00')->getActiveSheet();
    }

    public function test_judul_dan_judul_kolom(): void
    {
        $ws = $this->buat();

        $this->assertSame('Sisa Kompensasi', $ws->getTitle());
        $this->assertSame('REKAP JUMLAH SISA KOMPENSASI MAHASISWA', $ws->getCell('A7')->getValue());
        $this->assertSame('SEMESTER GENAP 2025', $ws->getCell('A8')->getValue());

        $judul = ['NO', 'NIM', 'NAMA', 'PRODI', 'KELAS', 'NAMA DOSEN WALI', 'JUMLAH SISA KOMPENSASI'];
        foreach ($judul as $i => $teks) {
            $this->assertSame($teks, $ws->getCell(chr(65 + $i) . '12')->getValue());
        }
    }

    public function test_isi_baris(): void
    {
        $ws = $this->buat();

        $this->assertEquals(1, $ws->getCell('A13')->getValue());
        $this->assertEquals(6304240068, $ws->getCell('B13')->getValue());
        $this->assertSame('Arsyah Khairizal', $ws->getCell('C13')->getValue());
        $this->assertSame('RPL', $ws->getCell('D13')->getValue());
        $this->assertSame('4C', $ws->getCell('E13')->getValue());
        $this->assertSame('Eva Yumami, S.Kom., M.T.', $ws->getCell('F13')->getValue());
        $this->assertEquals(22, $ws->getCell('G13')->getValue());
        $this->assertEquals(2, $ws->getCell('A14')->getValue());
    }

    public function test_nama_berawalan_sama_dengan_bukan_rumus(): void
    {
        $sel = $this->buat()->getCell('C14');

        $this->assertSame('=1+1', $sel->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sel->getDataType());   // tetap teks, tidak dijalankan sebagai rumus
    }

    public function test_keterangan_filter_dan_kop(): void
    {
        $this->assertNull($this->buat()->getCell('A11')->getValue());                 // tanpa filter: baris 11 kosong
        $this->assertSame('KELAS 4C RPL', $this->buat('KELAS 4C RPL')->getCell('A11')->getValue());

        // Teks kop mengikuti config/sipkom.php
        $ws = $this->buat();
        $this->assertSame(config('sipkom.kop.baris.1'), $ws->getCell('B2')->getValue());
        $this->assertTrue($ws->getMergeCells() !== [] && isset($ws->getMergeCells()['A7:G7']));
    }

    public function test_pengaturan_cetak(): void
    {
        $ws = $this->buat();

        $this->assertSame(PageSetup::PAPERSIZE_A4, $ws->getPageSetup()->getPaperSize());
        $this->assertSame(PageSetup::ORIENTATION_PORTRAIT, $ws->getPageSetup()->getOrientation());
        $this->assertTrue($ws->getPageSetup()->getFitToPage());   // lebar muat satu halaman
    }
}
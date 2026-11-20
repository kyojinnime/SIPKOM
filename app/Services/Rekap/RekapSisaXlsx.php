<?php

namespace App\Services\Rekap;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Membuat berkas Excel rekap sisa kompensasi yang meniru contoh klien:
 * kop dan logo (baris 1-6), judul (7-10), keterangan filter (11), judul kolom hijau (12), data (13 dst).
 * Kelas ini murni: tidak mengakses database, hanya menerima data yang sudah siap.
 */
class RekapSisaXlsx
{
    private const FONT = 'Times New Roman';
    private const BARIS_HEADER = 12;
    private const BARIS_MULAI = 13;
    private const WARNA_HEADER = 'C3D69B';   // hijau muda, sama dengan contoh klien

    // Lebar kolom A sampai G, sama dengan contoh klien
    private const LEBAR = ['A' => 5.5, 'B' => 15, 'C' => 36.4, 'D' => 9.5, 'E' => 9, 'F' => 39.9, 'G' => 14.5];

    private const JUDUL_KOLOM = ['NO', 'NIM', 'NAMA', 'PRODI', 'KELAS', 'NAMA DOSEN WALI', 'JUMLAH SISA KOMPENSASI'];

    /**
     * @param array       $baris         [['nim','nama','prodi','kelas','dosen','sisa'], ...] sudah terurut
     * @param string      $judulSemester mis. "SEMESTER GENAP 2025"
     * @param string|null $keterangan    mis. "KELAS 4C RPL"; null = baris 11 dikosongkan
     * @param string      $tanggalCetak  untuk catatan kaki cetak
     */
    public function buat(array $baris, string $judulSemester, ?string $keterangan, string $tanggalCetak): Spreadsheet
    {
        $wb = new Spreadsheet();
        $wb->getProperties()->setCreator('SIPKOM')->setTitle('Rekap Jumlah Sisa Kompensasi Mahasiswa');
        $wb->getDefaultStyle()->getFont()->setName(self::FONT)->setSize(11);

        $ws = $wb->getActiveSheet();
        $ws->setTitle('Sisa Kompensasi');

        foreach (self::LEBAR as $kolom => $lebar) {
            $ws->getColumnDimension($kolom)->setWidth($lebar);
        }

        $this->kop($ws);
        $this->judul($ws, $judulSemester, $keterangan);
        $this->headerKolom($ws);
        $terakhir = $this->isi($ws, $baris);
        $this->aturCetak($ws, $terakhir, $tanggalCetak);

        return $wb;
    }

    /** Kop surat: lima baris teks di B:G, garis ganda di bawahnya, dan logo di kiri. Teks dari config('sipkom.kop'). */
    private function kop(Worksheet $ws): void
    {
        $teks = array_slice(array_values((array) config('sipkom.kop.baris', [])), 0, 5);

        foreach ($teks as $i => $isi) {
            $r = $i + 1;
            $ws->mergeCells("B{$r}:G{$r}");
            $ws->setCellValueExplicit("B{$r}", (string) $isi, DataType::TYPE_STRING);
            // Baris kedua (nama institusi) lebih besar dan tebal, seperti contoh
            $ws->getStyle("B{$r}")->getFont()->setSize($r === 2 ? 14 : 11)->setBold($r === 2);
            $ws->getStyle("B{$r}:G{$r}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            $ws->getRowDimension($r)->setRowHeight($r === 2 ? 18 : 15);
        }

        // Garis ganda di bawah kop (pengganti garis bentuk pada contoh; PhpSpreadsheet tidak mendukung bentuk)
        $ws->getStyle('A5:G5')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE);
        $ws->getRowDimension(6)->setRowHeight(17);

        // Logo: dilewati bila berkasnya tidak ada, supaya rekap tetap jadi
        $jalur = public_path((string) config('sipkom.kop.logo', ''));
        if (is_file($jalur)) {
            $logo = new Drawing();
            $logo->setName('Logo');
            $logo->setPath($jalur);
            $logo->setCoordinates('A1');
            $logo->setOffsetX(8);
            $logo->setOffsetY(3);
            $logo->setHeight(92);   // lebar ikut menyesuaikan (proporsi tetap)
            $logo->setWorksheet($ws);
        }
    }

    /** Judul tengah (baris 7-10) dan keterangan filter (baris 11, hanya bila ada). */
    private function judul(Worksheet $ws, string $judulSemester, ?string $keterangan): void
    {
        $baris = [
            7  => 'REKAP JUMLAH SISA KOMPENSASI MAHASISWA',
            8  => $judulSemester,
            9  => (string) config('sipkom.kop.jurusan', 'JURUSAN TEKNIK INFORMATIKA'),
            10 => (string) config('sipkom.kop.institusi', 'POLITEKNIK NEGERI BENGKALIS'),
        ];

        foreach ($baris as $r => $isi) {
            $ws->mergeCells("A{$r}:G{$r}");
            $ws->setCellValueExplicit("A{$r}", $isi, DataType::TYPE_STRING);
            $ws->getStyle("A{$r}")->getFont()->setBold(true);
            $ws->getStyle("A{$r}:G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $ws->getRowDimension(10)->setRowHeight(17.25);

        if ($keterangan !== null && $keterangan !== '') {
            $ws->mergeCells('A11:G11');
            $ws->setCellValueExplicit('A11', $keterangan, DataType::TYPE_STRING);
            $ws->getStyle('A11')->getFont()->setSize(10)->setItalic(true);
            $ws->getStyle('A11:G11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $ws->getRowDimension(11)->setRowHeight(15);
    }

    /** Judul kolom (baris 12): tebal, tengah, berlatar hijau, bergaris. */
    private function headerKolom(Worksheet $ws): void
    {
        $r = self::BARIS_HEADER;

        foreach (self::JUDUL_KOLOM as $i => $isi) {
            $ws->setCellValueExplicit(chr(65 + $i) . $r, $isi, DataType::TYPE_STRING);
        }

        $gaya = $ws->getStyle("A{$r}:G{$r}");
        $gaya->getFont()->setBold(true);
        $gaya->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $gaya->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::WARNA_HEADER);

        $ws->getRowDimension($r)->setRowHeight(36);
    }

    /** Baris data. Mengembalikan nomor baris terakhir. Semua teks ditulis sebagai TEKS (bukan rumus). */
    private function isi(Worksheet $ws, array $baris): int
    {
        $mulai = self::BARIS_MULAI;
        $r = $mulai;

        foreach (array_values($baris) as $i => $b) {
            $ws->setCellValue("A{$r}", $i + 1);

            // NIM sebagai angka (seperti contoh klien) bila aman; selain itu sebagai teks
            if (preg_match('/^[1-9]\d{0,14}$/', (string) $b['nim'])) {
                $ws->setCellValue("B{$r}", (float) $b['nim']);
            } else {
                $ws->setCellValueExplicit("B{$r}", (string) $b['nim'], DataType::TYPE_STRING);
            }

            // Teks dari database ditulis eksplisit sebagai string: nilai berawalan "=" tidak akan dijalankan sebagai rumus
            $ws->setCellValueExplicit("C{$r}", (string) $b['nama'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit("D{$r}", (string) $b['prodi'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit("E{$r}", (string) $b['kelas'], DataType::TYPE_STRING);
            $ws->setCellValueExplicit("F{$r}", (string) $b['dosen'], DataType::TYPE_STRING);
            $ws->setCellValue("G{$r}", (int) $b['sisa']);

            $ws->getRowDimension($r)->setRowHeight(20);
            $r++;
        }

        $akhir = $r - 1;
        if ($akhir < $mulai) {
            return self::BARIS_HEADER;   // tanpa data (tidak terjadi lewat controller, yang menolak data kosong)
        }

        // Gaya blok data: huruf 10, garis tipis, rata tengah vertikal
        $blok = "A{$mulai}:G{$akhir}";
        $ws->getStyle($blok)->getFont()->setSize(10);
        $ws->getStyle($blok)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle($blok)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        // Kolom pendek di tengah; nama dan dosen wali rata kiri dengan pembungkus baris
        foreach (['A', 'B', 'D', 'E', 'G'] as $kolom) {
            $ws->getStyle("{$kolom}{$mulai}:{$kolom}{$akhir}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach (['C', 'F'] as $kolom) {
            $ws->getStyle("{$kolom}{$mulai}:{$kolom}{$akhir}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        }
        $ws->getStyle("C{$mulai}:C{$akhir}")->getFont()->setSize(11);       // nama sedikit lebih besar, seperti contoh
        $ws->getStyle("B{$mulai}:B{$akhir}")->getNumberFormat()->setFormatCode('0');

        return $akhir;
    }

    /** Pengaturan cetak: A4 tegak, lebar muat satu halaman, judul kolom diulang, nomor halaman di kaki. */
    private function aturCetak(Worksheet $ws, int $terakhir, string $tanggalCetak): void
    {
        $ps = $ws->getPageSetup();
        $ps->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
        $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ps->setFitToWidth(1);
        $ps->setFitToHeight(0);   // tinggi bebas: berapa pun halamannya
        $ps->setHorizontalCentered(true);
        $ps->setPrintArea("A1:G{$terakhir}");
        $ps->setRowsToRepeatAtTopByStartAndEnd(self::BARIS_HEADER, self::BARIS_HEADER);
        // Pengaktifan "muat satu halaman" ada di PageSetup. setFitToWidth/Height di atas sebenarnya sudah
        // mengaktifkannya otomatis; baris ini hanya menegaskan.
        $ps->setFitToPage(true);

        // Margin mengikuti contoh klien (inci)
        $ws->getPageMargins()->setLeft(0.315)->setRight(0.315)->setTop(0.709)->setBottom(0.433)
            ->setHeader(0.3)->setFooter(0.2);

        $font = '&"' . self::FONT . ',Regular"&8';
        $ws->getHeaderFooter()->setOddFooter("&L{$font}Dicetak: {$tanggalCetak}&R{$font}Halaman &P dari &N");
    }
}
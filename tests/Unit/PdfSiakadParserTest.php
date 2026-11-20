<?php

namespace Tests\Unit;

use App\Services\Impor\PdfSiakadParser;
use App\Services\Impor\PdfTidakValidException;
use Tests\TestCase;

class PdfSiakadParserTest extends TestCase
{
    /** Jalankan parser pada PDF contoh. Dilewati bila fixture belum disalin. */
    private function hasil(): array
    {
        $path = base_path('tests/Fixtures/laporan-evaluasi.pdf');
        if (!is_file($path)) {
            $this->markTestSkipped('Salin PDF contoh ke tests/Fixtures/laporan-evaluasi.pdf');
        }
        return (new PdfSiakadParser())->parse($path);
    }

    /** Ubah blok menjadi peta nim => baris agar mudah diperiksa. */
    private function petaNim(array $blok): array
    {
        $peta = [];
        foreach ($blok['baris'] as $r) {
            $peta[$r['nim']] = $r;
        }
        return $peta;
    }

    public function test_identitas_dua_blok_kelas(): void
    {
        $h = $this->hasil();

        $this->assertCount(2, $h['blok']);

        // Blok 1: Kelas C, semester 4
        $this->assertSame(4, $h['blok'][0]['semester']);
        $this->assertSame('C', $h['blok'][0]['kelas']);
        $this->assertStringContainsString('Rekayasa', $h['blok'][0]['prodi']);
        $this->assertStringContainsString('YUMAMI', $h['blok'][0]['dpa']);
        $this->assertSame(23, $h['blok'][0]['jumlah_dibaca']);   // 23 baris mahasiswa, termasuk yang tanpa A

        // Blok 2: Kelas B, semester 6
        $this->assertSame(6, $h['blok'][1]['semester']);
        $this->assertSame('B', $h['blok'][1]['kelas']);
        $this->assertSame(31, $h['blok'][1]['jumlah_dibaca']);
    }

    public function test_tabel_penerimaan_10_mahasiswa_199_jam(): void
    {
        $h = $this->hasil();

        // Nilai A yang benar untuk tiap NIM (bagian 8.5 dokumen konteks)
        $harapan = [
            0 => ['6304240061' => 5.0, '6304240063' => 5.0, '6304240068' => 22.0, '6304240072' => 21.0,
                  '6304240077' => 30.0, '6304240079' => 5.0, '6304240083' => 5.0],
            1 => ['6304230040' => 5.0, '6304230047' => 50.0, '6304230063' => 51.0],
        ];

        $total = 0.0;
        foreach ($harapan as $i => $daftar) {
            $peta = $this->petaNim($h['blok'][$i]);
            // Jumlah mahasiswa dan NIM-nya harus persis
            $this->assertEqualsCanonicalizing(array_keys($daftar), array_keys($peta));
            foreach ($daftar as $nim => $a) {
                $this->assertSame($a, $peta[$nim]['a'], "A untuk {$nim}");
                $total += $peta[$nim]['a'];
            }
        }
        $this->assertSame(199.0, $total);
    }

    public function test_sp_terbaca(): void
    {
        $h = $this->hasil();
        $c = $this->petaNim($h['blok'][0]);
        $b = $this->petaNim($h['blok'][1]);

        $this->assertSame(1, $c['6304240068']['sp']);
        $this->assertSame(1, $c['6304240072']['sp']);
        $this->assertSame(1, $c['6304240077']['sp']);
        $this->assertNull($c['6304240061']['sp']);   // 5 jam tanpa SP
        $this->assertSame(3, $b['6304230047']['sp']);
        $this->assertSame(3, $b['6304230063']['sp']);
    }

    public function test_footer_terbaca(): void
    {
        $meta = $this->hasil()['meta'];

        $this->assertSame('2026-04-17 16:04:39', $meta['dicetak_pada']->format('Y-m-d H:i:s'));
        $this->assertSame('20252', $meta['id_semester']);
        $this->assertSame('198904182022032008', $meta['id_wali']);
    }

    /** Dipisah dari uji lain: gagal di sini berarti spasi antar kata hilang, bukan kolom A salah. */
    public function test_nama_bertingkat_dan_spasi(): void
    {
        $h = $this->hasil();
        $c = $this->petaNim($h['blok'][0]);

        $this->assertSame('Muthohhirin Hakimi', $c['6304240061']['nama']);
        $this->assertSame('ARSYAH KHAIRIZAL', $c['6304240068']['nama']);          // dua baris
        $this->assertSame('MUHAMMAD IQBAL SYAHBANI', $c['6304240077']['nama']);   // dua baris
        $this->assertSame('MUHAMAD ZAHRAN ANUGRAH', $c['6304240079']['nama']);    // tiga baris
    }

    public function test_file_bukan_pdf_ditolak_dengan_pesan_ramah(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bukan_pdf_');
        file_put_contents($tmp, 'ini hanya teks biasa');

        $this->expectException(PdfTidakValidException::class);
        try {
            (new PdfSiakadParser())->parse($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    
    /** Format SIAKAD kedua (tiap huruf satu fragmen, ada kolom Total Evaluasi). Dilewati bila fixture belum disalin. */
    private function hasilKedua(): array
    {
        $path = base_path('tests/Fixtures/laporan-evaluasi-2.pdf');
        if (!is_file($path)) {
            $this->markTestSkipped('Salin PDF contoh kedua ke tests/Fixtures/laporan-evaluasi-2.pdf');
        }
        return (new PdfSiakadParser())->parse($path);
    }

    public function test_format_kedua_identitas_tiga_blok(): void
    {
        $h = $this->hasilKedua();

        $this->assertCount(3, $h['blok']);
        $this->assertSame(['A', 'B', 'C'], array_column($h['blok'], 'kelas'));
        $this->assertSame([20, 22, 20], array_column($h['blok'], 'jumlah_dibaca'));
        $this->assertSame('D3 - Teknik Informatika', $h['blok'][0]['prodi']);
        $this->assertSame(3, $h['blok'][0]['semester']);
        $this->assertSame('SRI MAWARNI, S.Si., M.Si.', $h['blok'][0]['dpa']);
        $this->assertSame('Miftahul Jannah, S.Pd., M.Kom.', $h['blok'][1]['dpa']);   // DPA berbaris dua
        $this->assertSame('2026-11-12 10:16:51', $h['meta']['dicetak_pada']->format('Y-m-d H:i:s'));
        $this->assertSame('20261', $h['meta']['id_semester']);
        $this->assertNull($h['meta']['id_wali']);   // alamat SIAKAD pada file ini tidak memuat id_wali
    }

    public function test_format_kedua_tabel_penerimaan_11_mahasiswa_100_jam(): void
    {
        $h = $this->hasilKedua();

        // Nilai kolom A yang benar, dicocokkan dengan kolom "Total SIA ... Jam" di PDF
        $harapan = [
            0 => ['6103250001' => 5, '6103250016' => 7, '6103250019' => 24, '6103250034' => 2,
                  '6103250037' => 2, '6103250064' => 37, '6103250067' => 2],
            1 => ['6103250017' => 8, '6103250059' => 3],
            2 => ['6103250036' => 8, '6103250066' => 2],
        ];

        $total = 0.0;
        foreach ($harapan as $i => $daftar) {
            $peta = $this->petaNim($h['blok'][$i]);
            $this->assertEqualsCanonicalizing(array_keys($daftar), array_keys($peta));
            foreach ($daftar as $nim => $a) {
                $this->assertSame((float) $a, $peta[$nim]['a'], "A untuk {$nim}");
                $total += $peta[$nim]['a'];
            }
        }
        $this->assertSame(100.0, $total);

        // SP dan nama berbaris dua
        $kelasA = $this->petaNim($h['blok'][0]);
        $this->assertSame(1, $kelasA['6103250019']['sp']);
        $this->assertSame(2, $kelasA['6103250064']['sp']);
        $this->assertSame('MHD. FARIJH ANANDA CAHYAWITA', $kelasA['6103250019']['nama']);
    }
}
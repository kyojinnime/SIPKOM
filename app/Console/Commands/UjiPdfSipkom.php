<?php

namespace App\Console\Commands;

use App\Services\Impor\PdfSiakadParser;
use App\Services\Impor\PdfTidakValidException;
use Illuminate\Console\Command;

/**
 * Alat diagnosis: menampilkan hasil PdfSiakadParser untuk satu PDF.
 * Dipakai saat SIAKAD mengubah format PDF dan import menolak file.
 */
class UjiPdfSipkom extends Command
{
    protected $signature = 'sipkom:uji-pdf {berkas? : Path PDF. Bawaan: tests/Fixtures/laporan-evaluasi.pdf}';
    protected $description = 'Uji PdfSiakadParser pada satu PDF (sementara)';

    public function handle(PdfSiakadParser $parser): int
    {
        // Tentukan file: argumen bila ada, bila tidak pakai fixture uji
        $path = $this->argument('berkas') ?: base_path('tests/Fixtures/laporan-evaluasi.pdf');
        if (!is_file($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return self::FAILURE;
        }

        // Jalankan parser; galat ramah ditampilkan apa adanya
        try {
            $hasil = $parser->parse($path);
        } catch (PdfTidakValidException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        // Metadata footer
        $meta = $hasil['meta'];
        $this->info('Dicetak pada : ' . ($meta['dicetak_pada']?->format('Y-m-d H:i:s') ?? '(tidak terbaca)'));
        $this->info('id_semester  : ' . ($meta['id_semester'] ?? '(tidak terbaca)'));
        $this->info('id_wali      : ' . ($meta['id_wali'] ?? '(tidak terbaca)'));

        // Per blok kelas: identitas, tabel mahasiswa, subtotal
        $totalMhs = 0;
        $totalJam = 0.0;
        foreach ($hasil['blok'] as $i => $b) {
            $this->newLine();
            $this->line(sprintf(
                'BLOK %d | prodi: %s | semester: %s | kelas: %s | DPA: %s | NIDN: %s | baris dibaca: %d',
                $i + 1, $b['prodi'], $b['semester'], $b['kelas'], $b['dpa'], $b['nidn'], $b['jumlah_dibaca']
            ));

            $this->table(
                ['NIM', 'Nama', 'A', 'SP'],
                array_map(fn ($r) => [$r['nim'], $r['nama'], $r['a'], $r['sp'] ?? '-'], $b['baris'])
            );

            $jam = array_sum(array_column($b['baris'], 'a'));
            $this->line('Subtotal: ' . count($b['baris']) . " mahasiswa, {$jam} jam");
            $totalMhs += count($b['baris']);
            $totalJam += $jam;
        }

        $this->newLine();
        $this->info("TOTAL: {$totalMhs} mahasiswa, {$totalJam} jam   (harapan: 10 mahasiswa, 199 jam)");

        return self::SUCCESS;
    }
}
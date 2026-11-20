<?php

namespace Tests\Feature;

use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\PenugasanDosenWali;
use App\Models\Prodi;
use App\Models\RiwayatImpor;
use App\Models\User;
use App\Services\Impor\ImportKompenService;
use App\Services\Impor\PdfSiakadParser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SiapkanData;
use Tests\TestCase;

/** Penyimpanan hasil import: data benar, tidak ganda, tidak mereset password, aturan tanggal cetak dan jam terlaksana. */
class ImportKompenServiceTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private const BERKAS = 'tests/Fixtures/laporan-evaluasi.pdf';

    /** Data dasar + hasil baca PDF contoh. Dilewati bila fixture belum disalin. */
    private function siapkan(): array
    {
        if (!is_file(base_path(self::BERKAS))) {
            $this->markTestSkipped('Salin PDF contoh ke ' . self::BERKAS);
        }

        $periode = $this->buatPeriode('2025/2026', 'genap', true);
        Prodi::forceCreate(['nama_prodi' => 'D4 Rekayasa Perangkat Lunak']);
        $admin = $this->buatAkun('admin', 'admin_uji');
        $eva = $this->buatDosen('Eva Yumami', 'eva_uji');
        $hasil = app(PdfSiakadParser::class)->parse(base_path(self::BERKAS));

        return [$periode, $admin, $eva, $hasil];
    }

    public function test_import_membuat_kelas_mahasiswa_akun_dan_jam_wajib(): void
    {
        [$periode, $admin, $eva, $hasil] = $this->siapkan();

        $r = app(ImportKompenService::class)->simpan($hasil, $periode, $admin, 'uji.pdf', 'hash1');

        $this->assertSame(10, $r['total']['disimpan']);
        $this->assertSame(0, $r['total']['ditolak']);
        $this->assertSame(199, $r['total']['jam']);
        $this->assertSame(2, Kelas::count());
        $this->assertSame(10, Mahasiswa::count());
        $this->assertSame(10, User::where('role', 'mahasiswa')->count());
        $this->assertSame(199, (int) JumlahKompen::where('periode_id', $periode->id)->sum('jumlah_jam'));

        // Dosen wali terdaftar (nama cocok tanpa gelar) otomatis tertaut ke kedua kelas
        $this->assertSame(2, PenugasanDosenWali::where('dosen_wali_id', $eva->id)->where('periode_id', $periode->id)->count());
        $this->assertSame(2, RiwayatImpor::count());
    }

    public function test_import_ulang_tidak_menggandakan_data_dan_tidak_mereset_password(): void
    {
        [$periode, $admin, , $hasil] = $this->siapkan();
        $layanan = app(ImportKompenService::class);
        $layanan->simpan($hasil, $periode, $admin, 'uji.pdf', 'hash1');

        // Satu mahasiswa sudah mengganti password awalnya
        $akun = User::where('username', '6304240068')->first();
        $akun->update(['must_change_password' => false]);

        $r = $layanan->simpan($hasil, $periode, $admin, 'uji.pdf', 'hash1');   // file yang sama

        $this->assertSame(10, $r['total']['tidak_berubah']);
        $this->assertSame(0, $r['total']['akun_baru']);
        $this->assertSame(10, Mahasiswa::count());
        $this->assertSame(10, User::where('role', 'mahasiswa')->count());
        $this->assertFalse((bool) $akun->fresh()->must_change_password);        // tidak direset
    }

    public function test_file_lebih_lama_ditolak_untuk_dosen_wali_dan_butuh_centang_timpa_untuk_admin(): void
    {
        [$periode, $admin, $eva, $hasil] = $this->siapkan();
        $layanan = app(ImportKompenService::class);
        $layanan->simpan($hasil, $periode, $admin, 'baru.pdf', 'hash-baru');   // dicetak 17 April 2026

        $lama = $hasil;
        $lama['meta']['dicetak_pada'] = Carbon::create(2026, 3, 1, 10, 0, 0, 'Asia/Jakarta');

        // Dosen wali: kedua blok ditolak, tidak ada yang tersimpan
        $r = $layanan->simpan($lama, $periode, $eva->user, 'lama.pdf', 'hash-lama');
        $this->assertSame(0, $r['total']['disimpan']);
        $this->assertSame(['ditolak', 'ditolak'], array_column($r['blok'], 'status'));

        // Admin tanpa centang "timpa": dilewati. Dengan centang untuk kedua blok: disimpan.
        $r = $layanan->simpan($lama, $periode, $admin, 'lama.pdf', 'hash-lama');
        $this->assertSame(['dilewati', 'dilewati'], array_column($r['blok'], 'status'));

        $r = $layanan->simpan($lama, $periode, $admin, 'lama.pdf', 'hash-lama', [0, 1]);
        $this->assertSame(10, $r['total']['disimpan']);
    }

    public function test_jam_baru_tidak_boleh_di_bawah_jam_terlaksana(): void
    {
        [$periode, $admin, , $hasil] = $this->siapkan();
        $layanan = app(ImportKompenService::class);
        $layanan->simpan($hasil, $periode, $admin, 'uji.pdf', 'hash1');

        // Andri Saputra (21 jam) sudah melaksanakan 10 jam
        $baris = JumlahKompen::where('mahasiswa_id', Mahasiswa::where('nim', '6304240072')->value('id'))->first();
        $this->buatRealisasi($baris, 10);

        // File yang lebih baru menurunkan jamnya menjadi 5
        $baru = $hasil;
        foreach ($baru['blok'] as $i => $blok) {
            foreach ($blok['baris'] as $j => $mhs) {
                if ($mhs['nim'] === '6304240072') {
                    $baru['blok'][$i]['baris'][$j]['a'] = 5.0;
                }
            }
        }
        $baru['meta']['dicetak_pada'] = Carbon::create(2026, 5, 1, 10, 0, 0, 'Asia/Jakarta');

        $r = $layanan->simpan($baru, $periode, $admin, 'baru.pdf', 'hash-baru');

        $this->assertSame(1, $r['total']['ditolak']);
        $this->assertSame('6304240072', $r['galat'][0]['nim']);
        $this->assertSame(21, (int) $baris->fresh()->jumlah_jam);   // jam lama tidak berubah
    }
}
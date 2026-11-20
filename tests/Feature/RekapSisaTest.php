<?php

namespace Tests\Feature;

use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\Periode;
use App\Models\User;
use App\Services\Rekap\RekapSisaData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SiapkanData;
use Tests\TestCase;

class RekapSisaTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    private Periode $periode;
    private User $admin;
    private DosenWali $eva;

    protected function setUp(): void
    {
        parent::setUp();

        $this->periode = $this->buatPeriode();   // Genap 2025/2026
        $this->admin = $this->buatAkun('admin', 'admin_uji');
        $this->eva = $this->buatDosen('Eva Yumami', 'eva_uji');

        $kelasC = $this->buatKelas($this->periode, $this->eva, 'C', 2024);   // semester 4
        $kelasA = $this->buatKelas($this->periode, null, 'A', 2023);          // semester 6, tanpa dosen wali

        $this->buatMahasiswa($kelasC, '6304240001', 'ARSYAH KHAIRIZAL', $this->periode, 16);
        $selesai = $this->buatMahasiswa($kelasC, '6304240002', 'SUDAH SELESAI', $this->periode, 5);
        $this->buatRealisasi(JumlahKompen::where('mahasiswa_id', $selesai->id)->first(), 5);   // sisa 0: tidak tampil
        $this->buatMahasiswa($kelasA, '6304230003', 'ANDRI SAPUTRA', $this->periode, 8);
    }

    public function test_hanya_sisa_lebih_dari_nol_terurut_dan_terformat(): void
    {
        $h = app(RekapSisaData::class)->ambil($this->admin, $this->periode, null, null);

        // Urut: semester 4 (kelas C) lebih dulu dari semester 6 (kelas A); mahasiswa yang sudah selesai tidak ada
        $this->assertSame(['6304240001', '6304230003'], array_column($h['baris'], 'nim'));
        $this->assertSame(['4C', '6A'], array_column($h['baris'], 'kelas'));
        $this->assertSame(['RPL', 'RPL'], array_column($h['baris'], 'prodi'));
        $this->assertSame(['Eva Yumami', '-'], array_column($h['baris'], 'dosen'));   // kelas tanpa dosen wali: strip
        $this->assertSame([16, 8], array_column($h['baris'], 'sisa'));
        $this->assertSame('Arsyah Khairizal', $h['baris'][0]['nama']);                // huruf besar semua dirapikan
        $this->assertNull($h['keterangan']);
    }

    public function test_dosen_wali_hanya_mendapat_kelasnya(): void
    {
        $h = app(RekapSisaData::class)->ambil($this->eva->user, $this->periode, null, null);

        $this->assertSame(['6304240001'], array_column($h['baris'], 'nim'));
        $this->assertSame('DOSEN WALI: Eva Yumami', $h['keterangan']);
    }

    public function test_unduh_menghasilkan_berkas_excel(): void
    {
        $respons = $this->actingAs($this->admin)->get(route('kompen.rekap', ['periode' => $this->periode->id]));

        $respons->assertOk()->assertDownload('rekap-sisa-kompensasi-genap-2025.xlsx');
        $this->assertStringStartsWith('PK', $respons->streamedContent());   // berkas .xlsx adalah arsip zip
    }

    public function test_pilihan_tanpa_data_kembali_dengan_pesan(): void
    {
        $kosong = $this->buatDosen('Dosen Kosong', 'kosong_uji');   // tidak memegang kelas

        $this->actingAs($kosong->user)
            ->get(route('kompen.rekap', ['periode' => $this->periode->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('rekap');
    }
}
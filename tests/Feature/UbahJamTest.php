<?php

namespace Tests\Feature;

use App\Models\JumlahKompen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SiapkanData;
use Tests\TestCase;

/** Aturan ubah jam wajib dan pembatalan kewajiban. */
class UbahJamTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_jam_tidak_boleh_di_bawah_jam_terlaksana_dan_pembatalan_wajib_beralasan(): void
    {
        $periode = $this->buatPeriode();
        $admin = $this->buatAkun('admin', 'admin_uji');
        $mhs = $this->buatMahasiswa($this->buatKelas($periode, null), '6304240001', 'MAHASISWA SATU', $periode, 22);
        $baris = JumlahKompen::where('mahasiswa_id', $mhs->id)->first();
        $this->buatRealisasi($baris, 10);   // 10 dari 22 jam sudah terlaksana

        $this->actingAs($admin);

        // Di bawah jam terlaksana: ditolak. Tepat sama dengan jam terlaksana: boleh.
        $this->putJson(route('kompen.ubah', $baris->id), ['jumlah_jam' => 8])
            ->assertStatus(422)->assertJsonValidationErrors('jumlah_jam');
        $this->putJson(route('kompen.ubah', $baris->id), ['jumlah_jam' => 10])->assertOk();
        $this->assertSame(10, (int) $baris->fresh()->jumlah_jam);

        // Pembatalan tanpa alasan ditolak
        $this->patchJson(route('kompen.batal', $baris->id), [])
            ->assertStatus(422)->assertJsonValidationErrors('alasan');

        // Dengan alasan: berhasil, data tidak dihapus
        $this->patchJson(route('kompen.batal', $baris->id), ['alasan' => 'surat izin diterima'])->assertOk();
        $this->assertNotNull($baris->fresh()->dibatalkan_pada);
        $this->assertSame('surat izin diterima', $baris->fresh()->alasan_batal);

        // Kewajiban yang sudah dibatalkan tidak bisa diubah lagi
        $this->putJson(route('kompen.ubah', $baris->id), ['jumlah_jam' => 12])->assertStatus(422);
    }
}
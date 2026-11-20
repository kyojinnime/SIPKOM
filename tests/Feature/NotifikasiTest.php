<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SiapkanData;
use Tests\TestCase;

class NotifikasiTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_notifikasi_milik_akun_lain_menghasilkan_404(): void
    {
        $pemilik = $this->buatAkun('admin', 'admin_uji');
        $lain = $this->buatAkun('dosen_wali', 'dosen_uji');
        $n = Notifikasi::create(['user_id' => $pemilik->id, 'jenis' => 'uji', 'judul' => 'Milik admin', 'tautan' => '/data-kompen']);

        $this->actingAs($lain);
        $this->get(route('notifikasi.buka', $n->id))->assertNotFound();
        $this->postJson(route('notifikasi.dibaca', $n->id))->assertNotFound();

        $this->assertNull($n->fresh()->dibaca_pada);   // tidak ikut tertandai
    }

    public function test_buka_menandai_dibaca_dan_hanya_menuju_tautan_aman(): void
    {
        $akun = $this->buatAkun('admin', 'admin_uji');
        $dalam = Notifikasi::create(['user_id' => $akun->id, 'jenis' => 'uji', 'judul' => 'Dalam', 'tautan' => '/data-kompen']);
        $luar = Notifikasi::create(['user_id' => $akun->id, 'jenis' => 'uji', 'judul' => 'Luar', 'tautan' => 'https://situs-lain.example/x']);

        $this->actingAs($akun);

        $this->get(route('notifikasi.buka', $dalam->id))->assertRedirect('/data-kompen');
        $this->assertNotNull($dalam->fresh()->dibaca_pada);

        // Tautan ke situs lain dialihkan ke halaman Notifikasi
        $this->get(route('notifikasi.buka', $luar->id))->assertRedirect(route('notifikasi.index'));
    }

    public function test_ringkas_dan_tandai_semua_dibaca(): void
    {
        $akun = $this->buatAkun('admin', 'admin_uji');
        Notifikasi::create(['user_id' => $akun->id, 'jenis' => 'uji', 'judul' => 'Satu']);
        Notifikasi::create(['user_id' => $akun->id, 'jenis' => 'uji', 'judul' => 'Dua']);

        $this->actingAs($akun);

        $this->getJson(route('notifikasi.ringkas'))->assertOk()->assertJson(['jumlah' => 2]);
        $this->postJson(route('notifikasi.dibaca_semua'))->assertOk();
        $this->getJson(route('notifikasi.ringkas'))->assertJson(['jumlah' => 0]);
    }
}
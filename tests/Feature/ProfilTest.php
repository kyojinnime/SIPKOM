<?php

namespace Tests\Feature;

use App\Models\Pengawas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SiapkanData;
use Tests\TestCase;

/** Halaman Profil Saya (Langkah 16). */
class ProfilTest extends TestCase
{
    use RefreshDatabase, SiapkanData;

    public function test_dosen_wali_memperbarui_profil_dan_nip_sendiri(): void
    {
        $dosen = $this->buatDosen('Eva Yumami', 'eva');
        $this->actingAs($dosen->user);

        $this->putJson(route('profil.perbarui'), [
            'nama'    => 'Eva Baru',
            'email'   => 'eva.baru@uji.test',
            'telepon' => '081234567890',
            'nip'     => '12345678',
        ])->assertOk();

        $this->assertSame('Eva Baru', $dosen->user->fresh()->name);
        $this->assertSame('eva.baru@uji.test', $dosen->user->fresh()->email);
        $this->assertSame('eva.baru@uji.test', $dosen->user->fresh()->username); // username ikut email
        $this->assertSame('Eva Baru', $dosen->fresh()->nama_dosen);
        $this->assertSame('12345678', $dosen->fresh()->nip);
    }

    public function test_email_bentrok_ditolak(): void
    {
        $dosenA = $this->buatDosen('Dosen A', 'a');
        $this->buatDosen('Dosen B', 'b');
        $this->actingAs($dosenA->user);

        $this->putJson(route('profil.perbarui'), [
            'nama'  => 'Dosen A',
            'email' => 'b@uji.test', // sudah dipakai Dosen B
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_nip_dosen_duplikat_ditolak(): void
    {
        $dosenA = $this->buatDosen('Dosen A', 'a');
        $dosenA->update(['nip' => '12345678']);

        $dosenB = $this->buatDosen('Dosen B', 'b');
        $this->actingAs($dosenB->user);

        $this->putJson(route('profil.perbarui'), [
            'nama'  => 'Dosen B',
            'email' => 'b@uji.test',
            'nip'   => '12345678',
        ])->assertStatus(422)->assertJsonValidationErrors('nip');
    }

    public function test_nip_pengawas_duplikat_diperbolehkan(): void
    {
        Pengawas::forceCreate([
            'user_id'       => $this->buatAkun('pengawas', 'p1', 'Pengawas A')->id,
            'nama_pengawas' => 'Pengawas A',
            'nip'           => '12345678',
        ]);

        $pengawasB = Pengawas::forceCreate([
            'user_id'       => $this->buatAkun('pengawas', 'p2', 'Pengawas B')->id,
            'nama_pengawas' => 'Pengawas B',
        ]);
        $this->actingAs($pengawasB->user);

        $this->putJson(route('profil.perbarui'), [
            'nama'  => 'Pengawas B',
            'email' => 'p2@uji.test',
            'nip'   => '12345678',
        ])->assertOk();
    }

    public function test_mahasiswa_tidak_bisa_mengubah_nama(): void
    {
        $periode = $this->buatPeriode();
        $kelas = $this->buatKelas($periode, null);
        $mhs = $this->buatMahasiswa($kelas, '6304240001', 'Nama Asli', null, 0, true);
        $this->actingAs($mhs->user);

        $this->putJson(route('profil.perbarui'), [
            'nama'  => 'Nama Palsu',
            'email' => 'mhs@uji.test',
        ])->assertOk();

        $this->assertSame('Nama Asli', $mhs->user->fresh()->name);
        $this->assertSame('6304240001', $mhs->user->fresh()->username); // username tetap NIM
    }

    public function test_foto_salah_tipe_ditolak(): void
    {
        Storage::fake('public');
        $admin = $this->buatAkun('admin', 'admin_uji');
        $this->actingAs($admin);

        $berkas = UploadedFile::fake()->create('foto.txt', 100, 'text/plain');

        $this->postJson(route('profil.foto'), ['foto' => $berkas])
            ->assertStatus(422)->assertJsonValidationErrors('foto');
    }

    public function test_halaman_profil_terbuka_untuk_semua_peran(): void
    {
        $periode = $this->buatPeriode();

        foreach (['admin', 'dosen_wali', 'pengawas'] as $role) {
            $user = $this->buatAkun($role, 'akun_' . $role);
            $this->actingAs($user)->get(route('profil'))->assertOk();
        }

        $kelas = $this->buatKelas($periode, null);
        $mhs = $this->buatMahasiswa($kelas, '6304240001', 'Nama Asli', null, 0, true);
        $this->actingAs($mhs->user)->get(route('profil'))->assertOk();
    }
}

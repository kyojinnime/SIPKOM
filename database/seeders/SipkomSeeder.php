<?php

namespace Database\Seeders;

use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\Kompen;
use App\Models\Mahasiswa;
use App\Models\Pengawas;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\Ruang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk mencoba Beranda: 124 mahasiswa kompen, 112 selesai, 12 belum.
 * Foto memakai gambar contoh di public/images; ganti dengan data asli nanti.
 * Semua akun contoh kata sandi "password".
 */
class SipkomSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('id_ID');
        $sandi = Hash::make('password');

        // ===== BAGIAN: Periode aktif =====
        $periode = Periode::create(['tahun_ajaran' => '2026/2027', 'semester' => 'ganjil', 'aktif' => true]);

        // ===== BAGIAN: Akun admin =====
        User::create(['name' => 'Admin SIPKOM', 'username' => 'admin', 'role' => 'admin', 'password' => $sandi]);

        // ===== BAGIAN: Prodi =====
        $prodiTi  = Prodi::create(['nama_prodi' => 'D3 Teknik Informatika']);
        $prodiRpl = Prodi::create(['nama_prodi' => 'D4 Rekayasa Perangkat Lunak']);

        // ===== BAGIAN: Dosen wali + kelas (nama kelas "A" sengaja ada di dua prodi) =====
        $daftarKelas = [];
        $susunan = [
            [$prodiTi, 2025, 'A'], [$prodiTi, 2025, 'B'],
            [$prodiRpl, 2025, 'A'], [$prodiTi, 2024, 'A'],
        ];
        foreach ($susunan as $i => [$prodi, $angkatan, $huruf]) {
            $user = User::create([
                'name' => "Dosen Wali " . ($i + 1), 'username' => 'dosen' . ($i + 1),
                'role' => 'dosen_wali', 'password' => $sandi,
            ]);
            $dosen = DosenWali::create(['user_id' => $user->id, 'nama_dosen' => $faker->name()]);
            $daftarKelas[] = Kelas::create([
                'prodi_id' => $prodi->id, 'dosen_wali_id' => $dosen->id,
                'angkatan' => $angkatan, 'nama_kelas' => $huruf,
            ]);
        }

        // ===== BAGIAN: Pengawas + ruang =====
        $dataRuang = [
            ['Laboratorium Sistem Informasi', ['Maintenance Lab', 'Instalasi Jaringan', 'Instal OS'], true],
            ['Laboratorium Jaringan', ['Perapian Kabel', 'Crimping UTP'], true],
            ['Laboratorium Basis Data', ['Pembersihan Perangkat', 'Instal Aplikasi'], true],
            ['Laboratorium Multimedia', ['Merapikan Peralatan', 'Pembersihan Ruangan'], false],
            ['Ruang Administrasi Jurusan', ['Pengarsipan Dokumen', 'Rekap Data'], true],
        ];
        $daftarRuang = [];
        foreach ($dataRuang as $i => [$nama, $pekerjaan, $tersedia]) {
            $user = User::create([
                'name' => "Pengawas " . ($i + 1), 'username' => 'pengawas' . ($i + 1),
                'role' => 'pengawas', 'password' => $sandi,
            ]);
            $pengawas = Pengawas::create(['user_id' => $user->id, 'nama_pengawas' => $faker->name()]);
            $daftarRuang[] = Ruang::create([
                'pengawas_id' => $pengawas->id, 'nama_ruang' => $nama,
                'foto' => 'images/lokasi/l' . (($i % 6) + 1) . '.png',
                'jenis_pekerjaan' => $pekerjaan, 'status_tersedia' => $tersedia,
            ]);
        }

        // ===== BAGIAN: 124 mahasiswa; 112 pertama selesai, 12 terakhir belum =====
        for ($n = 1; $n <= 124; $n++) {
            $kelas = $daftarKelas[($n - 1) % count($daftarKelas)];
            $mhs = Mahasiswa::create([
                'nim' => (string) (6304250000 + $n),
                'nama_mahasiswa' => $faker->name(),
                'kelas_id' => $kelas->id,
            ]);

            $jamWajib = $faker->randomElement([16, 32, 48]);
            $jk = JumlahKompen::create([
                'mahasiswa_id' => $mhs->id, 'periode_id' => $periode->id,
                'jumlah_jam' => $jamWajib,
                'keterangan' => [16 => 'SP I', 32 => 'SP II', 48 => 'SP III'][$jamWajib],
            ]);

            $selesai = $n <= 112;
            // Mahasiswa belum selesai baru mengerjakan separuh jam wajibnya
            $jamDikerjakan = $selesai ? $jamWajib : intdiv($jamWajib, 2);

            // Pecah jam yang sudah dikerjakan menjadi sesi 4 atau 8 jam
            while ($jamDikerjakan > 0) {
                $jam = min($jamDikerjakan, $faker->randomElement([4, 8]));
                $ruang = $faker->randomElement($daftarRuang);
                Kompen::create([
                    'jumlah_kompen_id' => $jk->id, 'mahasiswa_id' => $mhs->id, 'ruang_id' => $ruang->id,
                    'status' => 'selesai',
                    'tgl_kompen' => now()->subDays(rand(1, 60)),
                    'jam_mulai' => '08:00:00', 'jam_selesai' => sprintf('%02d:00:00', 8 + $jam),
                    'jumlah_jam_pelaksanaan' => $jam,
                    'dokumentasi' => 'images/galeri/g' . rand(1, 6) . '.jpg',
                    'divalidasi_oleh' => $ruang->pengawas_id, 'divalidasi_pada' => now(),
                ]);
                $jamDikerjakan -= $jam;
            }
        }
    }
}
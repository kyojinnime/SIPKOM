<?php

namespace Tests\Support;

use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\Kompen;
use App\Models\Mahasiswa;
use App\Models\PenugasanDosenWali;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/** Pembantu membuat data uji (tanpa factory). Semua memakai forceCreate agar tidak bergantung pada $fillable. */
trait SiapkanData
{
    /** Akun login. Mahasiswa tanpa email (username = NIM), role lain berusername bebas. */
    protected function buatAkun(string $role, string $username, ?string $nama = null): User
    {
        return User::forceCreate([
            'name'                 => $nama ?? $username,
            'username'             => $username,
            'email'                => $role === 'mahasiswa' ? null : $username . '@uji.test',
            'password'             => Hash::make('rahasia123'),
            'role'                 => $role,
            'is_active'            => true,
            'must_change_password' => false,
        ]);
    }

    protected function buatPeriode(string $tahun = '2025/2026', string $semester = 'genap', bool $aktif = true): Periode
    {
        return Periode::forceCreate(['tahun_ajaran' => $tahun, 'semester' => $semester, 'aktif' => $aktif]);
    }

    /** Dosen wali beserta akunnya. */
    protected function buatDosen(string $nama, string $username): DosenWali
    {
        $akun = $this->buatAkun('dosen_wali', $username, $nama);

        return DosenWali::forceCreate(['user_id' => $akun->id, 'nama_dosen' => $nama]);
    }

    /** Kelas pada prodi, ditugaskan ke dosen (bila ada) pada periode itu. */
    protected function buatKelas(Periode $periode, ?DosenWali $dosen, string $huruf = 'C', int $angkatan = 2024,
                                 string $namaProdi = 'D4 Rekayasa Perangkat Lunak'): Kelas
    {
        $prodi = Prodi::where('nama_prodi', $namaProdi)->first() ?? Prodi::forceCreate(['nama_prodi' => $namaProdi]);
        $kelas = Kelas::forceCreate(['prodi_id' => $prodi->id, 'angkatan' => $angkatan, 'nama_kelas' => $huruf]);

        if ($dosen) {
            PenugasanDosenWali::forceCreate([
                'kelas_id' => $kelas->id, 'periode_id' => $periode->id, 'dosen_wali_id' => $dosen->id,
            ]);
        }

        return $kelas;
    }

    /** Mahasiswa; bila $jam > 0 sekaligus dibuatkan jam wajib pada periode itu. */
    protected function buatMahasiswa(Kelas $kelas, string $nim, string $nama, ?Periode $periode = null,
                                     int $jam = 0, bool $denganAkun = false): Mahasiswa
    {
        $mhs = Mahasiswa::forceCreate([
            'user_id'        => $denganAkun ? $this->buatAkun('mahasiswa', $nim, $nama)->id : null,
            'nim'            => $nim,
            'nama_mahasiswa' => $nama,
            'kelas_id'       => $kelas->id,
        ]);

        if ($periode && $jam > 0) {
            JumlahKompen::forceCreate(['mahasiswa_id' => $mhs->id, 'periode_id' => $periode->id, 'jumlah_jam' => $jam]);
        }

        return $mhs;
    }

    /** Jam terlaksana yang sudah tervalidasi. Tabel ruang tidak dibutuhkan uji ini, jadi pemeriksaan kunci asing dimatikan sebentar. */
    protected function buatRealisasi(JumlahKompen $baris, int $jam): Kompen
    {
        Schema::disableForeignKeyConstraints();
        $kompen = Kompen::forceCreate([
            'jumlah_kompen_id'       => $baris->id,
            'mahasiswa_id'           => $baris->mahasiswa_id,
            'ruang_id'               => 1,
            'status'                 => 'selesai',
            'tgl_kompen'             => now()->toDateString(),
            'jumlah_jam_pelaksanaan' => $jam,
            'divalidasi_pada'        => now(),
        ]);
        Schema::enableForeignKeyConstraints();

        return $kompen;
    }
}
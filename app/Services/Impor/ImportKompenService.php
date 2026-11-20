<?php

namespace App\Services\Impor;

use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\LogAktivitas;
use App\Models\Mahasiswa;
use App\Models\PenugasanDosenWali;
use App\Models\Periode;
use App\Models\RiwayatImpor;
use App\Models\User;
use App\Services\NotifikasiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil import PDF dalam SATU transaksi: kelas, penugasan dosen, mahasiswa, akun,
 * jam wajib, riwayat, log, dan notifikasi. Gagal di mana pun = tidak ada yang berubah.
 *
 * Pemeriksaan DIULANG terhadap database terbaru (pratinjau bisa sudah usang), dan centang
 * "timpa" / "nolkan" dari form hanya dipakai bila cocok dengan hasil pemeriksaan ulang itu.
 */
class ImportKompenService
{
    public function __construct(
        private ImportValidator $validator,
        private NotifikasiService $notifikasi,
    ) {
    }

    /**
     * @param array $hasil    keluaran PdfSiakadParser::parse()
     * @param int[] $timpa    nomor blok yang dicentang "timpa file lama" (hanya berlaku untuk admin)
     * @param int[] $nolkan   id jumlah_kompen yang dicentang "turunkan jam" (mahasiswa hilang di file)
     * @return array ringkasan untuk halaman selesai
     */
    public function simpan(array $hasil, Periode $periode, User $user, string $namaBerkas, string $hash,
                           array $timpa = [], array $nolkan = []): array
    {
        // Pemeriksaan ulang terhadap database saat ini
        $rencana = $this->validator->analisis($hasil, $periode, $user, $namaBerkas, $hash);

        // Tanggal cetak dikonversi ke zona waktu aplikasi: Eloquent menyimpan jam apa adanya tanpa konversi
        $dicetak = $hasil['meta']['dicetak_pada'] instanceof Carbon
            ? $hasil['meta']['dicetak_pada']->copy()->setTimezone(config('app.timezone'))
            : null;

        $ctx = [
            'periode'       => $periode,
            'periode_label' => $rencana['periode_label'],
            'user'          => $user,
            'nama'          => mb_substr($namaBerkas, 0, 255),
            'hash'          => $hash,
            'dicetak'       => $dicetak,
            'timpa'         => array_flip(array_map('intval', $timpa)),
            'nolkan'        => array_flip(array_map('intval', $nolkan)),
            'ambang_notif'  => (int) config('sipkom.ambang_notifikasi_jam', 16),
            'ambang_merah'  => (int) config('sipkom.ambang_merah_jam', 64),
        ];

        return DB::transaction(fn () => $this->jalankan($rencana, $ctx));
    }

    // =====================================================================
    // Alur utama
    // =====================================================================

    private function jalankan(array $rencana, array $ctx): array
    {
        $total = ['disimpan' => 0, 'ditolak' => 0, 'jam' => 0, 'kelas_baru' => 0, 'mhs_baru' => 0,
                  'akun_baru' => 0, 'diubah' => 0, 'diaktifkan' => 0, 'tidak_berubah' => 0,
                  'dinolkan' => 0, 'notifikasi' => 0];
        $blokHasil = [];
        $galat = [];       // baris yang tidak disimpan (untuk CSV)
        $notifMhs = [];    // notifikasi untuk mahasiswa
        $perDosen = [];    // ringkasan per dosen wali

        foreach ($rencana['blok'] as $i => $b) {
            $entri = $this->entriBaru($this->labelBlok($b));

            if (!empty($b['galat'])) {
                // Blok bermasalah (prodi tidak ada, file lama untuk dosen wali, dll)
                $entri = $this->tolakBlok($entri, $b, 'ditolak', implode(' ', $b['galat']), $galat);
            } elseif (empty($b['baris'])) {
                // Tidak ada mahasiswa beralpa: tidak ada yang dibuat
                $entri['status'] = 'kosong';
                $entri['alasan'] = $b['info'][0] ?? null;
            } elseif ($b['butuh_timpa'] && !isset($ctx['timpa'][$i])) {
                // Admin, file lebih lama, kotak "Timpa" tidak dicentang
                $entri = $this->tolakBlok($entri, $b, 'dilewati',
                    'File lebih lama dari import terakhir dan kotak "Timpa" tidak dicentang.', $galat);
            } else {
                $entri = $this->simpanBlok($b, $entri, $ctx, $galat, $notifMhs, $perDosen);
            }

            // Jumlahkan ke total seluruh file
            foreach (['disimpan', 'ditolak', 'jam', 'mhs_baru', 'akun_baru', 'diubah', 'diaktifkan', 'tidak_berubah', 'dinolkan'] as $k) {
                $total[$k] += $entri[$k];
            }
            $total['kelas_baru'] += $entri['kelas_baru'] ? 1 : 0;
            $blokHasil[] = $entri;
        }

        // ----- Notifikasi mahasiswa (A mencapai ambang) -----
        $total['notifikasi'] += $this->notifikasi->kirimBanyak($notifMhs);

        // ----- Notifikasi ringkasan untuk dosen wali -----
        if ($perDosen) {
            $akunDosen = DosenWali::whereIn('id', array_keys($perDosen))->pluck('user_id', 'id');
            $daftar = [];
            foreach ($perDosen as $dosenId => $d) {
                if (empty($akunDosen[$dosenId])) {
                    continue;
                }
                $daftar[] = [
                    'user_id' => $akunDosen[$dosenId],
                    'jenis'   => 'ringkasan_import',
                    'judul'   => 'Import data kompensasi selesai',
                    'isi'     => sprintf(
                        '%s: %d mahasiswa berkewajiban kompensasi (%d mahasiswa baru), %d terkena SP, %d dengan %d jam atau lebih.',
                        implode(', ', $d['kelas']), $d['berkewajiban'], $d['mhs_baru'], $d['sp'], $d['besar'], $ctx['ambang_merah']
                    ),
                    'tautan'  => route('kompen.index'),
                ];
            }
            $total['notifikasi'] += $this->notifikasi->kirimBanyak($daftar);
        }

        return [
            'nama_berkas'   => $rencana['nama_berkas'],
            'periode_label' => $rencana['periode_label'],
            'dicetak_teks'  => $rencana['dicetak_teks'],
            'waktu'         => now()->locale('id')->translatedFormat('j F Y, H:i'),
            'total'         => $total,
            'blok'          => $blokHasil,
            'galat'         => $galat,
        ];
    }

    /** Menyimpan satu blok kelas yang lolos. Mengembalikan entri blok yang sudah terisi hitungan. */
    private function simpanBlok(array $b, array $entri, array $ctx, array &$galat, array &$notifMhs, array &$perDosen): array
    {
        /** @var Periode $periode */
        $periode = $ctx['periode'];
        $entri['status'] = 'disimpan';

        // ----- Kelas: cari atau buat (kunci unik: prodi, angkatan, nama kelas) -----
        $kelas = Kelas::firstOrCreate([
            'prodi_id'   => $b['prodi_id'],
            'angkatan'   => $b['angkatan'],
            'nama_kelas' => trim((string) $b['kelas']),
        ]);
        $entri['kelas_baru'] = $kelas->wasRecentlyCreated;

        // ----- Penugasan dosen wali: hanya dibuat bila belum ada, tidak pernah mengubah yang ada -----
        if (in_array($b['penugasan']['aksi'], ['importir', 'cocok'], true) && $b['penugasan']['dosen_id']) {
            PenugasanDosenWali::firstOrCreate(
                ['kelas_id' => $kelas->id, 'periode_id' => $periode->id],
                ['dosen_wali_id' => $b['penugasan']['dosen_id']]
            );
        }
        $dosenId = PenugasanDosenWali::where('kelas_id', $kelas->id)
            ->where('periode_id', $periode->id)->value('dosen_wali_id');

        $jumlahSp = 0;
        $jumlahBesar = 0;

        // ----- Baris mahasiswa -----
        foreach ($b['baris'] as $r) {
            if ($r['status'] === 'merah') {
                $galat[] = $this->barisGalat($entri['label'], $r, implode(' ', $r['pesan']));
                $entri['ditolak']++;
                continue;
            }

            // Mahasiswa: buat, atau perbarui nama dan kelas seperlunya
            $nama = $r['nama'] !== '' ? $r['nama'] : $r['nim'];
            $mhs = Mahasiswa::where('nim', $r['nim'])->first();
            if ($mhs === null) {
                $mhs = Mahasiswa::create([
                    'user_id' => null, 'nim' => $r['nim'], 'nama_mahasiswa' => $nama, 'kelas_id' => $kelas->id,
                ]);
                $entri['mhs_baru']++;
            } else {
                $kelasLama = (int) $mhs->kelas_id;
                if ($mhs->nama_mahasiswa !== $nama || $kelasLama !== (int) $kelas->id) {
                    $mhs->update(['nama_mahasiswa' => $nama, 'kelas_id' => $kelas->id]);
                    if ($kelasLama !== (int) $kelas->id) {
                        LogAktivitas::catat('impor_pindah_kelas', $mhs, ['kelas_id' => $kelasLama], ['kelas_id' => $kelas->id]);
                    }
                }
            }

            // Akun login: hanya dibuat bila belum punya; akun yang ada tidak disentuh (password tidak direset)
            if (!$mhs->user_id) {
                $akun = User::create([
                    'name'                 => $nama,
                    'username'             => $r['nim'],
                    'email'                => null,
                    'password'             => config('sipkom.password_awal'),   // di-hash otomatis oleh cast
                    'role'                 => 'mahasiswa',
                    'is_active'            => true,
                    'must_change_password' => true,
                ]);
                $mhs->update(['user_id' => $akun->id]);
                $entri['akun_baru']++;
            }

            // Jam wajib: buat atau ganti baris (mahasiswa, periode)
            $jam = (int) $r['a'];
            $ket = $r['sp_label'];
            $jk = JumlahKompen::where('mahasiswa_id', $mhs->id)->where('periode_id', $periode->id)->first();

            if ($jk === null) {
                JumlahKompen::create([
                    'mahasiswa_id' => $mhs->id, 'periode_id' => $periode->id,
                    'jumlah_jam' => $jam, 'keterangan' => $ket,
                ]);
                $aksi = 'baru';
            } else {
                $lama = (int) $jk->jumlah_jam;
                $batal = $jk->dibatalkan_pada !== null;

                if ($lama !== $jam || $batal || $jk->keterangan !== $ket) {
                    $jk->jumlah_jam = $jam;
                    $jk->keterangan = $ket;
                    if ($batal) {
                        // Import mengaktifkan kembali kewajiban yang pernah dibatalkan
                        $jk->dibatalkan_pada = null;
                        $jk->alasan_batal = null;
                    }
                    $jk->save();
                }

                if ($batal) {
                    $aksi = 'aktifkan';
                    $entri['diaktifkan']++;
                    LogAktivitas::catat('impor_aktifkan_kembali', $jk, ['jumlah_jam' => $lama, 'dibatalkan' => true], ['jumlah_jam' => $jam]);
                } elseif ($lama !== $jam) {
                    $aksi = 'ubah';
                    $entri['diubah']++;
                    LogAktivitas::catat('impor_ubah_jam', $jk, ['jumlah_jam' => $lama], ['jumlah_jam' => $jam]);
                } else {
                    $aksi = 'sama';
                    $entri['tidak_berubah']++;
                }
            }

            $entri['disimpan']++;
            $entri['jam'] += $jam;
            $jumlahSp += $r['sp_label'] !== null ? 1 : 0;
            $jumlahBesar += $jam >= $ctx['ambang_merah'] ? 1 : 0;

            // Notifikasi ke mahasiswa: hanya bila ada perubahan dan jam mencapai ambang
            if ($aksi !== 'sama' && $jam >= $ctx['ambang_notif']) {
                $notifMhs[] = [
                    'user_id' => $mhs->user_id,
                    'jenis'   => 'kompen_wajib',
                    'judul'   => "Anda memiliki kewajiban kompensasi {$jam} jam",
                    'isi'     => "Kewajiban kompensasi {$jam} jam" . ($ket ? " ({$ket})" : '')
                        . " tercatat untuk periode {$ctx['periode_label']}. Buka Lokasi Kompen di Beranda dan ajukan kompensasi ke ruang yang tersedia.",
                    'tautan'  => route('beranda') . '#lokasi-kompen',
                ];
            }
            if ($aksi === 'baru') {
                $entri['baru']++;
            }
        }

        // ----- Mahasiswa hilang yang dicentang: jam wajib diturunkan -----
        foreach ($b['hilang'] as $h) {
            if (!isset($ctx['nolkan'][$h['jumlah_kompen_id']])) {
                continue;
            }
            $jk = JumlahKompen::find($h['jumlah_kompen_id']);
            $lama = $jk ? (int) $jk->jumlah_jam : 0;
            $baru = (int) $h['jam_setelah'];   // sudah memperhitungkan batas bawah (jam terlaksana)
            if (!$jk || $baru >= $lama) {
                continue;
            }
            $jk->jumlah_jam = $baru;
            $jk->save();
            $entri['dinolkan']++;
            LogAktivitas::catat('impor_turunkan_jam', $jk, ['jumlah_jam' => $lama], ['jumlah_jam' => $baru]);
        }

        // ----- Riwayat dan log: satu baris per kelas per import -----
        $ringkasanBlok = array_diff_key($entri, array_flip(['label', 'status', 'alasan']));
        $riwayat = RiwayatImpor::create([
            'user_id'      => $ctx['user']->id,
            'kelas_id'     => $kelas->id,
            'periode_id'   => $periode->id,
            'nama_berkas'  => $ctx['nama'],
            'hash_berkas'  => $ctx['hash'],
            'dicetak_pada' => $ctx['dicetak'],
            'ringkasan'    => $ringkasanBlok,
        ]);
        LogAktivitas::catat('impor_pdf', $riwayat, null, $ringkasanBlok);

        // ----- Ringkasan untuk dosen wali kelas ini (hanya bila ada yang berubah) -----
        if ($dosenId && ($entri['baru'] + $entri['diubah'] + $entri['diaktifkan']) > 0) {
            $d = $perDosen[$dosenId] ?? ['kelas' => [], 'berkewajiban' => 0, 'mhs_baru' => 0, 'sp' => 0, 'besar' => 0];
            $d['kelas'][] = 'Semester ' . $b['semester'] . ' kelas ' . $b['kelas'];
            $d['berkewajiban'] += $entri['disimpan'];
            $d['mhs_baru'] += $entri['mhs_baru'];
            $d['sp'] += $jumlahSp;
            $d['besar'] += $jumlahBesar;
            $perDosen[$dosenId] = $d;
        }

        return $entri;
    }

    // =====================================================================
    // Alat bantu
    // =====================================================================

    /** Entri hasil satu blok dengan semua hitungan bernilai 0. */
    private function entriBaru(string $label): array
    {
        return ['label' => $label, 'status' => 'kosong', 'alasan' => null,
                'disimpan' => 0, 'ditolak' => 0, 'jam' => 0, 'kelas_baru' => false,
                'mhs_baru' => 0, 'akun_baru' => 0, 'baru' => 0, 'diubah' => 0,
                'diaktifkan' => 0, 'tidak_berubah' => 0, 'dinolkan' => 0];
    }

    /** Blok tidak disimpan: semua barisnya masuk daftar galat dengan satu alasan. */
    private function tolakBlok(array $entri, array $b, string $status, string $alasan, array &$galat): array
    {
        $entri['status'] = $status;
        $entri['alasan'] = $alasan;
        $entri['ditolak'] = count($b['baris']);
        foreach ($b['baris'] as $r) {
            $galat[] = $this->barisGalat($entri['label'], $r, $alasan);
        }
        return $entri;
    }

    /** Satu baris daftar galat (untuk halaman ringkasan dan CSV). */
    private function barisGalat(string $label, array $r, string $alasan): array
    {
        return ['kelas' => $label, 'nim' => $r['nim'], 'nama' => $r['nama'], 'jam' => $r['a'], 'alasan' => $alasan];
    }

    /** Label blok untuk tampilan, mis. "D4 Rekayasa Perangkat Lunak | Semester 4 | Kelas C". */
    private function labelBlok(array $b): string
    {
        return ($b['prodi_nama'] ?: ($b['prodi_pdf'] ?: 'Program studi tidak terbaca'))
            . ' | Semester ' . ($b['semester'] ?? '-') . ' | Kelas ' . ($b['kelas'] ?? '-');
    }
}
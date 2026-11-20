<?php

namespace App\Services\Impor;

use App\Models\DosenWali;
use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\PenugasanDosenWali;
use App\Models\Periode;
use App\Models\Prodi;
use App\Models\RiwayatImpor;
use App\Models\User;
use App\Services\CakupanKompen;
use App\Services\KompenHitung;
use App\Services\SemesterHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Memeriksa hasil PdfSiakadParser terhadap database dan aturan bisnis (bagian 2.7 dan 2.7a),
 * lalu menyusun "rencana" untuk halaman pratinjau. TIDAK menyimpan apa pun ke database.
 *
 * Warna baris: hijau = siap, kuning = siap dengan peringatan, merah = ditolak (dilewati saat simpan).
 */
class ImportValidator
{
    private const ROMAWI = [1 => 'I', 2 => 'II', 3 => 'III'];

    public function __construct(private PencocokDosen $pencocok)
    {
    }

    /** Titik masuk: susun rencana lengkap untuk satu file PDF. */
    public function analisis(array $hasil, Periode $periode, User $user, string $namaBerkas, string $hash): array
    {
        $meta = $hasil['meta'];
        /** @var Carbon|null $dicetak */
        $dicetak = $meta['dicetak_pada'];

        // Konteks yang dipakai semua blok
        $ctx = [
            'admin'   => CakupanKompen::semua($user),
            'dosen'   => CakupanKompen::dosenWali($user),   // null bila pengimpor admin
            'periode' => $periode,
            'dicetak' => $dicetak,
            'hash'    => $hash,
            'id_wali' => $meta['id_wali'],
            // Prodi dikunci dengan nama baku agar "D4 - Rekayasa ..." cocok dengan "D4 Rekayasa ..."
            'prodi'   => Prodi::all()->keyBy(fn ($p) => self::kunciProdi($p->nama_prodi)),
        ];

        // ===== Peringatan yang berlaku untuk seluruh file =====
        $global = [];
        if ($dicetak === null) {
            $global[] = 'Tanggal cetak tidak terbaca dari file. Urutan versi import tidak bisa diperiksa.';
        }
        // Kode semester SIAKAD: tahun mulai + indeks semester (mis. 2025/2026 genap = 20252)
        $harapan = $periode->tahunMulai() . $periode->getSemesterIndex();
        if ($meta['id_semester'] !== null && $meta['id_semester'] !== $harapan) {
            $global[] = "Kode semester di file ({$meta['id_semester']}) tidak sama dengan yang diharapkan untuk periode ini ({$harapan}). Periksa pilihan periode.";
        }

        // ===== Analisis tiap blok kelas =====
        $blok = [];
        foreach ($hasil['blok'] as $b) {
            $blok[] = $this->analisisBlok($b, $ctx);
        }

        // ===== Ringkasan seluruh file =====
        $rg = ['baris' => 0, 'siap' => 0, 'hijau' => 0, 'kuning' => 0, 'merah' => 0,
               'jam' => 0, 'kelas_baru' => 0, 'akun_baru' => 0, 'hilang' => 0];
        foreach ($blok as $b) {
            $rg['kelas_baru'] += ($b['kelas_baru'] && empty($b['galat']) && !empty($b['baris'])) ? 1 : 0;
            $rg['hilang'] += count($b['hilang']);
            foreach ($b['baris'] as $r) {
                $rg['baris']++;
                $rg[$r['status']]++;
                if ($r['status'] !== 'merah') {
                    $rg['siap']++;
                    $rg['jam'] += $r['a'];
                    $rg['akun_baru'] += $r['akun_baru'] ? 1 : 0;
                }
            }
        }

        return [
            'nama_berkas'       => $namaBerkas,
            'hash'              => $hash,
            'periode_id'        => $periode->id,
            'periode_label'     => ucfirst($periode->semester) . ' ' . $periode->tahun_ajaran,
            // Disimpan sebagai teks agar aman di cache; Langkah 9 mengubahnya kembali ke tanggal
            'dicetak_pada'      => $dicetak?->toIso8601String(),
            'dicetak_teks'      => $dicetak ? self::teksTanggal($dicetak) : null,
            'id_semester'       => $meta['id_semester'],
            'id_wali'           => $meta['id_wali'],
            'peringatan_global' => $global,
            'blok'              => $blok,
            'ringkasan'         => $rg,
        ];
    }

    // =====================================================================
    // Satu blok kelas
    // =====================================================================

    private function analisisBlok(array $b, array $ctx): array
    {
        /** @var Periode $periode */
        $periode = $ctx['periode'];
        $baris = $b['baris'];

        // Bentuk dasar keluaran blok
        $hasil = [
            'prodi_pdf' => $b['prodi'], 'prodi_id' => null, 'prodi_nama' => null,
            'semester' => $b['semester'], 'kelas' => $b['kelas'], 'dpa' => $b['dpa'],
            'dibaca' => $b['jumlah_dibaca'], 'angkatan' => null,
            'kelas_id' => null, 'kelas_baru' => false,
            'penugasan' => ['aksi' => 'tidak_perlu', 'dosen_id' => null, 'dosen_nama' => null],
            'info' => [], 'peringatan' => [], 'galat' => [],
            'butuh_timpa' => false, 'versi' => null,
            'baris' => [], 'hilang' => [],
            'ringkas' => ['siap' => 0, 'ditolak' => 0, 'jam' => 0],
        ];

        // Blok tanpa mahasiswa beralpa: tidak ada yang disimpan, kelas tidak dibuat
        if (empty($baris)) {
            $hasil['info'][] = 'Tidak ada mahasiswa dengan alpa pada kelas ini. Tidak ada yang disimpan dari blok ini.';
            return $hasil;
        }

        // ----- Angkatan: yang paling banyak muncul pada NIM -----
        $hitung = [];
        foreach ($baris as $r) {
            $a = SemesterHelper::angkatanDariNim($r['nim']);
            if ($a !== null) {
                $hitung[$a] = ($hitung[$a] ?? 0) + 1;
            }
        }
        arsort($hitung);
        $angkatan = $hitung ? array_key_first($hitung) : null;
        $hasil['angkatan'] = $angkatan;

        // ----- Galat tingkat blok (membuat seluruh baris merah) -----
        $prodi = $b['prodi'] ? $ctx['prodi']->get(self::kunciProdi($b['prodi'])) : null;
        $namaKelas = trim((string) $b['kelas']);

        if ($prodi === null) {
            $hasil['galat'][] = 'Program studi "' . ($b['prodi'] ?: 'tidak terbaca')
                . '" tidak ditemukan di Data Master. Buat dulu di menu Kelola Data, Program Studi, lalu unggah ulang.';
        }
        if ($namaKelas === '' || mb_strlen($namaKelas) > 10) {
            $hasil['galat'][] = 'Nama kelas tidak terbaca dari file.';
        }
        if ($angkatan === null) {
            $hasil['galat'][] = 'Angkatan tidak dapat ditentukan dari NIM.';
        }

        // ----- Pemeriksaan silang semester di PDF dengan periode terpilih (kuning) -----
        if ($angkatan !== null && $b['semester'] !== null
            && !SemesterHelper::semesterCocok($angkatan, (int) $b['semester'], $periode)) {
            $seharusnya = SemesterHelper::hitung($angkatan, $periode);
            $hasil['peringatan'][] = "Semester di file ({$b['semester']}) tidak sesuai dengan angkatan {$angkatan} pada periode ini "
                . "(seharusnya semester {$seharusnya}). Periksa pilihan periode.";
        }

        // ----- Kelas, penugasan dosen, dan versi file -----
        $kelas = null;
        $versi = null;
        if (empty($hasil['galat'])) {
            $hasil['prodi_id'] = $prodi->id;
            $hasil['prodi_nama'] = $prodi->nama_prodi;

            $kelas = Kelas::where('prodi_id', $prodi->id)
                ->where('angkatan', $angkatan)
                ->where('nama_kelas', $namaKelas)
                ->first();
            $hasil['kelas_id'] = $kelas?->id;
            $hasil['kelas_baru'] = $kelas === null;
            if ($kelas === null) {
                $hasil['info'][] = 'Kelas ini belum ada dan akan dibuat saat disimpan.';
            }

            $this->periksaPenugasan($hasil, $kelas, $ctx, $b);

            // Versi file hanya bisa dibandingkan bila kelas sudah ada
            if ($kelas !== null) {
                $versi = $this->periksaVersi($hasil, $kelas, $ctx);
                $hasil['versi'] = $versi;
            }
        }

        $galatBlok = !empty($hasil['galat']);

        // ----- Baris mahasiswa -----
        $nims = array_column($baris, 'nim');
        $mhsPeta = collect();
        $jkPeta = collect();
        $userPeta = collect();
        if (!$galatBlok) {
            // Tiga query untuk seluruh blok (bukan satu per baris)
            $mhsPeta = Mahasiswa::with('kelas')->whereIn('nim', $nims)->get()->keyBy('nim');
            $jkPeta = JumlahKompen::where('periode_id', $periode->id)
                ->whereIn('mahasiswa_id', $mhsPeta->pluck('id'))->get()->keyBy('mahasiswa_id');
            $userPeta = User::whereIn('username', $nims)->pluck('id', 'username');
        }

        $dilihat = [];
        foreach ($baris as $r) {
            $hasil['baris'][] = $this->nilaiBaris(
                $r, $hasil, $kelas, $versi, $galatBlok, $mhsPeta, $jkPeta, $userPeta, $dilihat, $angkatan
            );
        }

        // ----- Mahasiswa yang hilang pada file ini (kuning, dengan kotak konfirmasi) -----
        if ($kelas !== null && !$galatBlok && $versi !== 'lebih_lama') {
            $hasil['hilang'] = $this->cariHilang($kelas, $periode, array_flip($nims));
        }

        // ----- Ringkasan blok -----
        foreach ($hasil['baris'] as $r) {
            if ($r['status'] === 'merah') {
                $hasil['ringkas']['ditolak']++;
            } else {
                $hasil['ringkas']['siap']++;
                $hasil['ringkas']['jam'] += $r['a'];
            }
        }

        return $hasil;
    }

    // =====================================================================
    // Bagian-bagian pemeriksaan
    // =====================================================================

    /** Penugasan dosen wali untuk (kelas, periode): info dan peringatan, tidak pernah memblokir. */
    private function periksaPenugasan(array &$hasil, ?Kelas $kelas, array $ctx, array $b): void
    {
        /** @var DosenWali|null $dosen */
        $dosen = $ctx['dosen'];

        $penugasan = $kelas
            ? PenugasanDosenWali::with('dosenWali')
                ->where('kelas_id', $kelas->id)->where('periode_id', $ctx['periode']->id)->first()
            : null;

        if ($penugasan) {
            $nama = $penugasan->dosenWali?->nama_dosen;
            $hasil['penugasan'] = ['aksi' => 'sudah_ada', 'dosen_id' => (int) $penugasan->dosen_wali_id, 'dosen_nama' => $nama];

            if ($dosen && (int) $penugasan->dosen_wali_id !== (int) $dosen->id) {
                $hasil['peringatan'][] = "Kelas ini ditugaskan kepada {$nama} pada periode ini. Penugasan tidak diubah, "
                    . 'dan kelas ini tidak akan tampil di daftar Anda.';
            } else {
                $hasil['info'][] = "Dosen wali kelas ini: {$nama}.";
            }
        } elseif ($dosen) {
            // Dosen wali yang mengimpor: kelas yang belum bertuan ditugaskan kepadanya
            $hasil['penugasan'] = ['aksi' => 'importir', 'dosen_id' => (int) $dosen->id, 'dosen_nama' => $dosen->nama_dosen];
            $hasil['info'][] = 'Kelas ini akan ditugaskan kepada Anda pada periode ini.';
        } else {
            // Admin: dosen dicari dari nama DPA (dan NIP bila terisi)
            $cari = $this->pencocok->cari($b['dpa'], $ctx['id_wali']);
            if ($cari['status'] === 'cocok') {
                $hasil['penugasan'] = ['aksi' => 'cocok', 'dosen_id' => (int) $cari['dosen']->id, 'dosen_nama' => $cari['dosen']->nama_dosen];
                $hasil['info'][] = "Dosen wali akan ditetapkan: {$cari['dosen']->nama_dosen} (dari DPA di file).";
            } else {
                $hasil['penugasan'] = ['aksi' => 'kosong', 'dosen_id' => null, 'dosen_nama' => null];
                $sebab = $cari['status'] === 'ganda' ? 'cocok dengan lebih dari satu dosen wali' : 'tidak ditemukan di data dosen wali';
                $hasil['peringatan'][] = 'DPA "' . ($b['dpa'] ?: 'tidak terbaca') . "\" {$sebab}. "
                    . 'Kelas tetap dibuat tanpa dosen wali. Atur di Kelola Data, Kelas.';
            }
        }

        // Dosen wali pengimpor: DPA di file sebaiknya namanya sendiri
        if ($dosen && !$this->pencocok->cocok($dosen, $b['dpa'], $ctx['id_wali'])) {
            $hasil['peringatan'][] = 'DPA di file (' . ($b['dpa'] ?: 'tidak terbaca') . ') berbeda dengan nama Anda.';
        }
    }

    /**
     * Membandingkan tanggal cetak file dengan import terakhir (kelas, periode) yang sama.
     * Hasil: null (belum pernah diimpor), 'baru', 'sama', atau 'lebih_lama'.
     */
    private function periksaVersi(array &$hasil, Kelas $kelas, array $ctx): ?string
    {
        $terakhir = RiwayatImpor::terakhir($kelas->id, $ctx['periode']->id);
        if (!$terakhir) {
            return null;
        }

        /** @var Carbon|null $dicetak */
        $dicetak = $ctx['dicetak'];
        $tglTerakhir = $terakhir->dicetak_pada ? self::teksTanggal($terakhir->dicetak_pada) : 'tanggal tidak tercatat';

        // File yang sama persis (isi sama) atau dicetak pada detik yang sama
        $sama = $terakhir->hash_berkas === $ctx['hash'];
        $lebihLama = false;
        if ($dicetak && $terakhir->dicetak_pada) {
            $cmp = $dicetak->getTimestamp() <=> $terakhir->dicetak_pada->getTimestamp();
            $sama = $sama || $cmp === 0;
            $lebihLama = !$sama && $cmp < 0;
        }

        if ($sama) {
            $hasil['peringatan'][] = "File ini tampaknya sudah pernah diimpor untuk kelas ini (import terakhir: {$tglTerakhir}). Hasilnya akan sama.";
            return 'sama';
        }

        if ($lebihLama) {
            $pesan = 'File ini dicetak ' . self::teksTanggal($dicetak) . ", lebih lama dari import terakhir untuk kelas ini ({$tglTerakhir}).";
            if ($ctx['admin']) {
                // Admin boleh menimpa dengan konfirmasi
                $hasil['butuh_timpa'] = true;
                $hasil['peringatan'][] = $pesan . ' Centang "Timpa" di bawah bila tetap ingin memakai file ini.';
            } else {
                $hasil['galat'][] = $pesan . ' Gunakan file yang lebih baru dari SIAKAD.';
            }
            return 'lebih_lama';
        }

        return 'baru';
    }

    /** Menilai satu baris mahasiswa: status, pesan, dan tindakan yang akan dilakukan saat simpan. */
    private function nilaiBaris(array $r, array $hasil, ?Kelas $kelas, ?string $versi, bool $galatBlok,
                                Collection $mhsPeta, Collection $jkPeta, Collection $userPeta,
                                array &$dilihat, ?int $angkatan): array
    {
        $jam = (float) $r['a'];
        $jamInt = (int) round($jam);
        $mhs = $mhsPeta->get($r['nim']);
        $jk = $mhs ? $jkPeta->get($mhs->id) : null;

        $merah = null;
        $pesan = [];
        $aksi = 'baru';
        $jamLama = null;

        // ----- Alasan MERAH (baris dilewati); pemeriksaan pertama yang kena dipakai -----
        if ($galatBlok) {
            $merah = $versi === 'lebih_lama' ? 'File lebih lama dari import terakhir.' : 'Blok kelas bermasalah. Lihat pesan di atas.';
        } elseif (isset($dilihat[$r['nim']])) {
            $merah = 'NIM muncul lebih dari sekali di file ini.';
        } elseif (fmod($jam, 1.0) !== 0.0) {
            $merah = "Jam harus bilangan bulat (nilai di file: {$r['a']}).";
        } else {
            // Username NIM sudah dipakai akun yang bukan milik mahasiswa ini
            $uid = $userPeta->get($r['nim']);
            if ($uid !== null && (!$mhs || (int) $mhs->user_id !== (int) $uid)) {
                $merah = 'Username NIM ini sudah dipakai akun lain.';
            } elseif ($jk) {
                // B06: jam baru tidak boleh di bawah jam yang sudah terlaksana
                $min = KompenHitung::jamMinimum($mhs->id, $jk);
                if ($jamInt < $min) {
                    $merah = "Jam baru ({$jamInt}) lebih kecil dari jam yang sudah terlaksana (minimal {$min}).";
                }
            }
        }
        $dilihat[$r['nim']] = true;

        // ----- Tindakan dan peringatan KUNING (hanya bila tidak merah) -----
        if ($merah === null) {
            if ($jk) {
                $jamLama = (int) $jk->jumlah_jam;
                if ($jk->dibatalkan_pada) {
                    $aksi = 'aktifkan';
                    $pesan[] = 'Kewajiban sebelumnya dibatalkan. Import akan mengaktifkannya kembali.';
                } else {
                    $aksi = $jamLama === $jamInt ? 'sama' : 'ubah';
                }
            }
            if ($mhs && ($kelas === null || (int) $mhs->kelas_id !== (int) $kelas->id)) {
                $kl = $mhs->kelas;
                $pesan[] = 'Pindah kelas: sebelumnya kelas ' . ($kl?->nama_kelas ?? '-') . ' angkatan ' . ($kl?->angkatan ?? '-') . '.';
            }
            $angNim = SemesterHelper::angkatanDariNim($r['nim']);
            if ($angkatan !== null && $angNim !== null && $angNim !== $angkatan) {
                $pesan[] = "Angkatan NIM ({$angNim}) berbeda dengan angkatan kelas ({$angkatan}).";
            }
        }

        // ----- Tingkat SP: dari file bila ada, bila tidak dari jam (B04) -----
        $sp = $r['sp'] ? self::ROMAWI[$r['sp']] : ($jamInt >= 48 ? 'III' : ($jamInt >= 32 ? 'II' : ($jamInt >= 16 ? 'I' : null)));

        return [
            'nim'       => $r['nim'],
            'nama'      => $r['nama'],
            'a'         => $jamInt,
            'sp_label'  => $sp ? "SP {$sp}" : null,
            'status'    => $merah ? 'merah' : ($pesan ? 'kuning' : 'hijau'),
            'pesan'     => $merah ? [$merah] : $pesan,
            'aksi'      => $merah ? 'dilewati' : $aksi,
            'jam_lama'  => $jamLama,
            'mhs_baru'  => !$mhs,
            'akun_baru' => !$mhs || !$mhs->user_id,
        ];
    }

    /**
     * Mahasiswa di kelas ini yang punya jam wajib pada periode ini, tetapi tidak ada nilai alpa di file.
     * Yang jam wajibnya tidak bisa turun (sudah terlaksana penuh) tidak ditampilkan.
     */
    private function cariHilang(Kelas $kelas, Periode $periode, array $adaDiFile): array
    {
        $kandidat = JumlahKompen::with('mahasiswa')
            ->where('periode_id', $periode->id)
            ->where('jumlah_jam', '>', 0)
            ->whereNull('dibatalkan_pada')
            ->whereHas('mahasiswa', fn ($q) => $q->where('kelas_id', $kelas->id))
            ->get();

        $hilang = [];
        foreach ($kandidat as $k) {
            if (isset($adaDiFile[$k->mahasiswa->nim])) {
                continue;
            }
            // Batas bawah: jam wajib tidak boleh lebih kecil dari jam yang sudah terlaksana
            $setelah = KompenHitung::jamMinimum((int) $k->mahasiswa_id, $k);
            if ($setelah >= (int) $k->jumlah_jam) {
                continue;
            }
            $hilang[] = [
                'jumlah_kompen_id' => $k->id,
                'nim'              => $k->mahasiswa->nim,
                'nama'             => $k->mahasiswa->nama_mahasiswa,
                'jam_lama'         => (int) $k->jumlah_jam,
                'jam_setelah'      => $setelah,
            ];
        }
        return $hilang;
    }

    // =====================================================================
    // Alat bantu
    // =====================================================================

    /** Nama prodi baku: huruf kecil, hanya huruf dan angka. "D4 - Rekayasa PL" sama dengan "D4 Rekayasa PL". */
    private static function kunciProdi(?string $nama): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $nama));
    }

    /** Tanggal untuk tampilan, mis. "17 April 2026, 16:04". Memakai salinan agar objek asli tidak berubah. */
    private static function teksTanggal(Carbon $tgl): string
    {
        return (clone $tgl)->locale('id')->translatedFormat('j F Y, H:i');
    }
}
<?php

namespace App\Services\Rekap;

use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\PenugasanDosenWali;
use App\Models\Periode;
use App\Models\User;
use App\Services\CakupanKompen;
use App\Services\KompenHitung;
use App\Services\SemesterHelper;
use Illuminate\Support\Str;

/**
 * Menyusun data rekap sisa kompensasi: mahasiswa dalam cakupan akun yang masih punya sisa jam > 0.
 * Sisa = total lintas periode (aturan B08), dihitung KompenHitung. Hasilnya sudah terurut:
 * prodi, semester, huruf kelas, NIM (sama dengan contoh klien).
 */
class RekapSisaData
{
    // Jumlah id mahasiswa per query hitung (menjaga daftar IN tetap wajar)
    private const POTONGAN_ID = 500;

    /**
     * @return array{baris: array, keterangan: ?string, suffix: string}
     *   baris      = [['nim','nama','prodi','kelas','dosen','sisa'], ...]
     *   keterangan = teks baris 11 pada file (kelas atau dosen yang disaring), null bila tidak ada
     *   suffix     = potongan nama file untuk filter, mis. "kelas-4c-rpl"
     */
    public function ambil(User $user, Periode $periode, ?int $kelasId, ?int $dosenId): array
    {
        // ===== BAGIAN 1: kandidat dalam cakupan akun (admin semua, dosen wali kelasnya) =====
        $kandidat = CakupanKompen::mahasiswa($user, $periode->id)
            // Hanya yang punya kewajiban yang tidak dibatalkan (periode mana pun)
            ->whereHas('jumlahKompen', fn ($q) => $q->whereNull('dibatalkan_pada'))
            ->when($kelasId, fn ($q) => $q->where('kelas_id', $kelasId))
            ->when($dosenId, fn ($q) => $q->whereHas('kelas.penugasan', fn ($p) => $p
                ->where('dosen_wali_id', $dosenId)->where('periode_id', $periode->id)))
            ->with('kelas.prodi')
            ->get();

        // ===== BAGIAN 2: sisa jam total (dua query per potongan, tanpa N+1) =====
        $ring = [];
        foreach ($kandidat->pluck('id')->chunk(self::POTONGAN_ID) as $potong) {
            $ring += KompenHitung::ringkasanBanyak($potong->all());
        }

        // ===== BAGIAN 3: nama dosen wali per kelas pada periode ini =====
        $dosenPeta = PenugasanDosenWali::with('dosenWali')
            ->where('periode_id', $periode->id)
            ->whereIn('kelas_id', $kandidat->pluck('kelas_id')->unique())
            ->get()
            ->mapWithKeys(fn ($p) => [$p->kelas_id => $p->dosenWali?->nama_dosen]);

        // ===== BAGIAN 4: susun baris, hanya yang sisanya lebih dari 0 =====
        $baris = [];
        foreach ($kandidat as $m) {
            $sisa = (int) ($ring[$m->id]['sisa'] ?? 0);
            if ($sisa <= 0) {
                continue;
            }

            $k = $m->kelas;
            $angkatan = (int) ($k?->angkatan ?? 0);

            $baris[] = [
                'nim'       => $m->nim,
                'nama'      => $this->rapikanNama($m->nama_mahasiswa),
                'prodi'     => SingkatanProdi::dari($k?->prodi?->nama_prodi),
                'kelas'     => $k ? SemesterHelper::hitungTampil($angkatan, $periode) . $k->nama_kelas : '-',
                'dosen'     => $dosenPeta[$m->kelas_id] ?? '-',   // kelas tanpa dosen wali: tanda strip
                'sisa'      => $sisa,
                // Kunci urut (dibuang di akhir)
                '_semester' => $k ? SemesterHelper::hitung($angkatan, $periode) : 0,
                '_huruf'    => (string) ($k?->nama_kelas ?? ''),
            ];
        }

        // Urut: prodi, semester, huruf kelas, NIM
        usort($baris, fn ($a, $b) =>
            [$a['prodi'], $a['_semester'], $a['_huruf'], $a['nim']] <=> [$b['prodi'], $b['_semester'], $b['_huruf'], $b['nim']]);
        $baris = array_map(fn ($b) => array_diff_key($b, array_flip(['_semester', '_huruf'])), $baris);

        // ===== BAGIAN 5: keterangan filter (baris 11 file) dan potongan nama file =====
        $keterangan = [];
        $suffix = [];

        if ($kelasId && ($kls = Kelas::with('prodi')->find($kelasId))) {
            $kode = SemesterHelper::hitungTampil((int) $kls->angkatan, $periode) . $kls->nama_kelas;
            $singkat = SingkatanProdi::dari($kls->prodi?->nama_prodi);
            $keterangan[] = "KELAS {$kode} {$singkat}";
            $suffix[] = "kelas {$kode} {$singkat}";
        }

        // Dosen: pilihan admin, atau dosen wali yang sedang mengunduh (null untuk admin tanpa pilihan)
        $dosen = $dosenId ? DosenWali::find($dosenId) : CakupanKompen::dosenWali($user);
        if ($dosen) {
            $keterangan[] = 'DOSEN WALI: ' . $dosen->nama_dosen;
            $suffix[] = 'dosen ' . $dosen->nama_dosen;
        }

        return [
            'baris'      => $baris,
            'keterangan' => $keterangan ? implode(' | ', $keterangan) : null,
            'suffix'     => Str::slug(implode(' ', $suffix)),
        ];
    }

    /** Nama yang tertulis huruf besar semua (dari PDF SIAKAD) diubah ke kapital awal kata; selain itu dibiarkan. */
    private function rapikanNama(?string $nama): string
    {
        $n = trim((string) preg_replace('/\s+/u', ' ', (string) $nama));

        return ($n !== '' && $n === mb_strtoupper($n))
            ? mb_convert_case(mb_strtolower($n), MB_CASE_TITLE)
            : $n;
    }
}
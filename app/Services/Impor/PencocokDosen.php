<?php

namespace App\Services\Impor;

use App\Models\DosenWali;
use Illuminate\Support\Collection;

/**
 * Mencocokkan nama DPA di PDF dengan data dosen_wali.
 * Nama dinormalkan (huruf kecil, tanpa gelar dan tanda baca). NIP dipakai hanya bila terisi.
 * Contoh: "EVA YUMAMI, S.Kom., M.T." dan "Eva Yumami" sama-sama menjadi "eva yumami".
 */
class PencocokDosen
{
    // Awalan gelar (di depan nama) dan akhiran gelar (di belakang nama, tanpa koma)
    private const AWALAN = ['dr', 'drs', 'dra', 'ir', 'prof', 'hj', 'h', 'kh'];
    private const GELAR  = ['skom', 'mkom', 'mt', 'sst', 'mcs', 'phd', 'st', 'msi', 'msc', 'mm',
                            'spd', 'mpd', 'se', 'ssi', 'sh', 'mh', 'amd', 'mti'];

    /** Daftar dosen wali, dimuat sekali per objek agar tidak query berulang. */
    private ?Collection $dosen = null;

    /** Mengubah nama menjadi bentuk baku untuk dibandingkan. */
    public static function normalisasi(?string $nama): string
    {
        $n = mb_strtolower(trim((string) $nama));

        // Gelar belakang biasanya ditulis setelah koma pertama: buang
        $n = explode(',', $n)[0];

        // Titik dibuang TANPA spasi: "s.kom." menjadi "skom", "m. putra" menjadi "m putra"
        $n = str_replace('.', '', $n);

        // Tanda baca lain menjadi spasi
        $n = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $n);
        $token = preg_split('/\s+/u', trim($n), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Buang awalan gelar di depan (sisakan minimal satu kata)
        while (count($token) > 1 && in_array($token[0], self::AWALAN, true)) {
            array_shift($token);
        }
        // Buang akhiran gelar di belakang (sisakan minimal satu kata)
        while (count($token) > 1 && in_array(end($token), self::GELAR, true)) {
            array_pop($token);
        }

        return implode(' ', $token);
    }

    /** Hanya angka: untuk membandingkan NIP yang ditulis dengan spasi atau tanda baca berbeda. */
    private static function angka(?string $s): string
    {
        return preg_replace('/\D/', '', (string) $s);
    }

    /** True bila dosen ini cocok dengan DPA di file (lewat nama, atau lewat NIP bila terisi). */
    public function cocok(DosenWali $dosen, ?string $namaDpa, ?string $idWali): bool
    {
        $target = self::normalisasi($namaDpa);
        if ($target !== '' && self::normalisasi($dosen->nama_dosen) === $target) {
            return true;
        }

        $nip = self::angka($dosen->nip);
        return $nip !== '' && $nip === self::angka($idWali);
    }

    /**
     * Mencari dosen wali untuk DPA di file.
     * Hasil: ['status' => 'cocok' | 'ganda' | 'tidak_ada', 'dosen' => DosenWali|null]
     */
    public function cari(?string $namaDpa, ?string $idWali): array
    {
        $this->dosen ??= DosenWali::select('id', 'nama_dosen', 'nip')->get();

        $hit = $this->dosen->filter(fn ($d) => $this->cocok($d, $namaDpa, $idWali))->values();

        return match (true) {
            $hit->count() === 1 => ['status' => 'cocok', 'dosen' => $hit->first()],
            $hit->count() > 1   => ['status' => 'ganda', 'dosen' => null],
            default             => ['status' => 'tidak_ada', 'dosen' => null],
        };
    }
}
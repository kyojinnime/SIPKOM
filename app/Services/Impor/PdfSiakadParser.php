<?php

namespace App\Services\Impor;

use Carbon\Carbon;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Membaca PDF "Laporan Evaluasi Perkuliahan" SIAKAD menjadi data terstruktur.
 *
 * Prinsip (hasil pengukuran PDF contoh):
 * - Koordinat absolut berbeda tiap halaman dan tiap blok kelas, jadi semua hitungan
 *   RELATIF terhadap header kolom dan baris NIM pada halaman yang sama.
 * - Halaman lanjutan tidak punya header dan identitas kelas: pakai yang terakhir terbaca.
 * - Teks sering terpecah per kerning (T + otal, 1 + 1.0, S + P + 1): fragmen digabung manual.
 * - Hanya kolom A (alpa) yang diambil. S, I, dan Total SIA diabaikan.
 *
 * Keluaran parse():
 *   [
 *     'meta' => ['dicetak_pada' => Carbon|null, 'id_semester' => ?string, 'id_wali' => ?string],
 *     'blok' => [ ['prodi','semester','kelas','dpa','nidn','jumlah_dibaca',
 *                  'baris' => [ ['nim','nama','a'(float),'sp'(int|null)] ]] ],
 *   ]
 */
class PdfSiakadParser
{
    // Lebar satu digit dan satu titik pada angka absensi (kalibrasi dari PDF contoh)
    private const LEBAR_DIGIT = 4.0;
    private const LEBAR_TITIK = 2.0;

    // Toleransi selisih y agar dua fragmen dianggap satu baris
    private const TOLERANSI_Y = 1.5;

    // Jarak maksimal (poin) pusat angka ke pusat header kolom agar dianggap milik kolom itu
    private const TOLERANSI_KOLOM = 8.0;

    // Jarak y maksimal baris lanjutan nama di bawah baris NIM (nama terpanjang 3 baris, sekitar 25)
    private const MAKS_JARAK_NAMA = 40.0;

    // Angka yang berjarak kurang dari ini (poin) dianggap potongan dari satu angka
        private const JARAK_GABUNG_ANGKA = 6.0;

    // Dua potongan angka dianggap satu bila yang kedua mulai tepat di ujung yang pertama (selisih sampai batas ini).
    // Antar kolom absensi selalu ada jarak lebih besar (sekitar 3 poin atau lebih).
    private const GAP_GABUNG_ANGKA = 1.8;

    // Huruf yang berdempetan (jarak awal ke awal sampai batas ini) disatukan menjadi satu kata pada judul kolom
    private const GAP_GABUNG_KATA = 8.5;

    // Batas jumlah halaman agar file raksasa tidak menghabiskan memori server
    private const MAKS_HALAMAN = 100;

    private const PESAN_TIDAK_VALID =
        'File ini tidak terbaca sebagai Laporan Evaluasi Perkuliahan SIAKAD. '
        . 'Pastikan file adalah PDF hasil cetak dari SIAKAD (bukan foto atau hasil pindai).';

    // Nama bulan Indonesia dan Inggris untuk tanggal cetak
    private const BULAN = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5, 'june' => 6, 'july' => 7,
        'august' => 8, 'october' => 10, 'december' => 12,
    ];

    /**
     * Titik masuk: baca satu file PDF.
     *
     * @throws PdfTidakValidException bila file tidak terbaca atau bukan format SIAKAD
     */
    public function parse(string $path): array
    {
        // ===== BAGIAN 1: baca file dan ambil fragmen teks per halaman =====
        try {
            $pdf = (new Parser())->parseFile($path);
            $semuaHalaman = $pdf->getPages();

            // Tolak file yang terlalu panjang sebelum teksnya dibaca
            if (count($semuaHalaman) > self::MAKS_HALAMAN) {
                throw new PdfTidakValidException('File terlalu panjang (maksimal ' . self::MAKS_HALAMAN . ' halaman).');
            }

            $halaman = [];
            foreach ($semuaHalaman as $i => $page) {
                $halaman[$i + 1] = $this->ambilFragmen($page->getDataTm());
            }
        } catch (PdfTidakValidException $e) {
            throw $e;   // pesan ramah diteruskan apa adanya, jangan dibungkus pesan umum
        } catch (Throwable $e) {
            // Apa pun galat internal pustaka, pengguna hanya melihat pesan ramah
            throw new PdfTidakValidException(self::PESAN_TIDAK_VALID, 0, $e);
        }

        // PDF hasil pindai tidak punya lapisan teks
        if (collect($halaman)->every(fn ($f) => empty($f))) {
            throw new PdfTidakValidException(self::PESAN_TIDAK_VALID);
        }

        // ===== BAGIAN 2: footer (tanggal cetak, id_semester, id_wali) =====
        $meta = $this->bacaFooter($halaman);

        // ===== BAGIAN 3: jalan per halaman, bangun blok kelas =====
        $blok = [];            // daftar blok kelas
        $aktif = null;         // indeks blok yang sedang diisi
        $header = null;        // header kolom terakhir (dibawa ke halaman lanjutan)

        foreach ($halaman as $frag) {
            // Halaman yang memuat label "Program Studi" membuka blok kelas baru
            $identitas = $this->bacaIdentitas($frag);
            if ($identitas !== null) {
                $blok[] = $identitas + ['jumlah_dibaca' => 0, 'baris' => []];
                $aktif = array_key_last($blok);
            }

            // Halaman yang memuat header "REKAP ABSENSI" memperbarui posisi kolom
            $bacaHeader = $this->bacaHeaderKolom($frag);
            if ($bacaHeader !== null) {
                $header = $bacaHeader;
            }

            // Halaman tanpa baris mahasiswa (mis. halaman catatan kaki) dilewati
            $barisNim = $this->cariBarisNim($frag);
            if (empty($barisNim)) {
                continue;
            }

            // Ada mahasiswa tetapi identitas atau header belum pernah terbaca: format tidak dikenal
            if ($aktif === null || $header === null) {
                throw new PdfTidakValidException(self::PESAN_TIDAK_VALID);
            }

            foreach ($this->bacaBaris($frag, $barisNim, $header) as $mhs) {
                $blok[$aktif]['jumlah_dibaca']++;
                // Hanya yang punya alpa (A > 0) yang menjadi kewajiban kompensasi
                if ($mhs['a'] !== null && $mhs['a'] > 0) {
                    $blok[$aktif]['baris'][] = $mhs;
                }
            }
        }

        if (empty($blok)) {
            throw new PdfTidakValidException(self::PESAN_TIDAK_VALID);
        }

        return ['meta' => $meta, 'blok' => $blok];
    }

    // =====================================================================
    // Pembaca bagian-bagian halaman
    // =====================================================================

    /**
     * Ubah hasil getDataTm() menjadi daftar fragmen sederhana.
     * Setiap fragmen: x, y, t (teks mentah, spasi dipertahankan), n (teks dipangkas).
     */
    private function ambilFragmen(array $dataTm): array
    {
        $hasil = [];
        foreach ($dataTm as $item) {
            $tm = $item[0] ?? [];
            $teks = (string) ($item[1] ?? '');

            // Matriks teks punya 6 angka; e (indeks 4) = x dan f (indeks 5) = y
            if (!is_array($tm) || count($tm) < 6 || $teks === '') {
                continue;
            }

            $hasil[] = [
                'x' => (float) $tm[4],
                'y' => (float) $tm[5],
                't' => $teks,
                'n' => trim($teks),
            ];
        }
        return $this->gabungKarakterAngka($hasil);
    }

    /**
     * Menyatukan potongan angka yang berdempetan, mis. "1" + "0" menjadi "10" dan "2" + "2" + "." + "0" menjadi "22.0".
     * Perlu untuk PDF yang menyimpan setiap karakter sebagai fragmen sendiri. Pada PDF yang angkanya sudah utuh
     * tidak ada yang berubah. Hanya fragmen yang seluruhnya angka atau titik yang disatukan; huruf dan spasi tidak disentuh.
     */
    private function gabungKarakterAngka(array $frag): array
    {
        $hasil = [];

        foreach ($this->kelompokBaris($frag) as $baris) {
            $terakhir = null;   // potongan angka terakhir pada baris ini: ['x' =>, 't' =>, 'idx' =>]

            foreach ($baris['frag'] as $f) {
                $angka = preg_match('/^[\d.]+$/', $f['t']) === 1;

                if ($angka && $terakhir !== null
                    && $f['x'] >= $terakhir['x']
                    && ($f['x'] - ($terakhir['x'] + $this->lebarAngka($terakhir['t']))) <= self::GAP_GABUNG_ANGKA) {
                    // Sambung ke token angka sebelumnya
                    $hasil[$terakhir['idx']]['t'] .= $f['t'];
                    $hasil[$terakhir['idx']]['n'] = $hasil[$terakhir['idx']]['t'];
                    $terakhir['x'] = $f['x'];
                    $terakhir['t'] = $f['t'];
                    continue;
                }

                $hasil[] = $f;
                $terakhir = $angka ? ['x' => $f['x'], 't' => $f['t'], 'idx' => array_key_last($hasil)] : null;
            }
        }

        return $hasil;
    }

    /**
     * Menyatukan fragmen sebaris yang berdempetan menjadi token kata: ["N", "r"] menjadi "Nr", ["T", "otal"] menjadi "Total".
     * Fragmen harus sudah urut menurut x.
     */
    private function tokenBaris(array $fragBaris): array
    {
        $token = [];

        foreach ($fragBaris as $f) {
            $k = array_key_last($token);
            if ($k !== null && ($f['x'] - $token[$k]['x_akhir']) <= self::GAP_GABUNG_KATA) {
                $token[$k]['t'] .= $f['t'];
                $token[$k]['x_akhir'] = $f['x'];
            } else {
                $token[] = ['x' => $f['x'], 'y' => $f['y'], 't' => $f['t'], 'x_akhir' => $f['x']];
            }
        }
        foreach ($token as &$tk) {
            $tk['n'] = trim($tk['t']);
        }
        unset($tk);

        return $token;
    }

    /**
     * Baca footer dari seluruh halaman: tanggal cetak, id_semester, id_wali.
     * Teks footer tersebar, jadi fragmen satu halaman digabung menurut urutan aliran.
     */
    private function bacaFooter(array $halaman): array
    {
        $meta = ['dicetak_pada' => null, 'id_semester' => null, 'id_wali' => null];

        foreach ($halaman as $frag) {
            $gabung = implode('', array_column($frag, 't'));

            // Contoh: "pada 17 April 2026 16:04:39 WIB"
            if ($meta['dicetak_pada'] === null && preg_match(
                '/pada\s*(\d{1,2})\s*([A-Za-z]+)\s*(\d{4})\s*(\d{2}):(\d{2}):(\d{2})/u', $gabung, $m
            )) {
                $bulan = self::BULAN[mb_strtolower($m[2])] ?? null;
                if ($bulan !== null) {
                    $meta['dicetak_pada'] = Carbon::create(
                        (int) $m[3], $bulan, (int) $m[1], (int) $m[4], (int) $m[5], (int) $m[6], 'Asia/Jakarta'
                    );
                }
            }
                        // id_semester dan id_wali berada di dalam satu fragmen URL footer.
            // Dicari per fragmen, BUKAN di teks gabungan, karena pada teks gabungan angka
            // terakhir id_wali menempel dengan fragmen berikutnya ("4" dari "4/17/26").
            foreach ($frag as $f) {
                if ($meta['id_semester'] === null && preg_match('/id_semester=(\d+)/', $f['t'], $m)) {
                    $meta['id_semester'] = $m[1];
                }
                if ($meta['id_wali'] === null && preg_match('/id_wali=(\d+)/', $f['t'], $m)) {
                    $meta['id_wali'] = $m[1];
                }
            }

            // PDF yang menyimpan tiap huruf sebagai fragmen: alamat SIAKAD hanya utuh pada teks per baris
            if ($meta['id_semester'] === null || $meta['id_wali'] === null) {
                foreach ($this->kelompokBaris($frag) as $baris) {
                    $teks = implode('', array_column($baris['frag'], 't'));

                    if ($meta['id_semester'] === null && preg_match('/id_semester=(\d+)/', $teks, $m)) {
                        $meta['id_semester'] = $m[1];
                    }
                    if ($meta['id_wali'] === null && preg_match('/id_wali=(\d+)/', $teks, $m)) {
                        $meta['id_wali'] = $m[1];
                    }
                }
            }
        }
        return $meta;
    }

    /**
     * Baca kotak identitas kiri atas (prodi, semester, kelas, DPA, NIDN).
     * Mengembalikan null bila halaman ini bukan awal blok kelas.
     */
    private function bacaIdentitas(array $frag): ?array
    {
        // Label "Program Studi" di kiri halaman menandai awal blok
        // Label "Program Studi" di kiri halaman menandai awal blok.
        // Teks baris disusun dari semua fragmen sebaris di sisi kiri, karena sebagian PDF menyimpan tiap huruf sendiri.
        $label = null;
        $sisiKiri = array_filter($frag, fn ($f) => $f['x'] < 250);
        foreach ($this->kelompokBaris($sisiKiri) as $baris) {
            if (preg_match('/^Program\s*Studi\b/iu', $this->rapikan(implode('', array_column($baris['frag'], 't'))))) {
                $label = ['y' => $baris['y']];
                break;
            }
        }
        if ($label === null) {
            return null;
        }

        // Fragmen kotak identitas: sisi kiri, dari label ke bawah sekitar 110 poin
        $kotak = array_filter($frag, fn ($f) => $f['x'] < 250
            && $f['y'] <= $label['y'] + self::TOLERANSI_Y
            && $f['y'] >= $label['y'] - 110);

        // Kelompokkan jadi baris teks, lalu pecah menjadi pasangan "label: nilai"
        $nilai = [];
        $kunci = null;
        foreach ($this->kelompokBaris($kotak) as $baris) {
            $teks = $this->rapikan(implode('', array_column($baris['frag'], 't')));

            if (preg_match('/^(Program Studi|Semester|Kelas|DPA|NIDN\/NIDK|Dicetak Oleh)\s*:\s*(.*)$/iu', $teks, $m)) {
                $kunci = mb_strtolower($m[1]);
                $nilai[$kunci] = $m[2];
            } elseif ($kunci !== null) {
                // Baris tanpa label = lanjutan baris sebelumnya (mis. "Perangkat Lunak")
                $nilai[$kunci] = trim($nilai[$kunci] . ' ' . $teks);
            }
        }

        return [
            'prodi'    => $nilai['program studi'] ?? null,
            'semester' => isset($nilai['semester']) && preg_match('/\d+/', $nilai['semester'], $m) ? (int) $m[0] : null,
            // "Kelas C" -> "C"
            'kelas'    => isset($nilai['kelas']) ? trim(preg_replace('/^Kelas\s+/iu', '', $nilai['kelas'])) : null,
            'dpa'      => $nilai['dpa'] ?? null,
            'nidn'     => $nilai['nidn/nidk'] ?? null,
        ];
    }

    /**
     * Baca header kolom absensi: cari "Nr", lalu S, I, A, T(otal) berurutan di baris yang sama.
     * Mengembalikan pusat tiap kolom dan batas kiri kolom nilai (untuk memisahkan nama).
     * Null bila halaman ini tidak punya header (halaman lanjutan).
     */
        /**
     * Baca header kolom absensi: cari token "Nr", lalu S, I, A, dan "Total" berurutan sesudahnya.
     * Mengembalikan pusat tiap kolom dan batas kiri kolom nilai (untuk memisahkan nama).
     * Null bila halaman ini tidak punya header (halaman lanjutan).
     */
    private function bacaHeaderKolom(array $frag): ?array
    {
        // Token per baris: huruf yang berdempetan disatukan, jadi "N" + "r" terbaca "Nr" dan "T" + "otal" terbaca "Total"
        $barisHeader = null;
        $nilaiX = null;
        foreach ($this->kelompokBaris($frag) as $baris) {
            $token = $this->tokenBaris($baris['frag']);

            foreach ($token as $i => $tk) {
                // Baris judul kolom absensi: yang memuat token "Nr"
                if ($barisHeader === null && $tk['n'] === 'Nr') {
                    $barisHeader = array_slice($token, $i);
                }
                // Kolom nilai paling kiri ("Nh") menjadi batas kanan zona nama
                if ($tk['n'] === 'Nh' && ($nilaiX === null || $tk['x'] < $nilaiX)) {
                    $nilaiX = $tk['x'];
                }
            }
        }
        if ($barisHeader === null) {
            return null;
        }

        // Setelah "Nr": S, I, A, lalu "Total" (berurutan agar token lain tidak salah ambil)
        $urut = ['S', 'I', 'A', 'T'];
        $posisi = [];
        $i = 0;
        foreach ($barisHeader as $tk) {
            if ($i < 4 && ($tk['n'] === $urut[$i] || ($urut[$i] === 'T' && $tk['n'] === 'Total'))) {
                $posisi[$urut[$i]] = $tk['x'];
                $i++;
            }
        }
        if ($i < 4) {
            return null;
        }

        return [
            // Pusat kolom = awal label + setengah lebar label (kalibrasi dari PDF contoh)
            'kolom'  => [
                'S'     => $posisi['S'] + 2.4,
                'I'     => $posisi['I'] + 1.0,
                'A'     => $posisi['A'] + 2.4,
                'TOTAL' => $posisi['T'] + 8.0,
            ],
            'nilaiX' => $nilaiX !== null ? $nilaiX - 4 : 200.0,
        ];
    }

    /**
     * Temukan baris mahasiswa: fragmen berawalan NIM 10 digit di sisi kiri.
     * Urut dari atas ke bawah (y besar ke kecil).
     */
    private function cariBarisNim(array $frag): array
    {
        $baris = [];
        foreach ($frag as $f) {
            // NIM bisa berdiri sendiri atau menempel dengan awal nama
            if ($f['x'] < 110 && preg_match('/^(\d{10})(.*)$/su', ltrim($f['t']), $m)) {
                $baris[] = ['nim' => $m[1], 'x' => $f['x'], 'y' => $f['y'], 'sisa' => trim($m[2])];
            }
        }
        usort($baris, fn ($a, $b) => $b['y'] <=> $a['y']);
        return $baris;
    }

    /**
     * Baca semua mahasiswa pada satu halaman: nama, nilai kolom A, dan status SP.
     */
    private function bacaBaris(array $frag, array $barisNim, array $header): array
    {
        $kolom = $header['kolom'];
        $nimX = $barisNim[0]['x'];

        // ===== Nama: fragmen di zona nama, dikelompokkan ke baris NIM terdekat di atasnya =====
        $namaPerBaris = [];
        foreach ($frag as $f) {
            // Nama dimulai tepat sesudah NIM (10 digit); batas dihitung dari lebar angka, bukan jarak tetap
            if ($f['x'] < $nimX + $this->lebarAngka('0000000000') - 1.0 || $f['x'] >= $header['nilaiX']) {
                continue;
            }
            // Baris NIM terdekat yang y-nya sama atau di atas fragmen ini
            $pilih = null;
            foreach ($barisNim as $idx => $r) {
                if ($f['y'] <= $r['y'] + self::TOLERANSI_Y) {
                    $pilih = $idx;
                }
            }
            if ($pilih === null || ($barisNim[$pilih]['y'] - $f['y']) > self::MAKS_JARAK_NAMA) {
                continue;   // di atas semua baris (identitas, judul) atau terlalu jauh (catatan kaki)
            }
            $namaPerBaris[$pilih][] = $f;
        }

        // ===== Angka absensi: ambil kandidat di area kolom S sampai Total =====
        $kiri = $kolom['S'] - 15;
        $kanan = $kolom['TOTAL'] + 15;
        $angka = array_values(array_filter($frag, fn ($f) =>
            preg_match('/^\d+(\.\d+)?$/', $f['n']) && $f['x'] >= $kiri && $f['x'] <= $kanan));

        // Zona status (SP): di kanan kolom Total
        $statusX = $kolom['TOTAL'] + 18;

        $hasil = [];
        foreach ($barisNim as $idx => $r) {
            // --- nama: gabung teks mentah per baris, lalu antar baris dengan spasi ---
            $potongan = [$r['sisa']];
            foreach ($this->kelompokBaris($namaPerBaris[$idx] ?? []) as $b) {
                $potongan[] = implode('', array_column($b['frag'], 't'));
            }
            $nama = $this->rapikan(implode(' ', $potongan));

            // --- angka sebaris NIM: gabungkan potongan, lalu tentukan kolomnya ---
            $sebaris = array_values(array_filter($angka, fn ($f) => abs($f['y'] - $r['y']) <= self::TOLERANSI_Y));
            $nilai = [];
            foreach ($this->gabungAngka($sebaris) as $f) {
                // Pusat angka = awal + setengah lebar. Kolom = pusat header terdekat dalam toleransi
                $pusat = $f['x'] + $this->lebarAngka($f['n']) / 2;
                $terdekat = null;
                $jarak = PHP_FLOAT_MAX;
                foreach ($kolom as $nm => $c) {
                    if (abs($pusat - $c) < $jarak) {
                        $jarak = abs($pusat - $c);
                        $terdekat = $nm;
                    }
                }
                if ($terdekat !== null && $jarak <= self::TOLERANSI_KOLOM) {
                    $nilai[$terdekat] = $f['n'];
                }
            }

            // --- status SP: gabung potongan "S","P","1" di zona status, cari pola SP n ---
            $status = array_filter($frag, fn ($f) =>
                $f['x'] >= $statusX && abs($f['y'] - $r['y']) <= self::TOLERANSI_Y);
            usort($status, fn ($a, $b) => $a['x'] <=> $b['x']);
            $teksStatus = preg_replace('/\s+/u', '', implode('', array_column($status, 't')));
            $sp = preg_match('/SP([123])/i', $teksStatus, $m) ? (int) $m[1] : null;

            $hasil[] = [
                'nim'  => $r['nim'],
                'nama' => $nama,
                'a'    => isset($nilai['A']) ? (float) $nilai['A'] : null,
                'sp'   => $sp,
            ];
        }
        return $hasil;
    }

    // =====================================================================
    // Alat bantu
    // =====================================================================

    /**
     * Kelompokkan fragmen menjadi baris menurut y (atas ke bawah), tiap baris urut menurut x.
     * Keluaran: [ ['y' => float, 'frag' => [...]], ... ]
     */
    private function kelompokBaris(array $frag): array
    {
        $frag = array_values($frag);
        usort($frag, fn ($a, $b) => ($b['y'] <=> $a['y']) ?: ($a['x'] <=> $b['x']));

        $baris = [];
        foreach ($frag as $f) {
            $k = array_key_last($baris);
            // Masuk baris yang sama bila selisih y dengan jangkar baris kecil
            if ($k !== null && abs($baris[$k]['y'] - $f['y']) <= self::TOLERANSI_Y) {
                $baris[$k]['frag'][] = $f;
            } else {
                $baris[] = ['y' => $f['y'], 'frag' => [$f]];
            }
        }
        foreach ($baris as &$b) {
            usort($b['frag'], fn ($p, $q) => $p['x'] <=> $q['x']);
        }
        unset($b);

        return $baris;
    }

    /**
     * Gabungkan potongan angka yang bersebelahan, mis. "1" + "1.0" menjadi "11.0".
     * Syarat: jarak awal ke awal lebih dari 0 dan kurang dari batas, dan potongan sebelumnya belum berisi titik.
     */
    private function gabungAngka(array $angka): array
    {
        usort($angka, fn ($a, $b) => $a['x'] <=> $b['x']);

        $hasil = [];
        foreach ($angka as $f) {
            $k = array_key_last($hasil);
            if ($k !== null
                && ($f['x'] - $hasil[$k]['x']) > 0
                && ($f['x'] - $hasil[$k]['x']) <= self::JARAK_GABUNG_ANGKA
                && !str_contains($hasil[$k]['n'], '.')) {
                $hasil[$k]['n'] .= $f['n'];
            } else {
                $hasil[] = $f;
            }
        }
        return $hasil;
    }

    /** Perkiraan lebar angka: tiap digit 4,0 poin, tiap titik 2,0 poin. */
    private function lebarAngka(string $angka): float
    {
        return preg_match_all('/\d/', $angka) * self::LEBAR_DIGIT
            + substr_count($angka, '.') * self::LEBAR_TITIK;
    }

    /** Ratakan spasi ganda dan pangkas ujung. */
    private function rapikan(string $teks): string
    {
        return trim(preg_replace('/\s+/u', ' ', $teks));
    }
}
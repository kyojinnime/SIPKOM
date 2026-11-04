<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Periode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PeriodeController extends Controller
{
    /** Halaman Kelola Periode: daftar periode, jumlah mahasiswa per periode, dan periode aktif. */
    public function index()
    {
        $periode = $this->urut()->get();
        $jumlah = $this->hitungJumlah();
        $aktif = $periode->firstWhere('aktif', true);

        return view('admin.periode.index', compact('periode', 'jumlah', 'aktif'));
    }

    /** Query periode terbaru di atas. 'genap' > 'ganjil' secara abjad, jadi genap tampil di atas ganjil. */
    private function urut()
    {
        return Periode::orderBy('tahun_ajaran', 'desc')->orderBy('semester', 'desc');
    }

    /** Jumlah mahasiswa berkompensasi per periode (satu query), hasil: [periode_id => jumlah]. */
    private function hitungJumlah()
    {
        return DB::table('jumlah_kompen')
            ->select('periode_id', DB::raw('COUNT(*) as n'))
            ->groupBy('periode_id')
            ->pluck('n', 'periode_id');
    }

    /**
     * [BARU] Salin penugasan dosen wali dari periode sebelumnya ke periode yang baru aktif.
     * insertOrIgnore: kelas yang sudah punya penugasan di periode baru tidak ditimpa.
     * Hasil: jumlah baris yang disalin.
     */
    private function salinPenugasan(?Periode $lama, Periode $baru): int
    {
        if (!$lama || $lama->id === $baru->id) {
            return 0;
        }
        // Salin hanya maju: berpindah ke periode yang lebih lama tidak boleh menambah data riwayat
        if ($this->urutanWaktu($baru) <= $this->urutanWaktu($lama)) {
            return 0;
        }

        $sekarang = now();
        $baris = DB::table('penugasan_dosen_wali')->where('periode_id', $lama->id)->get(['kelas_id', 'dosen_wali_id'])
            ->map(fn($b) => [
                'kelas_id' => $b->kelas_id,
                'periode_id' => $baru->id,
                'dosen_wali_id' => $b->dosen_wali_id,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ])->all();

        return $baris ? DB::table('penugasan_dosen_wali')->insertOrIgnore($baris) : 0;
    }

    /** [BARU] Nilai urut waktu periode: dua semester per tahun. Dipakai membandingkan periode lebih baru atau lama. */
    private function urutanWaktu(Periode $p): int
    {
        return $p->tahunMulai() * 2 + $p->getSemesterIndex();
    }

    /** [BARU] Kalimat tambahan untuk pesan sukses bila ada penugasan yang disalin. */
    private function teksSalin(int $n): string
    {
        return $n > 0 ? " Dosen wali {$n} kelas disalin dari periode sebelumnya." : '';
    }

    /**
     * Aturan validasi tambah dan ubah.
     * $id diisi saat ubah agar kombinasi miliknya sendiri tidak dianggap bentrok.
     */
    private function aturan(Request $request, ?int $id = null): array
    {
        return [
            // bail: berhenti di aturan pertama yang gagal, supaya closure hanya jalan bila format benar
            'tahun_ajaran' => [
                'bail',
                'required',
                'string',
                'regex:/^\d{4}\/\d{4}$/',
                function ($atribut, $nilai, $gagal) {
                    // Tahun kedua harus tepat satu tahun setelah tahun pertama
                    [$a, $b] = explode('/', $nilai);
                    if ((int) $b !== (int) $a + 1) {
                        $gagal('Tahun kedua harus satu tahun setelah tahun pertama, mis. 2026/2027.');
                    }
                }
            ],
            // Unik per kombinasi (tahun ajaran, semester)
            'semester' => [
                'required',
                Rule::in(['ganjil', 'genap']),
                Rule::unique('periode', 'semester')
                    ->where(fn($q) => $q->where('tahun_ajaran', $request->input('tahun_ajaran')))
                    ->ignore($id)
            ],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    /** Pesan galat berbahasa Indonesia. */
    private function pesan(): array
    {
        return [
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.regex'    => 'Format tahun ajaran harus seperti 2026/2027.',
            'semester.required'     => 'Semester wajib dipilih.',
            'semester.in'           => 'Semester harus ganjil atau genap.',
            'semester.unique'       => 'Periode ini sudah ada.',
        ];
    }

    /** Simpan periode baru. Periode pertama otomatis aktif. */
    public function store(Request $request): JsonResponse
    {
        $valid = $request->validate($this->aturan($request), $this->pesan());

        $disalin = 0; // jumlah penugasan yang disalin dari periode aktif sebelumnya
        $periode = DB::transaction(function () use ($valid, $request, &$disalin) {
            $lama = Periode::where('aktif', true)->first();
            $aktif = $request->boolean('aktif') || !$lama;
            if ($aktif) {
                Periode::where('aktif', true)->update(['aktif' => false]); // hanya satu yang aktif
            }

            $baru = Periode::create([
                'tahun_ajaran' => $valid['tahun_ajaran'],
                'semester'     => $valid['semester'],
                'aktif'        => $aktif,
            ]);
            if ($aktif) {
                $disalin = $this->salinPenugasan($lama, $baru);
            }

            return $baru;
        });

        return $this->balasan($periode, 'Periode ditambahkan.' . $this->teksSalin($disalin), 201);
    }

    /** Simpan perubahan. Periode aktif tidak bisa dimatikan lewat sini; aktifkan periode lain. */
    public function update(Request $request, int $id): JsonResponse
    {
        $periode = Periode::findOrFail($id);
        $valid = $request->validate($this->aturan($request, $id), $this->pesan());

        $disalin = 0;
        DB::transaction(function () use ($periode, $valid, $request, &$disalin) {
            $periode->update([
                'tahun_ajaran' => $valid['tahun_ajaran'],
                'semester'     => $valid['semester'],
            ]);

            // Hanya bisa menaikkan menjadi aktif; yang lain dinonaktifkan, lalu dosen wali disalin
            if ($request->boolean('aktif') && !$periode->aktif) {
                $lama = Periode::where('aktif', true)->first();
                Periode::where('aktif', true)->update(['aktif' => false]);
                $periode->update(['aktif' => true]);
                $disalin = $this->salinPenugasan($lama, $periode);
            }
        });

        return $this->balasan($periode, 'Periode diperbarui.' . $this->teksSalin($disalin));
    }

    /** Jadikan periode ini aktif; periode aktif sebelumnya dinonaktifkan dalam satu transaksi. */
    public function aktifkan(int $id): JsonResponse
    {
        $periode = Periode::findOrFail($id);

        if ($periode->aktif) {
            return $this->balasan($periode, 'Periode ini sudah aktif.');
        }

        $disalin = 0;
        DB::transaction(function () use ($periode, &$disalin) {
            $lama = Periode::where('aktif', true)->first();
            Periode::where('aktif', true)->update(['aktif' => false]);
            $periode->update(['aktif' => true]);
            $disalin = $this->salinPenugasan($lama, $periode); // dosen wali ikut ke periode baru
        });

        return $this->balasan($periode, 'Periode diaktifkan.' . $this->teksSalin($disalin));
    }

    /** Hapus periode. DITOLAK (409) bila aktif atau sudah dipakai data lain. */
    public function destroy(int $id): JsonResponse
    {
        $periode = Periode::findOrFail($id);

        // Periode aktif dipakai Beranda dan import
        if ($periode->aktif) {
            return response()->json(['pesan' => 'Tidak bisa dihapus: periode ini sedang aktif.'], 409);
        }

        // Periode yang sudah punya data kompensasi menyimpan riwayat mahasiswa
        $dataKompen = DB::table('jumlah_kompen')->where('periode_id', $id)->count();
        if ($dataKompen > 0) {
            return response()->json([
                'pesan' => "Tidak bisa dihapus: sudah ada data kompensasi {$dataKompen} mahasiswa di periode ini.",
            ], 409);
        }

        // Penugasan dosen wali per periode (FK restrict); cek hanya bila tabelnya ada
        // Periode ini tanpa data kompensasi, jadi penugasan dosen wali di dalamnya hanya catatan/salinan.
        // Satu transaksi: penugasan dihapus dulu karena FK restrict.
        DB::transaction(function () use ($periode, $id) {
            DB::table('penugasan_dosen_wali')->where('periode_id', $id)->delete();
            $periode->delete();
        });

        return response()->json(['pesan' => 'Periode dihapus.', 'id' => $id]);
    }

    /**
     * Susun balasan JSON: pesan, id, label periode aktif, dan HTML seluruh daftar.
     * Seluruh daftar dikirim karena urutan dan lencana aktif bisa berubah di banyak baris.
     */
    private function balasan(Periode $periode, string $pesan, int $status = 200): JsonResponse
    {
        $semua = $this->urut()->get();
        $jumlah = $this->hitungJumlah();

        // Render tiap baris memakai partial yang sama dengan halaman
        $html = $semua->map(fn($p, $i) => view('admin.periode._baris', [
            'item' => $p,
            'nomor' => $i + 1,
            'jumlah' => $jumlah[$p->id] ?? 0,
        ])->render())->implode('');

        $aktif = $semua->firstWhere('aktif', true);

        return response()->json([
            'pesan'  => $pesan,
            'id'     => $periode->id,
            'daftar' => $html,
            'aktif'  => $aktif ? $aktif->tahun_ajaran . ' ' . ucfirst($aktif->semester) : '-',
        ], $status);
    }
}

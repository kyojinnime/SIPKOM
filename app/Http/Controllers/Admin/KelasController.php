<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\PenugasanDosenWali;
use App\Models\Periode;
use App\Models\Prodi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    /** Halaman Kelola Kelas: daftar kelas, pilihan prodi dan dosen wali, ringkasan. */
    public function index()
    {
        $aktif = Periode::yangAktif();
        $kelas = $this->semuaKelas($aktif);
        $prodi = Prodi::orderBy('nama_prodi')->get();
        $dosen = DosenWali::with('user')->orderBy('nama_dosen')->get();
        $hitung = $this->hitungRingkas($kelas);

        return view('admin.kelas.index', compact('kelas', 'prodi', 'dosen', 'aktif', 'hitung'));
    }

    /**
     * Semua kelas beserta data tampilan untuk periode aktif.
     * Penugasan hanya yang di periode aktif (dosen wali ikut dimuat), jadi tidak ada query per baris.
     */
    private function semuaKelas(?Periode $aktif)
    {
        $idAktif = $aktif?->id;

        $kelas = Kelas::with([
                'prodi',
                'penugasan' => fn ($q) => $q->where('periode_id', $idAktif)->with('dosenWali'),
            ])
            ->withCount('mahasiswa')
            ->orderByDesc('angkatan')->orderBy('prodi_id')->orderBy('nama_kelas')
            ->get();

        foreach ($kelas as $k) { $this->lengkapi($k, $aktif); }

        return $kelas;
    }

    /**
     * Isi data tampilan (tidak disimpan ke database): semester di periode aktif,
     * status lulus, dan dosen wali periode aktif.
     * Rumus semester sama dengan dokumen: (tahun mulai - angkatan) x 2 + indeks semester.
     */
    private function lengkapi(Kelas $k, ?Periode $aktif): void
    {
        $mentah = $aktif ? ($aktif->tahunMulai() - $k->angkatan) * 2 + $aktif->getSemesterIndex() : null;

        $k->semester_aktif = $mentah === null ? null : max(1, $mentah);
        $k->sudah_lulus    = $mentah !== null && $mentah > $this->batasSemester($k->prodi?->nama_prodi);
        $k->setRelation('dosen_aktif', $k->penugasan->first()?->dosenWali);
    }

    /** Batas semester per jenjang, dibaca dari awalan nama prodi: D3 = 6, D4 dan lainnya = 8. */
    private function batasSemester(?string $namaProdi): int
    {
        if ($namaProdi && preg_match('/^\s*D\s*-?\s*3\b/i', $namaProdi)) { return 6; }
        return 8;
    }

    /** Angka ringkasan: kelas aktif, kelas lulus, dan kelas aktif tanpa dosen wali. */
    private function hitungRingkas($kelas): array
    {
        $aktif = $kelas->where('sudah_lulus', false);

        return [
            'aktif'      => $aktif->count(),
            'lulus'      => $kelas->count() - $aktif->count(),
            'tanpaDosen' => $aktif->filter(fn ($k) => $k->dosen_aktif === null)->count(),
        ];
    }

    /** Rapikan huruf kelas sebelum validasi: tanpa spasi tepi, huruf besar. */
    private function rapikan(Request $request): void
    {
        $request->merge(['nama_kelas' => mb_strtoupper(trim((string) $request->input('nama_kelas')))]);
    }

    /**
     * Aturan validasi tambah dan ubah. $lama diisi saat ubah agar data miliknya sendiri tidak bentrok
     * dan dosen wali yang tidak diganti boleh tetap walau akunnya sudah nonaktif.
     */
    private function aturan(Request $request, Periode $aktif, ?Kelas $lama = null): array
    {
        return [
            'prodi_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            // Angkatan baru paling akhir adalah tahun mulai periode aktif (semester 1)
            'angkatan' => ['required', 'integer', 'min:2000', 'max:' . $aktif->tahunMulai()],
            // Unik per kombinasi (prodi, angkatan, huruf)
            'nama_kelas' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/',
                Rule::unique('kelas', 'nama_kelas')
                    ->where(fn ($q) => $q->where('prodi_id', $request->input('prodi_id'))
                                         ->where('angkatan', $request->input('angkatan')))
                    ->ignore($lama?->id)],
            // Dosen wali boleh kosong; bail agar closure hanya jalan bila id valid
            'dosen_wali_id' => ['bail', 'nullable', 'integer', Rule::exists('dosen_wali', 'id'),
                function ($atribut, $nilai, $gagal) use ($lama, $aktif) {
                    // Dosen wali tidak diganti: tidak perlu dicek ulang
                    $sekarang = $lama
                        ? PenugasanDosenWali::where('kelas_id', $lama->id)->where('periode_id', $aktif->id)->value('dosen_wali_id')
                        : null;
                    if ((int) $sekarang === (int) $nilai) { return; }
                    // Kelas tidak boleh diserahkan ke dosen wali yang akunnya nonaktif
                    $d = DosenWali::with('user')->find($nilai);
                    if ($d && !$d->user?->is_active) {
                        $gagal('Akun dosen wali ini nonaktif. Pilih dosen wali lain.');
                    }
                }],
        ];
    }

    /** Pesan galat berbahasa Indonesia. */
    private function pesan(): array
    {
        return [
            'prodi_id.required'     => 'Program studi wajib dipilih.',
            'prodi_id.exists'       => 'Program studi yang dipilih tidak valid.',
            'angkatan.required'     => 'Angkatan wajib diisi.',
            'angkatan.integer'      => 'Angkatan harus berupa angka.',
            'angkatan.min'          => 'Angkatan minimal 2000.',
            'angkatan.max'          => 'Angkatan tidak boleh melewati tahun mulai periode aktif (:max).',
            'nama_kelas.required'   => 'Huruf kelas wajib diisi.',
            'nama_kelas.max'        => 'Huruf kelas maksimal 10 karakter.',
            'nama_kelas.regex'      => 'Huruf kelas hanya boleh huruf dan angka, tanpa spasi.',
            'nama_kelas.unique'     => 'Kelas ini sudah ada di prodi dan angkatan yang sama.',
            'dosen_wali_id.exists'  => 'Dosen wali yang dipilih tidak valid.',
        ];
    }

    /** Respons standar bila belum ada periode aktif (dosen wali dan semester bergantung padanya). */
    private function tanpaPeriode(): JsonResponse
    {
        return response()->json([
            'pesan' => 'Belum ada periode aktif. Aktifkan satu periode di menu Kelola Periode dulu.',
        ], 409);
    }

    /** Atur dosen wali kelas di periode aktif: diisi bila ada pilihan, dihapus bila kosong. */
    private function simpanDosen(Kelas $kelas, Periode $aktif, $dosenId): void
    {
        if ($dosenId) {
            PenugasanDosenWali::updateOrCreate(
                ['kelas_id' => $kelas->id, 'periode_id' => $aktif->id],
                ['dosen_wali_id' => $dosenId]
            );
        } else {
            PenugasanDosenWali::where('kelas_id', $kelas->id)->where('periode_id', $aktif->id)->delete();
        }
    }

    /** Simpan kelas baru beserta dosen wali periode aktif dalam satu transaksi. */
    public function store(Request $request): JsonResponse
    {
        $aktif = Periode::yangAktif();
        if (!$aktif) { return $this->tanpaPeriode(); }

        $this->rapikan($request);
        $valid = $request->validate($this->aturan($request, $aktif), $this->pesan());

        $kelas = DB::transaction(function () use ($valid, $aktif) {
            $kelas = Kelas::create(Arr::only($valid, ['prodi_id', 'angkatan', 'nama_kelas']));
            $this->simpanDosen($kelas, $aktif, $valid['dosen_wali_id'] ?? null);
            return $kelas;
        });

        return $this->balasan($kelas, 'Kelas ditambahkan.', $aktif, 201);
    }

    /** Simpan perubahan kelas. Prodi dan angkatan terkunci bila kelas sudah punya mahasiswa. */
    public function update(Request $request, int $id): JsonResponse
    {
        $aktif = Periode::yangAktif();
        if (!$aktif) { return $this->tanpaPeriode(); }

        $kelas = Kelas::withCount('mahasiswa')->findOrFail($id);
        $this->rapikan($request);
        $valid = $request->validate($this->aturan($request, $aktif, $kelas), $this->pesan());

        // Prodi atau angkatan berubah padahal sudah ada mahasiswa: tolak di kolom prodi
        $berubah = (int) $valid['prodi_id'] !== (int) $kelas->prodi_id
                || (int) $valid['angkatan'] !== (int) $kelas->angkatan;
        if ($kelas->mahasiswa_count > 0 && $berubah) {
            throw ValidationException::withMessages([
                'prodi_id' => ["Prodi dan angkatan tidak bisa diubah: kelas sudah punya {$kelas->mahasiswa_count} mahasiswa."],
            ]);
        }

        DB::transaction(function () use ($kelas, $valid, $aktif) {
            $kelas->update(Arr::only($valid, ['prodi_id', 'angkatan', 'nama_kelas']));
            $this->simpanDosen($kelas, $aktif, $valid['dosen_wali_id'] ?? null);
        });

        return $this->balasan($kelas, 'Kelas diperbarui.', $aktif);
    }

    /**
     * Hapus kelas. DITOLAK (409) bila punya mahasiswa atau riwayat dosen wali di periode lain,
     * supaya data riwayat tidak hilang. Penugasan periode aktif ikut dihapus.
     */
    public function destroy(int $id): JsonResponse
    {
        $kelas = Kelas::withCount('mahasiswa')->findOrFail($id);

        if ($kelas->mahasiswa_count > 0) {
            return response()->json([
                'pesan' => "Tidak bisa dihapus: kelas sudah punya {$kelas->mahasiswa_count} mahasiswa.",
            ], 409);
        }

        // Penugasan di periode selain yang aktif dianggap riwayat
        $idAktif = Periode::yangAktif()?->id;
        $riwayat = PenugasanDosenWali::where('kelas_id', $id)
            ->when($idAktif, fn ($q) => $q->where('periode_id', '!=', $idAktif))
            ->count();
        if ($riwayat > 0) {
            return response()->json([
                'pesan' => "Tidak bisa dihapus: kelas punya riwayat dosen wali di {$riwayat} periode lain.",
            ], 409);
        }

        DB::transaction(function () use ($kelas) {
            PenugasanDosenWali::where('kelas_id', $kelas->id)->delete(); // FK restrict: penugasan dulu
            $kelas->delete();
        });

        return response()->json(['pesan' => 'Kelas dihapus.', 'id' => $id]);
    }

    /**
     * Susun balasan JSON: pesan, id, dan HTML seluruh daftar.
     * Seluruh daftar dikirim karena urutan, status lulus, dan angka bisa berubah.
     */
    private function balasan(Kelas $kelas, string $pesan, Periode $aktif, int $status = 200): JsonResponse
    {
        $semua = $this->semuaKelas($aktif);

        $html = $semua->map(fn ($k, $i) => view('admin.kelas._baris', [
            'item' => $k, 'nomor' => $i + 1,
        ])->render())->implode('');

        return response()->json(['pesan' => $pesan, 'id' => $kelas->id, 'daftar' => $html], $status);
    }
}
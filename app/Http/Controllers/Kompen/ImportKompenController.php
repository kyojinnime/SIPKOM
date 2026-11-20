<?php

namespace App\Http\Controllers\Kompen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kompen\UnggahImportRequest;
use App\Models\JumlahKompen;
use App\Models\Periode;
use App\Services\CakupanKompen;
use App\Services\Impor\PdfSiakadParser;
use App\Services\Impor\PdfTidakValidException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use App\Services\Impor\ImportValidator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Services\Impor\ImportKompenService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Import PDF Laporan Evaluasi Perkuliahan oleh dosen wali dan admin. */
class ImportKompenController extends Controller
{
    /** Langkah 1: halaman unggah. */
    public function unggah(Request $request): View
    {
        // Hanya admin dan dosen wali (policy yang sama dengan halaman Data Kompen)
        Gate::authorize('viewAny', JumlahKompen::class);

        $user = $request->user();
        $admin = CakupanKompen::semua($user);
        $periodeAktif = Periode::yangAktif();

        // Pilihan periode: admin semua (terbaru dulu; urutan enum ganjil < genap), dosen wali hanya periode aktif
        $daftarPeriode = $admin
            ? Periode::orderByDesc('tahun_ajaran')->orderByDesc('semester')->get()
            : collect([$periodeAktif])->filter();

        // Kondisi yang membuat form tidak boleh tampil
        $pesanBlokir = null;
        if (!$admin && CakupanKompen::dosenWali($user) === null) {
            $pesanBlokir = 'Data dosen wali untuk akun Anda belum terdaftar. Hubungi admin.';
        } elseif ($daftarPeriode->isEmpty()) {
            $pesanBlokir = $admin
                ? 'Belum ada periode. Buat periode di menu Kelola Data, Periode.'
                : 'Belum ada periode aktif. Hubungi admin.';
        }

        return view('kompen.import.unggah', [
            'admin'          => $admin,
            'daftarPeriode'  => $daftarPeriode,
            'periodeDipilih' => old('periode_id', $periodeAktif?->id ?? $daftarPeriode->first()?->id),
            'pesanBlokir'    => $pesanBlokir,
            'maksMb'         => UnggahImportRequest::MAKS_MB,
        ]);
    }

    /**
     * Langkah 1 ke 2: validasi berkas, lalu baca PDF.
     * SEMENTARA: hasil bacaan hanya ditampilkan sebagai ringkasan. Diganti halaman pratinjau di Langkah 8.
     */
        /**
     * Langkah 1 ke 2: validasi berkas, baca PDF, periksa terhadap database,
     * simpan rencana di cache 30 menit, lalu buka halaman pratinjau.
     */
    public function pratinjau(UnggahImportRequest $request, PdfSiakadParser $parser, ImportValidator $validator): RedirectResponse
    {
        $user = $request->user();

        // Akun dosen wali tanpa data dosen wali tidak boleh mengimpor
        if (!CakupanKompen::semua($user) && CakupanKompen::dosenWali($user) === null) {
            return back()->withErrors(['berkas' => 'Data dosen wali untuk akun Anda belum terdaftar. Hubungi admin.']);
        }

        $berkas = $request->file('berkas');
        $periode = Periode::findOrFail($request->validated('periode_id'));

        // Baca PDF; galat apa pun menjadi pesan ramah di bawah kolom berkas
        try {
            $hasil = $parser->parse($berkas->getRealPath());
        } catch (PdfTidakValidException $e) {
            return back()
                ->withInput($request->only('periode_id'))
                ->withErrors(['berkas' => $e->getMessage()]);
        }

        // Periksa terhadap database dan aturan bisnis; hasilnya "rencana" untuk pratinjau
        $rencana = $validator->analisis(
            $hasil, $periode, $user,
            $berkas->getClientOriginalName(),
            hash_file('sha256', $berkas->getRealPath())
        );

        // Simpan di cache; kunci memuat id akun sehingga pratinjau tidak bisa dibuka akun lain
        // Simpan di cache; kunci memuat id akun sehingga pratinjau tidak bisa dibuka akun lain
        $token = Str::random(40);
        $kunci = $this->kunciCache($user->id, $token);
        Cache::put($kunci, $rencana, now()->addMinutes(30));

        // Data mentah hasil baca PDF ikut disimpan: saat Simpan ditekan, pemeriksaan diulang
        // terhadap database terbaru memakai data ini
        Cache::put($kunci . ':data', [
            'hasil'       => $hasil,
            'periode_id'  => $periode->id,
            'nama_berkas' => $rencana['nama_berkas'],
            'hash'        => $rencana['hash'],
        ], now()->addMinutes(30));

        return redirect()->route('kompen.import.lihat', $token);
    }

    /** Langkah 2: tampilkan pratinjau dari cache. */
    public function lihat(Request $request, string $token): View|RedirectResponse
    {
        Gate::authorize('viewAny', JumlahKompen::class);

        $rencana = Cache::get($this->kunciCache($request->user()->id, $token));

        // Kedaluwarsa, token salah, atau milik akun lain: kembali ke halaman unggah dengan pesan ramah
        if ($rencana === null) {
            return redirect()->route('kompen.import')
                ->withErrors(['berkas' => 'Pratinjau sudah kedaluwarsa atau tidak ditemukan. Unggah ulang file Anda.']);
        }

        return view('kompen.import.pratinjau', ['rencana' => $rencana, 'token' => $token]);
    }

    /** Kunci cache pratinjau, terikat pada akun pengimpor. */
    private function kunciCache(int $userId, string $token): string
    {
        return "impor_pratinjau:{$userId}:{$token}";
    }

        /** Kunci cache ringkasan hasil simpan, terikat pada akun pengimpor. */
    private function kunciRingkasan(int $userId, string $token): string
    {
        return "impor_ringkasan:{$userId}:{$token}";
    }

    /** Langkah 2 ke 3: simpan hasil pratinjau. Hanya bisa sekali per pratinjau. */
    public function simpan(Request $request, string $token, ImportKompenService $service): RedirectResponse
    {
        Gate::authorize('viewAny', JumlahKompen::class);

        $user = $request->user();
        $kunci = $this->kunciCache($user->id, $token);
        $data = Cache::get($kunci . ':data');

        // Kedaluwarsa, sudah pernah disimpan, atau bukan milik akun ini
        if ($data === null) {
            return redirect()->route('kompen.import')
                ->withErrors(['berkas' => 'Pratinjau sudah kedaluwarsa atau sudah disimpan. Unggah ulang file Anda bila perlu.']);
        }

        // Centang dari form: nomor blok (timpa) dan id jumlah_kompen (nolkan)
        $input = $request->validate([
            'timpa'    => ['nullable', 'array'],
            'timpa.*'  => ['integer', 'min:0'],
            'nolkan'   => ['nullable', 'array'],
            'nolkan.*' => ['integer', 'min:1'],
        ]);

        // Dosen wali hanya boleh mengimpor ke periode aktif (aturan yang sama dengan halaman unggah)
        $periode = Periode::findOrFail($data['periode_id']);
        if (!CakupanKompen::semua($user) && !$periode->aktif) {
            return back()->withErrors(['simpan' => 'Periode ini tidak lagi aktif. Unggah ulang file Anda.']);
        }

        // Kunci agar klik ganda atau dua tab tidak menyimpan dua kali
        $kunciSimpan = Cache::lock("impor_simpan:{$user->id}:{$token}", 120);
        if (!$kunciSimpan->get()) {
            return back()->withErrors(['simpan' => 'Import sedang diproses. Tunggu sebentar.']);
        }

        try {
            $ringkasan = $service->simpan(
                $data['hasil'], $periode, $user, $data['nama_berkas'], $data['hash'],
                $input['timpa'] ?? [], $input['nolkan'] ?? []
            );
        } catch (Throwable $e) {
            report($e);   // galat lengkap masuk storage/logs/laravel.log
            return back()->withErrors(['simpan' => 'Penyimpanan gagal dan tidak ada data yang berubah. Coba lagi, atau hubungi admin bila terulang.']);
        } finally {
            $kunciSimpan->release();
        }

        // Berhasil: simpan ringkasan 1 jam untuk halaman selesai, hapus pratinjau agar tidak bisa disimpan ulang
        Cache::put($this->kunciRingkasan($user->id, $token), $ringkasan, now()->addHour());
        Cache::forget($kunci);
        Cache::forget($kunci . ':data');

        return redirect()->route('kompen.import.selesai', $token);
    }

    /** Langkah 3: halaman ringkasan hasil simpan. */
    public function selesai(Request $request, string $token): View|RedirectResponse
    {
        Gate::authorize('viewAny', JumlahKompen::class);

        $ringkasan = Cache::get($this->kunciRingkasan($request->user()->id, $token));
        if ($ringkasan === null) {
            return redirect()->route('kompen.import')
                ->withErrors(['berkas' => 'Ringkasan sudah tidak tersedia. Data yang sudah disimpan tetap aman.']);
        }

        return view('kompen.import.selesai', ['ringkasan' => $ringkasan, 'token' => $token]);
    }

    /** Unduh daftar baris yang tidak disimpan sebagai CSV. */
    public function galat(Request $request, string $token): StreamedResponse
    {
        Gate::authorize('viewAny', JumlahKompen::class);

        $ringkasan = Cache::get($this->kunciRingkasan($request->user()->id, $token));
        if ($ringkasan === null || empty($ringkasan['galat'])) {
            abort(404);
        }

        return response()->streamDownload(function () use ($ringkasan) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM UTF-8 agar Excel membaca karakter dengan benar
            fputcsv($out, ['Kelas', 'NIM', 'Nama', 'Jam alpa', 'Alasan']);
            foreach ($ringkasan['galat'] as $g) {
                fputcsv($out, array_map(fn ($sel) => $this->amankanSel($sel),
                    [$g['kelas'], $g['nim'], $g['nama'], $g['jam'], $g['alasan']]));
            }
            fclose($out);
        }, 'galat-import-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Mencegah injeksi rumus di Excel: sel yang diawali = + - @ diberi tanda petik di depannya.
     * Nama dan alasan berasal dari file yang diunggah, jadi tidak boleh dipercaya.
     */
    private function amankanSel(mixed $nilai): string
    {
        $teks = (string) $nilai;
        return preg_match('/^[=+\-@\t\r]/', $teks) ? "'" . $teks : $teks;
    }
}
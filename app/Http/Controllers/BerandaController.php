<?php

namespace App\Http\Controllers;

use App\Models\JumlahKompen;
use App\Models\Kelas;
use App\Models\Kompen;
use App\Models\Mahasiswa;
use App\Models\PesanKonsultasi;
use App\Models\Periode;
use App\Models\Ruang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class BerandaController extends Controller
{
    /** Panjang minimum kata kunci pencarian publik bila tidak memilih kelas. */
    private const MIN_KATA_KUNCI = 3;

    /** Jumlah hasil pencarian per halaman. */
    private const PER_HALAMAN = 10;

    /**
     * Menampilkan Beranda: statistik, pencarian jam, galeri, dan lokasi kompen.
     */
    public function index(Request $request): View
    {
        // Periode aktif menentukan data mana yang dihitung di statistik dan pencarian
        $periode = Periode::yangAktif();

        // Galeri: 6 sesi terbaru yang sudah divalidasi pengawas dan punya foto
        $galeri = Kompen::with(['mahasiswa', 'ruang'])
            ->tervalidasi()
            ->whereNotNull('dokumentasi')
            ->latest('tgl_kompen')
            ->limit(6)
            ->get();

        // Lokasi: ruang yang tersedia ditampilkan lebih dulu
        $lokasi = Ruang::with('pengawas')
            ->orderByDesc('status_tersedia')
            ->orderBy('nama_ruang')
            ->get();

        // Daftar kelas untuk dropdown pencarian
        $daftarKelas = Kelas::with('prodi')->orderBy('prodi_id')->orderBy('angkatan', 'desc')->orderBy('nama_kelas')->get();

        [$hasilCari, $pesanCari] = $this->cariJam($request, $periode);

        return view('beranda', [
            'periode'     => $periode,
            'statistik'   => $this->hitungStatistik($periode),
            'galeri'      => $galeri,
            'lokasi'      => $lokasi,
            'daftarKelas' => $daftarKelas,
            'hasilCari'   => $hasilCari,
            'pesanCari'   => $pesanCari,
        ]);
    }

    /**
     * Menyimpan pertanyaan dari formulir konsultasi lalu kembali ke bagian formulir.
     */
    public function kirimKonsultasi(Request $request): RedirectResponse
    {
        // Validasi input; pesan error otomatis dikirim ke view lewat $errors
        $data = $request->validate([
            'email'      => ['required', 'email', 'max:100'],
            'pertanyaan' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'email.required'      => 'Email wajib diisi.',
            'email.email'         => 'Format email tidak valid.',
            'pertanyaan.required' => 'Pertanyaan wajib diisi.',
            'pertanyaan.min'      => 'Pertanyaan minimal 5 karakter.',
        ]);

        PesanKonsultasi::create($data);

        return redirect()->to(route('beranda') . '#konsultasi')
            ->with('sukses', 'Pertanyaan terkirim. Admin akan menghubungi lewat email.');
    }

    /**
     * Menghitung tiga angka statistik Beranda untuk periode aktif.
     * Mahasiswa dianggap selesai bila sisa jam = 0.
     * Catatan: dihitung di PHP karena jumlahnya ratusan; bila data membesar,
     * pindahkan ke satu query agregat.
     *
     * @return array{total:int, selesai:int, belum:int}
     */
    private function hitungStatistik(?Periode $periode): array
    {
        // Belum ada periode aktif -> semua angka nol
        if (!$periode) {
            return ['total' => 0, 'selesai' => 0, 'belum' => 0];
        }

        $baris = JumlahKompen::where('periode_id', $periode->id)->denganJamTerlaksana()->get();

        $total   = $baris->count();
        $selesai = $baris->filter(fn ($b) => $b->selesai)->count();

        return ['total' => $total, 'selesai' => $selesai, 'belum' => $total - $selesai];
    }

    /**
     * Pencarian publik jam kompensasi (nama / NIM / kelas).
     * Aturan: kata kunci minimal 3 huruf, atau pilih kelas. Hasil dibatasi per halaman
     * agar daftar mahasiswa tidak bisa diunduh sekaligus.
     *
     * @return array{0: ?LengthAwarePaginator, 1: ?string} [hasil, pesan]
     */
    private function cariJam(Request $request, ?Periode $periode): array
    {
        $kataKunci = trim((string) $request->query('q', ''));
        $kelasId   = (int) $request->query('kelas', 0);

        // Tidak ada pencarian yang diminta -> bagian hasil tidak ditampilkan
        if ($kataKunci === '' && $kelasId === 0) {
            return [null, null];
        }
        if (!$periode) {
            return [null, 'Periode kompensasi belum diatur oleh admin.'];
        }
        if ($kelasId === 0 && mb_strlen($kataKunci) < self::MIN_KATA_KUNCI) {
            return [null, 'Ketik minimal ' . self::MIN_KATA_KUNCI . ' huruf nama atau NIM, atau pilih kelas.'];
        }

        // Karakter khusus LIKE diberi escape agar "%" dan "_" dibaca apa adanya
        $aman = addcslashes($kataKunci, '%_\\');

        $hasil = JumlahKompen::query()
            ->where('periode_id', $periode->id)
            ->denganJamTerlaksana()
            ->with('mahasiswa.kelas.prodi')
            ->whereHas('mahasiswa', function ($m) use ($aman, $kataKunci, $kelasId) {
                if ($kelasId > 0) {
                    $m->where('kelas_id', $kelasId);
                }
                if ($kataKunci !== '') {
                    $m->where(fn ($w) => $w->where('nama_mahasiswa', 'like', "%{$aman}%")
                        ->orWhere('nim', 'like', "%{$aman}%"));
                }
            })
            // Urut abjad nama mahasiswa lewat subquery
            ->orderBy(Mahasiswa::select('nama_mahasiswa')
                ->whereColumn('mahasiswa.id', 'jumlah_kompen.mahasiswa_id'))
            ->paginate(self::PER_HALAMAN)
            ->withQueryString();   // pertahankan ?q= dan ?kelas= saat pindah halaman

        return [$hasil, $hasil->isEmpty() ? 'Data tidak ditemukan untuk periode ini.' : null];
    }
}
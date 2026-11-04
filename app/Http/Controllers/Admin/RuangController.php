<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pengawas;
use App\Models\Ruang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Kelola Ruang (khusus admin): nama ruang, pengawas utama, pengawas kedua, dan nonaktifkan.
 * Status buka/tutup dan kuota adalah hak pengawas, jadi TIDAK diubah di sini.
 */
class RuangController extends Controller
{
    /** Halaman Kelola Ruang: daftar ruang, pilihan pengawas, dan jumlah ruang yang sedang dibuka pengawas. */
    public function index()
    {
        $ruang = $this->semuaRuang();
        $pengawas = Pengawas::with('user')->orderBy('nama_pengawas')->get();
        $dibuka = $this->hitungDibuka($ruang);

        return view('admin.ruang.index', compact('ruang', 'pengawas', 'dibuka'));
    }

    /** Semua ruang (termasuk nonaktif) urut nama; kedua pengawas dan akunnya dimuat sekaligus agar tidak N+1. */
    private function semuaRuang()
    {
        return Ruang::with(['pengawas.user', 'pengawasKedua.user'])->orderBy('nama_ruang')->get();
    }

    /** Ruang yang sedang dibuka pengawas untuk kompensasi: aktif dan berstatus tersedia. */
    private function hitungDibuka($ruang): int
    {
        return $ruang->where('aktif', true)->where('status_tersedia', true)->count();
    }

    /**
     * Aturan penugasan pengawas: akun nonaktif tidak boleh ditugaskan baru.
     * $idLama = pengawas yang sudah menjabat di kolom ini; tidak dicek ulang bila tidak diganti.
     */
    private function cekPengawasAktif(?int $idLama): \Closure
    {
        return function ($atribut, $nilai, $gagal) use ($idLama) {
            if ((int) $idLama === (int) $nilai) { return; }
            $p = Pengawas::with('user')->find($nilai);
            if ($p && !$p->user?->is_active) {
                $gagal('Akun pengawas ini nonaktif. Pilih pengawas lain.');
            }
        };
    }

    /** Aturan validasi tambah dan ubah. $lama diisi saat ubah agar nama miliknya sendiri tidak bentrok. */
    private function aturan(?Ruang $lama = null): array
    {
        return [
            'nama_ruang' => ['required', 'string', 'max:100',
                             Rule::unique('ruang', 'nama_ruang')->ignore($lama?->id)],
            // bail: berhenti di aturan pertama yang gagal, closure hanya jalan bila id ada
            'pengawas_id' => ['bail', 'required', 'integer', Rule::exists('pengawas', 'id'),
                              $this->cekPengawasAktif($lama?->pengawas_id)],
            // Pengawas kedua boleh kosong, tapi tidak boleh orang yang sama dengan pengawas utama
            'pengawas_kedua_id' => ['bail', 'nullable', 'integer', Rule::exists('pengawas', 'id'),
                                    'different:pengawas_id',
                                    $this->cekPengawasAktif($lama?->pengawas_kedua_id)],
        ];
    }

    /** Pesan galat berbahasa Indonesia. */
    private function pesan(): array
    {
        return [
            'nama_ruang.required'          => 'Nama ruang wajib diisi.',
            'nama_ruang.max'               => 'Nama ruang maksimal 100 karakter.',
            'nama_ruang.unique'            => 'Ruang dengan nama ini sudah ada.',
            'pengawas_id.required'         => 'Pengawas utama wajib dipilih.',
            'pengawas_id.exists'           => 'Pengawas yang dipilih tidak valid.',
            'pengawas_kedua_id.exists'     => 'Pengawas kedua yang dipilih tidak valid.',
            'pengawas_kedua_id.different'  => 'Pengawas kedua harus orang yang berbeda dari pengawas utama.',
        ];
    }

    /** Simpan ruang baru. Langsung aktif, berstatus Tutup, kuota kosong (pengawas yang mengatur). */
    public function store(Request $request): JsonResponse
    {
        $valid = $request->validate($this->aturan(), $this->pesan());
        $ruang = Ruang::create($valid + ['aktif' => true, 'status_tersedia' => false]);

        return $this->balasan($ruang, 'Ruang ditambahkan.', 201);
    }

    /** Simpan perubahan nama dan pengawas. Kuota, status, dan deskripsi milik pengawas, tidak disentuh. */
    public function update(Request $request, int $id): JsonResponse
    {
        $ruang = Ruang::findOrFail($id);
        $valid = $request->validate($this->aturan($ruang), $this->pesan());
        $ruang->update($valid);

        return $this->balasan($ruang, 'Ruang diperbarui.');
    }

    /**
     * Nonaktifkan atau aktifkan kembali ruang. Data tidak dihapus agar riwayat tetap utuh.
     * Saat dinonaktifkan, status ikut ditutup; saat diaktifkan kembali tetap Tutup
     * sampai pengawas membukanya sendiri.
     */
    public function toggle(int $id): JsonResponse
    {
        $ruang = Ruang::findOrFail($id);

        if ($ruang->aktif) {
            $ruang->update(['aktif' => false, 'status_tersedia' => false]);
            $pesan = 'Ruang dinonaktifkan.';
        } else {
            $ruang->update(['aktif' => true]);
            $pesan = 'Ruang diaktifkan kembali. Status masih Tutup sampai pengawas membukanya.';
        }

        return $this->balasan($ruang, $pesan);
    }

    /**
     * Susun balasan JSON: pesan, id, jumlah ruang dibuka, dan HTML seluruh daftar.
     * Seluruh daftar dikirim karena urutan nama bisa berubah setelah tambah atau ubah.
     */
    private function balasan(Ruang $ruang, string $pesan, int $status = 200): JsonResponse
    {
        $semua = $this->semuaRuang();

        $html = $semua->map(fn ($r, $i) => view('admin.ruang._baris', [
            'item' => $r, 'nomor' => $i + 1,
        ])->render())->implode('');

        return response()->json([
            'pesan'  => $pesan,
            'id'     => $ruang->id,
            'daftar' => $html,
            'dibuka' => $this->hitungDibuka($semua),
        ], $status);
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProdiController extends Controller
{
    /** Halaman Kelola Program Studi. withCount: jumlah kelas per prodi dalam satu query. */
    public function index()
    {
        $prodi = Prodi::withCount('kelas')->orderBy('nama_prodi')->get();

        return view('admin.prodi.index', compact('prodi'));
    }

    /**
     * Aturan validasi tambah dan ubah.
     * $id diisi saat ubah agar nama miliknya sendiri tidak dianggap bentrok.
     */
    private function aturan(?int $id = null): array
    {
        return [
            'nama_prodi' => ['required', 'string', 'max:100',
                             Rule::unique('prodi', 'nama_prodi')->ignore($id)],
        ];
    }

    /** Pesan galat berbahasa Indonesia. */
    private function pesan(): array
    {
        return [
            'nama_prodi.required' => 'Nama program studi wajib diisi.',
            'nama_prodi.max'      => 'Nama program studi maksimal 100 karakter.',
            'nama_prodi.unique'   => 'Program studi dengan nama ini sudah ada.',
        ];
    }

    /** Simpan prodi baru. Balasan 201 berisi pesan dan HTML satu baris. */
    public function store(Request $request): JsonResponse
    {
        $valid = $request->validate($this->aturan(), $this->pesan());
        $prodi = Prodi::create($valid);

        return $this->balasan($prodi, 'Program studi ditambahkan.', 201);
    }

    /** Simpan perubahan nama prodi. */
    public function update(Request $request, int $id): JsonResponse
    {
        $prodi = Prodi::findOrFail($id);
        $valid = $request->validate($this->aturan($id), $this->pesan());
        $prodi->update($valid);

        return $this->balasan($prodi, 'Program studi diperbarui.');
    }

    /** Hapus prodi. DITOLAK (409) bila masih dipakai kelas, supaya data kelas tidak yatim. */
    public function destroy(int $id): JsonResponse
    {
        $prodi = Prodi::findOrFail($id);

        // Cek relasi aktif sebelum menghapus
        $jumlahKelas = $prodi->kelas()->count();
        if ($jumlahKelas > 0) {
            return response()->json([
                'pesan' => "Tidak bisa dihapus: masih dipakai {$jumlahKelas} kelas.",
            ], 409);
        }

        $prodi->delete();

        return response()->json(['pesan' => 'Program studi dihapus.', 'id' => $id]);
    }

    /** Susun balasan JSON: pesan, id, dan HTML satu baris dari partial yang sama dengan halaman. */
    private function balasan(Prodi $prodi, string $pesan, int $status = 200): JsonResponse
    {
        $prodi->loadCount('kelas'); // jumlah kelas untuk teks di baris

        return response()->json([
            'pesan' => $pesan,
            'id'    => $prodi->id,
            'html'  => view('admin.prodi._baris', ['item' => $prodi])->render(),
        ], $status);
    }
}
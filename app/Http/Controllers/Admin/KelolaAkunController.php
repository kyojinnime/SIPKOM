<?php

namespace App\Http\Controllers\Admin;

use App\Models\Periode;
use App\Http\Controllers\Controller;
use App\Models\DosenWali;
use App\Models\Pengawas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class KelolaAkunController extends Controller
{
    /**
     * Halaman Kelola Akun: dosen wali dan pengawas dalam satu halaman (dua tab).
     * Datanya sedikit (puluhan baris), jadi dimuat sekaligus; pencarian dan pindah tab dikerjakan JS.
     */
    public function index()
    {
        // withCount: jumlah penugasan kelas / ruang per orang dalam satu query, bukan satu query per baris
        $dosen = DosenWali::with('user')->withCount($this->hitungArg('dosen'))->orderBy('nama_dosen')->get();
        Pengawas::with('user')->withCount($this->hitungArg('pengawas'))->orderBy('nama_pengawas')->get();

        return view('admin.kelola-akun', compact('dosen', 'pengawas'));
    }

    /** [BARU] Peta jenis akun: model, kolom nama, role, dan relasi yang dihitung. Tipe lain ditolak (404). */
    private function profil(string $tipe): array
    {
        return match ($tipe) {
            'dosen' => ['model' => DosenWali::class, 'kolomNama' => 'nama_dosen', 'role' => 'dosen_wali'],
            'pengawas' => ['model' => Pengawas::class,  'kolomNama' => 'nama_pengawas', 'role' => 'pengawas'],
            default    => abort(404),
        };
    }

    /**
     * [BARU] Argumen withCount/loadCount per jenis akun.
     * Dosen wali: hanya penugasan di periode aktif (bukan semua periode, supaya angka tidak membengkak).
     * Pengawas: semua ruangnya.
     */
    private function hitungArg(string $tipe)
    {
        if ($tipe === 'dosen') {
            $idAktif = Periode::yangAktif()?->id;
            return ['penugasan' => fn($q) => $q->where('periode_id', $idAktif)];
        }
        return ['ruang', 'ruangKedua'];
    }

    /**
     * [BARU] Aturan validasi popup. $userId diisi saat ubah agar email milik sendiri tidak dianggap bentrok.
     * Email = username (kolom username maksimal 50), jadi keduanya harus unik.
     */
    private function aturan(?int $userId = null): array
    {
        return [
            'nama'  => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email:rfc',
                'max:50',
                Rule::unique('users', 'email')->ignore($userId),
                Rule::unique('users', 'username')->ignore($userId)
            ],
        ];
    }

    /** [BARU] Pesan error berbahasa Indonesia. */
    private function pesan(): array
    {
        return [
            'nama.required'  => 'Nama wajib diisi.',
            'nama.max'       => 'Nama maksimal 100 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Format email tidak valid.',
            'email.max'      => 'Email maksimal 50 karakter.',
            'email.unique'   => 'Email sudah dipakai akun lain.',
        ];
    }

    /** [BARU] Simpan akun baru (user + profil) dalam satu transaksi, balas JSON berisi HTML satu baris. */
    public function simpan(Request $request, string $tipe): JsonResponse
    {
        $p = $this->profil($tipe);
        $valid = $request->validate($this->aturan(), $this->pesan());

        $item = DB::transaction(function () use ($valid, $p) {
            // Akun login: username = email, password awal dari config, wajib ganti saat login pertama
            $user = User::create([
                'name'                 => $valid['nama'],
                'username'             => $valid['email'],
                'email'                => $valid['email'],
                'password'             => config('sipkom.password_awal'), // di-hash otomatis oleh cast 'hashed'
                'role'                 => $p['role'],
                'is_active'            => true,
                'must_change_password' => true,
            ]);

            // Profil terhubung ke akun tadi; data lain diisi pemilik akun lewat halaman profil nanti
            return $p['model']::create([
                'user_id'        => $user->id,
                $p['kolomNama']  => $valid['nama'],
            ]);
        });

        return $this->balasan(
            $tipe,
            $item,
            "{$valid['nama']} ditambahkan. Password awal: " . config('sipkom.password_awal'),
            201
        );
    }

    /** [BARU] Simpan perubahan nama/email. Email berubah berarti username login ikut berubah. */
    public function perbarui(Request $request, string $tipe, int $id): JsonResponse
    {
        $p = $this->profil($tipe);
        $item = $p['model']::with('user')->findOrFail($id);
        $valid = $request->validate($this->aturan($item->user_id), $this->pesan());

        DB::transaction(function () use ($valid, $item, $p) {
            $item->user->update([
                'name'     => $valid['nama'],
                'username' => $valid['email'],
                'email'    => $valid['email'],
            ]);
            $item->update([$p['kolomNama'] => $valid['nama']]);
        });

        return $this->balasan($tipe, $item, 'Data akun diperbarui.');
    }

    /** [BARU] Aktifkan / nonaktifkan akun. Akun nonaktif tidak bisa login; data dan riwayat tetap aman. */
    public function toggle(string $tipe, int $id): JsonResponse
    {
        $p = $this->profil($tipe);
        $item = $p['model']::with('user')->findOrFail($id);

        // Balik status akun
        $item->user->update(['is_active' => !$item->user->is_active]);

        $pesan = $item->user->is_active ? 'Akun diaktifkan kembali.' : 'Akun dinonaktifkan.';

        // Peringatan: yang dinonaktifkan masih memegang kelas / ruang, perlu pengganti
        // Dosen: hitung penugasan di periode aktif; pengawas: semua ruangnya
        $jumlah = $tipe === 'dosen'
            ? $item->penugasan()->where('periode_id', Periode::yangAktif()?->id)->count()
            : $item->ruang()->count() + $item->ruangKedua()->count();
        if (!$item->user->is_active && $jumlah > 0) {
            $pesan .= $tipe === 'dosen'
                ? " Masih memegang {$jumlah} kelas, tetapkan dosen wali pengganti."
                : " Masih mengawasi {$jumlah} ruang, tetapkan pengawas pengganti.";
        }

        return $this->balasan($tipe, $item, $pesan);
    }

    /** [BARU] Reset password ke password awal dan wajibkan ganti saat login berikutnya. */
    public function reset(string $tipe, int $id): JsonResponse
    {
        $p = $this->profil($tipe);
        $item = $p['model']::with('user')->findOrFail($id);

        $item->user->update([
            'password'             => config('sipkom.password_awal'), // di-hash otomatis oleh cast 'hashed'
            'must_change_password' => true,
        ]);

        return response()->json([
            'pesan' => 'Password ' . $item->{$p['kolomNama']} . ' direset ke password awal: ' . config('sipkom.password_awal'),
        ]);
    }

    /** [BARU] Hapus permanen. DITOLAK (409) bila masih ada relasi; sarankan Nonaktifkan. */
    public function hapus(string $tipe, int $id): JsonResponse
    {
        $p = $this->profil($tipe);
        $item = $p['model']::with('user')->findOrFail($id);

        // Cek relasi aktif sebelum menghapus
        if ($tipe === 'dosen') {
            // Riwayat penugasan di periode mana pun menahan penghapusan agar riwayat dosen wali aman
            $tertahan = $item->penugasan()->exists();
            $alasan = 'Tidak bisa dihapus: dosen wali punya riwayat penugasan kelas. Gunakan Nonaktifkan.';
            $alasan = 'Tidak bisa dihapus: dosen wali masih memegang kelas. Gunakan Nonaktifkan.';
        } else {
            // Tabel kompen mungkin belum dimigrasi; cek hanya bila tabelnya ada
            $item->ruang()->exists() || $item->ruangKedua()->exists()
                || (Schema::hasTable('kompen') && DB::table('kompen')->where('divalidasi_oleh', $item->id)->exists());
            $alasan = 'Tidak bisa dihapus: pengawas masih punya ruang atau riwayat validasi. Gunakan Nonaktifkan.';
        }
        if ($tertahan) {
            return response()->json(['message' => $alasan], 409);
        }

        DB::transaction(function () use ($item) {
            $user = $item->user;
            $item->delete();   // profil dulu, karena user_id memakai restrictOnDelete
            $user?->delete();
        });

        return response()->json(['pesan' => 'Akun dihapus.', 'id' => $id]);
    }

    /** [BARU] Susun balasan JSON: pesan + HTML satu baris (memakai partial yang sama dengan halaman). */
    private function balasan(string $tipe, $item, string $pesan, int $status = 200): JsonResponse
    {
        $item->load('user')->loadCount($this->hitungArg($tipe));

        return response()->json([
            'pesan' => $pesan,
            'id'    => $item->id,
            'html'  => view('admin.partials.baris-akun', ['tipe' => $tipe, 'item' => $item])->render(),
        ], $status);
    }
}

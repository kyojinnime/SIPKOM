<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfilRequest;
use App\Models\DosenWali;
use App\Models\LogAktivitas;
use App\Models\Mahasiswa;
use App\Models\Pengawas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Halaman Profil Saya untuk keempat peran. */
class ProfilController extends Controller
{
    /** Menampilkan halaman profil beserta data profil per peran. */
    public function index()
    {
        $user = auth()->user();
        $profil = null;

        if ($user->role === 'dosen_wali') {
            $profil = DosenWali::where('user_id', $user->id)->first();
        } elseif ($user->role === 'pengawas') {
            $profil = Pengawas::where('user_id', $user->id)->first();
        } elseif ($user->role === 'mahasiswa') {
            $profil = Mahasiswa::with('kelas.prodi')->where('user_id', $user->id)->first();
        }

        return view('profil', compact('user', 'profil'));
    }

    /** Memperbarui data diri. Satu transaksi agar user dan profil peran selalu sinkron. */
    public function perbarui(ProfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Nilai lama untuk jejak audit
        $lama = [
            'name'    => $user->name,
            'email'   => $user->email,
            'telepon' => $user->telepon,
        ];

        DB::transaction(function () use ($user, $data, $lama) {
            // Mahasiswa tidak mengirim kolom nama (tetap dari SIAKAD)
            if (array_key_exists('nama', $data)) {
                $user->name = $data['nama'];
            }
            if (array_key_exists('email', $data)) {
                $user->email = $data['email'];
            }
            if (array_key_exists('telepon', $data)) {
                $user->telepon = $data['telepon'];
            }

            // Dosen wali dan pengawas: username selalu mengikuti email
            if (in_array($user->role, ['dosen_wali', 'pengawas'], true)) {
                $user->username = $user->email;
            }

            $user->save();

            // Profil per peran ikut diperbarui dalam transaksi yang sama
            if ($user->role === 'dosen_wali') {
                $profil = DosenWali::where('user_id', $user->id)->first();
                if ($profil) {
                    $profil->update([
                        'nama_dosen' => $user->name,
                        'nip'        => $data['nip'] ?? null,
                    ]);
                }
            } elseif ($user->role === 'pengawas') {
                $profil = Pengawas::where('user_id', $user->id)->first();
                if ($profil) {
                    $profil->update([
                        'nama_pengawas' => $user->name,
                        'nip'           => $data['nip'] ?? null,
                    ]);
                }
            }

            LogAktivitas::catat('profil_perbarui', $user, $lama, [
                'name'    => $user->name,
                'email'   => $user->email,
                'telepon' => $user->telepon,
            ]);
        });

        return response()->json([
            'pesan' => 'Profil diperbarui.',
            'nama'  => $user->fresh()->name,
        ]);
    }

    /** Mengunggah atau mengganti foto profil (JPG/PNG, maksimal 2 MB). Foto lama dihapus. */
    public function foto(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpeg,png', 'max:2048'],
        ], [
            'foto.required' => 'Foto wajib dipilih.',
            'foto.image'    => 'File harus berupa gambar.',
            'foto.mimes'    => 'Foto harus berformat JPG atau PNG.',
            'foto.max'      => 'Foto maksimal 2 MB.',
        ]);

        // Hapus foto lama agar folder tidak menumpuk
        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
        }

        $user->update(['foto' => $request->file('foto')->store('foto-profil', 'public')]);

        LogAktivitas::catat('profil_foto', $user);

        return response()->json([
            'pesan' => 'Foto profil diperbarui.',
            'url'   => $user->fresh()->url_foto,
        ]);
    }

    /** Menghapus foto profil; tampilan kembali memakai inisial. */
    public function hapusFoto(): JsonResponse
    {
        $user = auth()->user();

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
            $user->update(['foto' => null]);
            LogAktivitas::catat('profil_foto_hapus', $user);
        }

        return response()->json(['pesan' => 'Foto profil dihapus.']);
    }
}

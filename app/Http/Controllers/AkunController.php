<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AkunController extends Controller
{
    /**
     * Ganti password akun yang sedang login (dipanggil dari popup lewat fetch).
     * Aturan: password saat ini harus benar, password baru minimal 8 karakter, cocok dengan
     * konfirmasi, berbeda dari password saat ini, dan bukan password awal.
     */
    public function ubahPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Akun yang dinonaktifkan admin tidak boleh mengubah apa pun
        abort_unless($user->is_active, 403);

        $request->validate([
            // 'current_password' membandingkan dengan password akun yang sedang login
            'password_lama' => ['required', 'current_password'],
            'password_baru' => [
                'required', 'string', 'min:8', 'max:100',
                'confirmed',                // cocok dengan kolom password_baru_confirmation
                'different:password_lama',
                // Password awal (config) tidak boleh dipakai lagi
                function (string $atribut, mixed $nilai, Closure $gagal) {
                    if ($nilai === config('sipkom.password_awal')) {
                        $gagal('Password baru tidak boleh sama dengan password awal.');
                    }
                },
            ],
        ], [
            'password_lama.required'         => 'Password saat ini wajib diisi.',
            'password_lama.current_password' => 'Password saat ini salah.',
            'password_baru.required'         => 'Password baru wajib diisi.',
            'password_baru.min'              => 'Password baru minimal 8 karakter.',
            'password_baru.max'              => 'Password baru maksimal 100 karakter.',
            'password_baru.confirmed'        => 'Konfirmasi password tidak cocok.',
            'password_baru.different'        => 'Password baru harus berbeda dari password saat ini.',
        ]);

        // Simpan password baru (di-hash otomatis oleh cast 'hashed') dan matikan penanda wajib ganti
        $user->update([
            'password'             => $request->input('password_baru'),
            'must_change_password' => false,
        ]);

        // Buat ID sesi baru untuk sesi ini (pengguna tetap login)
        $request->session()->migrate();
        
        // [BISA DIBUANG] Keluarkan perangkat/browser lain yang memakai akun ini.
        // Hapus blok if di bawah bila fitur ini tidak diinginkan.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return response()->json(['pesan' => 'Password berhasil diganti.']);
    }
}
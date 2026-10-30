<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CekRole
{
    /**
     * Membatasi route hanya untuk role tertentu.
     * Pemakaian di route: ->middleware('role:admin') atau 'role:dosen_wali,pengawas'.
     * Sekalian mengeluarkan akun yang dinonaktifkan walau sesinya masih hidup.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Belum login: kembali ke Beranda
        if (!$user) {
            return redirect('/');
        }

        // Akun dinonaktifkan admin: logout paksa, bersihkan sesi, kirim pesan
        if (!$user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/')->with('error', 'Akun Anda dinonaktifkan. Hubungi admin.');
        }

        // Role tidak sesuai: 403 (akses ditolak di server, bukan sekadar menu disembunyikan)
        if (!in_array($user->role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
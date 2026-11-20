<?php

namespace App\Http\Controllers\Kompen;

use App\Http\Controllers\Controller;
use App\Models\JumlahKompen;
use App\Models\LogAktivitas;
use App\Models\Mahasiswa;
use App\Services\CakupanKompen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Reset password akun mahasiswa ke password awal oleh dosen wali (kelasnya) atau admin (semua). */
class ResetPasswordMahasiswaController extends Controller
{
    public function __invoke(Request $request, Mahasiswa $mahasiswa): JsonResponse
    {
        // Hanya admin dan dosen wali (aturan yang sama dengan halaman Data Kompen)
        Gate::authorize('viewAny', JumlahKompen::class);

        $user = $request->user();

        // Cakupan lintas periode: dosen wali hanya mahasiswa pada kelas yang pernah ditugaskan kepadanya
        abort_unless(
            CakupanKompen::mahasiswa($user, null)->whereKey($mahasiswa->getKey())->exists(),
            403
        );

        // Mahasiswa harus punya akun, dan akun itu harus berperan mahasiswa (cegah tautan user_id yang salah)
        $akun = $mahasiswa->user;
        if ($akun === null || $akun->role !== 'mahasiswa') {
            return response()->json([
                'message' => 'Mahasiswa ini belum punya akun login. Akun dibuat otomatis saat import PDF.',
            ], 422);
        }

        $awal = (string) config('sipkom.password_awal');

        DB::transaction(function () use ($akun, $mahasiswa, $user) {
            // Ganti token "ingat saya" agar cookie lama tidak bisa dipakai masuk lagi
            $akun->setRememberToken(Str::random(60));
            $akun->update([
                'password'             => config('sipkom.password_awal'),   // di-hash otomatis oleh cast
                'must_change_password' => true,
            ]);

            // Putuskan semua sesi yang sedang berjalan milik akun ini (sesi disimpan di database)
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $akun->id)->delete();
            }

            // Jejak audit: siapa mereset siapa. Password tidak dicatat.
            LogAktivitas::catat('reset_password_mahasiswa', $akun, null, [
                'nim'      => $mahasiswa->nim,
                'oleh_role' => $user->role,
            ]);
        });

        return response()->json([
            'pesan' => "Password {$mahasiswa->nama_mahasiswa} direset ke password awal: {$awal}. Wajib diganti saat login berikutnya.",
        ]);
    }
}
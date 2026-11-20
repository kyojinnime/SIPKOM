<?php

namespace App\Services;

use App\Models\DosenWali;
use App\Models\Kelas;
use App\Models\Mahasiswa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu-satunya tempat yang menentukan data mana yang boleh dilihat/diubah.
 *   - admin      : semua kelas dan mahasiswa
 *   - dosen wali : hanya kelas yang DITUGASKAN kepadanya pada periode terpilih
 *   - role lain  : tidak ada (query kosong)
 * Dipakai oleh controller (daftar) dan policy (aksi pada satu data), supaya aturannya tidak tersebar.
 */
class CakupanKompen
{
    /** Cache per request: akun -> data dosen wali, agar tidak query berulang. */
    private static array $cacheDosen = [];

    /** Data dosen wali milik akun ini; null bila akun bukan dosen wali atau datanya belum ada. */
    public static function dosenWali(User $user): ?DosenWali
    {
        if ($user->role !== 'dosen_wali') {
            return null;
        }
        return self::$cacheDosen[$user->id] ??= DosenWali::where('user_id', $user->id)->first();
    }

    /** True bila akun ini admin (cakupan tidak dibatasi). */
    public static function semua(User $user): bool
    {
        return $user->role === 'admin';
    }

    /** Query mahasiswa yang boleh diakses akun ini pada periode tersebut. */
        public static function mahasiswa(User $user, ?int $periodeId): Builder
    {
        // Admin: semua mahasiswa
        if (self::semua($user)) {
            return Mahasiswa::query();
        }

        // Bukan dosen wali (atau datanya tidak ada): query yang pasti kosong
        $dosen = self::dosenWali($user);
        if (!$dosen) {
            return Mahasiswa::query()->whereRaw('1 = 0');
        }

                // periodeId kosong = semua periode: kelas yang pernah ditugaskan kepadanya di periode mana pun
        return Mahasiswa::query()->whereHas('kelas.penugasan', fn ($q) => $q
            ->where('dosen_wali_id', $dosen->id)
            ->when($periodeId !== null, fn ($w) => $w->where('periode_id', $periodeId)));
    }

    /** Query kelas yang boleh diakses akun ini pada periode tersebut (untuk filter dan pilihan kelas). */
    public static function kelas(User $user, ?int $periodeId): Builder
    {
        if (self::semua($user)) {
            return Kelas::query();
        }

        $dosen = self::dosenWali($user);
        if (!$dosen) {
            return Kelas::query()->whereRaw('1 = 0');
        }

        return Kelas::query()->whereHas('penugasan', fn ($q) => $q
         ->where('dosen_wali_id', $dosen->id)
        ->when($periodeId !== null, fn ($w) => $w->where('periode_id', $periodeId)));
    }

    /** True bila mahasiswa ini ada dalam cakupan akun pada periode tersebut. */
    public static function bolehAkses(User $user, Mahasiswa $mahasiswa, int $periodeId): bool
    {
        return self::mahasiswa($user, $periodeId)->whereKey($mahasiswa->getKey())->exists();
    }
}
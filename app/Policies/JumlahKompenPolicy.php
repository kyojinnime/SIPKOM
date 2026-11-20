<?php

namespace App\Policies;

use App\Models\JumlahKompen;
use App\Models\User;
use App\Services\CakupanKompen;

/**
 * Hak akses pada satu baris jam kompensasi wajib (jumlah_kompen).
 * Admin lolos untuk semua. Dosen wali hanya untuk mahasiswa pada kelas yang
 * ditugaskan kepadanya di periode baris itu. Role lain selalu ditolak (403).
 */
class JumlahKompenPolicy
{
    /** Boleh membuka halaman daftar Data Kompen. */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'dosen_wali'], true);
    }

    /** Boleh melihat satu baris (detail progres). */
    public function view(User $user, JumlahKompen $baris): bool
    {
        return $this->dalamCakupan($user, $baris);
    }

    /** Boleh mengubah jam wajib. */
    public function update(User $user, JumlahKompen $baris): bool
    {
        return $this->dalamCakupan($user, $baris);
    }

    /** Boleh membatalkan kewajiban. */
    public function batalkan(User $user, JumlahKompen $baris): bool
    {
        return $this->dalamCakupan($user, $baris);
    }

    /** Pemeriksaan bersama: role berhak DAN mahasiswa baris ini ada dalam cakupan pada periode baris. */
    private function dalamCakupan(User $user, JumlahKompen $baris): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }

        $mahasiswa = $baris->mahasiswa;

        return $mahasiswa !== null
            && CakupanKompen::bolehAkses($user, $mahasiswa, (int) $baris->periode_id);
    }
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Periode;
use App\Models\Kelas;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ambil periode aktif untuk backfill
        $periodeAktif = Periode::where('aktif', true)->first();
        // Tolak migrasi bila sudah ada kelas tetapi belum ada periode aktif. Tanpa periode aktif,
        // penugasan tidak bisa disalin, sedangkan kolom dosen_wali_id tetap dihapus di bawah,
        // sehingga hubungan kelas ke dosen wali hilang tanpa jejak.
        if (!$periodeAktif && Kelas::count() > 0) {
            throw new \RuntimeException('Aktifkan satu periode dulu (tabel periode, kolom aktif = 1) sebelum menjalankan migrasi ini.');
        }

        if ($periodeAktif) {
            // 2. Pindahkan penugasan dari tabel kelas ke tabel penugasan_dosen_wali
            $semuaKelas = Kelas::all();
            foreach ($semuaKelas as $kelas) {
                DB::table('penugasan_dosen_wali')->insert([
                    'kelas_id' => $kelas->id,
                    'periode_id' => $periodeAktif->id,
                    'dosen_wali_id' => $kelas->dosen_wali_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Hapus kolom dosen_wali_id dari kelas
        Schema::table('kelas', function (Blueprint $table) {
            $table->dropForeign(['dosen_wali_id']);
            $table->dropColumn('dosen_wali_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Kembalikan kolom dosen_wali_id ke kelas
        Schema::table('kelas', function (Blueprint $table) {
            $table->foreignId('dosen_wali_id')->nullable()->constrained('dosen_wali')->restrictOnDelete();
        });

        // 2. Kembalikan data dari penugasan_dosen_wali
        $penugasan = DB::table('penugasan_dosen_wali')->get();
        foreach ($penugasan as $item) {
            DB::table('kelas')->where('id', $item->kelas_id)->update(['dosen_wali_id' => $item->dosen_wali_id]);
        }
    }
};

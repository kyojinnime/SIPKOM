<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah penanda status akun pada tabel users.
     * Kolom 'role' sudah ada dari migrasi sebelumnya, jadi kolom baru diletakkan setelahnya.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // [BARU] Akun nonaktif tidak bisa login (dosen diganti, mahasiswa DO, dll).
            $table->boolean('is_active')->default(true)->after('role');

            // [BARU] true = pengguna dipaksa ganti password saat login (password awal masih dipakai).
            $table->boolean('must_change_password')->default(true)->after('is_active');
        });
    }

    /** Membatalkan migrasi: hapus dua kolom tadi. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'must_change_password']);
        });
    }
};
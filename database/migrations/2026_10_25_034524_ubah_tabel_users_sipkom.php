<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambah kolom username dan role pada tabel users bawaan Laravel.
     * Tabel ini menggantikan tabel Login + Registrasi dari class diagram.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Username untuk login (unik). Nullable agar data lama tidak error saat migrasi.
            $table->string('username', 50)->nullable()->unique()->after('id');

            // Hak akses pengguna: menentukan dashboard dan fitur yang boleh dibuka.
            $table->enum('role', ['admin', 'dosen_wali', 'pengawas', 'mahasiswa'])
                ->default('mahasiswa')->after('password');

            // Email tidak wajib, karena login memakai username.
            $table->string('email')->nullable()->change();
        });
    }

    /** Mengembalikan tabel users seperti semula. */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
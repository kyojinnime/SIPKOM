<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabel dosen wali. Kelas yang diampu dicatat di tabel kelas, bukan di sini. */
    public function up(): void
    {
        Schema::create('dosen_wali', function (Blueprint $table) {
            $table->id();
            // Akun login dosen wali; tidak boleh dihapus selama masih terhubung.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('nama_dosen', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dosen_wali');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel kelas = satu angkatan dalam satu prodi.
     * Dosen wali melekat ke kelas ini dari awal sampai lulus,
     * walau tingkatnya naik (1A -> 2A -> 3A tetap satu baris).
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();
            $table->foreignId('dosen_wali_id')->constrained('dosen_wali')->restrictOnDelete();
            $table->unsignedSmallInteger('angkatan');   // tahun masuk, mis. 2025
            $table->string('nama_kelas', 10);           // huruf kelas, mis. "A"
            $table->timestamps();

            // Mencegah kelas ganda; kelas "A" di prodi lain tetap boleh.
            $table->unique(['prodi_id', 'angkatan', 'nama_kelas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
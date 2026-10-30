<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jam kompensasi wajib: satu baris per mahasiswa per periode (Cara A).
     * Diisi lewat import dari surat kompensasi.
     */
    public function up(): void
    {
        Schema::create('jumlah_kompen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('periode_id')->constrained('periode')->restrictOnDelete();
            $table->unsignedSmallInteger('jumlah_jam');   // total jam wajib, mis. 16 / 32 / 48
            $table->string('keterangan', 100)->nullable(); // mis. "SP II"
            $table->timestamps();

            // Dasar upsert saat import: import ulang tidak membuat data ganda.
            $table->unique(['mahasiswa_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jumlah_kompen');
    }
};
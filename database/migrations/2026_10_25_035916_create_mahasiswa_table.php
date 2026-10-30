<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tabel mahasiswa. Prodi dan semester dihitung lewat tabel kelas. */
    public function up(): void
    {
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            // Kosong sampai mahasiswa registrasi akun; akun dihapus -> kolom jadi null.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nim', 20)->unique();
            $table->string('nama_mahasiswa', 100)->index();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};
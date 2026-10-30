<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel periode (tahun ajaran + semester).
     * Satu periode ditandai aktif; statistik Beranda memakai periode ini.
     */
    public function up(): void
    {
        Schema::create('periode', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran', 9);                   // format "2026/2027"
            $table->enum('semester', ['ganjil', 'genap']);
            $table->boolean('aktif')->default(false);
            $table->timestamps();

            $table->unique(['tahun_ajaran', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode');
    }
};
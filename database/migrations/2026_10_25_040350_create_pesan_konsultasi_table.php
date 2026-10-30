<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pesan dari formulir "ada pertanyaan..?" di Beranda. */
    public function up(): void
    {
        Schema::create('pesan_konsultasi', function (Blueprint $table) {
            $table->id();
            $table->string('email', 100);
            $table->text('pertanyaan');
            $table->boolean('sudah_dibaca')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan_konsultasi');
    }
};
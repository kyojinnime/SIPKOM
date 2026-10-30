<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Model pesan dari formulir konsultasi di Beranda. */
class PesanKonsultasi extends Model
{
    protected $table = 'pesan_konsultasi';
    protected $fillable = ['email', 'pertanyaan', 'sudah_dibaca'];
    protected $casts = ['sudah_dibaca' => 'boolean'];
}
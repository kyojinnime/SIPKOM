<?php

/**
 * Data statis SIPKOM yang tampil di Beranda (kontak, media sosial).
 * Ubah di sini bila ada perubahan; tidak perlu tabel database.
 * Panggil dengan config('sipkom.kontak.telepon').
 */
return [
    'nama'  => 'Sistem Pengelolaan Kompensasi Mahasiswa',
    'kontak' => [
        'nama'      => 'Admin SIPKOM',
        'telepon'   => '0852 7207 3300',
        'email'     => 'labsipolbeng@gmail.com',   // PERIKSA: terbaca dari mockup, mohon dicek ejaannya
        'instagram' => 'SIPKOM Polbeng',
    ],

    // [BARU] Password awal semua akun baru; wajib diganti saat login pertama.
    // Bisa diubah lewat .env: SIPKOM_PASSWORD_AWAL=xxxx
    'password_awal' => env('SIPKOM_PASSWORD_AWAL', '12345678'),
];
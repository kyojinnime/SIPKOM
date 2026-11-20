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

    // [BARU] Ambang jam alpa (B04 dan B05, usulan menunggu klien).
    // Mahasiswa dengan jam >= ambang_notifikasi_jam mendapat notifikasi; >= ambang_merah_jam ditandai merah.
    'ambang_notifikasi_jam' => 16,
    'ambang_merah_jam'      => 64,

    // [BARU] Kop dan judul rekap Excel (Langkah 14). Teksnya dari contoh klien; ubah di sini bila ada perubahan.
    // Penulisan "http.://" pada contoh klien saya betulkan menjadi "http://".
    'kop' => [
        'baris' => [
            'KEMENTERIAN PENDIDIKAN TINGGI, SAINS DAN TEKNOLOGI',
            'POLITEKNIK NEGERI BENGKALIS',
            'Jalan Bathin Alam, Sungaialam, Bengkalis, Riau 28711',
            'Telepon: (+62766) 24566, Fax: (+62766) 800 1000',
            'Laman: http://www.polbeng.ac.id, E-mail: polbeng@polbeng.ac.id',
        ],
        'logo'      => 'images/logo-polbeng.png',   // relatif terhadap folder public
        'jurusan'   => 'JURUSAN TEKNIK INFORMATIKA',
        'institusi' => 'POLITEKNIK NEGERI BENGKALIS',
    ],
];
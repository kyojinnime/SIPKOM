<?php

use App\Http\Controllers\BerandaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\KelolaAkunController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\AkunController;
use App\Http\Controllers\Kompen\DataKompenController;
use App\Http\Controllers\Kompen\ImportKompenController;
use App\Http\Controllers\Kompen\JamKompenController;
use App\Http\Controllers\Kompen\ProgresMahasiswaController;
use App\Http\Controllers\Kompen\ResetPasswordMahasiswaController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\Kompen\RekapSisaController;

// Beranda publik; juga menangani pencarian jam kompensasi lewat query string (?q=&kelas=)
Route::get('/', [BerandaController::class, 'index'])->name('beranda');

// Kirim pertanyaan dari formulir konsultasi; dibatasi 5 kiriman per menit per pengguna
Route::post('/konsultasi', [BerandaController::class, 'kirimKonsultasi'])
    ->middleware('throttle:5,1')
    ->name('konsultasi.kirim');

// ===== Login dan keluar =====
// 'guest': pengguna yang sudah login dialihkan keluar dari halaman login
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'tampil'])->name('login');
    Route::post('/login', [LoginController::class, 'proses'])->name('login.proses');
});

// Keluar lewat POST + CSRF, bukan tautan biasa
Route::post('/logout', [LoginController::class, 'keluar'])->middleware('auth')->name('logout');

// Ganti password akun sendiri (dari popup). Dibatasi 5 percobaan per menit karena
// kolom "password saat ini" bisa dipakai menebak password pada sesi yang ditinggal terbuka.
Route::put('/akun/password', [AkunController::class, 'ubahPassword'])
    ->middleware(['auth', 'throttle:5,1'])->name('akun.password');

// ===== Notifikasi dalam aplikasi: semua role (role: juga mengeluarkan akun yang dinonaktifkan) =====
// Setiap akun hanya bisa mengakses notifikasi miliknya; milik orang lain menghasilkan 404.
Route::middleware(['auth', 'role:admin,dosen_wali,pengawas,mahasiswa'])->prefix('notifikasi')->name('notifikasi.')->group(function () {
    Route::get('/', [NotifikasiController::class, 'index'])->name('index');

    // Data lonceng (JSON) dan tandai semua dibaca
    Route::get('ringkas', [NotifikasiController::class, 'ringkas'])
        ->middleware('throttle:60,1')->name('ringkas');
    Route::post('dibaca-semua', [NotifikasiController::class, 'dibacaSemua'])
        ->middleware('throttle:20,1')->name('dibaca_semua');

    // Satu notifikasi: tandai dibaca, dan buka (tandai dibaca lalu menuju tautannya)
    Route::post('{id}/dibaca', [NotifikasiController::class, 'dibaca'])
        ->whereNumber('id')->middleware('throttle:60,1')->name('dibaca');
    Route::get('{id}/buka', [NotifikasiController::class, 'buka'])
        ->whereNumber('id')->name('buka');
});

    // ===== Area Data Kompen: dosen wali dan admin =====
// Cakupan data (kelas mana yang boleh dilihat) dibatasi di controller dan policy, bukan di sini.
Route::middleware(['auth', 'role:dosen_wali,admin'])->prefix('data-kompen')->name('kompen.')->group(function () {
    Route::get('/', [DataKompenController::class, 'index'])->name('index');

    // Import PDF: halaman unggah, dan baca file (dibatasi 10 permintaan per menit)
    Route::get('import', [ImportKompenController::class, 'unggah'])->name('import');
    Route::post('import/pratinjau', [ImportKompenController::class, 'pratinjau'])
        ->middleware('throttle:10,1')->name('import.pratinjau');

    // Halaman pratinjau (token 40 karakter dari cache, hanya milik pengimpor)
    Route::get('import/{token}', [ImportKompenController::class, 'lihat'])
        ->where('token', '[A-Za-z0-9]{40}')->name('import.lihat');

    // Simpan hasil pratinjau (dibatasi 6 per menit), halaman ringkasan, dan unduhan CSV galat
    Route::post('import/{token}/simpan', [ImportKompenController::class, 'simpan'])
        ->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:6,1')->name('import.simpan');
    Route::get('import/{token}/selesai', [ImportKompenController::class, 'selesai'])
        ->where('token', '[A-Za-z0-9]{40}')->name('import.selesai');
    Route::get('import/{token}/galat.csv', [ImportKompenController::class, 'galat'])
        ->where('token', '[A-Za-z0-9]{40}')->name('import.galat');

    // Detail progres satu mahasiswa
    Route::get('mahasiswa/{mahasiswa}', [ProgresMahasiswaController::class, 'tampil'])
        ->whereNumber('mahasiswa')->name('mahasiswa');

    // Reset password mahasiswa ke password awal (dosen wali: kelasnya, admin: semua); dibatasi 10 permintaan per menit
    Route::patch('mahasiswa/{mahasiswa}/reset-password', ResetPasswordMahasiswaController::class)
        ->whereNumber('mahasiswa')->middleware('throttle:10,1')->name('reset');

    // Unduh rekap sisa kompensasi (Excel); dibatasi 10 per menit karena membuat berkas
    Route::get('rekap-sisa', RekapSisaController::class)
        ->middleware('throttle:10,1')->name('rekap');

    // Ubah jam wajib dan batalkan kewajiban (satu baris jumlah_kompen); dibatasi 30 permintaan per menit
    Route::put('{jumlahKompen}', [JamKompenController::class, 'ubah'])
        ->whereNumber('jumlahKompen')->middleware('throttle:30,1')->name('ubah');
    Route::patch('{jumlahKompen}/batal', [JamKompenController::class, 'batalkan'])
        ->whereNumber('jumlahKompen')->middleware('throttle:30,1')->name('batal');
});

// ===== Area Admin: hanya akun aktif ber-role admin =====
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Halaman Kelola Akun: sekarang lewat controller agar data dosen wali dan pengawas ikut dimuat
    Route::get('kelola-akun', [KelolaAkunController::class, 'index'])->name('akun');
    // [BARU] Simpan dan ubah akun dari popup. {tipe} hanya boleh dosen / pengawas.
    Route::post('kelola-akun/{tipe}', [KelolaAkunController::class, 'simpan'])
        ->whereIn('tipe', ['dosen', 'pengawas'])->name('akun.simpan');
    Route::put('kelola-akun/{tipe}/{id}', [KelolaAkunController::class, 'perbarui'])
        ->whereIn('tipe', ['dosen', 'pengawas'])->whereNumber('id')->name('akun.ubah');
    // [BARU] Aksi pada baris: aktif/nonaktif, reset password, hapus
    Route::patch('kelola-akun/{tipe}/{id}/toggle', [KelolaAkunController::class, 'toggle'])
        ->whereIn('tipe', ['dosen', 'pengawas'])->whereNumber('id')->name('akun.toggle');
    Route::patch('kelola-akun/{tipe}/{id}/reset', [KelolaAkunController::class, 'reset'])
        ->whereIn('tipe', ['dosen', 'pengawas'])->whereNumber('id')->name('akun.reset');
    Route::delete('kelola-akun/{tipe}/{id}', [KelolaAkunController::class, 'hapus'])
        ->whereIn('tipe', ['dosen', 'pengawas'])->whereNumber('id')->name('akun.hapus');

    // Routes for Master Data (Kelola Data)
// [UBAH] Prodi hanya butuh index, store, update, destroy (form lewat dialog, bukan halaman terpisah)
Route::resource('prodi', \App\Http\Controllers\Admin\ProdiController::class)
    ->only(['index', 'store', 'update', 'destroy']);    
// [UBAH] Kelas: hanya index, store, update, destroy (form lewat dialog)
Route::resource('kelas', \App\Http\Controllers\Admin\KelasController::class)
    ->only(['index', 'store', 'update', 'destroy']);// [UBAH] Periode: hanya index, store, update, destroy
Route::resource('periode', \App\Http\Controllers\Admin\PeriodeController::class)
    ->only(['index', 'store', 'update', 'destroy']);
// [BARU] Jadikan periode aktif
Route::patch('periode/{id}/aktifkan', [\App\Http\Controllers\Admin\PeriodeController::class, 'aktifkan'])
    ->whereNumber('id')->name('periode.aktifkan');    // [UBAH] Ruang: route destroy diganti toggle (nonaktifkan / aktifkan kembali)
Route::get('ruang', [\App\Http\Controllers\Admin\RuangController::class, 'index'])->name('ruang.index');
Route::post('ruang', [\App\Http\Controllers\Admin\RuangController::class, 'store'])->name('ruang.store');
Route::put('ruang/{id}', [\App\Http\Controllers\Admin\RuangController::class, 'update'])->whereNumber('id')->name('ruang.update');
Route::patch('ruang/{id}/toggle', [\App\Http\Controllers\Admin\RuangController::class, 'toggle'])->whereNumber('id')->name('ruang.toggle');
});


<?php

use App\Http\Controllers\BerandaController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admin\KelolaAkunController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\AkunController;

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
    ->whereNumber('id')->name('periode.aktifkan');    Route::get('ruang', [\App\Http\Controllers\Admin\RuangController::class, 'index'])->name('ruang.index');
    // [UBAH] Ruang: route destroy diganti toggle (nonaktifkan / aktifkan kembali)
Route::get('ruang', [\App\Http\Controllers\Admin\RuangController::class, 'index'])->name('ruang.index');
Route::post('ruang', [\App\Http\Controllers\Admin\RuangController::class, 'store'])->name('ruang.store');
Route::put('ruang/{id}', [\App\Http\Controllers\Admin\RuangController::class, 'update'])->whereNumber('id')->name('ruang.update');
Route::patch('ruang/{id}/toggle', [\App\Http\Controllers\Admin\RuangController::class, 'toggle'])->whereNumber('id')->name('ruang.toggle');
});

// ===== SEMENTARA: hapus semuanya saat halaman login sudah dibuat =====

// Masuk cepat sebagai admin, HANYA di environment local
if (app()->environment('local')) {
    Route::get('/dev-login', function () {
        Auth::login(User::where('username', 'admin')->firstOrFail());
        return redirect()->route('admin.akun'); // langsung ke halaman Kelola Akun
    });
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /** Jumlah percobaan gagal sebelum login dikunci. */
    private const MAKS_PERCOBAAN = 5;

    /** Lama kunci dalam detik. */
    private const DETIK_KUNCI = 60;

    /** Menampilkan halaman login. */
    public function tampil()
    {
        return view('auth.login');
    }

    /** Memproses login: validasi, batas percobaan, cek password, cek akun aktif, buat sesi. */
    public function proses(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:100'],
        ], [
            'username.required' => 'NIM atau email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $username = trim($data['username']);

        // Cari akun lewat username dulu; bila tidak ada, coba lewat email.
        // Admin bisa masuk dengan "admin" atau dengan emailnya.
        $user = User::where('username', $username)->first()
            ?? User::where('email', $username)->first();

        // Kunci pembatas: satu akun dihitung satu, apa pun nama yang diketik (username atau email),
        // supaya bergantian memakai keduanya tidak menggandakan jatah percobaan.
        // Nama yang tidak terdaftar dihitung per teks yang diketik. Keduanya digabung dengan alamat IP.
        $kunci = ($user ? 'akun' . $user->id : 'tamu' . Str::lower($username)) . '|' . $request->ip();

        // Sudah terlalu banyak gagal: tolak dan kirim sisa detik untuk hitung mundur di layar
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            $detik = RateLimiter::availableIn($kunci);
            return $this->gagal($request, "Terlalu banyak percobaan. Coba lagi dalam {$detik} detik.", $detik);
        }

        // Hash tetap dihitung walau username tidak ada, supaya waktu respons sama
        // dan orang luar tidak bisa menebak username yang terdaftar dari kecepatan balasan.
        if ($user) {
            $benar = Hash::check($data['password'], $user->password);
        } else {
            Hash::make($data['password']);
            $benar = false;
        }

        // Username atau password salah: pesan umum, tidak menyebut mana yang salah
        if (!$benar) {
            RateLimiter::hit($kunci, self::DETIK_KUNCI);
            return $this->gagal($request, 'Username atau password salah.');
        }

        // Kredensial benar tetapi akun dinonaktifkan admin
        if (!$user->is_active) {
            return $this->gagal($request, 'Akun Anda dinonaktifkan. Hubungi admin.');
        }

        // Berhasil: bersihkan hitungan gagal, buat sesi baru (cegah session fixation)
        RateLimiter::clear($kunci);
        Auth::login($user);
        $request->session()->regenerate();

        // Ke halaman tujuan semula bila ada, kalau tidak ke Beranda.
        // 'ganti_password' dibaca popup wajib ganti password (Langkah 8) pada halaman berikutnya.
        return redirect()->intended(route('beranda'))
            ->with('ganti_password', (bool) $user->must_change_password);
    }

    /** Keluar: hapus sesi dan token CSRF, kembali ke Beranda. */
    public function keluar(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda');
    }

    /** Kembali ke form dengan satu pesan galat; username tetap terisi, password tidak. */
    private function gagal(Request $request, string $pesan, int $detik = 0): RedirectResponse
    {
        return back()
            ->withInput($request->only('username'))
            ->withErrors(['username' => $pesan])
            ->with('kunci_detik', $detik);
    }
}
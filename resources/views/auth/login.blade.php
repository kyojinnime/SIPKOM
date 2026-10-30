<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SIPKOM</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>

<body>

    {{-- Halaman login tanpa header, mengikuti desain Figma --}}
    <main class="login">
        <div class="login__isi kontainer">

            {{-- Kolom kiri: judul dan kartu login --}}
            <div class="login__kiri">
                <h1>SIPKOM</h1>
                <h2>SISTEM PENGELOLAAN KOMPENSASI MAHASISWA</h2>

                <div class="kartu-login">
                    <img src="{{ asset('images/logo-sipkom.png') }}" alt="Logo SIPKOM" class="kartu-login__logo">
                    <h3>Log In</h3>

                    {{-- data-kunci: sisa detik terkunci dari server, dipakai JS untuk hitung mundur --}}
                    <form method="POST" action="{{ route('login.proses') }}" id="form-login" novalidate
                        data-kunci="{{ (int) session('kunci_detik', 0) }}">
                        @csrf

                        {{-- Satu pesan galat (umum) di atas kolom --}}
                        @if ($errors->any())
                            <div class="galat-umum" role="alert" data-galat>{{ $errors->first() }}</div>
                        @endif

                        <div class="kolom">
                            <label class="sr-only" for="username">NIM, email, atau username</label>
                            <input type="text" id="username" name="username" value="{{ old('username') }}"
                                placeholder="Masukkan NIM, email, atau username..." maxlength="50"
                                autocomplete="username" autofocus required>
                        </div>

                        <div class="kolom">
                            <label class="sr-only" for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Masukkan password..."
                                maxlength="100" autocomplete="current-password" required>

                            {{-- Tombol mata: tampilkan / sembunyikan password --}}
                            <button type="button" class="kolom__mata" data-mata aria-label="Tampilkan password"
                                aria-pressed="false">
                                <svg class="ikon-mata" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="ikon-mata-coret" viewBox="0 0 24 24" aria-hidden="true">
                                    <path
                                        d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                    <line x1="1" y1="1" x2="23" y2="23" />
                                </svg>
                            </button>
                        </div>

                        <button type="submit" class="login__kirim" data-kirim>Submit</button>
                    </form>

                    <div class="tautan">
                        {{-- Fitur lupa password dibuat di tahap berikutnya; sementara tampil toast --}}
                        <a href="#" data-lupa>Lupa Password</a>
                    </div>
                </div>

                <a href="{{ route('beranda') }}" class="kembali">&lsaquo; Kembali ke Beranda</a>
            </div>

            {{-- Kolom kanan: foto gedung --}}
            <div class="login__kanan">
                <img src="{{ asset('images/hero.png') }}" alt="Mahasiswa di depan gedung Politeknik Negeri Bengkalis">
            </div>
        </div>
    </main>

    @include('partials.footer')

    {{-- Toast dipakai untuk pesan "Lupa Password" sementara --}}
    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/login.js') }}"></script>
</body>

</html>

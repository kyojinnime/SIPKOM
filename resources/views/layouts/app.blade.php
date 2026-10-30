<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    {{-- viewport wajib agar tampilan menyesuaikan lebar layar smartphone --}}
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul', 'SIPKOM') - Sistem Pengelolaan Kompensasi Mahasiswa</title>

    {{-- Font Poppins; bila offline, browser memakai font sans-serif bawaan --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    {{-- CSS dasar (variabel warna, header, footer, tombol) lalu CSS khusus halaman --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        {{-- Gaya popup keamanan akun: hanya untuk akun yang masih memakai password awal --}}
    @if (auth()->check() && auth()->user()->must_change_password)
        <link rel="stylesheet" href="{{ asset('css/ganti-password.css') }}">
    @endif
    @stack('css')
</head>

<body>
    @include('partials.header')

    <main>
        @yield('konten')
    </main>

    @include('partials.footer')

    {{-- Pesan kecil (toast) untuk fitur yang butuh login --}}
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
        {{-- Popup peringatan dan ganti password --}}
    @if (auth()->check() && auth()->user()->must_change_password)
        @include('partials.ganti-password')
    @endif

    <script src="{{ asset('js/app.js') }}"></script>
    {{-- [BARU] dropdown akun; dimuat di semua halaman karena header ada di semua halaman --}}
    <script src="{{ asset('js/akun-menu.js') }}"></script>
    @if (auth()->check() && auth()->user()->must_change_password)
        <script src="{{ asset('js/ganti-password.js') }}"></script>
    @endif
    @stack('js')
</body>

</html>

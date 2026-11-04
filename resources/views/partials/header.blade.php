{{-- Header: logo, menu, tombol Login/Registrasi. Menu selain Beranda abu-abu sampai login. --}}
@php
    // Daftar menu; 'aktif' = bisa diklik tanpa login
    $menu = [
        ['nama' => 'Beranda', 'url' => route('beranda'), 'aktif' => true],
        ['nama' => 'Data Kompen', 'url' => null, 'aktif' => false],
        ['nama' => 'Lokasi Kompen', 'url' => null, 'aktif' => false],
        ['nama' => 'Tentang Sipkom', 'url' => null, 'aktif' => false],
    ];
    $labelRole = [
        'admin' => 'Admin',
        'dosen_wali' => 'Dosen Wali',
        'pengawas' => 'Pengawas Lapangan',
        'mahasiswa' => 'Mahasiswa',
    ];
@endphp

<header class="header">
    <div class="header__isi kontainer">
        {{-- Logo dan nama sistem --}}
        <a href="{{ route('beranda') }}" class="brand">
            <img src="{{ asset('images/logo-sipkom.png') }}" alt="Logo SIPKOM" class="brand__logo">
            <span class="brand__nama">SISTEM PENGELOLAAN<br>KOMPENSASI MAHASISWA</span>
        </a>

        {{-- Tombol hamburger: hanya tampil di layar kecil --}}
        <button type="button" class="header__toggle" id="menuToggle" aria-label="Buka menu" aria-expanded="false"
            aria-controls="menuPanel">
            <span></span><span></span><span></span>
        </button>

        {{-- Panel berisi menu dan tombol; di layar kecil menjadi dropdown --}}
        <div class="header__panel" id="menuPanel">
            <nav class="nav" aria-label="Menu utama">
                @foreach ($menu as $m)
                    @if ($m['aktif'])
                        <a href="{{ $m['url'] }}" 
                           class="nav__link {{ request()->is('/') || request()->routeIs('beranda') ? 'nav__link--aktif' : '' }}">
                            {{ $m['nama'] }}
                        </a>
                    @else
                        {{-- Menu nonaktif: abu-abu, memunculkan pesan "login dulu" saat disentuh --}}
                        <span class="nav__link nav__link--nonaktif" role="link" aria-disabled="true" tabindex="0"
                            data-butuh-login>{{ $m['nama'] }}</span>
                    @endif
                @endforeach
                {{-- [BARU] Menu khusus admin: tidak dirender untuk role lain, jadi tidak ada link-nya sama sekali --}}
                @auth
                    @if (auth()->user()->role === 'admin')
                        <a href="{{ route('admin.akun') }}"
                            class="nav__link nav__link--admin {{ request()->routeIs('admin.akun') ? 'nav__link--aktif' : '' }}">Kelola
                            Akun</a>
                        {{-- Dropdown Kelola Data --}}
                        <div class="nav-dropdown" data-nav-dropdown>
                            <button type="button" class="nav__link nav__link--dropdown {{ request()->routeIs('admin.prodi.*', 'admin.kelas.*', 'admin.periode.*', 'admin.ruang.*') ? 'nav__link--aktif' : '' }}" data-nav-dropdown-tombol
                                    aria-haspopup="true" aria-expanded="false">
                                Kelola Data
                            </button>
                            <div class="nav-dropdown__menu" data-nav-dropdown-menu hidden>
                                <a href="{{ route('admin.prodi.index') }}" class="nav-dropdown__item">Program Studi</a>
                                <a href="{{ route('admin.kelas.index') }}" class="nav-dropdown__item">Kelas</a>
                                <a href="{{ route('admin.periode.index') }}" class="nav-dropdown__item">Periode</a>
                                <a href="{{ route('admin.ruang.index') }}" class="nav-dropdown__item">Ruang Kompen</a>
                            </div>
                        </div>
                    @endif
                @endauth
            </nav>

            {{-- Login dan Registrasi --}}
            <div class="auth">
                @guest
                    <a href="{{ route('login') }}" class="btn btn--kecil" id="tombolLogin">Login</a>
                @endguest
                
                @auth
                    <div class="akun" data-akun>
                        <button type="button" class="akun__tombol" data-akun-tombol
                                aria-haspopup="true" aria-expanded="false">
                            <span class="akun__inisial" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>

                            @if (auth()->user()->must_change_password)
                                <span class="akun__titik" data-sandi-pengingat title="Password awal belum diganti"></span>
                            @endif

                            <span class="akun__nama">{{ explode(' ', auth()->user()->name)[0] }}</span>
                        </button>

                        <div class="akun__menu" data-akun-menu hidden>
                            <div class="akun__info">
                                <strong>{{ auth()->user()->name }}</strong>
                                <small>{{ $labelRole[auth()->user()->role] ?? auth()->user()->role }}</small>
                            </div>

                            @if (auth()->user()->must_change_password)
                                <div class="akun__pengingat" data-sandi-pengingat>Password awal belum diganti</div>
                                <button type="button" class="akun__item" data-buka-sandi data-sandi-pengingat>Ganti Password</button>
                            @endif

                            @if (Route::has('logout'))
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="akun__item">Keluar</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</header>

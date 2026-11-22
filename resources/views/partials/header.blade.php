{{-- Header: logo, menu, tombol Login/Registrasi. Menu selain Beranda abu-abu sampai login. --}}
@php
    // Daftar menu; 'aktif' = bisa diklik tanpa login
        // Jumlah notifikasi belum dibaca untuk badge lonceng (satu COUNT terindeks; 0 untuk tamu)
    $jumlahNotif = auth()->check() ? app(\App\Services\NotifikasiService::class)->jumlahBelumDibaca(auth()->id()) : 0;

    // Role yang sudah bisa membuka Data Kompen (Sprint 2: dosen wali dan admin)
    $bisaDataKompen = auth()->check() && in_array(auth()->user()->role, ['admin', 'dosen_wali'], true);

    // Daftar menu; 'aktif' = bisa diklik; 'pola' = pola nama rute agar hanya menu yang sedang dibuka yang menyala
    $menu = [
        ['nama' => 'Beranda', 'url' => route('beranda'), 'aktif' => true, 'pola' => 'beranda'],
        ['nama' => 'Data Kompen', 'url' => $bisaDataKompen ? route('kompen.index') : null, 'aktif' => $bisaDataKompen, 'pola' => 'kompen.*'],
        ['nama' => 'Lokasi Kompen', 'url' => null, 'aktif' => false, 'pola' => 'lokasi.*'],
        ['nama' => 'Tentang Sipkom', 'url' => null, 'aktif' => false, 'pola' => 'tentang.*'],
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
                           class="nav__link {{ request()->routeIs($m['pola']) ? 'nav__link--aktif' : '' }}">                            
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
                    {{-- Lonceng notifikasi: badge dari server, daftar dimuat saat dibuka (notifikasi.js) --}}
                    <div class="lonceng" data-lonceng
                         data-url-ringkas="{{ route('notifikasi.ringkas') }}"
                         data-url-semua="{{ route('notifikasi.dibaca_semua') }}">
                        <button type="button" class="lonceng__tombol" data-lonceng-tombol
                                aria-haspopup="true" aria-expanded="false"
                                aria-label="Notifikasi{{ $jumlahNotif > 0 ? ', ' . $jumlahNotif . ' belum dibaca' : '' }}">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10.3 20a1.9 1.9 0 0 0 3.4 0"/></svg>
                            <span class="lonceng__teks">Notifikasi</span>
                            <span class="lonceng__badge" data-lonceng-badge @if ($jumlahNotif === 0) hidden @endif>{{ $jumlahNotif > 99 ? '99+' : $jumlahNotif }}</span>
                        </button>

                        <div class="lonceng__panel" data-lonceng-panel hidden>
                            <div class="lonceng__kepala">
                                <strong>Notifikasi</strong>
                                <button type="button" class="lonceng__semua" data-lonceng-semua>Tandai semua dibaca</button>
                            </div>
                            <ul class="lonceng__daftar" data-lonceng-daftar></ul>
                            <p class="lonceng__kosong" data-lonceng-kosong hidden>Belum ada notifikasi.</p>
                            <a href="{{ route('notifikasi.index') }}" class="lonceng__lihat">Lihat semua</a>
                        </div>
                    </div>

                    <div class="akun" data-akun>
                        <button type="button" class="akun__tombol" data-akun-tombol
                                aria-haspopup="true" aria-expanded="false">
                            <span class="akun__inisial" aria-hidden="true">
                                @if (auth()->user()->url_foto)
                                    <img src="{{ auth()->user()->url_foto }}" alt="" class="akun__foto">
                                @else
                                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                                @endif
                            </span>

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
                            @endif

                            <a href="{{ route('profil') }}" class="akun__item">Profil Saya</a>

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

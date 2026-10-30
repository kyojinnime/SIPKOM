{{-- Popup keamanan akun: peringatan password awal (5 detik) lalu form ganti password.
     Dimuat hanya untuk akun yang masih memakai password awal.
     data-otomatis = 1 bila pengguna baru saja login, jadi popup langsung terbuka. --}}
@php
    $kolom = [
        ['id' => 'sandi-lama',  'name' => 'password_lama',              'label' => 'Password saat ini',  'auto' => 'current-password'],
        ['id' => 'sandi-baru',  'name' => 'password_baru',              'label' => 'Password baru',      'auto' => 'new-password'],
        ['id' => 'sandi-ulang', 'name' => 'password_baru_confirmation', 'label' => 'Ulangi password baru', 'auto' => 'new-password'],
    ];
@endphp

<dialog class="sandi" id="modal-sandi" aria-label="Keamanan akun"
        data-otomatis="{{ session('ganti_password') ? '1' : '0' }}"
        data-url="{{ route('akun.password') }}">

    {{-- Tampilan 1: peringatan ramah --}}
    <div class="sandi__peringatan" data-tampil="peringatan">
        <span class="sandi__ikon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 2l8 3v6c0 5-3.4 9.4-8 11-4.6-1.6-8-6-8-11V5l8-3z"/><path d="M9 12l2 2 4-4"/></svg>
        </span>
        <h2>Amankan akunmu dulu, yuk</h2>
        <p>Kamu masih memakai password awal. Password ini sama untuk semua akun baru, jadi orang lain mudah menebaknya. Ganti sekarang supaya data kompensasimu aman.</p>

        {{-- Ruang tetap: berisi hitung mundur, lalu diganti tombol (tinggi sama, tidak melompat) --}}
        <div class="sandi__ruang">
            <div data-hitung>
                <div class="sandi__bar"><span data-bar></span></div>
                <p class="sandi__hitung">Pilihan muncul dalam <strong data-detik>5</strong> detik</p>
            </div>
            <div class="sandi__aksi" data-pilihan hidden>
                <button type="button" class="btn btn--garis" data-sandi-nanti>Nanti</button>
                <button type="button" class="btn btn--solid" data-sandi-ganti>Ganti Password</button>
            </div>
        </div>
    </div>

    {{-- Tampilan 2: form ganti password --}}
    <form class="sandi__form" id="form-sandi" data-tampil="form" hidden novalidate>
        @csrf

        <header class="sandi__kepala">
            <div>
                <h2>Ganti Password</h2>
                <p>Minimal 8 karakter, dan tidak boleh sama dengan password awal.</p>
            </div>
            <button type="button" class="sandi__tutup" data-sandi-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
            </button>
        </header>

        <div class="sandi__badan">
            {{-- Galat umum: sesi habis, gagal koneksi, terlalu banyak percobaan --}}
            <div class="sandi__galat-umum" data-galat-umum role="alert" hidden></div>

            @foreach ($kolom as $k)
                <div class="sandi__field">
                    <label for="{{ $k['id'] }}">{{ $k['label'] }}</label>
                    <div class="sandi__kolom">
                        <input type="password" id="{{ $k['id'] }}" name="{{ $k['name'] }}"
                               autocomplete="{{ $k['auto'] }}" maxlength="100">
                        {{-- Tombol mata: tampilkan / sembunyikan password --}}
                        <button type="button" class="sandi__mata" data-mata aria-label="Tampilkan password" aria-pressed="false">
                            <svg class="ikon-mata" viewBox="0 0 24 24" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="ikon-mata-coret" viewBox="0 0 24 24" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        </button>
                    </div>

                    {{-- Indikator kekuatan hanya di kolom password baru (petunjuk, tidak memblokir) --}}
                    @if ($k['name'] === 'password_baru')
                        <div class="sandi__kekuatan" data-kekuatan data-level="0" aria-hidden="true"><span></span></div>
                        <small class="sandi__petunjuk" data-kekuatan-teks>Minimal 8 karakter.</small>
                    @endif

                    <span class="sandi__galat" data-galat="{{ $k['name'] }}"></span>
                </div>
            @endforeach
        </div>

        <footer class="sandi__kaki">
            <button type="button" class="btn btn--garis" data-sandi-tutup>Batal</button>
            <button type="submit" class="btn btn--solid" data-simpan>Simpan</button>
        </footer>
    </form>
</dialog>
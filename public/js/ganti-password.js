/* =====================================================================
   ganti-password.js - popup keamanan akun
   Isi: peringatan 5 detik, form ganti password (fetch), indikator kekuatan,
        tombol mata, pembukaan dari dropdown akun
   ===================================================================== */

(function () {
    'use strict';

    var modal = document.getElementById('modal-sandi');
    if (!modal) { return; } // popup hanya ada untuk akun yang masih memakai password awal

    // ===== BAGIAN: Elemen dan keadaan =====
    var DETIK_TUNGGU = 5; // lama peringatan tidak bisa ditutup

    var form = document.getElementById('form-sandi');
    var ruangHitung = modal.querySelector('[data-hitung]');
    var ruangPilihan = modal.querySelector('[data-pilihan]');
    var detikEl = modal.querySelector('[data-detik]');
    var bar = modal.querySelector('[data-bar]');
    var galatUmum = form.querySelector('[data-galat-umum]');
    var tombolSimpan = form.querySelector('[data-simpan]');
    var inputLama = document.getElementById('sandi-lama');
    var inputBaru = document.getElementById('sandi-baru');
    var inputUlang = document.getElementById('sandi-ulang');
    var meter = form.querySelector('[data-kekuatan]');
    var teksKekuatan = form.querySelector('[data-kekuatan-teks]');

    var terkunci = false; // true selama hitung mundur: popup tidak bisa ditutup
    var timer = null;

    /** Menampilkan pesan lewat toast milik app.js (bila ada). */
    function toast(pesan, lama) {
        if (window.SIPKOM && window.SIPKOM.toast) { window.SIPKOM.toast(pesan, lama); }
    }

    /** Menampilkan satu tampilan di dalam popup: 'peringatan' atau 'form'. */
    function tampilkan(nama) {
        modal.querySelectorAll('[data-tampil]').forEach(function (el) {
            el.hidden = el.getAttribute('data-tampil') !== nama;
        });
    }

    // ===== BAGIAN: Tampilan peringatan =====

    /** Membuka peringatan; selama DETIK_TUNGGU detik tidak bisa ditutup, lalu tombol muncul. */
    function bukaPeringatan() {
        clearInterval(timer);
        tampilkan('peringatan');
        ruangHitung.hidden = false;
        ruangPilihan.hidden = true;

        var sisa = DETIK_TUNGGU;
        detikEl.textContent = sisa;
        terkunci = true;

        // Mulai ulang animasi bar: lepas kelas, paksa hitung ulang tata letak, pasang lagi
        bar.classList.remove('sandi__bar--jalan');
        bar.style.animationDuration = DETIK_TUNGGU + 's';
        void bar.offsetWidth;
        bar.classList.add('sandi__bar--jalan');

        if (!modal.open) { modal.showModal(); }

        timer = setInterval(function () {
            sisa--;
            detikEl.textContent = sisa;
            if (sisa <= 0) {
                clearInterval(timer);
                terkunci = false;
                ruangHitung.hidden = true;
                ruangPilihan.hidden = false;
                modal.querySelector('[data-sandi-ganti]').focus();
            }
        }, 1000);
    }

    // Esc saat terkunci ditolak. Chrome dapat menutup dialog pada Esc kedua tanpa bisa dicegah,
    // jadi saat 'close' terjadi dalam keadaan terkunci, dialog dibuka lagi.
    modal.addEventListener('cancel', function (e) {
        if (terkunci) { e.preventDefault(); }
    });
    modal.addEventListener('close', function () {
        if (terkunci) { modal.showModal(); }
    });

    // Klik latar gelap menutup popup (sama artinya dengan "Nanti"), kecuali saat terkunci
    modal.addEventListener('click', function (e) {
        if (e.target === modal && !terkunci) { modal.close(); }
    });

    // ===== BAGIAN: Tampilan form =====

    /** Mengosongkan semua pesan galat. */
    function hapusGalat() {
        form.querySelectorAll('[data-galat]').forEach(function (s) { s.textContent = ''; });
        form.querySelectorAll('.sandi__field--galat').forEach(function (f) { f.classList.remove('sandi__field--galat'); });
        galatUmum.hidden = true;
        galatUmum.textContent = '';
    }

    /** Menampilkan galat di bawah satu kolom. */
    function galat(kolom, teks) {
        var span = form.querySelector('[data-galat="' + kolom + '"]');
        if (span) {
            span.textContent = teks;
            span.closest('.sandi__field').classList.add('sandi__field--galat');
        }
    }

    /** Mengembalikan semua kolom ke bentuk titik-titik dan label "Tampilkan". */
    function resetMata() {
        form.querySelectorAll('[data-mata]').forEach(function (m) {
            m.parentNode.querySelector('input').type = 'password';
            m.setAttribute('aria-pressed', 'false');
            m.setAttribute('aria-label', 'Tampilkan password');
        });
    }

    /** Membuka form (dari tombol "Ganti Password" atau dari dropdown akun). */
    function bukaForm() {
        clearInterval(timer);
        terkunci = false;
        form.reset();
        hapusGalat();
        resetMata();
        perbaruiKekuatan();
        tampilkan('form');
        if (!modal.open) { modal.showModal(); }
        inputLama.focus();
    }

    /** Menilai kekuatan password baru: hanya petunjuk, tidak memblokir. */
    function perbaruiKekuatan() {
        var s = inputBaru.value;
        var level = 0;
        var teks = 'Minimal 8 karakter.';

        if (s.length > 0 && s.length < 8) {
            level = 1;
            teks = 'Terlalu pendek, minimal 8 karakter.';
        } else if (s.length >= 8) {
            var poin = 0;
            if (s.length >= 12) { poin++; }
            if (/[a-z]/.test(s) && /[A-Z]/.test(s)) { poin++; }
            if (/\d/.test(s)) { poin++; }
            if (/[^A-Za-z0-9]/.test(s)) { poin++; }
            level = poin <= 1 ? 1 : (poin === 2 ? 2 : 3);
            teks = ['', 'Lemah', 'Cukup', 'Kuat'][level];
        }
        meter.setAttribute('data-level', level);
        teksKekuatan.textContent = teks;
    }
    inputBaru.addEventListener('input', perbaruiKekuatan);

    /** Pemeriksaan cepat di browser; server tetap memeriksa ulang. Mengembalikan true bila lolos. */
    function validasiKlien() {
        var ok = true;
        if (!inputLama.value) { galat('password_lama', 'Password saat ini wajib diisi.'); ok = false; }
        if (inputBaru.value.length < 8) {
            galat('password_baru', 'Password baru minimal 8 karakter.'); ok = false;
        } else if (inputBaru.value === inputLama.value) {
            galat('password_baru', 'Password baru harus berbeda dari password saat ini.'); ok = false;
        }
        if (inputUlang.value !== inputBaru.value) {
            galat('password_baru_confirmation', 'Konfirmasi password tidak cocok.'); ok = false;
        }
        if (!ok) {
            var pertama = form.querySelector('.sandi__field--galat input');
            if (pertama) { pertama.focus(); }
        }
        return ok;
    }

    /** Menampilkan satu galat umum (sesi habis, koneksi, terlalu banyak percobaan). */
    function galatUmumTampil(teks) {
        galatUmum.textContent = teks;
        galatUmum.hidden = false;
    }

    /** Mengunci / membuka tombol Simpan selama proses kirim (cegah klik ganda). */
    function aturSimpan(sedangKirim) {
        tombolSimpan.disabled = sedangKirim;
        tombolSimpan.textContent = sedangKirim ? 'Menyimpan...' : 'Simpan';
    }

    /** Menghapus pengingat (titik merah, baris peringatan, item dropdown) setelah password diganti. */
    function hapusPengingat() {
        document.querySelectorAll('[data-sandi-pengingat]').forEach(function (el) { el.remove(); });
    }

    /** Mengirim form lewat fetch; popup tertutup dan pengguna tetap login bila berhasil. */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        hapusGalat();
        if (!validasiKlien()) { return; }

        var data = new FormData(form);   // sudah berisi _token (CSRF)
        data.append('_method', 'PUT');

        aturSimpan(true);
        try {
            var res = await fetch(modal.getAttribute('data-url'), {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var json = await res.json().catch(function () { return {}; });

            if (res.status === 422) {
                var errors = json.errors || {};
                Object.keys(errors).forEach(function (k) { galat(k, errors[k][0]); });
                var pertama = form.querySelector('.sandi__field--galat input');
                if (pertama) { pertama.focus(); }
                return;
            }
            if (res.status === 419) { galatUmumTampil('Sesi habis. Muat ulang halaman lalu coba lagi.'); return; }
            if (res.status === 429) { galatUmumTampil('Terlalu banyak percobaan. Tunggu satu menit lalu coba lagi.'); return; }
            if (res.status === 403) { galatUmumTampil('Akses ditolak.'); return; }
            if (!res.ok) { galatUmumTampil(json.message || 'Terjadi kesalahan. Coba lagi.'); return; }

            // Berhasil
            modal.close();
            hapusPengingat();
            toast(json.pesan || 'Password berhasil diganti.', 3500);
        } catch (err) {
            galatUmumTampil('Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.');
        } finally {
            aturSimpan(false);
        }
    });

    // ===== BAGIAN: Klik di dalam popup (satu pendengar untuk semua tombol) =====
    modal.addEventListener('click', function (e) {
        // Tombol mata
        var mata = e.target.closest('[data-mata]');
        if (mata) {
            var input = mata.parentNode.querySelector('input');
            var terlihat = input.type === 'text';
            input.type = terlihat ? 'password' : 'text';
            mata.setAttribute('aria-pressed', terlihat ? 'false' : 'true');
            mata.setAttribute('aria-label', terlihat ? 'Tampilkan password' : 'Sembunyikan password');
            return;
        }
        if (e.target.closest('[data-sandi-nanti]') || e.target.closest('[data-sandi-tutup]')) { modal.close(); return; }
        if (e.target.closest('[data-sandi-ganti]')) { bukaForm(); }
    });

    // ===== BAGIAN: Item "Ganti Password" di dropdown akun =====
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[data-buka-sandi]')) { return; }

        // Tutup dulu dropdown akunnya
        var menu = document.querySelector('[data-akun-menu]');
        var tombolAkun = document.querySelector('[data-akun-tombol]');
        if (menu) { menu.hidden = true; }
        if (tombolAkun) { tombolAkun.setAttribute('aria-expanded', 'false'); }

        bukaForm();
    });

    // ===== BAGIAN: Inisialisasi =====
    // Baru saja login dengan password awal: peringatan langsung muncul
    if (modal.getAttribute('data-otomatis') === '1') { bukaPeringatan(); }
})();
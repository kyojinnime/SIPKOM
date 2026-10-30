/* =====================================================================
   login.js - halaman login
   Isi: tombol mata, hitung mundur kunci, cegah kirim ganda, Lupa Password
   ===================================================================== */

(function () {
    'use strict';

    var form = document.getElementById('form-login');
    var tombolKirim = form.querySelector('[data-kirim]');
    var inputPassword = document.getElementById('password');
    var tombolMata = form.querySelector('[data-mata]');

    // ===== BAGIAN: Tombol mata =====
    /** Menukar tampilan password antara titik-titik dan teks, sambil memperbarui label aksesibilitas. */
    tombolMata.addEventListener('click', function () {
        var terlihat = inputPassword.type === 'text';
        inputPassword.type = terlihat ? 'password' : 'text';
        tombolMata.setAttribute('aria-pressed', terlihat ? 'false' : 'true');
        tombolMata.setAttribute('aria-label', terlihat ? 'Tampilkan password' : 'Sembunyikan password');
        inputPassword.focus();
    });

    // ===== BAGIAN: Hitung mundur saat login dikunci =====
    var sisa = parseInt(form.getAttribute('data-kunci'), 10) || 0;

    /** Menampilkan sisa detik di tombol; saat habis tombol aktif lagi dan pesan galat dihapus. */
    function jalankanHitungMundur() {
        if (sisa <= 0) { return; }
        var galat = form.querySelector('[data-galat]');
        tombolKirim.disabled = true;

        var timer = setInterval(function () {
            sisa--;
            if (sisa <= 0) {
                clearInterval(timer);
                tombolKirim.disabled = false;
                tombolKirim.textContent = 'Submit';
                if (galat) { galat.remove(); }
                inputPassword.focus();
                return;
            }
            tombolKirim.textContent = 'Coba lagi dalam ' + sisa + ' detik';
        }, 1000);

        tombolKirim.textContent = 'Coba lagi dalam ' + sisa + ' detik';
    }
    jalankanHitungMundur();

    // ===== BAGIAN: Cegah kirim ganda =====
    form.addEventListener('submit', function () {
        // Dijalankan setelah pengiriman dimulai; tombol aktif lagi bila halaman dipulihkan lewat tombol Back
        setTimeout(function () {
            tombolKirim.disabled = true;
            tombolKirim.textContent = 'Memproses...';
        }, 0);
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted && sisa <= 0) {
            tombolKirim.disabled = false;
            tombolKirim.textContent = 'Submit';
        }
    });

    // ===== BAGIAN: Lupa Password (sementara) =====
    var lupa = document.querySelector('[data-lupa]');
    if (lupa) {
        lupa.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.SIPKOM && window.SIPKOM.toast) {
                window.SIPKOM.toast('Fitur lupa password segera tersedia. Untuk sementara hubungi admin.', 4500);
            }
        });
    }
})();
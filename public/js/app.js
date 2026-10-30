/* =====================================================================
   app.js - skrip dasar semua halaman: menu hamburger dan toast
   ===================================================================== */

(function () {
    'use strict';

    // ===== BAGIAN: Menu hamburger (layar kecil) =====
    var tombol = document.getElementById('menuToggle');
    var panel = document.getElementById('menuPanel');

    if (tombol && panel) {
        tombol.addEventListener('click', function () {
            // Buka/tutup panel lalu perbarui atribut aksesibilitas
            var buka = panel.classList.toggle('header__panel--buka');
            tombol.setAttribute('aria-expanded', buka ? 'true' : 'false');
        });
    }

    // ===== BAGIAN: Toast =====
    var toast = document.getElementById('toast');
    var timer = null;

    /** Menampilkan pesan singkat di bawah layar selama 2,5 detik. */
    function tampilkanToast(pesan, lama) {
        if (!toast) { return; }
        toast.textContent = pesan;
        toast.classList.add('toast--tampil');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('toast--tampil'); }, lama || 2500);
        // [BARU] Buka satu pintu agar skrip halaman lain (popup, kelola akun) bisa memakai toast yang sama
        window.SIPKOM = window.SIPKOM || {};
        window.SIPKOM.toast = tampilkanToast;
    }

    // Elemen bertanda data-butuh-login (menu abu-abu, Ajukan Kompen, Lihat Semua)
    document.querySelectorAll('[data-butuh-login]').forEach(function (el) {
        el.addEventListener('click', function () {
            tampilkanToast('Silakan login terlebih dahulu untuk membuka fitur ini.');
        });
        // Dukungan keyboard untuk menu abu-abu (span)
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                tampilkanToast('Silakan login terlebih dahulu untuk membuka fitur ini.');
            }
        });
    });
})();
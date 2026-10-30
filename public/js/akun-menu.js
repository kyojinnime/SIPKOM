/* =====================================================================
   akun-menu.js - dropdown akun di header (buka/tutup)
   ===================================================================== */

(function () {
    'use strict';

    var akun = document.querySelector('[data-akun]');
    if (!akun) { return; } // belum login: tidak ada dropdown

    var tombol = akun.querySelector('[data-akun-tombol]');
    var menu = akun.querySelector('[data-akun-menu]');

    /** Membuka menu dan memberi tahu pembaca layar lewat aria-expanded. */
    function buka() {
        menu.hidden = false;
        tombol.setAttribute('aria-expanded', 'true');
    }

    /** Menutup menu. */
    function tutup() {
        menu.hidden = true;
        tombol.setAttribute('aria-expanded', 'false');
    }

    // Klik tombol: bergantian buka/tutup
    tombol.addEventListener('click', function (e) {
        e.stopPropagation();
        if (menu.hidden) { buka(); } else { tutup(); }
    });

    // Klik di luar dropdown: tutup
    document.addEventListener('click', function (e) {
        if (!akun.contains(e.target)) { tutup(); }
    });

    // Tombol Esc: tutup dan kembalikan fokus ke tombol
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !menu.hidden) {
            tutup();
            tombol.focus();
        }
    });
})();
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

    // Sediakan toast bersama sebelum handler halaman lain menggunakannya.
    window.SIPKOM = window.SIPKOM || {};
    window.SIPKOM.toast = function (pesan, lama) {
        if (!toast) { return; }
        toast.textContent = pesan;
        toast.classList.add('toast--tampil');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('toast--tampil'); }, typeof lama === 'number' ? lama : 2500);
    };

    /** Menampilkan pesan fitur yang memerlukan login. */
    function tampilkanToast(pesan) {
        window.SIPKOM.toast(pesan);
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

    // ===== BAGIAN: Dropdown Navigasi (Kelola Data) =====
    // Integrasi dengan akun-menu.js untuk mutual exclusion
    var navDropdown = document.querySelector('[data-nav-dropdown]');
    if (navDropdown) {
        var dropdownTombol = navDropdown.querySelector('[data-nav-dropdown-tombol]');
        var dropdownMenu = navDropdown.querySelector('[data-nav-dropdown-menu]');

        if (dropdownTombol && dropdownMenu) {
            dropdownTombol.addEventListener('click', function (e) {
                e.stopPropagation();
                var buka = dropdownMenu.hasAttribute('hidden');
                
                // Tutup dropdown akun jika terbuka (koordinasi dengan akun-menu.js)
                var akunMenu = document.querySelector('[data-akun-menu]');
                if (akunMenu && !akunMenu.hasAttribute('hidden')) {
                    akunMenu.setAttribute('hidden', '');
                    var akunTombol = document.querySelector('[data-akun-tombol]');
                    if (akunTombol) akunTombol.setAttribute('aria-expanded', 'false');
                }

                if (buka) {
                    dropdownMenu.removeAttribute('hidden');
                    dropdownTombol.setAttribute('aria-expanded', 'true');
                } else {
                    dropdownMenu.setAttribute('hidden', '');
                    dropdownTombol.setAttribute('aria-expanded', 'false');
                }
            });

            // Tutup dropdown saat klik di luar
            document.addEventListener('click', function (e) {
                if (!navDropdown.contains(e.target)) {
                    dropdownMenu.setAttribute('hidden', '');
                    dropdownTombol.setAttribute('aria-expanded', 'false');
                }
            });
        }
    }
})();
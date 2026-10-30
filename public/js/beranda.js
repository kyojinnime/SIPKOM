/* =====================================================================
   beranda.js - skrip khusus Beranda: panah carousel Lokasi Kompen
   ===================================================================== */

(function () {
    'use strict';

    document.querySelectorAll('[data-carousel]').forEach(function (carousel) {
        var jalur = carousel.querySelector('[data-carousel-jalur]');
        var kiri = carousel.querySelector('[data-carousel-prev]');
        var kanan = carousel.querySelector('[data-carousel-next]');
        if (!jalur) { return; }

        /** Menggeser jalur sejauh 80% lebar tampilan; arah -1 kiri, 1 kanan. */
        function geser(arah) {
            jalur.scrollBy({ left: arah * jalur.clientWidth * 0.8, behavior: 'smooth' });
        }

        if (kiri) { kiri.addEventListener('click', function () { geser(-1); }); }
        if (kanan) { kanan.addEventListener('click', function () { geser(1); }); }

        /** Menyembunyikan panah bila semua kartu sudah muat tanpa digeser. */
        function perbaruiPanah() {
            var perluGeser = jalur.scrollWidth > jalur.clientWidth + 4;
            if (kiri) { kiri.hidden = !perluGeser; }
            if (kanan) { kanan.hidden = !perluGeser; }
        }
        perbaruiPanah();
        window.addEventListener('resize', perbaruiPanah);
    });
})();
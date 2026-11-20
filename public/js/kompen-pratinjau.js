/* =====================================================================
   kompen-pratinjau.js - halaman Pratinjau Import
   Isi: filter baris (Semua / Peringatan / Ditolak), tanpa request ke server
   ===================================================================== */

(function () {
    'use strict';

    var tombol = document.querySelectorAll('[data-filter]');
    if (!tombol.length) { return; }   // tidak ada baris untuk difilter

    // Status baris yang ditampilkan untuk tiap filter
    var peta = {
        semua: ['hijau', 'kuning', 'merah'],
        peringatan: ['kuning'],
        ditolak: ['merah']
    };

    /** Menampilkan hanya baris yang sesuai filter, lalu mengatur pesan "kosong" tiap blok. */
    function terapkan(nama) {
        var boleh = peta[nama] || peta.semua;

        // Tampilan tombol pil
        tombol.forEach(function (t) {
            var aktif = t.getAttribute('data-filter') === nama;
            t.classList.toggle('tab__btn--aktif', aktif);
            t.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });

        // Tiap blok kelas: sembunyikan baris yang tidak cocok dan hitung yang tersisa
        document.querySelectorAll('[data-blok]').forEach(function (blok) {
            var tampil = 0;
            var baris = blok.querySelectorAll('[data-status]');
            baris.forEach(function (b) {
                var cocok = boleh.indexOf(b.getAttribute('data-status')) !== -1;
                b.hidden = !cocok;
                if (cocok) { tampil++; }
            });

            // Pesan "tidak ada baris" hanya bila blok punya baris tetapi semuanya tersaring
            var kosong = blok.querySelector('[data-kosong-filter]');
            if (kosong) { kosong.hidden = !(baris.length > 0 && tampil === 0); }
            var bungkus = blok.querySelector('.pt-bungkus');
            if (bungkus) { bungkus.hidden = baris.length > 0 && tampil === 0; }
        });
    }

    tombol.forEach(function (t) {
        t.addEventListener('click', function () { terapkan(t.getAttribute('data-filter')); });
    });
})();


/* ===== BAGIAN: Kunci tombol Simpan agar tidak terkirim dua kali ===== */
(function () {
    'use strict';

    var form = document.querySelector('[data-form-simpan]');
    var tombol = form ? form.querySelector('[data-simpan]') : null;
    if (!tombol) { return; }

    form.addEventListener('submit', function (e) {
        // Klik kedua atau tombol memang nonaktif: jangan kirim
        if (tombol.disabled) { e.preventDefault(); return; }
        tombol.disabled = true;
        tombol.textContent = 'Menyimpan...';
    });

    // Tombol Kembali di browser (halaman dari cache): buka kunci, kecuali memang nonaktif dari awal
    window.addEventListener('pageshow', function () {
        tombol.disabled = tombol.hasAttribute('data-mati');
        tombol.textContent = 'Simpan Data';
    });
})();
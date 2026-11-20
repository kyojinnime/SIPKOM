/* =====================================================================
   notifikasi.js - lonceng di header dan halaman Notifikasi
   Isi: dropdown, penyegaran badge, tandai dibaca, tandai semua dibaca.
   Mandiri: tidak bergantung pada akun-menu.js maupun master-data.js.
   ===================================================================== */

(function () {
    'use strict';

    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    var csrf = metaCsrf ? metaCsrf.content : '';

    /** Kirim permintaan JSON. Selalu menghasilkan {ok, status, body}, tidak pernah melempar galat. */
    function kirim(method, url) {
        return fetch(url, {
            method: method,
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
        })
            .then(function (res) {
                // Balasan bukan JSON (mis. halaman login saat sesi habis) dianggap body kosong
                return res.json().catch(function () { return {}; })
                    .then(function (body) { return { ok: res.ok, status: res.status, body: body }; });
            })
            .catch(function () { return { ok: false, status: 0, body: {} }; });
    }

    // ===== BAGIAN 1: tampilan jumlah belum dibaca (badge dan angka di halaman) =====

    /** Perbarui badge lonceng dan semua penanda [data-jumlah-belum]. */
    function aturJumlah(jumlah) {
        document.querySelectorAll('[data-lonceng-badge]').forEach(function (b) {
            b.textContent = jumlah > 99 ? '99+' : String(jumlah);
            b.hidden = jumlah <= 0;
        });
        document.querySelectorAll('[data-lonceng-tombol]').forEach(function (t) {
            t.setAttribute('aria-label', 'Notifikasi' + (jumlah > 0 ? ', ' + jumlah + ' belum dibaca' : ''));
        });
        document.querySelectorAll('[data-jumlah-belum]').forEach(function (el) { el.textContent = String(jumlah); });
    }

    /** Hapus tanda belum dibaca pada semua baris yang tampil (dropdown dan halaman). */
    function bersihkanTanda() {
        document.querySelectorAll('.notif-baris--baru').forEach(function (li) { li.classList.remove('notif-baris--baru'); });
        document.querySelectorAll('[data-tandai], [data-dibaca-semua]').forEach(function (b) { b.remove(); });
    }

    // ===== BAGIAN 2: lonceng di header =====
    var lonceng = document.querySelector('[data-lonceng]');
    var terbuka = false;

    if (lonceng) {
        var tombol = lonceng.querySelector('[data-lonceng-tombol]');
        var panel = lonceng.querySelector('[data-lonceng-panel]');
        var daftar = lonceng.querySelector('[data-lonceng-daftar]');
        var kosong = lonceng.querySelector('[data-lonceng-kosong]');
        var tombolSemua = lonceng.querySelector('[data-lonceng-semua]');
        var urlRingkas = lonceng.getAttribute('data-url-ringkas');
        var urlSemua = lonceng.getAttribute('data-url-semua');

        /** Buat satu baris notifikasi dengan DOM (textContent, bukan innerHTML, agar aman dari XSS). */
        function buatBaris(n) {
            var li = document.createElement('li');
            li.className = 'notif-baris' + (n.dibaca ? '' : ' notif-baris--baru');

            var a = document.createElement('a');
            a.className = 'notif-tautan';
            a.href = n.url;

            var titik = document.createElement('span');
            titik.className = 'notif-titik';
            titik.setAttribute('aria-hidden', 'true');

            var teks = document.createElement('span');
            teks.className = 'notif-teks';

            var judul = document.createElement('span');
            judul.className = 'notif-judul';
            judul.textContent = n.judul;
            teks.appendChild(judul);

            if (n.isi) {
                var isi = document.createElement('span');
                isi.className = 'notif-isi';
                isi.textContent = n.isi;
                teks.appendChild(isi);
            }
            if (n.waktu) {
                var waktu = document.createElement('span');
                waktu.className = 'notif-waktu';
                waktu.textContent = n.waktu;
                teks.appendChild(waktu);
            }

            a.appendChild(titik);
            a.appendChild(teks);
            li.appendChild(a);
            return li;
        }

        /** Tutup panel. */
        function tutup() {
            terbuka = false;
            panel.hidden = true;
            tombol.setAttribute('aria-expanded', 'false');
        }

        /** Ambil daftar terbaru dari server lalu tampilkan. */
        function muat() {
            kosong.textContent = 'Memuat...';
            kosong.hidden = false;
            daftar.textContent = '';

            kirim('GET', urlRingkas).then(function (h) {
                if (!h.ok || !Array.isArray(h.body.daftar)) {
                    kosong.textContent = 'Gagal memuat notifikasi. Coba lagi.';
                    return;
                }
                daftar.textContent = '';
                h.body.daftar.forEach(function (n) { daftar.appendChild(buatBaris(n)); });
                kosong.textContent = 'Belum ada notifikasi.';
                kosong.hidden = h.body.daftar.length > 0;
                if (typeof h.body.jumlah === 'number') {
                    aturJumlah(h.body.jumlah);
                    tombolSemua.disabled = h.body.jumlah === 0;
                }
            });
        }

        /** Buka panel; menu akun dan Kelola Data ditutup lebih dulu (cara yang sama dengan app.js). */
        function buka() {
            var akunMenu = document.querySelector('[data-akun-menu]');
            if (akunMenu) {
                akunMenu.setAttribute('hidden', '');
                var akunTombol = document.querySelector('[data-akun-tombol]');
                if (akunTombol) { akunTombol.setAttribute('aria-expanded', 'false'); }
            }
            var navMenu = document.querySelector('[data-nav-dropdown-menu]');
            if (navMenu) {
                navMenu.setAttribute('hidden', '');
                var navTombol = document.querySelector('[data-nav-dropdown-tombol]');
                if (navTombol) { navTombol.setAttribute('aria-expanded', 'false'); }
            }

            terbuka = true;
            panel.hidden = false;
            tombol.setAttribute('aria-expanded', 'true');
            muat();
        }

        tombol.addEventListener('click', function () { terbuka ? tutup() : buka(); });

        // Tandai semua dibaca dari dalam panel
        tombolSemua.addEventListener('click', function () {
            tombolSemua.disabled = true;
            kirim('POST', urlSemua).then(function (h) {
                if (h.ok) {
                    aturJumlah(0);
                    bersihkanTanda();
                } else {
                    tombolSemua.disabled = false;
                    if (window.SIPKOM && window.SIPKOM.toast) { window.SIPKOM.toast('Gagal menandai notifikasi. Coba lagi.'); }
                }
            });
        });

        // Klik di luar menutup panel. Fase capture dipakai karena app.js menghentikan perambatan klik
        // pada tombol Kelola Data (stopPropagation), sehingga pendengar biasa tidak akan menerimanya.
        document.addEventListener('click', function (e) {
            if (terbuka && !lonceng.contains(e.target)) { tutup(); }
        }, true);

        // Esc menutup panel dan mengembalikan fokus ke lonceng
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && terbuka) { tutup(); tombol.focus(); }
        });

        // Penyegaran badge berkala (tanpa WebSocket): hanya saat tab terlihat dan panel tertutup
        setInterval(function () {
            if (document.hidden || terbuka) { return; }
            kirim('GET', urlRingkas + '?tanpa_daftar=1').then(function (h) {
                if (h.ok && typeof h.body.jumlah === 'number') { aturJumlah(h.body.jumlah); }
            });
        }, 90000);
    }

    // ===== BAGIAN 3: halaman Notifikasi (satu pendengar klik untuk semua tombol) =====
    document.addEventListener('click', function (e) {
        // Tandai satu notifikasi dibaca
        var satu = e.target.closest('[data-tandai]');
        if (satu) {
            satu.disabled = true;
            kirim('POST', satu.getAttribute('data-url')).then(function (h) {
                if (h.ok) {
                    var li = satu.closest('.notif-baris');
                    if (li) { li.classList.remove('notif-baris--baru'); }
                    satu.remove();
                    if (typeof h.body.jumlah === 'number') { aturJumlah(h.body.jumlah); }
                } else {
                    satu.disabled = false;
                }
            });
            return;
        }

        // Tandai semua dibaca dari halaman Notifikasi
        var semua = e.target.closest('[data-dibaca-semua]');
        if (semua) {
            semua.disabled = true;
            kirim('POST', semua.getAttribute('data-url')).then(function (h) {
                if (h.ok) {
                    aturJumlah(0);
                    bersihkanTanda();
                } else {
                    semua.disabled = false;
                }
            });
        }
    });
})();
/* =====================================================================
   data-kompen.js - halaman Data Kompen dan Detail Progres
   Isi: kirim otomatis filter, popup ubah jam wajib, popup batalkan kewajiban.
   Mandiri: tidak memakai master-data.js.
   ===================================================================== */

(function () {
    'use strict';

    // ===== BAGIAN 1: filter otomatis (hanya ada di halaman daftar) =====
    var formSaring = document.querySelector('[data-form-saring]');
    if (formSaring) {
        // Pilihan periode, kelas, dan dosen: begitu diganti, langsung terapkan.
        // Pencarian dikirim dengan menekan Enter (form GET biasa).
        formSaring.querySelectorAll('select[data-otomatis]').forEach(function (pilihan) {
            pilihan.addEventListener('change', function () {
                // form.submit() memuat ulang halaman, jadi tidak ada risiko kirim ganda
                formSaring.submit();
            });
        });
    }

    // ===== BAGIAN 2: toast dari aksi sebelumnya (halaman dimuat ulang setelah berhasil) =====
    try {
        var pesanTersimpan = sessionStorage.getItem('kompen_toast');
        if (pesanTersimpan) {
            sessionStorage.removeItem('kompen_toast');
            if (window.SIPKOM && window.SIPKOM.toast) { window.SIPKOM.toast(pesanTersimpan, 4000); }
        }
    } catch (e) { /* penyimpanan browser diblokir: toast dilewati */ }

    // ===== BAGIAN 3: popup ubah jam dan batalkan =====
    var dlgUbah = document.getElementById('modal-ubah');
    var dlgBatal = document.getElementById('modal-batal');
    if (!dlgUbah || !dlgBatal) { return; }   // halaman tanpa popup

    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    var csrf = metaCsrf ? metaCsrf.content : '';

    // Keadaan baris yang sedang diproses
    var idAktif = null;      // id jumlah_kompen
    var minAktif = 1;        // batas bawah jam wajib
    var terlaksanaAktif = 0; // jam terlaksana mahasiswa

    // Pesan bila server tidak memberi pesan sendiri
    var PESAN_BAWAAN = {
        0: 'Koneksi gagal. Periksa jaringan lalu coba lagi.',
        403: 'Anda tidak berhak melakukan aksi ini.',
        404: 'Data tidak ditemukan. Muat ulang halaman.',
        419: 'Sesi habis. Muat ulang halaman lalu coba lagi.',
        429: 'Terlalu banyak percobaan. Tunggu sebentar.',
        500: 'Terjadi kesalahan di server.'
    };

    /** Kirim JSON ke server. Selalu menghasilkan {ok, status, body}, tidak pernah melempar galat. */
    function kirim(method, url, data) {
        return fetch(url, {
            method: method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(data)
        })
            .then(function (res) {
                // Balasan bukan JSON (mis. halaman 419 atau 500) dianggap body kosong
                return res.json().catch(function () { return {}; })
                    .then(function (body) { return { ok: res.ok, status: res.status, body: body }; });
            })
            .catch(function () { return { ok: false, status: 0, body: {} }; });
    }

    /** Hapus semua tanda galat pada form. */
    function bersihkan(form) {
        form.querySelectorAll('[data-galat]').forEach(function (el) { el.textContent = ''; });
        form.querySelectorAll('.field--galat').forEach(function (el) { el.classList.remove('field--galat'); });
        var umum = form.querySelector('[data-galat-umum]');
        if (umum) { umum.textContent = ''; umum.hidden = true; }
    }

    /** Tampilkan galat: errors = {kolom: [pesan]} di bawah kolom, umum = teks di kotak merah. */
    function tampilkan(form, errors, umum) {
        var pertama = null;
        Object.keys(errors || {}).forEach(function (kolom) {
            var tempat = form.querySelector('[data-galat="' + kolom + '"]');
            var input = form.elements[kolom];
            if (tempat) { tempat.textContent = errors[kolom][0]; }
            if (input) {
                var field = input.closest('.field');
                if (field) { field.classList.add('field--galat'); }
                if (!pertama) { pertama = input; }
            }
        });
        if (umum) {
            var kotak = form.querySelector('[data-galat-umum]');
            kotak.textContent = umum;
            kotak.hidden = false;
        }
        if (pertama) { pertama.focus(); }
    }

    /** Kunci tombol agar tidak terklik dua kali; teks asli dipulihkan saat dibuka. */
    function kunci(tombol, aktif, teks) {
        if (aktif) {
            tombol.dataset.teksAsli = tombol.textContent;
            tombol.disabled = true;
            if (teks) { tombol.textContent = teks; }
        } else {
            tombol.disabled = false;
            if (tombol.dataset.teksAsli) {
                tombol.textContent = tombol.dataset.teksAsli;
                delete tombol.dataset.teksAsli;
            }
        }
    }

    /** Perilaku standar popup: tombol [data-tutup], klik latar, dan bersihkan form saat tertutup. */
    function pasangDialog(dlg) {
        // Menyeret teks dari kolom ke latar tidak boleh menutup popup: catat di mana klik dimulai
        var mulaiDiLatar = false;
        dlg.addEventListener('mousedown', function (e) { mulaiDiLatar = (e.target === dlg); });
        dlg.addEventListener('click', function (e) {
            if ((e.target === dlg && mulaiDiLatar) || e.target.closest('[data-tutup]')) { dlg.close(); }
        });
        dlg.addEventListener('close', function () {
            var form = dlg.querySelector('form');
            form.reset();
            bersihkan(form);
        });
    }

    /**
     * Pasang pengiriman form popup.
     * ambilData(form) -> objek yang dikirim; periksa(data) -> {kolom: [pesan]} atau null.
     * Berhasil: pesan disimpan untuk toast lalu halaman dimuat ulang.
     */
    function pasangKirim(dlg, method, teksMemuat, ambilData, periksa) {
        var form = dlg.querySelector('form');
        var tombol = form.querySelector('[data-kirim]');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            bersihkan(form);

            var data = ambilData(form);
            var galat = periksa(data);
            if (galat) { tampilkan(form, galat); return; }

            var url = dlg.getAttribute('data-url').replace('__ID__', idAktif);
            kunci(tombol, true, teksMemuat);

            kirim(method, url, data).then(function (h) {
                if (h.ok) {
                    try { sessionStorage.setItem('kompen_toast', h.body.pesan || 'Berhasil disimpan.'); } catch (x) { /* abaikan */ }
                    location.reload();   // tombol tetap terkunci sampai halaman dimuat ulang
                    return;
                }
                kunci(tombol, false);
                if (h.status === 422 && h.body.errors) {
                    tampilkan(form, h.body.errors);
                } else {
                    tampilkan(form, null, h.body.message || PESAN_BAWAAN[h.status] || 'Permintaan gagal diproses.');
                }
            });
        });
    }

    pasangDialog(dlgUbah);
    pasangDialog(dlgBatal);

    // ----- Popup reset password (hanya ada di halaman detail) -----
    var dlgReset = document.getElementById('modal-reset');
    if (dlgReset) {
        pasangDialog(dlgReset);
        // Tanpa isian: cukup konfirmasi. Berhasil: toast memuat password awal, lalu halaman dimuat ulang
        pasangKirim(dlgReset, 'PATCH', 'Memproses...', function () { return {}; }, function () { return null; });
    }
    // ----- Popup ubah jam: pemeriksaan di browser (server tetap yang utama) -----
    pasangKirim(dlgUbah, 'PUT', 'Menyimpan...',
        function (form) {
            return { jumlah_jam: form.elements['jumlah_jam'].value.trim(), alasan: form.elements['alasan'].value.trim() };
        },
        function (d) {
            if (d.jumlah_jam === '') { return { jumlah_jam: ['Jam wajib harus diisi.'] }; }
            if (!/^\d+$/.test(d.jumlah_jam)) { return { jumlah_jam: ['Jam wajib harus bilangan bulat.'] }; }
            var n = Number(d.jumlah_jam);
            if (n < minAktif) {
                return { jumlah_jam: [minAktif > 1
                    ? 'Jam wajib tidak boleh kurang dari ' + minAktif + ' jam karena sebagian sudah terlaksana.'
                    : 'Jam wajib minimal 1 jam. Untuk menghapus kewajiban, gunakan Batalkan.'] };
            }
            if (n > 999) { return { jumlah_jam: ['Jam wajib maksimal 999 jam.'] }; }
            return null;
        });

    // ----- Popup batalkan: alasan wajib -----
    pasangKirim(dlgBatal, 'PATCH', 'Memproses...',
        function (form) { return { alasan: form.elements['alasan'].value.trim() }; },
        function (d) {
            if (d.alasan.length < 3) { return { alasan: ['Alasan pembatalan wajib diisi (minimal 3 karakter).'] }; }
            return null;
        });

    /** Buka popup ubah jam dari tombol yang diklik. */
    function bukaUbah(tombol) {
        idAktif = tombol.getAttribute('data-id');
        minAktif = Number(tombol.getAttribute('data-min')) || 1;
        terlaksanaAktif = Number(tombol.getAttribute('data-terlaksana')) || 0;

        var form = dlgUbah.querySelector('form');
        dlgUbah.querySelector('[data-sub]').textContent = tombol.getAttribute('data-nama') + ' (' + tombol.getAttribute('data-nim') + ')';
        form.elements['jumlah_jam'].value = tombol.getAttribute('data-jam');
        form.elements['jumlah_jam'].min = String(minAktif);
        dlgUbah.querySelector('[data-bantuan]').textContent = 'Minimal ' + minAktif + ' jam'
            + (terlaksanaAktif > 0 ? '. Sudah terlaksana ' + terlaksanaAktif + ' jam.' : '.');

        dlgUbah.showModal();
        form.elements['jumlah_jam'].focus();
        form.elements['jumlah_jam'].select();
    }

    /** Buka popup batalkan dari tombol yang diklik. */
    function bukaBatal(tombol) {
        idAktif = tombol.getAttribute('data-id');
        terlaksanaAktif = Number(tombol.getAttribute('data-terlaksana')) || 0;

        dlgBatal.querySelector('[data-sub]').textContent = tombol.getAttribute('data-nama') + ' (' + tombol.getAttribute('data-nim') + ')';

        // Peringatan hanya bila sudah ada jam terlaksana
        var peringatan = dlgBatal.querySelector('[data-peringatan]');
        peringatan.hidden = terlaksanaAktif === 0;
        peringatan.textContent = terlaksanaAktif > 0
            ? 'Mahasiswa ini sudah menyelesaikan ' + terlaksanaAktif + ' jam kompensasi. Jam yang sudah terlaksana tetap tercatat.'
            : '';

        dlgBatal.showModal();
        dlgBatal.querySelector('textarea').focus();
    }

    
    /** Buka popup reset password dari tombol yang diklik. idAktif di sini adalah id mahasiswa. */
    function bukaReset(tombol) {
        idAktif = tombol.getAttribute('data-id');

        dlgReset.querySelector('[data-teks]').textContent =
            'Password ' + tombol.getAttribute('data-nama') + ' (' + tombol.getAttribute('data-nim') + ') '
            + 'dikembalikan ke password awal (' + tombol.getAttribute('data-awal') + ') dan wajib diganti saat login berikutnya. '
            + 'Sesi login yang sedang berjalan ikut diakhiri.';

        dlgReset.showModal();
        dlgReset.querySelector('[data-tutup]').focus();   // fokus awal di Batal agar tidak tertekan tak sengaja
    }

    // Satu pendengar klik untuk semua tombol aksi
    document.addEventListener('click', function (e) {
        var tombol = e.target.closest('[data-aksi]');
        if (!tombol) { return; }
        var aksi = tombol.getAttribute('data-aksi');
        if (aksi === 'ubah') { bukaUbah(tombol); }
        if (aksi === 'batal') { bukaBatal(tombol); }
        if (aksi === 'reset' && dlgReset) { bukaReset(tombol); }
    });
})();
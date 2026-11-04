/* =====================================================================
   master-data.js - pustaka bersama halaman Data Master admin
   (Program Studi, Periode, Ruang, Kelas).
   Dimuat SETELAH app.js (butuh SIPKOM.toast) dan SEBELUM js halaman.
   Hasil: window.SIPKOM.master
   ===================================================================== */
(function () {
    'use strict';

    // Tambah namespace tanpa menimpa SIPKOM milik app.js
    window.SIPKOM = window.SIPKOM || {};

    // ===== BAGIAN: Dasar (CSRF, toast, pesan galat) =====

    // Ambil token CSRF dari meta tag layout
    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    // Tampilkan toast lewat fasilitas app.js (diam bila belum tersedia)
    function toast(pesan, lama) {
        if (typeof window.SIPKOM.toast === 'function') { window.SIPKOM.toast(pesan, lama); }
    }

    // Ambil pesan dari hasil kirim(). Server memakai 'pesan' (baru) atau 'message' (lama).
    // Bila server tidak memberi pesan, pakai teks bawaan sesuai kode status.
    function pesanDari(hasil) {
        var b = (hasil && hasil.body) || {};
        if (b.pesan) { return b.pesan; }
        if (b.message) { return b.message; }
        var bawaan = {
            0: 'Koneksi gagal. Periksa jaringan lalu coba lagi.',
            403: 'Anda tidak berhak melakukan aksi ini.',
            404: 'Data tidak ditemukan. Muat ulang halaman.',
            419: 'Sesi habis. Muat ulang halaman lalu coba lagi.',
            429: 'Terlalu banyak percobaan. Tunggu sebentar.',
            500: 'Terjadi kesalahan di server.'
        };
        return bawaan[hasil && hasil.status] || 'Permintaan gagal diproses.';
    }

    // ===== BAGIAN: Kirim permintaan JSON =====

    // Kirim permintaan ke server. Selalu menghasilkan {ok, status, body}, tidak pernah melempar galat.
    // method: 'POST' | 'PUT' | 'PATCH' | 'DELETE' | 'GET'; data: objek (opsional)
    function kirim(method, url, data) {
        var opsi = {
            method: method,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() }
        };
        if (data) {
            opsi.headers['Content-Type'] = 'application/json';
            opsi.body = JSON.stringify(data);
        }
        return fetch(url, opsi)
            .then(function (res) {
                // Balasan bukan JSON (mis. halaman 419/500) dianggap body kosong
                return res.json().catch(function () { return {}; })
                    .then(function (body) { return { ok: res.ok, status: res.status, body: body }; });
            })
            .catch(function () {
                // Jaringan putus
                return { ok: false, status: 0, body: {} };
            });
    }

    // ===== BAGIAN: Galat form =====

    // Hapus semua tanda galat pada form (teks per kolom, kotak umum, bingkai merah)
    function bersihkanGalat(form) {
        form.querySelectorAll('[data-galat]').forEach(function (el) { el.textContent = ''; });
        form.querySelectorAll('.field--galat').forEach(function (el) { el.classList.remove('field--galat'); });
        form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
        var umum = form.querySelector('[data-galat-umum]');
        if (umum) { umum.textContent = ''; umum.hidden = true; }
    }

    // Tampilkan galat. errors = objek 422 Laravel {kolom: [pesan]}; umumPesan = teks untuk kotak umum.
    // Galat kolom yang tidak punya tempat ikut masuk ke kotak umum agar tidak hilang diam-diam.
    function tampilkanGalat(form, errors, umumPesan) {
        var sisa = [];
        var pertama = null;
        Object.keys(errors || {}).forEach(function (kolom) {
            var teks = errors[kolom][0];
            var tempat = form.querySelector('[data-galat="' + kolom + '"]');
            var input = form.querySelector('[name="' + kolom + '"]');
            if (tempat) { tempat.textContent = teks; } else { sisa.push(teks); }
            if (input) {
                input.setAttribute('aria-invalid', 'true');
                var field = input.closest('.field');
                if (field) { field.classList.add('field--galat'); }
                if (!pertama) { pertama = input; }
            }
        });
        if (umumPesan) { sisa.push(umumPesan); }

        var umum = form.querySelector('[data-galat-umum]');
        if (umum && sisa.length) {
            umum.textContent = sisa.join(' ');
            umum.hidden = false;
        }
        // Pindahkan fokus ke kolom bermasalah pertama
        if (pertama) { pertama.focus(); }
    }

    // Kunci atau lepas tombol kirim agar tidak terklik dua kali; teks asli dipulihkan otomatis
    function kunciTombol(tombol, kunci, teksMemuat) {
        if (!tombol) { return; }
        if (kunci) {
            tombol.dataset.teksAsli = tombol.textContent;
            tombol.disabled = true;
            if (teksMemuat) { tombol.textContent = teksMemuat; }
        } else {
            tombol.disabled = false;
            if (tombol.dataset.teksAsli) {
                tombol.textContent = tombol.dataset.teksAsli;
                delete tombol.dataset.teksAsli;
            }
        }
    }

    // Kirim form lewat satu pintu: kunci tombol, bersihkan galat, kirim, tampilkan galat bila gagal.
    // o = {form, tombol, method, url, data, teksMemuat}
    // Hasil: body JSON bila sukses, null bila gagal (galat sudah tampil di form)
    function ajukan(o) {
        bersihkanGalat(o.form);
        kunciTombol(o.tombol, true, o.teksMemuat || 'Menyimpan...');
        return kirim(o.method, o.url, o.data).then(function (hasil) {
            kunciTombol(o.tombol, false);
            if (hasil.ok) { return hasil.body; }
            if (hasil.status === 422 && hasil.body.errors) {
                tampilkanGalat(o.form, hasil.body.errors);
            } else {
                tampilkanGalat(o.form, null, pesanDari(hasil));
            }
            return null;
        });
    }

    // ===== BAGIAN: Dialog (elemen <dialog>) =====

    // Buka dialog sebagai modal (aman dipanggil dua kali)
    function bukaDialog(dialog) {
        if (!dialog.open) { dialog.showModal(); }
    }

    // Tutup dialog
    function tutupDialog(dialog) {
        if (dialog.open) { dialog.close(); }
    }

    // Pasang perilaku standar: tombol [data-tutup], klik latar, dan reset form saat tertutup
    function pasangDialog(dialog) {
        // Catat di mana tombol mouse ditekan; menyeret teks dari kolom ke latar tidak boleh menutup dialog
        var mulaiDiLatar = false;
        dialog.addEventListener('mousedown', function (e) { mulaiDiLatar = (e.target === dialog); });
        dialog.addEventListener('click', function (e) {
            if ((e.target === dialog && mulaiDiLatar) || e.target.closest('[data-tutup]')) { dialog.close(); }
        });
        // Setelah tertutup (tombol, latar, atau Esc): kosongkan form dan galatnya
        dialog.addEventListener('close', function () {
            var form = dialog.querySelector('form');
            if (form) { form.reset(); bersihkanGalat(form); }
        });
    }

    // ===== BAGIAN: Popup konfirmasi =====

    var dialogKonfirmasi = null;

    // Ikon popup konfirmasi (garis seragam dengan ikon lain)
    var IKON_BAHAYA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 001.7 3h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg>';
    var IKON_INFO = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0zM12 16v-4m0-4h.01"/></svg>';

    // Buat kerangka popup sekali saja lalu dipakai ulang
    function siapkanKonfirmasi() {
        if (dialogKonfirmasi) { return dialogKonfirmasi; }
        var d = document.createElement('dialog');
        d.className = 'modal modal--konfirmasi';
        d.innerHTML =
            '<div class="konf">' +
            '<div class="konf__ikon" data-konf-ikon></div>' +
            '<h2 data-konf-judul></h2>' +
            '<p data-konf-pesan></p>' +
            '<div class="konf__aksi">' +
            '<button type="button" class="btn btn--garis" data-konf-batal></button>' +
            '<button type="button" class="btn" data-konf-ok></button>' +
            '</div></div>';
        document.body.appendChild(d);

        // Tombol: tutup dengan nilai 'ya' atau 'tidak'
        d.querySelector('[data-konf-batal]').addEventListener('click', function () { d.close('tidak'); });
        d.querySelector('[data-konf-ok]').addEventListener('click', function () { d.close('ya'); });
        // Klik latar = batal
        d.addEventListener('click', function (e) { if (e.target === d) { d.close('tidak'); } });

        dialogKonfirmasi = d;
        return d;
    }

    // Tampilkan popup konfirmasi. Hasil: Promise<boolean> (true bila pengguna menekan tombol setuju).
    // o = {judul, pesan, teksOk, teksBatal, bahaya}. Fokus awal ada di Batal agar aksi berbahaya tidak tertekan tak sengaja.
    function konfirmasi(o) {
        var d = siapkanKonfirmasi();
        var ikon = d.querySelector('[data-konf-ikon]');
        var ok = d.querySelector('[data-konf-ok]');

        // Isi teks (textContent, bukan innerHTML, agar nama data aman dari XSS)
        d.querySelector('[data-konf-judul]').textContent = o.judul || 'Yakin?';
        d.querySelector('[data-konf-pesan]').textContent = o.pesan || '';
        d.querySelector('[data-konf-batal]').textContent = o.teksBatal || 'Batal';
        ok.textContent = o.teksOk || 'Ya, lanjutkan';

        // Tampilan: merah untuk aksi berbahaya, hijau untuk aksi biasa
        ikon.className = 'konf__ikon ' + (o.bahaya ? 'konf__ikon--bahaya' : 'konf__ikon--info');
        ikon.innerHTML = o.bahaya ? IKON_BAHAYA : IKON_INFO;
        ok.className = 'btn ' + (o.bahaya ? 'btn--bahaya' : 'btn--solid');

        return new Promise(function (selesai) {
            // returnValue dikosongkan agar Esc terbaca sebagai batal
            d.returnValue = '';
            d.addEventListener('close', function saat() {
                d.removeEventListener('close', saat);
                selesai(d.returnValue === 'ya');
            });
            d.showModal();
        });
    }

    // ===== BAGIAN: Daftar (baris, nomor, pencarian) =====

    // Ubah teks HTML satu baris menjadi elemen
    function elemenDari(html) {
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    // Tambah baris baru (HTML dari server) ke akhir daftar, lalu sorot sebentar
    function tambahBaris(daftar, html) {
        var baris = elemenDari(html);
        baris.classList.add('baris--baru');
        daftar.appendChild(baris);
        return baris;
    }

    // Ganti baris lama dengan HTML baru dari server, lalu sorot sebentar
    function gantiBaris(lama, html) {
        var baru = elemenDari(html);
        baru.classList.add('baris--baru');
        lama.replaceWith(baru);
        return baru;
    }

    // Hitung ulang nomor baris yang terlihat, angka ringkasan, dan keadaan kosong.
    // opsi = {ringkas: elemen angka total, kosongCari: elemen pesan "tidak cocok"}
    // Daftar wajib punya <li data-kosong-awal> sebagai pesan "belum ada data".
    function segarkan(daftar, opsi) {
        opsi = opsi || {};
        var semua = Array.prototype.slice.call(daftar.querySelectorAll('[data-baris]'));
        var terlihat = 0;
        semua.forEach(function (baris) {
            if (baris.hidden) { return; }
            terlihat++;
            var nomor = baris.querySelector('.nomor');
            if (nomor) { nomor.textContent = terlihat; }
        });

        if (opsi.ringkas) { opsi.ringkas.textContent = semua.length; }
        // "Belum ada data" tampil hanya bila daftar benar-benar kosong
        var kosongAwal = daftar.querySelector('[data-kosong-awal]');
        if (kosongAwal) { kosongAwal.hidden = semua.length > 0; }
        // "Tidak cocok" tampil bila ada data tapi semua tersaring
        if (opsi.kosongCari) { opsi.kosongCari.hidden = semua.length === 0 || terlihat > 0; }
        return { total: semua.length, terlihat: terlihat };
    }

    // Pasang pencarian di browser (tanpa request). Baris memakai atribut data-cari berisi teks huruf kecil.
    // opsi = {input, daftar, ringkas, kosongCari, filterLain(baris) => boolean}
    // Hasil: fungsi terapkan(); panggil lagi setelah baris ditambah/diubah/dihapus.
    function pasangPencarian(opsi) {
        function terapkan() {
            var q = opsi.input ? opsi.input.value.trim().toLocaleLowerCase() : '';
            opsi.daftar.querySelectorAll('[data-baris]').forEach(function (baris) {
                var cocokTeks = (baris.dataset.cari || '').indexOf(q) !== -1;
                var cocokLain = opsi.filterLain ? opsi.filterLain(baris) : true;
                baris.hidden = !(cocokTeks && cocokLain);
            });
            segarkan(opsi.daftar, opsi);
        }
        if (opsi.input) { opsi.input.addEventListener('input', terapkan); }
        return terapkan;
    }

    // Satu pendengar klik untuk semua tombol [data-aksi] di dalam daftar (termasuk baris yang muncul belakangan).
    // peta = {ubah: function (tombol, baris) {...}, hapus: function (tombol, baris) {...}}
    function delegasi(daftar, peta) {
        daftar.addEventListener('click', function (e) {
            var tombol = e.target.closest('[data-aksi]');
            if (!tombol || !daftar.contains(tombol)) { return; }
            var fn = peta[tombol.dataset.aksi];
            if (fn) { fn(tombol, tombol.closest('[data-baris]'), e); }
        });
    }

    // ===== BAGIAN: Ekspor =====
    window.SIPKOM.master = {
        csrf: csrf, toast: toast, pesanDari: pesanDari,
        kirim: kirim, ajukan: ajukan,
        bersihkanGalat: bersihkanGalat, tampilkanGalat: tampilkanGalat, kunciTombol: kunciTombol,
        bukaDialog: bukaDialog, tutupDialog: tutupDialog, pasangDialog: pasangDialog,
        konfirmasi: konfirmasi,
        tambahBaris: tambahBaris, gantiBaris: gantiBaris, segarkan: segarkan,
        pasangPencarian: pasangPencarian, delegasi: delegasi
    };
})();
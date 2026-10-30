/* =====================================================================
   kelola-akun.js - halaman Kelola Akun
   Isi: pindah tab, pencarian langsung, menu aksi, popup tambah/ubah (tanpa reload)
   Aksi nonaktifkan, reset password, hapus ditambahkan di Langkah 5.
   ===================================================================== */

(function () {
    'use strict';

    // ===== BAGIAN: Elemen dan keadaan =====
    var tombolTab = document.querySelectorAll('[data-tab]');
    var panel = document.querySelectorAll('[data-panel]');
    var inputCari = document.getElementById('cari-akun');
    var labelTambah = document.querySelector('[data-tambah-label]');

    // [BARU] elemen popup
    var modal = document.getElementById('modal-akun');
    var form = document.getElementById('form-akun');
    var judulModal = document.getElementById('modal-judul');
    var subModal = document.getElementById('modal-sub');
    var tombolSimpan = form.querySelector('[data-simpan]');
    var infoPassword = form.querySelector('[data-info-password]');
    var galatUmum = form.querySelector('[data-galat-umum]');
    
    // [BARU] elemen popup konfirmasi
    var konf = document.getElementById('modal-konfirmasi');
    var konfJudul = document.getElementById('konf-judul');
    var konfTeks = konf.querySelector('[data-konf-teks]');
    var konfIkon = konf.querySelector('[data-konf-ikon]');
    var konfGalat = konf.querySelector('[data-konf-galat]');
    var konfOk = konf.querySelector('[data-konf-ok]');
    var konfBatal = konf.querySelector('[data-konf-batal]');
    var token = form.elements['_token'].value; // token CSRF dipakai ulang untuk semua aksi

    // [BARU] keadaan konfirmasi: aksi apa, jenis akun, dan baris mana
    var aksiKonf = null;
    var tipeKonf = null;
    var barisKonf = null;

    // Nama tab untuk label tombol "Tambah ..." dan judul popup
    var namaTab = { dosen: 'Dosen Wali', pengawas: 'Pengawas' };
    var tabAktif = 'dosen';

    // [BARU] keadaan popup
    var mode = 'tambah';       // 'tambah' atau 'ubah'
    var tipeModal = 'dosen';   // jenis akun yang sedang ada di popup
    var idModal = null;        // id profil yang sedang diubah

    /** Menampilkan pesan lewat toast milik app.js (bila ada); lama = milidetik. */
    function toast(pesan, lama) {
        if (window.SIPKOM && window.SIPKOM.toast) { window.SIPKOM.toast(pesan, lama); }
    }

    // ===== BAGIAN: Tab dan pencarian =====

    /** Menyaring baris pada tab aktif sesuai kata kunci, lalu mengatur pesan kosong. */
    function saring() {
        var kata = inputCari.value.trim().toLowerCase();
        var panelAktif = document.querySelector('[data-panel="' + tabAktif + '"]');
        var baris = panelAktif.querySelectorAll('[data-baris]');
        var tampil = 0;

        baris.forEach(function (b) {
            var cocok = kata === '' || b.getAttribute('data-cari').indexOf(kata) !== -1;
            b.hidden = !cocok;
            if (cocok) { tampil++; }
        });

        // Kosong total (belum ada data) vs kosong karena pencarian
        panelAktif.querySelector('[data-kosong]').hidden = baris.length !== 0;
        panelAktif.querySelector('[data-kosong-cari]').hidden = !(baris.length > 0 && tampil === 0);
    }

    /** Pindah ke tab tertentu: ubah tampilan tab, panel, label tombol, dan alamat (#dosen / #pengawas). */
    function pilihTab(nama) {
        if (!namaTab[nama]) { nama = 'dosen'; }
        tabAktif = nama;

        tombolTab.forEach(function (t) {
            var aktif = t.getAttribute('data-tab') === nama;
            t.classList.toggle('tab__btn--aktif', aktif);
            t.setAttribute('aria-selected', aktif ? 'true' : 'false');
        });
        panel.forEach(function (p) {
            p.hidden = p.getAttribute('data-panel') !== nama;
        });

        labelTambah.textContent = namaTab[nama];
        history.replaceState(null, '', '#' + nama); // refresh halaman tetap di tab ini
        tutupSemuaMenu();
        saring();
    }

    /** [BARU] Menghitung ulang angka di tab dan di kepala halaman dari isi daftar saat ini. */
    function perbaruiAngka() {
        Object.keys(namaTab).forEach(function (t) {
            var jumlah = document.querySelectorAll('[data-panel="' + t + '"] [data-baris]').length;
            document.querySelector('[data-jumlah-tab="' + t + '"]').textContent = jumlah;
            document.querySelector('[data-ringkas="' + t + '"]').textContent = jumlah;
        });
        document.querySelector('[data-ringkas="nonaktif"]').textContent =
            document.querySelectorAll('.baris--nonaktif').length;
    }

    // ===== BAGIAN: Menu aksi (titik tiga) =====

    /** Menutup semua menu aksi yang terbuka, kecuali satu (opsional). */
    function tutupSemuaMenu(kecuali) {
        document.querySelectorAll('[data-menu]').forEach(function (m) {
            if (m === kecuali) { return; }
            m.hidden = true;
            var tombol = m.parentNode.querySelector('[data-menu-tombol]');
            if (tombol) { tombol.setAttribute('aria-expanded', 'false'); }
        });
    }

    // ===== BAGIAN: [BARU] Popup tambah / ubah =====

    /** Mengosongkan semua pesan galat di popup. */
    function hapusGalat() {
        form.querySelectorAll('[data-galat]').forEach(function (s) { s.textContent = ''; });
        form.querySelectorAll('.field--galat').forEach(function (f) { f.classList.remove('field--galat'); });
        galatUmum.hidden = true;
        galatUmum.textContent = '';
    }

    /** Membuka popup. modeBaru: 'tambah' / 'ubah'; baris: elemen <li> yang diubah (hanya untuk mode ubah). */
    function bukaModal(modeBaru, tipe, baris) {
        mode = modeBaru;
        tipeModal = tipe;
        idModal = baris ? baris.getAttribute('data-id') : null;

        hapusGalat();
        form.reset();

        judulModal.textContent = (mode === 'tambah' ? 'Tambah ' : 'Ubah ') + namaTab[tipe];
        subModal.textContent = mode === 'tambah'
            ? 'Akun dibuat dengan email sebagai username login.'
            : 'Mengubah email juga mengubah username login.';
        infoPassword.hidden = mode !== 'tambah';

        // Mode ubah: isi form dari atribut baris
        if (mode === 'ubah' && baris) {
            form.elements['nama'].value = baris.getAttribute('data-nama') || '';
            form.elements['email'].value = baris.getAttribute('data-email') || '';
        }

        modal.showModal();
    }

    /** Menampilkan galat validasi (422) di bawah kolom masing-masing. */
    function tampilkanGalat(galat) {
        Object.keys(galat).forEach(function (kolom) {
            var span = form.querySelector('[data-galat="' + kolom + '"]');
            if (span) {
                span.textContent = galat[kolom][0];
                span.closest('.field').classList.add('field--galat');
            }
        });
        // Fokus ke kolom bermasalah pertama
        var pertama = form.querySelector('.field--galat input');
        if (pertama) { pertama.focus(); }
    }

    /** Mengubah teks HTML dari server menjadi elemen <li>. */
    function buatElemen(html) {
        var t = document.createElement('template');
        t.innerHTML = html.trim();
        return t.content.firstElementChild;
    }

    /** Mengunci / membuka tombol Simpan selama proses kirim (cegah klik ganda). */
    function aturSimpan(sedangKirim) {
        tombolSimpan.disabled = sedangKirim;
        tombolSimpan.textContent = sedangKirim ? 'Menyimpan...' : 'Simpan';
    }

    /** Mengirim form lewat fetch lalu memperbarui daftar tanpa reload. */
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        hapusGalat();

        // Alamat dari atribut form; __TIPE__ dan __ID__ diganti nilai sebenarnya
        var url = (mode === 'tambah' ? form.getAttribute('data-url-simpan') : form.getAttribute('data-url-ubah'))
            .replace('__TIPE__', tipeModal)
            .replace('__ID__', idModal);

        var data = new FormData(form);                       // sudah berisi _token (CSRF)
        if (mode === 'ubah') { data.append('_method', 'PUT'); }

        aturSimpan(true);
        try {
            var res = await fetch(url, {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var json = await res.json().catch(function () { return {}; });

            if (res.status === 422) { tampilkanGalat(json.errors || {}); return; }
            if (res.status === 419) { galatUmum.textContent = 'Sesi habis. Muat ulang halaman lalu coba lagi.'; galatUmum.hidden = false; return; }
            if (res.status === 403) { galatUmum.textContent = 'Akses ditolak.'; galatUmum.hidden = false; return; }
            if (!res.ok) { galatUmum.textContent = json.message || 'Terjadi kesalahan. Coba lagi.'; galatUmum.hidden = false; return; }

            // Berhasil: sisipkan baris baru (di paling atas) atau ganti baris lama
            var el = buatElemen(json.html);
            if (mode === 'tambah') {
                var daftar = document.querySelector('[data-panel="' + tipeModal + '"] .daftar');
                daftar.insertBefore(el, daftar.firstChild);
                el.classList.add('baris--baru');
                inputCari.value = ''; // agar baris baru tidak tersaring dan hilang dari tampilan
            } else {
                var lama = document.querySelector('[data-panel="' + tipeModal + '"] [data-baris][data-id="' + idModal + '"]');
                if (lama) { lama.replaceWith(el); }
            }

            modal.close();
            perbaruiAngka();
            saring();
            // Pesan tambah memuat password awal, jadi ditampilkan lebih lama
            toast(json.pesan, mode === 'tambah' ? 7000 : 2500);
        } catch (err) {
            galatUmum.textContent = 'Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.';
            galatUmum.hidden = false;
        } finally {
            aturSimpan(false);
        }
    });

    // Tombol Batal / X menutup popup; klik latar gelap (backdrop) juga menutup
    modal.querySelectorAll('[data-modal-tutup]').forEach(function (t) {
        t.addEventListener('click', function () { modal.close(); });
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) { modal.close(); }
    });

        // ===== BAGIAN: [BARU] Popup konfirmasi (nonaktifkan, reset password, hapus) =====

    /** Isi popup per aksi: judul, teks, label tombol, dan gaya ('info' = hijau, 'bahaya' = merah). */
    function isiKonfirmasi(aksi, nama, aktif) {
        var awal = konf.getAttribute('data-password-awal');

        if (aksi === 'toggle' && aktif) {
            return { judul: 'Nonaktifkan akun?', gaya: 'bahaya', tombol: 'Nonaktifkan',
                teks: nama + ' tidak akan bisa login sampai akunnya diaktifkan kembali. Data dan riwayatnya tetap tersimpan.' };
        }
        if (aksi === 'toggle') {
            return { judul: 'Aktifkan akun?', gaya: 'info', tombol: 'Aktifkan',
                teks: nama + ' akan bisa login kembali.' };
        }
        if (aksi === 'reset') {
            return { judul: 'Reset password?', gaya: 'info', tombol: 'Reset password',
                teks: 'Password ' + nama + ' dikembalikan ke password awal (' + awal + ') dan wajib diganti saat login berikutnya.' };
        }
        return { judul: 'Hapus akun?', gaya: 'bahaya', tombol: 'Hapus permanen',
            teks: 'Akun ' + nama + ' dihapus permanen dan tidak bisa dikembalikan. Bila hanya ingin menghentikan akses, gunakan Nonaktifkan.' };
    }

    /** Membuka popup konfirmasi untuk satu aksi pada satu baris. Fokus awal di Batal agar aman. */
    function bukaKonfirmasi(aksi, tipe, baris) {
        aksiKonf = aksi;
        tipeKonf = tipe;
        barisKonf = baris;

        var isi = isiKonfirmasi(aksi, baris.getAttribute('data-nama'), !baris.classList.contains('baris--nonaktif'));
        konfJudul.textContent = isi.judul;
        konfTeks.textContent = isi.teks;          // textContent (bukan innerHTML) agar nama tidak bisa menyisipkan HTML
        konfOk.textContent = isi.tombol;
        konfOk.className = 'btn ' + (isi.gaya === 'bahaya' ? 'btn--bahaya' : 'btn--solid');
        konfIkon.className = 'konf__ikon konf__ikon--' + isi.gaya;
        konfGalat.hidden = true;
        konfGalat.textContent = '';
        konfOk.disabled = false;

        konf.showModal();
        konfBatal.focus();
    }

    /** Menjalankan aksi yang dikonfirmasi lewat fetch, lalu memperbarui daftar tanpa reload. */
    konfOk.addEventListener('click', async function () {
        var id = barisKonf.getAttribute('data-id');
        var url = konf.getAttribute('data-url-' + aksiKonf)
            .replace('__TIPE__', tipeKonf)
            .replace('__ID__', id);

        // Laravel memakai method spoofing: dikirim POST dengan _method PATCH / DELETE
        var data = new FormData();
        data.append('_token', token);
        data.append('_method', aksiKonf === 'hapus' ? 'DELETE' : 'PATCH');

        var teksAsal = konfOk.textContent;
        konfOk.disabled = true;                   // cegah klik ganda
        konfOk.textContent = 'Memproses...';
        try {
            var res = await fetch(url, {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var json = await res.json().catch(function () { return {}; });

            if (!res.ok) {
                // Popup tetap terbuka agar alasan penolakan terbaca
                var pesanGalat = json.message || 'Terjadi kesalahan. Coba lagi.';
                if (res.status === 419) { pesanGalat = 'Sesi habis. Muat ulang halaman lalu coba lagi.'; }
                if (res.status === 403) { pesanGalat = 'Akses ditolak.'; }
                konfGalat.textContent = pesanGalat;
                konfGalat.hidden = false;
                return;
            }

            // Berhasil: sesuaikan daftar
            if (aksiKonf === 'hapus') {
                barisKonf.remove();
            } else if (aksiKonf === 'toggle') {
                var el = buatElemen(json.html);   // baris baru dari server (status dan teks menu sudah benar)
                el.classList.add('baris--baru');
                barisKonf.replaceWith(el);
            }

            konf.close();
            perbaruiAngka();
            saring();
            // Pesan panjang (memuat password awal atau peringatan) tampil lebih lama
            toast(json.pesan, json.pesan.length > 60 ? 7000 : 2500);
        } catch (err) {
            konfGalat.textContent = 'Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.';
            konfGalat.hidden = false;
        } finally {
            konfOk.disabled = false;
            konfOk.textContent = teksAsal;
        }
    });

    // Tombol Batal dan klik latar gelap menutup popup konfirmasi
    konfBatal.addEventListener('click', function () { konf.close(); });
    konf.addEventListener('click', function (e) {
        if (e.target === konf) { konf.close(); }
    });

    // ===== BAGIAN: Klik di halaman (satu pendengar untuk semua, termasuk baris yang ditambah belakangan) =====
    document.addEventListener('click', function (e) {
        // Klik titik tiga: buka/tutup menu baris itu saja
        var tombolMenu = e.target.closest('[data-menu-tombol]');
        if (tombolMenu) {
            var menu = tombolMenu.parentNode.querySelector('[data-menu]');
            var akanBuka = menu.hidden;
            tutupSemuaMenu();
            menu.hidden = !akanBuka;
            tombolMenu.setAttribute('aria-expanded', akanBuka ? 'true' : 'false');
            return;
        }

        // Klik item menu
        var aksi = e.target.closest('[data-aksi]');
        if (aksi) {
            tutupSemuaMenu();
            var jenis = aksi.getAttribute('data-aksi');
            if (jenis === 'ubah') {
                // Ubah data: buka popup terisi data baris itu
                bukaModal('ubah', aksi.getAttribute('data-tipe'), aksi.closest('[data-baris]'));
            } else {
                // [BARU] toggle / reset / hapus: minta konfirmasi dulu
                bukaKonfirmasi(jenis, aksi.getAttribute('data-tipe'), aksi.closest('[data-baris]'));
            }
            return;
        }

        // [BARU] Klik "Tambah": buka popup sesuai tab aktif
        if (e.target.closest('[data-tambah]')) {
            bukaModal('tambah', tabAktif, null);
            return;
        }

        // Klik di tempat lain: tutup menu
        tutupSemuaMenu();
    });

    // Tombol Esc menutup menu aksi (popup sudah menutup sendiri lewat <dialog>)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { tutupSemuaMenu(); }
    });

    // ===== BAGIAN: Inisialisasi =====
    tombolTab.forEach(function (t) {
        t.addEventListener('click', function () { pilihTab(t.getAttribute('data-tab')); });
    });
    inputCari.addEventListener('input', saring);

    // Buka tab sesuai alamat (#pengawas), bila tidak ada pakai tab pertama
    pilihTab(location.hash.replace('#', ''));
})();
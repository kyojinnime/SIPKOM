/* =====================================================================
   periode.js - halaman Kelola Periode.
   Bergantung pada master-data.js (window.SIPKOM.master).
   Urutan periode bisa berubah saat tambah/aktifkan, jadi server membalas
   seluruh daftar (body.daftar) dan JS mengganti isi daftar sekaligus.
   ===================================================================== */
(function () {
    'use strict';

    // ===== BAGIAN: Elemen dan pemeriksaan awal =====
    var M = window.SIPKOM && window.SIPKOM.master;
    var dialog = document.querySelector('[data-dialog="periode"]');
    var daftar = document.querySelector('[data-daftar="periode"]');
    var tombolTambah = document.querySelector('[data-tambah]');
    if (!M || !dialog || !daftar || !tombolTambah) { return; }

    var form = dialog.querySelector('[data-form]');
    var inputTahun = form.querySelector('[name="tahun_ajaran"]');
    var pilihSemester = form.querySelector('[name="semester"]');
    var cekAktif = form.querySelector('[name="aktif"]');
    var infoAktif = form.querySelector('[data-info-aktif]');
    var tombolSimpan = form.querySelector('[data-simpan]');
    var ringkasTotal = document.querySelector('[data-ringkas="total"]');
    var ringkasAktif = document.querySelector('[data-ringkas="aktif"]');
    var urlDasar = daftar.dataset.url;       // mis. /admin/periode
    var barisDiubah = null;                  // null = mode tambah

    M.pasangDialog(dialog);

    // ===== BAGIAN: Pencarian =====
    var terapkan = M.pasangPencarian({
        input: document.querySelector('[data-cari-input]'),
        daftar: daftar,
        ringkas: ringkasTotal,
        kosongCari: document.querySelector('[data-kosong-cari]')
    });

    // ===== BAGIAN: Ganti seluruh isi daftar =====
    // Ganti semua baris dengan HTML dari server, sorot baris ber-id idSorot, lalu hitung ulang nomor
    function gantiDaftar(html, idSorot) {
        daftar.querySelectorAll('[data-baris]').forEach(function (b) { b.remove(); });
        var t = document.createElement('template');
        t.innerHTML = html;
        // Sisipkan sebelum pesan "belum ada periode"
        daftar.querySelector('[data-kosong-awal]').before(t.content);
        if (idSorot) {
            var baris = daftar.querySelector('[data-baris][data-id="' + idSorot + '"]');
            if (baris) { baris.classList.add('baris--baru'); }
        }
        terapkan();
    }

    // Terapkan balasan sukses: daftar, label periode aktif, dan toast
    function terapkanBalasan(body) {
        gantiDaftar(body.daftar, body.id);
        if (ringkasAktif) { ringkasAktif.textContent = body.aktif || '-'; }
        M.toast(body.pesan);
    }

    // ===== BAGIAN: Buka dialog =====
    // Mode tambah (baris null) atau ubah (baris terpilih)
    function bukaForm(baris) {
        barisDiubah = baris;
        dialog.querySelector('[data-judul]').textContent = baris ? 'Ubah Periode' : 'Tambah Periode';
        inputTahun.value = baris ? baris.dataset.tahun : '';
        pilihSemester.value = baris ? baris.dataset.semester : 'ganjil';

        // Periode yang sedang aktif: kotak centang terkunci (ganti aktif dengan mengaktifkan periode lain)
        var sedangAktif = baris && baris.dataset.aktif === '1';
        cekAktif.checked = !!sedangAktif;
        cekAktif.disabled = !!sedangAktif;
        infoAktif.textContent = sedangAktif
            ? 'Periode ini sedang aktif. Untuk menggantinya, aktifkan periode lain.'
            : 'Periode aktif sebelumnya otomatis dinonaktifkan.';

        M.bukaDialog(dialog);
        inputTahun.focus();
    }

    tombolTambah.addEventListener('click', function () { bukaForm(null); });

    // ===== BAGIAN: Simpan (tambah atau ubah) =====
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var tahun = inputTahun.value.trim();

        // Cek cepat di browser; aturan sebenarnya di server
        M.bersihkanGalat(form);
        if (!tahun) {
            M.tampilkanGalat(form, { tahun_ajaran: ['Tahun ajaran wajib diisi.'] });
            return;
        }

        var ubah = barisDiubah !== null;
        M.ajukan({
            form: form,
            tombol: tombolSimpan,
            method: ubah ? 'PUT' : 'POST',
            url: ubah ? urlDasar + '/' + barisDiubah.dataset.id : urlDasar,
            data: { tahun_ajaran: tahun, semester: pilihSemester.value, aktif: cekAktif.checked }
        }).then(function (body) {
            if (!body) { return; }              // gagal: galat sudah tampil di form
            M.tutupDialog(dialog);
            terapkanBalasan(body);
        });
    });

    // ===== BAGIAN: Aksi pada baris =====
    M.delegasi(daftar, {
        ubah: function (tombol, baris) { bukaForm(baris); },

        // Jadikan aktif: konfirmasi dulu karena mengubah periode untuk Beranda dan import
        aktifkan: function (tombol, baris) {
            M.konfirmasi({
                judul: 'Jadikan periode aktif?',
                pesan: '"' + baris.dataset.nama + '" akan dipakai Beranda dan import. Periode aktif sebelumnya otomatis dinonaktifkan.',
                teksOk: 'Jadikan aktif'
            }).then(function (setuju) {
                if (!setuju) { return; }
                tombol.disabled = true;
                M.kirim('PATCH', urlDasar + '/' + baris.dataset.id + '/aktifkan').then(function (hasil) {
                    tombol.disabled = false;
                    if (hasil.ok) { terapkanBalasan(hasil.body); } else { M.toast(M.pesanDari(hasil)); }
                });
            });
        },

        // Hapus: konfirmasi dulu; server menolak bila periode aktif atau sudah punya data
        hapus: function (tombol, baris) {
            M.konfirmasi({
                judul: 'Hapus periode?',
                pesan: '"' + baris.dataset.nama + '" akan dihapus permanen beserta penugasan dosen wali di periode ini. Periode yang sudah punya data kompensasi tidak bisa dihapus.',
                teksOk: 'Hapus',
                bahaya: true
            }).then(function (setuju) {
                if (!setuju) { return; }
                tombol.disabled = true;
                M.kirim('DELETE', urlDasar + '/' + baris.dataset.id).then(function (hasil) {
                    tombol.disabled = false;
                    if (hasil.ok) { baris.remove(); terapkan(); }
                    M.toast(M.pesanDari(hasil));
                });
            });
        }
    });
})();
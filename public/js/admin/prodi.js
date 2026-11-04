/* =====================================================================
   prodi.js - halaman Kelola Program Studi.
   Bergantung pada master-data.js (window.SIPKOM.master).
   ===================================================================== */
(function () {
    'use strict';

    // ===== BAGIAN: Elemen dan pemeriksaan awal =====
    var M = window.SIPKOM && window.SIPKOM.master;
    var dialog = document.querySelector('[data-dialog="prodi"]');
    var daftar = document.querySelector('[data-daftar="prodi"]');
    var tombolTambah = document.querySelector('[data-tambah]');
    // Hentikan bila pustaka bersama atau elemen halaman tidak ada
    if (!M || !dialog || !daftar || !tombolTambah) { return; }

    var form = dialog.querySelector('[data-form]');
    var inputNama = form.querySelector('[name="nama_prodi"]');
    var tombolSimpan = form.querySelector('[data-simpan]');
    var urlDasar = daftar.dataset.url;          // mis. /admin/prodi
    var barisDiubah = null;                     // baris yang sedang diubah; null = mode tambah

    // Pasang perilaku dialog standar (tutup lewat tombol, latar, Esc; reset form)
    M.pasangDialog(dialog);

    // ===== BAGIAN: Pencarian =====
    // terapkan() dipanggil ulang setiap isi daftar berubah
    var terapkan = M.pasangPencarian({
        input: document.querySelector('[data-cari-input]'),
        daftar: daftar,
        ringkas: document.querySelector('[data-ringkas="total"]'),
        kosongCari: document.querySelector('[data-kosong-cari]')
    });

    // ===== BAGIAN: Buka dialog =====
    // Buka dialog dalam mode tambah (baris null) atau ubah (baris terpilih)
    function bukaForm(baris) {
        barisDiubah = baris;
        dialog.querySelector('[data-judul]').textContent = baris ? 'Ubah Program Studi' : 'Tambah Program Studi';
        inputNama.value = baris ? baris.dataset.nama : '';
        M.bukaDialog(dialog);
        inputNama.focus();
        inputNama.select();
    }

    tombolTambah.addEventListener('click', function () { bukaForm(null); });

    // ===== BAGIAN: Simpan (tambah atau ubah) =====
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var nama = inputNama.value.trim();

        // Cek cepat di browser; aturan sebenarnya tetap di server
        M.bersihkanGalat(form);
        if (!nama) {
            M.tampilkanGalat(form, { nama_prodi: ['Nama program studi wajib diisi.'] });
            return;
        }

        var ubah = barisDiubah !== null;
        M.ajukan({
            form: form,
            tombol: tombolSimpan,
            method: ubah ? 'PUT' : 'POST',
            url: ubah ? urlDasar + '/' + barisDiubah.dataset.id : urlDasar,
            data: { nama_prodi: nama }
        }).then(function (body) {
            // body null berarti gagal; galat sudah tampil di form
            if (!body) { return; }
            if (ubah) { M.gantiBaris(barisDiubah, body.html); } else { M.tambahBaris(daftar, body.html); }
            M.tutupDialog(dialog);
            terapkan();
            M.toast(body.pesan);
        });
    });

    // ===== BAGIAN: Aksi pada baris =====
    M.delegasi(daftar, {
        // Ubah: isi form dengan data baris
        ubah: function (tombol, baris) { bukaForm(baris); },

        // Hapus: minta konfirmasi dulu, lalu kirim DELETE
        hapus: function (tombol, baris) {
            M.konfirmasi({
                judul: 'Hapus program studi?',
                pesan: '"' + baris.dataset.nama + '" akan dihapus permanen. Prodi yang masih dipakai kelas tidak bisa dihapus.',
                teksOk: 'Hapus',
                bahaya: true
            }).then(function (setuju) {
                if (!setuju) { return; }
                tombol.disabled = true;
                M.kirim('DELETE', urlDasar + '/' + baris.dataset.id).then(function (hasil) {
                    tombol.disabled = false;
                    if (hasil.ok) {
                        baris.remove();
                        terapkan();
                    }
                    // Sukses maupun gagal (mis. masih dipakai kelas): tampilkan pesan server
                    M.toast(M.pesanDari(hasil));
                });
            });
        }
    });
})();
/* =====================================================================
   ruang.js - halaman Kelola Ruang (admin).
   Bergantung pada master-data.js (window.SIPKOM.master).
   Admin hanya mengatur nama ruang dan pengawas; status dan kuota milik pengawas.
   Urutan daftar mengikuti nama ruang, jadi server membalas seluruh daftar.
   ===================================================================== */
(function () {
    'use strict';

    // ===== BAGIAN: Elemen dan pemeriksaan awal =====
    var M = window.SIPKOM && window.SIPKOM.master;
    var dialog = document.querySelector('[data-dialog="ruang"]');
    var daftar = document.querySelector('[data-daftar="ruang"]');
    var tombolTambah = document.querySelector('[data-tambah]');
    if (!M || !dialog || !daftar || !tombolTambah) { return; }

    var form = dialog.querySelector('[data-form]');
    var inNama = form.querySelector('[name="nama_ruang"]');
    var pilihUtama = form.querySelector('[name="pengawas_id"]');
    var pilihKedua = form.querySelector('[name="pengawas_kedua_id"]');
    var tombolSimpan = form.querySelector('[data-simpan]');
    var ringkasDibuka = document.querySelector('[data-ringkas="dibuka"]');
    var urlDasar = daftar.dataset.url;       // mis. /admin/ruang
    var barisDiubah = null;                  // null = mode tambah

    M.pasangDialog(dialog);

    // ===== BAGIAN: Pencarian (nama ruang dan nama kedua pengawas) =====
    var terapkan = M.pasangPencarian({
        input: document.querySelector('[data-cari-input]'),
        daftar: daftar,
        ringkas: document.querySelector('[data-ringkas="total"]'),
        kosongCari: document.querySelector('[data-kosong-cari]')
    });

    // ===== BAGIAN: Ganti seluruh isi daftar =====
    // Ganti semua baris dengan HTML dari server, sorot baris ber-id idSorot, hitung ulang nomor
    function gantiDaftar(html, idSorot) {
        daftar.querySelectorAll('[data-baris]').forEach(function (b) { b.remove(); });
        var t = document.createElement('template');
        t.innerHTML = html;
        daftar.querySelector('[data-kosong-awal]').before(t.content);
        if (idSorot) {
            var baris = daftar.querySelector('[data-baris][data-id="' + idSorot + '"]');
            if (baris) { baris.classList.add('baris--baru'); }
        }
        terapkan();
    }

    // Terapkan balasan sukses: daftar, angka "Sedang Dibuka", dan toast
    function terapkanBalasan(body) {
        gantiDaftar(body.daftar, body.id);
        if (ringkasDibuka) { ringkasDibuka.textContent = body.dibuka; }
        M.toast(body.pesan);
    }

    // ===== BAGIAN: Buka dialog =====
    // Mode tambah (baris null) atau ubah (baris terpilih)
    function bukaForm(baris) {
        barisDiubah = baris;
        dialog.querySelector('[data-judul]').textContent = baris ? 'Ubah Ruang' : 'Tambah Ruang';
        inNama.value = baris ? baris.dataset.nama : '';
        pilihUtama.value = baris ? baris.dataset.pengawasId : '';
        pilihKedua.value = baris ? baris.dataset.pengawasKeduaId : '';
        M.bukaDialog(dialog);
        inNama.focus();
    }

    tombolTambah.addEventListener('click', function () { bukaForm(null); });

    // ===== BAGIAN: Simpan (tambah atau ubah) =====
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var nama = inNama.value.trim();

        // Cek cepat di browser; aturan sebenarnya di server
        M.bersihkanGalat(form);
        var galat = {};
        if (!nama) { galat.nama_ruang = ['Nama ruang wajib diisi.']; }
        if (!pilihUtama.value) { galat.pengawas_id = ['Pengawas utama wajib dipilih.']; }
        if (pilihKedua.value && pilihKedua.value === pilihUtama.value) {
            galat.pengawas_kedua_id = ['Pengawas kedua harus orang yang berbeda dari pengawas utama.'];
        }
        if (Object.keys(galat).length) { M.tampilkanGalat(form, galat); return; }

        var ubah = barisDiubah !== null;
        M.ajukan({
            form: form,
            tombol: tombolSimpan,
            method: ubah ? 'PUT' : 'POST',
            url: ubah ? urlDasar + '/' + barisDiubah.dataset.id : urlDasar,
            data: {
                nama_ruang: nama,
                pengawas_id: Number(pilihUtama.value),
                pengawas_kedua_id: pilihKedua.value ? Number(pilihKedua.value) : null
            }
        }).then(function (body) {
            if (!body) { return; }              // gagal: galat sudah tampil di form
            M.tutupDialog(dialog);
            terapkanBalasan(body);
        });
    });

    // ===== BAGIAN: Aksi pada baris =====
    M.delegasi(daftar, {
        ubah: function (tombol, baris) { bukaForm(baris); },

        // Nonaktifkan (dengan konfirmasi) atau aktifkan kembali (langsung)
        toggle: function (tombol, baris) {
            var aktif = baris.dataset.aktif === '1';

            // Kirim PATCH lalu ganti daftar dengan hasil server
            function kirimToggle() {
                tombol.disabled = true;
                M.kirim('PATCH', urlDasar + '/' + baris.dataset.id + '/toggle').then(function (hasil) {
                    tombol.disabled = false;
                    if (hasil.ok) { terapkanBalasan(hasil.body); } else { M.toast(M.pesanDari(hasil)); }
                });
            }

            if (!aktif) { kirimToggle(); return; }

            M.konfirmasi({
                judul: 'Nonaktifkan ruang?',
                pesan: '"' + baris.dataset.nama + '" tidak bisa dipilih mahasiswa lagi. Riwayat pengajuan dan realisasi tetap tersimpan.',
                teksOk: 'Nonaktifkan',
                bahaya: true
            }).then(function (setuju) { if (setuju) { kirimToggle(); } });
        }
    });
})();
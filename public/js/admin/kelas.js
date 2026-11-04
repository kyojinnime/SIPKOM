/* =====================================================================
   kelas.js - halaman Kelola Kelas.
   Bergantung pada master-data.js (window.SIPKOM.master).
   Dua tab (Aktif | Lulus) dan filter prodi disaring di browser lewat data-lulus
   dan data-prodi-id. Setelah simpan, server membalas seluruh daftar.
   ===================================================================== */
(function () {
    'use strict';

    // ===== BAGIAN: Elemen dan pemeriksaan awal =====
    var M = window.SIPKOM && window.SIPKOM.master;
    var dialog = document.querySelector('[data-dialog="kelas"]');
    var daftar = document.querySelector('[data-daftar="kelas"]');
    var tombolTambah = document.querySelector('[data-tambah]');
    if (!M || !dialog || !daftar || !tombolTambah) { return; }

    var form = dialog.querySelector('[data-form]');
    var pilihProdi = form.querySelector('[name="prodi_id"]');
    var inAngkatan = form.querySelector('[name="angkatan"]');
    var inNama = form.querySelector('[name="nama_kelas"]');
    var pilihDosen = form.querySelector('[name="dosen_wali_id"]');
    var infoKunci = form.querySelector('[data-info-kunci]');
    var pratinjau = form.querySelector('[data-pratinjau]');
    var tombolSimpan = form.querySelector('[data-simpan]');
    var tabs = document.querySelectorAll('[data-tab]');
    var filterProdi = document.querySelector('[data-filter-prodi]');
    var urlDasar = daftar.dataset.url;                       // mis. /admin/kelas
    var tahunMulai = parseInt(daftar.dataset.tahunMulai, 10); // NaN bila belum ada periode aktif
    var indeks = parseInt(daftar.dataset.indeks, 10);         // 1 ganjil, 2 genap
    var barisDiubah = null;                                  // null = mode tambah
    var tabAktif = 'aktif';

    M.pasangDialog(dialog);

    // ===== BAGIAN: Penyaringan (tab, prodi, pencarian) =====
    // Baris tampil bila cocok dengan tab dan filter prodi; teks pencarian ditangani pasangPencarian
    function cocokFilter(baris) {
        var lulus = baris.dataset.lulus === '1';
        if ((tabAktif === 'lulus') !== lulus) { return false; }
        return !filterProdi.value || baris.dataset.prodiId === filterProdi.value;
    }

    var terapkan = M.pasangPencarian({
        input: document.querySelector('[data-cari-input]'),
        daftar: daftar,
        kosongCari: document.querySelector('[data-kosong-cari]'),
        filterLain: cocokFilter
    });

    // Hitung ulang angka tab dan kartu ringkasan dari isi daftar
    function hitungAngka() {
        var aktif = 0, lulus = 0, tanpaDosen = 0;
        daftar.querySelectorAll('[data-baris]').forEach(function (b) {
            if (b.dataset.lulus === '1') { lulus++; return; }
            aktif++;
            if (b.dataset.tanpaDosen === '1') { tanpaDosen++; }
        });
        document.querySelector('[data-tab-jumlah="aktif"]').textContent = aktif;
        document.querySelector('[data-tab-jumlah="lulus"]').textContent = lulus;
        document.querySelector('[data-ringkas="aktif"]').textContent = aktif;
        document.querySelector('[data-ringkas="tanpa-dosen"]').textContent = tanpaDosen;
    }

    // Terapkan semua penyaringan lalu perbarui angka
    function perbarui() { terapkan(); hitungAngka(); }

    // Pindah tab
    function pilihTab(nama) {
        tabAktif = nama;
        tabs.forEach(function (t) {
            var hidup = t.dataset.tab === nama;
            t.classList.toggle('tab__btn--aktif', hidup);
            t.setAttribute('aria-selected', hidup ? 'true' : 'false');
        });
        perbarui();
    }

    tabs.forEach(function (t) { t.addEventListener('click', function () { pilihTab(t.dataset.tab); }); });
    filterProdi.addEventListener('change', perbarui);

    // ===== BAGIAN: Ganti seluruh isi daftar =====
    // Ganti semua baris dengan HTML server, sorot baris ber-id idSorot, dan buka tab tempat baris itu berada
    function gantiDaftar(html, idSorot) {
        daftar.querySelectorAll('[data-baris]').forEach(function (b) { b.remove(); });
        var t = document.createElement('template');
        t.innerHTML = html;
        daftar.querySelector('[data-kosong-awal]').before(t.content);

        var baris = idSorot ? daftar.querySelector('[data-baris][data-id="' + idSorot + '"]') : null;
        if (baris) {
            baris.classList.add('baris--baru');
            tabAktif = baris.dataset.lulus === '1' ? 'lulus' : 'aktif';
        }
        pilihTab(tabAktif); // sekaligus memperbarui penyaringan dan angka
    }

    // ===== BAGIAN: Pratinjau semester di dialog =====
    // Tampilkan semester hasil rumus dokumen: (tahun mulai - angkatan) x 2 + indeks semester
    function perbaruiPratinjau() {
        var angkatan = parseInt(inAngkatan.value, 10);
        var huruf = inNama.value.trim().toUpperCase();
        if (isNaN(tahunMulai) || isNaN(angkatan)) { pratinjau.textContent = ''; return; }

        var semester = (tahunMulai - angkatan) * 2 + indeks;
        pratinjau.textContent = semester < 1
            ? 'Angkatan ini belum mulai kuliah di periode aktif.'
            : 'Di periode aktif: semester ' + semester + (huruf ? ' (kelas ' + semester + huruf + ')' : '') + '.';
    }
    inAngkatan.addEventListener('input', perbaruiPratinjau);
    inNama.addEventListener('input', perbaruiPratinjau);

    // ===== BAGIAN: Buka dialog =====
    // Mode tambah (baris null) atau ubah (baris terpilih)
    function bukaForm(baris) {
        barisDiubah = baris;
        dialog.querySelector('[data-judul]').textContent = baris ? 'Ubah Kelas' : 'Tambah Kelas';
        pilihProdi.value = baris ? baris.dataset.prodiId : '';
        inAngkatan.value = baris ? baris.dataset.angkatan : (isNaN(tahunMulai) ? '' : tahunMulai);
        inNama.value = baris ? baris.dataset.namaKelas : '';
        pilihDosen.value = baris ? baris.dataset.dosenId : '';

        // Kelas yang sudah punya mahasiswa: prodi dan angkatan dikunci agar NIM dan semester tidak jadi janggal
        var jumlahMhs = baris ? parseInt(baris.dataset.mahasiswa, 10) : 0;
        pilihProdi.disabled = inAngkatan.disabled = jumlahMhs > 0;
        infoKunci.hidden = jumlahMhs === 0;
        infoKunci.textContent = jumlahMhs > 0
            ? 'Prodi dan angkatan terkunci karena kelas ini sudah punya ' + jumlahMhs + ' mahasiswa.'
            : '';

        perbaruiPratinjau();
        M.bukaDialog(dialog);
        (pilihProdi.disabled ? inNama : pilihProdi).focus();
    }

    tombolTambah.addEventListener('click', function () { bukaForm(null); });

    // ===== BAGIAN: Simpan (tambah atau ubah) =====
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var angkatan = parseInt(inAngkatan.value, 10);
        var huruf = inNama.value.trim().toUpperCase();

        // Cek cepat di browser; aturan sebenarnya di server
        M.bersihkanGalat(form);
        var galat = {};
        if (!pilihProdi.value) { galat.prodi_id = ['Program studi wajib dipilih.']; }
        if (isNaN(angkatan)) { galat.angkatan = ['Angkatan wajib diisi.']; }
        if (!huruf) { galat.nama_kelas = ['Huruf kelas wajib diisi.']; }
        if (Object.keys(galat).length) { M.tampilkanGalat(form, galat); return; }

        var ubah = barisDiubah !== null;
        M.ajukan({
            form: form,
            tombol: tombolSimpan,
            method: ubah ? 'PUT' : 'POST',
            url: ubah ? urlDasar + '/' + barisDiubah.dataset.id : urlDasar,
            data: {
                prodi_id: Number(pilihProdi.value),
                angkatan: angkatan,
                nama_kelas: huruf,
                dosen_wali_id: pilihDosen.value ? Number(pilihDosen.value) : null
            }
        }).then(function (body) {
            if (!body) { return; }              // gagal: galat sudah tampil di form
            M.tutupDialog(dialog);
            gantiDaftar(body.daftar, body.id);
            M.toast(body.pesan);
        });
    });

    // ===== BAGIAN: Aksi pada baris =====
    M.delegasi(daftar, {
        ubah: function (tombol, baris) { bukaForm(baris); },

        // Hapus: konfirmasi dulu; server menolak bila ada mahasiswa atau riwayat dosen wali
        hapus: function (tombol, baris) {
            M.konfirmasi({
                judul: 'Hapus kelas?',
                pesan: 'Kelas ' + baris.dataset.nama + ' akan dihapus permanen. Kelas yang punya mahasiswa atau riwayat dosen wali di periode lain tidak bisa dihapus.',
                teksOk: 'Hapus',
                bahaya: true
            }).then(function (setuju) {
                if (!setuju) { return; }
                tombol.disabled = true;
                M.kirim('DELETE', urlDasar + '/' + baris.dataset.id).then(function (hasil) {
                    tombol.disabled = false;
                    if (hasil.ok) { baris.remove(); perbarui(); }
                    M.toast(M.pesanDari(hasil));
                });
            });
        }
    });

    // Terapkan tab awal (kelas lulus disembunyikan) dan hitung angka saat halaman dibuka
    perbarui();
})();
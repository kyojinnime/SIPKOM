/* =====================================================================
   profil.js - halaman Profil Saya (simpan data, unggah dan hapus foto)
   ===================================================================== */

(function () {
    'use strict';

    var form = document.getElementById('form-profil');
    if (!form) { return; }

    var tombolSimpan = form.querySelector('[data-simpan]');
    var galatUmum = form.querySelector('[data-galat-umum]');
    var inputFoto = document.getElementById('input-foto');
    var tombolHapusFoto = document.querySelector('[data-hapus-foto]');
    var galatFoto = document.querySelector('[data-foto-galat]');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    /** Menampilkan pesan lewat toast milik app.js. */
    function toast(pesan) {
        if (window.SIPKOM && window.SIPKOM.toast) { window.SIPKOM.toast(pesan, 3500); }
    }

    // Toast yang disimpan sebelum reload (agar header ikut terbarui)
    var pesanTersimpan = sessionStorage.getItem('sipkom.profil.toast');
    if (pesanTersimpan) {
        sessionStorage.removeItem('sipkom.profil.toast');
        toast(pesanTersimpan);
    }

    /** Mengosongkan semua pesan galat. */
    function bersihGalat() {
        form.querySelectorAll('[data-galat]').forEach(function (s) { s.textContent = ''; });
        form.querySelectorAll('.field--galat').forEach(function (f) { f.classList.remove('field--galat'); });
        galatUmum.hidden = true;
        galatUmum.textContent = '';
        if (galatFoto) { galatFoto.textContent = ''; }
    }

    /** Menampilkan galat di bawah satu kolom. */
    function tampilGalat(kolom, teks) {
        var span = form.querySelector('[data-galat="' + kolom + '"]');
        if (span) {
            span.textContent = teks;
            span.closest('.field').classList.add('field--galat');
        }
    }

    /** Menampilkan galat umum (sesi habis, koneksi). */
    function galatUmumTampil(teks) {
        galatUmum.textContent = teks;
        galatUmum.hidden = false;
    }

    /** Mengunci / membuka tombol Simpan. */
    function aturSimpan(sedang) {
        tombolSimpan.disabled = sedang;
        tombolSimpan.textContent = sedang ? 'Menyimpan...' : 'Simpan';
    }

    /** Simpan pesan lalu muat ulang agar nama dan avatar di header ikut terbarui. */
    function sukses(pesan) {
        sessionStorage.setItem('sipkom.profil.toast', pesan);
        window.location.reload();
    }

    // ===== Simpan data diri =====
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        bersihGalat();

        var data = new FormData(form); // sudah berisi _token dan _method

        aturSimpan(true);
        try {
            var res = await fetch(form.getAttribute('data-url'), {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var json = await res.json().catch(function () { return {}; });

            if (res.status === 422) {
                var errors = json.errors || {};
                Object.keys(errors).forEach(function (k) { tampilGalat(k, errors[k][0]); });
                return;
            }
            if (res.status === 419) { galatUmumTampil('Sesi habis. Muat ulang halaman lalu coba lagi.'); return; }
            if (!res.ok) { galatUmumTampil(json.message || 'Terjadi kesalahan. Coba lagi.'); return; }

            sukses(json.pesan || 'Profil diperbarui.');
        } catch (err) {
            galatUmumTampil('Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.');
        } finally {
            aturSimpan(false);
        }
    });

    // ===== Unggah foto saat file dipilih =====
    if (inputFoto) {
        inputFoto.addEventListener('change', async function () {
            if (!inputFoto.files.length) { return; }
            if (galatFoto) { galatFoto.textContent = ''; }

            var data = new FormData();
            data.append('_token', csrf);
            data.append('foto', inputFoto.files[0]);

            try {
                var res = await fetch(inputFoto.getAttribute('data-url'), {
                    method: 'POST',
                    body: data,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                var json = await res.json().catch(function () { return {}; });

                if (res.status === 422) {
                    if (galatFoto && json.errors && json.errors.foto) { galatFoto.textContent = json.errors.foto[0]; }
                    return;
                }
                if (res.status === 419) { if (galatFoto) galatFoto.textContent = 'Sesi habis. Muat ulang halaman lalu coba lagi.'; return; }
                if (!res.ok) { if (galatFoto) galatFoto.textContent = json.message || 'Gagal mengunggah foto.'; return; }

                sukses(json.pesan || 'Foto diperbarui.');
            } catch (err) {
                if (galatFoto) { galatFoto.textContent = 'Tidak dapat terhubung ke server.'; }
            }
        });
    }

    // ===== Hapus foto profil =====
    if (tombolHapusFoto) {
        tombolHapusFoto.addEventListener('click', async function () {
            tombolHapusFoto.disabled = true;

            var data = new FormData();
            data.append('_token', csrf);
            data.append('_method', 'DELETE');

            try {
                var res = await fetch(tombolHapusFoto.getAttribute('data-url'), {
                    method: 'POST',
                    body: data,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                var json = await res.json().catch(function () { return {}; });

                if (!res.ok) {
                    if (galatFoto) { galatFoto.textContent = json.message || 'Gagal menghapus foto.'; }
                    return;
                }

                sukses(json.pesan || 'Foto profil dihapus.');
            } catch (err) {
                if (galatFoto) { galatFoto.textContent = 'Tidak dapat terhubung ke server.'; }
            } finally {
                tombolHapusFoto.disabled = false;
            }
        });
    }
})();

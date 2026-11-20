<?php

namespace Tests;

use App\Services\CakupanKompen;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionProperty;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: RefreshDatabase menghapus seluruh tabel. Pemeriksaan ini berjalan SEBELUM trait database,
     * dan menghentikan uji bila koneksinya bukan database uji (namanya harus berakhiran "_test").
     */
    protected function setUpTraits()
    {
        $koneksi = config('database.default');
        $nama = (string) config("database.connections.{$koneksi}.database");

        if (!str_ends_with($nama, '_test') && $nama !== ':memory:') {
            throw new RuntimeException("Uji dihentikan: database '{$nama}' bukan database uji. Atur DB_DATABASE=sipkom_test di phpunit.xml.");
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // CakupanKompen menyimpan data dosen wali per akun secara statis; kosongkan agar tidak bocor antar uji
        (new ReflectionProperty(CakupanKompen::class, 'cacheDosen'))->setValue(null, []);
    }
}
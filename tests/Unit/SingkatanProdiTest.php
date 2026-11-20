<?php

namespace Tests\Unit;

use App\Services\Rekap\SingkatanProdi;
use PHPUnit\Framework\TestCase;

class SingkatanProdiTest extends TestCase
{
    public function test_singkatan_dari_nama_prodi(): void
    {
        $this->assertSame('RPL', SingkatanProdi::dari('D4 - Rekayasa Perangkat Lunak'));
        $this->assertSame('TI', SingkatanProdi::dari('D3 Teknik Informatika'));
        $this->assertSame('KSI', SingkatanProdi::dari('D4 Keamanan Sistem Informasi'));
        $this->assertSame('STI', SingkatanProdi::dari('D4 - Sistem dan Teknologi Informasi'));   // kata sambung dibuang
        $this->assertSame('TI', SingkatanProdi::dari('Teknik Informatika (Reguler)'));            // kurung dibuang
        $this->assertSame('RPL', SingkatanProdi::dari('Sarjana Terapan Rekayasa Perangkat Lunak'));
        $this->assertSame('TI', SingkatanProdi::dari('Diploma IV Teknik Informatika'));
    }

    public function test_nama_pendek_dan_kosong(): void
    {
        $this->assertSame('TI', SingkatanProdi::dari('TI'));    // sudah berupa singkatan
        $this->assertSame('-', SingkatanProdi::dari(''));
        $this->assertSame('-', SingkatanProdi::dari(null));
    }
}
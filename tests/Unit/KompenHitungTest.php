<?php

namespace Tests\Unit;

use App\Services\KompenHitung;
use PHPUnit\Framework\TestCase;

class KompenHitungTest extends TestCase
{
    public function test_status_tujuh_kasus(): void
    {
        // (wajib, terlaksana, barisAktif, barisDibatalkan) -> status
        $this->assertSame('tidak_ada',   KompenHitung::tentukanStatus(0, 0, 0, 0));   // belum pernah ada kewajiban
        $this->assertSame('dibatalkan',  KompenHitung::tentukanStatus(0, 0, 0, 2));   // semua dibatalkan
        $this->assertSame('belum_mulai', KompenHitung::tentukanStatus(16, 0, 1, 0));
        $this->assertSame('berjalan',    KompenHitung::tentukanStatus(16, 5, 1, 0));
        $this->assertSame('selesai',     KompenHitung::tentukanStatus(16, 16, 1, 0));
        $this->assertSame('tidak_ada',   KompenHitung::tentukanStatus(0, 0, 1, 0));   // jam dijadikan 0
        $this->assertSame('selesai',     KompenHitung::tentukanStatus(10, 12, 1, 0)); // terlaksana melebihi wajib
    }

    public function test_jam_minimum(): void
    {
        // Satu baris 16 jam, terlaksana 10 -> tidak boleh di bawah 10
        $this->assertSame(10, KompenHitung::hitungJamMinimum(10, 16, 16));
        // Dua baris 16 + 16, terlaksana 20, baris ini 16 -> minimum 4
        $this->assertSame(4, KompenHitung::hitungJamMinimum(20, 32, 16));
        // Belum ada realisasi -> boleh sampai 0
        $this->assertSame(0, KompenHitung::hitungJamMinimum(0, 16, 16));
    }
}
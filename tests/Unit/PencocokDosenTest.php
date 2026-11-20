<?php

namespace Tests\Unit;

use App\Services\Impor\PencocokDosen;
use PHPUnit\Framework\TestCase;

class PencocokDosenTest extends TestCase
{
    /** Nama dengan gelar dan tanpa gelar harus menjadi bentuk baku yang sama. */
    public function test_normalisasi_nama(): void
    {
        $this->assertSame('eva yumami', PencocokDosen::normalisasi('EVA YUMAMI, S.Kom., M.T.'));
        $this->assertSame('eva yumami', PencocokDosen::normalisasi('Eva Yumami'));
        $this->assertSame('eva yumami', PencocokDosen::normalisasi('Eva Yumami S.Kom M.T.'));   // gelar tanpa koma
        $this->assertSame('i gusti agung putu mahendra', PencocokDosen::normalisasi('I GUSTI AGUNG PUTU MAHENDRA, S.Kom.,M.Kom'));
        $this->assertSame('depandi enda', PencocokDosen::normalisasi('Depandi Enda, M.Kom., S.ST'));
        $this->assertSame('ahmad fauzi', PencocokDosen::normalisasi('Dr. Ahmad Fauzi, M.T.'));   // awalan gelar
    }

    /** Inisial "M." pada nama bukan gelar dan harus tetap ada. */
    public function test_inisial_tidak_dibuang(): void
    {
        $this->assertSame('m putra', PencocokDosen::normalisasi('M. PUTRA'));
        $this->assertSame('', PencocokDosen::normalisasi(null));
    }
}
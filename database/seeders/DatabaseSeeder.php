<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    // Fungsi: menjalankan seeder data contoh SIPKOM
    public function run(): void
    {
        // [UBAH] mengganti isi bawaan dengan pemanggilan seeder SIPKOM
        $this->call(SipkomSeeder::class);
    }
}

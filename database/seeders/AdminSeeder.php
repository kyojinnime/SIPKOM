<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Membuat satu akun admin awal.
     * firstOrCreate: aman dijalankan berulang, password admin yang sudah diganti tidak direset.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['username' => 'admin'], // kunci pencarian
            [
                'name'                 => 'Administrator', // kolom 'name' wajib (NOT NULL)
                'email'                => 'admin@sipkom.local',
                'password'             => config('sipkom.password_awal'), // otomatis di-hash oleh cast 'hashed'
                'role'                 => 'admin',
                'is_active'            => true,
                'must_change_password' => true,
            ]
        );
    }
}
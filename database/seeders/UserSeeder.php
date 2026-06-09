<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'EJAF Admin',
            'username' => 'admin',
            'password' => Hash::make('Admin@1234'),
            'is_admin' => true,
        ]);
    }
}
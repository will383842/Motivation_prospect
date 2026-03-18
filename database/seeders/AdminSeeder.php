<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'williamsjullin@gmail.com'],
            [
                'name' => 'William',
                'password' => Hash::make('SosExpat2026!'),
            ]
        );
    }
}

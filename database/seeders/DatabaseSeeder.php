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
    public function run(): void
{
    // 1. Create Yusuf (Admin)
    \App\Models\User::factory()->create([
        'name' => 'Yusuf',
        'email' => 'admin@fricalearn.com', // Change to your actual email
        'password' => bcrypt('password'),
        'is_admin' => 1,
    ]);

    // 2. Create Ayo (Student)
    $student = \App\Models\User::create([
        'name' => 'Ayo Test',
        'email' => 'ayo@test.com',
        'password' => bcrypt('password123'),
        'is_admin' => 0,
    ]);

    // 3. Give Ayo a Profile
    \App\Models\StudentProfile::create([
        'user_id' => $student->id,
        'language' => 'Yoruba',
        'total_points' => 0,
    ]);

    // 4. Run the Badge Seeder
    $this->call(BadgeSeeder::class);
}
}

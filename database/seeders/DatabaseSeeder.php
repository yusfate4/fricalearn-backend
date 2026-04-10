<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\StudentProfile;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create the Founder / Admin (Yusuf)
        User::updateOrCreate(
            ['email' => 'admin@fricalearn.com'],
            [
                'name' => 'Yusuf',
                'password' => Hash::make('12345678'),
                'is_admin' => true,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // 2. Create the Test Student (Ayo)
        $student = User::updateOrCreate(
            ['email' => 'ayo@test.com'],
            [
                'name' => 'Ayo Learner',
                'password' => Hash::make('password123'),
                'is_admin' => false,
                'role' => 'student', // 🚀 Explicitly set role
            ]
        );

        // 3. Give Ayo a Gamification Profile
        StudentProfile::updateOrCreate(
            ['user_id' => $student->id],
            [
                'language' => 'Yoruba', // 🚀 Updated key to match StudentProfile.php
                'total_points' => 510,           // Setting his points to match your screenshot
                'total_coins' => 100,
            ]
        );

        // 3. Create a Test Parent (e.g., Ayo's Dad)
$parent = User::updateOrCreate(
    ['email' => 'parent@test.com'],
    [
        'name' => 'Yusuf Parent',
        'password' => Hash::make('password123'),
        'role' => 'parent', // 🚀 This is the magic key
        'is_admin' => false,
    ]
);

        $this->command->info('✅ FricaLearn Users Seeded!');
        $this->call([RewardSeeder::class]);
    }
}
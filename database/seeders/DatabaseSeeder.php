<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\StudentProfile;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create the Founder / Admin (Yusuf)
        User::updateOrCreate(
            ['email' => 'admin@fricalearn.com'],
            [
                'name' => 'Yusuf',
                'password' => Hash::make('12345678'), // Easy password for local testing
                'is_admin' => true,
            ]
        );

        // 2. Create the Test Student (Ayo)
        $student = User::updateOrCreate(
            ['email' => 'ayo@test.com'],
            [
                'name' => 'Ayo Learner',
                'password' => Hash::make('password123'),
                'is_admin' => false,
            ]
        );

        // 3. Give Ayo a Gamification Profile
        // This ensures the dashboard doesn't crash looking for his points/rank
        StudentProfile::updateOrCreate(
            ['user_id' => $student->id],
            [
                'language' => 'Yoruba',
                'total_points' => 0,
                'total_coins' => 0,
            ]
        );

        $this->command->info('✅ FricaLearn Users Seeded: Admin (Yusuf) & Student (Ayo) are ready!');

        // For Rewards
        $this->call([RewardSeeder::class]);
    }
}
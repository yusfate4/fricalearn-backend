<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */public function run(): void
{
    $badges = [
        [
            'name' => 'Fast Starter',
            'description' => 'Completed your very first lesson!',
            'icon' => 'Zap',
            'required_points' => 0,
        ],
        [
            'name' => 'Yoruba Warrior',
            'description' => 'Earned 100 points in Yoruba lessons.',
            'icon' => 'Shield',
            'required_points' => 100,
        ],
        [
            'name' => 'Hausa Hero',
            'description' => 'Earned 100 points in Hausa lessons.',
            'icon' => 'Star',
            'required_points' => 100,
        ],
        [
            'name' => 'Grand Chief',
            'description' => 'Reached a total of 500 points!',
            'icon' => 'Trophy',
            'required_points' => 500,
        ],
    ];

    foreach ($badges as $badge) {
        \App\Models\Badge::updateOrCreate(['name' => $badge['name']], $badge);
    }
}
}

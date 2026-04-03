<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Reward;

class RewardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rewards = [
            [
                'title' => '1-on-1 Yoruba Speaking Practice (30 Mins)',
                'description' => 'Spend your coins to book a 30-minute live speaking session with a native Yoruba tutor!',
                'cost_coins' => 100,
                'type' => 'educational_product',
                'is_active' => true,
            ],
            [
                'title' => 'African Folktales Storybook (PDF)',
                'description' => 'Unlock a beautifully illustrated digital book featuring classic Yoruba folktales like "Ijapa the Tortoise".',
                'cost_coins' => 150,
                'type' => 'digital_voucher',
                'is_active' => true,
            ],
            [
                'title' => 'Golden Scholar Avatar Frame',
                'description' => 'Stand out on the leaderboard! Buy this premium golden frame for your profile picture.',
                'cost_coins' => 50,
                'type' => 'platform_credit',
                'is_active' => true,
            ],
            [
                'title' => '50% Off Next Month\'s Subscription',
                'description' => 'Use your hard-earned learning coins to get a massive discount on your tuition next month.',
                'cost_coins' => 200,
                'type' => 'digital_voucher',
                'is_active' => true,
            ],
        ];

        foreach ($rewards as $reward) {
            Reward::create($reward);
        }

        $this->command->info('🛍️ FricaLearn Reward Store has been stocked with items!');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'cost_coins',
        'image_url',
        'type',
        'is_active',
    ];

    public function redemptions()
    {
        return $this->hasMany(RewardRedemption::class);
    }
}
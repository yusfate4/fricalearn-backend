<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgressRecord extends Model
{
    // These are the fields we need for tracking (LMS-06)
    protected $fillable = ['user_id', 'lesson_id', 'status', 'started_at', 'completed_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
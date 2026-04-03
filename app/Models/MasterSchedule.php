<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterSchedule extends Model
{
    protected $fillable = ['day_of_week', 'start_time_wat', 'is_active'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointsHistory extends Model
{
    use HasFactory;

    // Explicitly define the table name to override Laravel's pluralization
    protected $table = 'points_history';

    protected $fillable = [
        'student_id',
        'points',
        'reason',
        'reference_type',
        'reference_id',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
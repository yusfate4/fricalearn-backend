<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EnrollmentPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'course_id',
        'child_name',
        'amount',
        'currency',
        'receipt_path',
        'status'
    ];

    // Relationships
    public function parent() { return $this->belongsTo(User::class, 'parent_id'); }
    public function course() { return $this->belongsTo(Course::class); }
}
